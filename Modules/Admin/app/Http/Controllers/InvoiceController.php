<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Ministry (IDIR) invoices / financial summaries, migrated from the legacy
 * Dynamics eqa_financialsummary entity. Also generates the Word "Order
 * Receipt" by filling the migrated EQA_Order_Receipt.docx document template
 * (the Dynamics content-control data binding) with the invoice's data.
 */
class InvoiceController extends Controller
{
    /** Sortable columns: request key => invoices column. */
    private const SORTS = [
        'invoice_number' => 'invoice_number',
        'invoice_type' => 'fee_type',
        'invoice_date' => 'invoice_date',
        'institution' => 'institution_name',
        'application' => 'application_reference',
        'status' => 'invoice_status',
    ];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->string('q'));
        $sort = array_key_exists((string) $request->string('sort'), self::SORTS) ? (string) $request->string('sort') : 'invoice_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $column = self::SORTS[$sort];

        $invoices = Schema::hasTable('invoices')
            ? DB::table('invoices')
                ->when($q !== '', function ($b) use ($q) {
                    $like = '%'.$q.'%';
                    $b->where(function ($w) use ($like) {
                        $w->where('invoice_number', 'ilike', $like)
                            ->orWhere('institution_name', 'ilike', $like)
                            ->orWhere('application_reference', 'ilike', $like);
                    });
                })
                // Blanks last in either direction.
                ->orderByRaw("{$column} is null")
                ->orderBy($column, $direction)
                ->orderByDesc('invoice_number')
                ->paginate(25)
                ->withQueryString()
            : null;

        return Inertia::render('Admin/Invoices', [
            'invoices' => $invoices,
            'q' => $q,
            'filters' => ['sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function show(string $crmId): Response
    {
        abort_unless(Schema::hasTable('invoices'), 404);
        $invoice = DB::table('invoices')->where('crm_id', $crmId)->first();
        abort_if($invoice === null, 404);

        $transactions = Schema::hasTable('payment_transactions')
            ? DB::table('payment_transactions')
                ->where('invoice_crm_id', $crmId)
                ->orderByDesc('id')
                ->get(['order_number', 'amount', 'status', 'trn_id', 'auth_code', 'card_type', 'message_text', 'created_at', 'completed_at'])
            : collect();

        return Inertia::render('Admin/InvoiceView', ['invoice' => $invoice, 'transactions' => $transactions]);
    }

    public function receipt(string $crmId): BinaryFileResponse
    {
        abort_unless(Schema::hasTable('invoices'), 404);
        $invoice = DB::table('invoices')->where('crm_id', $crmId)->first();
        abort_if($invoice === null, 404);
        abort_unless($invoice->invoice_status === 'Paid', 403, 'The order receipt is available once the invoice is paid.');

        $institution = $invoice->institution_crm_id && Schema::hasTable('institutions')
            ? DB::table('institutions')->where('crm_id', $invoice->institution_crm_id)->first()
            : null;
        $application = $invoice->application_crm_id && Schema::hasTable('applications')
            ? DB::table('applications')->where('crm_id', $invoice->application_crm_id)->first()
            : null;

        $template = base_path('Modules/Admin/resources/templates/EQA_Order_Receipt.docx');
        abort_unless(is_file($template) && class_exists(ZipArchive::class), 404, 'Order-receipt template unavailable.');

        $money = static fn ($v): string => '$'.number_format((float) ($v ?? 0), 2);
        $map = [
            'eqa_invoicenumber' => (string) ($invoice->invoice_number ?? ''),
            'eqa_invoicedate' => (string) ($invoice->invoice_date ?? ''),
            'eqa_institutionname' => (string) ($invoice->institution_name ?? ''),
            'eqa_invoiceamount' => $money($invoice->invoice_amount),
            'eqa_taxes' => $money($invoice->taxes),
            'eqa_totalcharges' => $money($invoice->total_charges),
            'eqa_invoicebalance' => $money($invoice->invoice_balance),
            'eqa_applicationfee' => $money($application->application_fee ?? 0),
            'eqa_annualdesignationfee' => $money($application->annual_designation_fee ?? 0),
            'primarycontactidname' => (string) ($institution->primary_contact ?? ''),
            'address1_line1' => (string) ($institution->street1 ?? ''),
            'address1_city' => (string) ($institution->city ?? ''),
            'address1_stateorprovince' => (string) ($institution->province ?? ''),
            'address1_postalcode' => (string) ($institution->postal_code ?? ''),
            'address1_county' => (string) ($institution->country ?? ''),
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'rcpt');
        copy($template, $tmp);

        $zip = new ZipArchive();
        if ($zip->open($tmp) === true) {
            foreach (['customXml/item2.xml', 'word/document.xml'] as $part) {
                $xml = $zip->getFromName($part);
                if ($xml === false) {
                    continue;
                }
                foreach ($map as $field => $value) {
                    $safe = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xml = str_replace("<{$field}>{$field}</{$field}>", "<{$field}>{$safe}</{$field}>", $xml);
                    $xml = preg_replace_callback(
                        '/(<w:t[^>]*>)'.preg_quote($field, '/').'(<\/w:t>)/',
                        static fn (array $m): string => $m[1].$safe.$m[2],
                        $xml,
                    );
                }
                $zip->addFromString($part, $xml);
            }
            $zip->close();
        }

        $name = 'EQA_Order_Receipt_'.preg_replace('/[^A-Za-z0-9]/', '', (string) ($invoice->invoice_number ?? 'receipt')).'.docx';

        return response()->download($tmp, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }
}
