<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Renders an EQA invoice record as a downloadable PDF. */
class InvoicePdf
{
    private const FEE_LABELS = [
        'application_new' => 'New Application Fee',
        'application_renewal' => 'Renewal Application Fee',
        'admin_variable' => 'Administrative Fee',
        'appeal' => 'Appeal Fee',
    ];

    /** Placeholders admins can use in the invoice wording (Maintenance > Invoice). */
    public const PLACEHOLDERS = [
        'invoice_number', 'due_date', 'application_reference', 'institution_name',
        'contact_email', 'program_name', 'ministry_name',
    ];

    public function render(object $invoice): string
    {
        $institution = $invoice->institution_crm_id && Schema::hasTable('institutions')
            ? DB::table('institutions')->where('crm_id', $invoice->institution_crm_id)->first()
            : null;
        $date = fn (?string $value): string => $value ? Carbon::parse($value)->format('F j, Y') : '—';
        $tokens = [
            'invoice_number' => (string) ($invoice->invoice_number ?? ''),
            'due_date' => $date($invoice->due_date ?? null),
            'application_reference' => (string) ($invoice->application_reference ?? ''),
            'institution_name' => (string) ($institution->name ?? $invoice->institution_name ?? ''),
            'contact_email' => (string) config('eqa.ministry.email'),
            'program_name' => (string) config('eqa.ministry.program'),
            'ministry_name' => (string) config('eqa.ministry.name'),
        ];

        $html = view('invoices.invoice', [
            'invoice' => $invoice,
            'title' => self::FEE_LABELS[$invoice->fee_type ?? ''] ?? 'EQA Fees',
            'billTo' => $this->billTo($invoice, $institution),
            'lines' => $this->lines($invoice),
            'paid' => round((float) ($invoice->payment_amount ?? 0), 2),
            'balance' => round((float) ($invoice->invoice_balance ?? $invoice->total_charges ?? 0), 2),
            'date' => $date,
            'money' => fn ($value): string => '$'.number_format((float) ($value ?? 0), 2),
            'issuer' => array_values(array_filter(array_merge(
                [config('eqa.ministry.program'), config('eqa.ministry.branch'), config('eqa.ministry.name')],
                preg_split('/\R/', (string) config('eqa.ministry.address')) ?: [],
            ))),
            'issuerEmail' => config('eqa.ministry.email'),
            'text' => collect(['title', 'payment_online', 'payment_offline', 'due_text', 'paid_message', 'notes', 'footer'])
                ->mapWithKeys(fn (string $key) => [$key => $this->fill((string) config('eqa.invoice.'.$key), $tokens)])
                ->all(),
            'seal' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('images/eqa-seal.png'))),
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function filename(object $invoice): string
    {
        return 'EQA_Invoice_'.(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($invoice->invoice_number ?? '')) ?: 'invoice').'.pdf';
    }

    /** A made-up unpaid invoice used to preview the wording under Maintenance > Invoice. */
    public function sample(): object
    {
        return (object) [
            'crm_id' => 'preview',
            'institution_crm_id' => null,
            'institution_name' => 'Sample College',
            'application_reference' => 'APP-00000000',
            'invoice_number' => 'INV-00000000',
            'invoice_date' => Carbon::now('America/Vancouver')->toDateString(),
            'due_date' => Carbon::now('America/Vancouver')->addDays((int) config('eqa.deadlines.admin_fee_days', 30))->toDateString(),
            'fee_type' => 'admin_variable',
            'invoice_status' => 'Unpaid',
            'invoice_amount' => 3080,
            'taxes' => 0,
            'total_charges' => 3080,
            'invoice_balance' => 3080,
            'payment_amount' => null,
            'description' => null,
            'line_items' => json_encode([
                ['label' => 'Multiple Location Fee (private institution, 2 active locations)', 'quantity' => 1, 'unit_price' => 3000, 'amount' => 3000],
                ['label' => 'International Student Fee (study permit students)', 'quantity' => 40, 'unit_price' => 2, 'amount' => 80],
            ]),
        ];
    }

    /**
     * Admin wording is plain text: escape it, then fill the {placeholders} and keep line breaks.
     *
     * @param  array<string, string>  $tokens
     */
    private function fill(string $text, array $tokens): string
    {
        $html = e($text);
        foreach ($tokens as $key => $value) {
            $html = str_replace('{'.$key.'}', e($value), $html);
        }

        return nl2br($html, false);
    }

    /** @return array<int, string> */
    private function billTo(object $invoice, ?object $institution): array
    {
        $locality = implode(' ', array_filter([
            trim((string) ($institution->city ?? '')),
            trim((string) ($institution->province ?? '')),
            trim((string) ($institution->postal_code ?? '')),
        ]));

        return array_values(array_filter(array_map('trim', [
            (string) ($institution->legal_name ?? '') ?: (string) ($institution->name ?? $invoice->institution_name ?? ''),
            ! empty($institution->primary_contact) ? 'Attn: '.$institution->primary_contact : '',
            (string) ($institution->street1 ?? ''),
            (string) ($institution->street2 ?? ''),
            $locality,
            (string) ($institution->country ?? ''),
        ])));
    }

    /**
     * Itemized lines from the invoice's stored line items, falling back to a single
     * line for legacy invoices that only carry a total.
     *
     * @return array<int, array{label: string, quantity: int|float, unit_price: float, amount: float}>
     */
    private function lines(object $invoice): array
    {
        $items = json_decode((string) ($invoice->line_items ?? ''), true);
        if (is_array($items) && $items !== []) {
            return array_map(fn (array $l): array => [
                'label' => (string) ($l['label'] ?? ''),
                'quantity' => $l['quantity'] ?? 1,
                'unit_price' => (float) ($l['unit_price'] ?? $l['amount'] ?? 0),
                'amount' => (float) ($l['amount'] ?? 0),
            ], $items);
        }

        $amount = (float) ($invoice->invoice_amount ?? $invoice->total_charges ?? 0);

        return [[
            'label' => self::FEE_LABELS[$invoice->fee_type ?? ''] ?? 'EQA Designation Fees',
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
        ]];
    }
}
