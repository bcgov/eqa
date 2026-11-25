<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Modules\Admin\Models\ApplicationWebCompliance;
use Modules\Admin\Models\WebsiteComplianceItem;
use Modules\Admin\Models\WebsiteComplianceSection;

/**
 * The application's Website Compliance section. A never-submitted draft follows the current checklist
 * (Maintenance > Website Compliance); every save stores the application's own copy of the checklist
 * text with the institution's link and/or comment (application_web_compliances). Once submitted the
 * application keeps that copy, whatever later changes are made to the checklist.
 *
 * Checklist shape: list of sections {title, intro, items: list of {key, item_id, label, help_text, url, comment}}.
 */
class WebsiteComplianceService
{
    /** @return list<array<string, mixed>> */
    public function formChecklist(?object $application, ?object $institution): array
    {
        if ($application !== null && ($application->workflow_stage ?? 'draft') !== 'draft') {
            $copy = $this->copy((string) $application->crm_id);
            if ($copy !== []) {
                return $copy;
            }
        }

        $answers = $application !== null
            ? ApplicationWebCompliance::where('application_crm_id', $application->crm_id)->get()->keyBy('website_compliance_item_id')
            : collect();

        return $this->liveChecklist($institution, $answers->all());
    }

    /**
     * The application's own copy, as saved.
     *
     * @return list<array<string, mixed>>
     */
    public function copy(string $applicationCrmId): array
    {
        $sections = [];
        $rows = ApplicationWebCompliance::where('application_crm_id', $applicationCrmId)
            ->orderBy('section_order')->orderBy('item_order')->orderBy('id')->get();

        foreach ($rows as $row) {
            $group = $row->section_order.'|'.$row->section_title;
            $sections[$group] ??= ['title' => $row->section_title, 'intro' => $row->section_intro, 'items' => []];
            $sections[$group]['items'][] = [
                'key' => $row->website_compliance_item_id !== null ? (string) $row->website_compliance_item_id : 'r'.$row->id,
                'item_id' => $row->website_compliance_item_id,
                'label' => $row->item_label,
                'help_text' => $row->item_help_text,
                'url' => $row->url,
                'comment' => $row->comment,
            ];
        }

        return array_values($sections);
    }

    /**
     * A link and/or a comment for every item on the checklist (required when submitting; a draft only
     * checks that any link entered is a web address).
     *
     * @param  list<array<string, mixed>>  $checklist
     * @return array<string, list<string>>
     */
    public function rules(array $checklist, bool $complete = true): array
    {
        $rules = ['website_compliance' => ['nullable', 'array']];
        foreach ($this->items($checklist) as $item) {
            $base = 'website_compliance.'.$item['key'];
            $rules[$base.'.url'] = array_merge(['nullable'], $complete ? ['required_without:'.$base.'.comment'] : [], ['string', 'max:2048', 'url:http,https']);
            $rules[$base.'.comment'] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'website_compliance.*.url.required_without' => 'Enter a link or a comment.',
            'website_compliance.*.url.url' => 'Enter a full web address starting with http:// or https://.',
            'website_compliance.*.url.max' => 'The link is too long.',
            'website_compliance.*.comment.max' => 'The comment cannot be longer than 2000 characters.',
        ];
    }

    /**
     * Store the application's own copy of the checklist with the answers given.
     *
     * @param  list<array<string, mixed>>  $checklist
     * @param  array<string, array{url?: ?string, comment?: ?string}>  $answers  keyed by item key
     */
    public function sync(string $applicationCrmId, array $checklist, array $answers): void
    {
        ApplicationWebCompliance::where('application_crm_id', $applicationCrmId)->delete();

        foreach (array_values($checklist) as $s => $section) {
            foreach (array_values($section['items']) as $i => $item) {
                $answer = $answers[$item['key']] ?? [];
                ApplicationWebCompliance::create([
                    'application_crm_id' => $applicationCrmId,
                    'website_compliance_item_id' => $item['item_id'],
                    'section_title' => $section['title'],
                    'section_intro' => $section['intro'],
                    'section_order' => $s + 1,
                    'item_label' => $item['label'],
                    'item_help_text' => $item['help_text'],
                    'item_order' => $i + 1,
                    'url' => $this->clean($answer['url'] ?? null),
                    'comment' => $this->clean($answer['comment'] ?? null),
                ]);
            }
        }
    }

    /**
     * Active sections and the active items that apply to the institution (PTIRU-only items need QA met through PTIRU).
     *
     * @param  array<int|string, ApplicationWebCompliance>  $answers  keyed by checklist item id
     * @return list<array<string, mixed>>
     */
    private function liveChecklist(?object $institution, array $answers): array
    {
        $ptiru = DesignationService::ptiruRequired($institution->qa_met_through ?? null);

        return WebsiteComplianceSection::where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->with('items')
            ->get()
            ->map(fn (WebsiteComplianceSection $section): array => [
                'title' => $section->title,
                'intro' => $section->intro,
                'items' => $section->items
                    ->filter(fn (WebsiteComplianceItem $item): bool => $item->is_active
                        && ($item->applies_to !== WebsiteComplianceItem::APPLIES_TO_PTIRU || $ptiru))
                    ->map(fn (WebsiteComplianceItem $item): array => [
                        'key' => (string) $item->id,
                        'item_id' => $item->id,
                        'label' => $item->label,
                        'help_text' => $item->help_text,
                        'url' => isset($answers[$item->id]) ? $answers[$item->id]->url : null,
                        'comment' => isset($answers[$item->id]) ? $answers[$item->id]->comment : null,
                    ])->values()->all(),
            ])
            ->filter(fn (array $section): bool => $section['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $checklist
     * @return list<array<string, mixed>>
     */
    private function items(array $checklist): array
    {
        return array_merge(...array_map(fn (array $section): array => $section['items'], $checklist ?: [['items' => []]]));
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
