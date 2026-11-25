<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Models\WebsiteComplianceItem;
use Modules\Admin\Models\WebsiteComplianceSection;

/**
 * Maintenance > Website Compliance (Ministry Admins): the checklist institutions answer on the
 * application. Changes apply to draft applications; submitted applications keep their own copy.
 */
class WebsiteComplianceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/MaintenanceWebsiteCompliance', [
            'sections' => WebsiteComplianceSection::orderBy('sort_order')->orderBy('id')->with('items')->get(),
            'appliesTo' => [
                ['value' => WebsiteComplianceItem::APPLIES_TO_ALL, 'label' => 'All institutions'],
                ['value' => WebsiteComplianceItem::APPLIES_TO_PTIRU, 'label' => 'PTIRU institutions only'],
            ],
        ]);
    }

    public function storeSection(Request $request): RedirectResponse
    {
        $data = $this->sectionData($request);
        WebsiteComplianceSection::create($data + ['sort_order' => (int) WebsiteComplianceSection::max('sort_order') + 1]);

        return back()->with('success', 'Section "'.$data['title'].'" added.');
    }

    public function updateSection(Request $request, WebsiteComplianceSection $section): RedirectResponse
    {
        $section->update($this->sectionData($request));

        return back()->with('success', 'Section "'.$section->title.'" updated.');
    }

    public function moveSection(Request $request, WebsiteComplianceSection $section): RedirectResponse
    {
        $this->move(WebsiteComplianceSection::query()->orderBy('sort_order')->orderBy('id')->get(), $section, $this->direction($request));

        return back();
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $this->itemData($request);
        WebsiteComplianceItem::create($data + [
            'sort_order' => (int) WebsiteComplianceItem::where('section_id', $data['section_id'])->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, WebsiteComplianceItem $item): RedirectResponse
    {
        $data = $this->itemData($request);
        if ((int) $data['section_id'] !== (int) $item->section_id) {
            // Moved to another section: goes to the end of that section.
            $data['sort_order'] = (int) WebsiteComplianceItem::where('section_id', $data['section_id'])->max('sort_order') + 1;
        }
        $item->update($data);

        return back()->with('success', 'Item updated.');
    }

    public function moveItem(Request $request, WebsiteComplianceItem $item): RedirectResponse
    {
        $siblings = WebsiteComplianceItem::where('section_id', $item->section_id)->orderBy('sort_order')->orderBy('id')->get();
        $this->move($siblings, $item, $this->direction($request));

        return back();
    }

    /** @return array<string, mixed> */
    private function sectionData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['is_active'] = (bool) $data['is_active'];

        return $data;
    }

    /** @return array<string, mixed> */
    private function itemData(Request $request): array
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('website_compliance_sections', 'id')],
            'label' => ['required', 'string', 'max:1000'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'applies_to' => ['required', Rule::in([WebsiteComplianceItem::APPLIES_TO_ALL, WebsiteComplianceItem::APPLIES_TO_PTIRU])],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['is_active'] = (bool) $data['is_active'];

        return $data;
    }

    private function direction(Request $request): string
    {
        return $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
    }

    /**
     * Swap a row with its neighbour, renumbering the list 1..n so the order is always explicit.
     *
     * @param  Collection<int, Model>  $rows
     */
    private function move($rows, Model $row, string $direction): void
    {
        $ids = $rows->pluck('id')->all();
        $from = array_search($row->getKey(), $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;
        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }
        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        DB::transaction(function () use ($rows, $ids): void {
            $byId = $rows->keyBy('id');
            foreach ($ids as $i => $id) {
                $byId[$id]->update(['sort_order' => $i + 1]);
            }
        });
    }
}
