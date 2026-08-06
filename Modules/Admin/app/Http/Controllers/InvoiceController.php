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
    public function index(Request $request): Response
    {
        $q = trim((string) $request->string('q'));

        $invoices = Schema::hasTable('invoices')
            ? DB::table('invoices')
                ->when($q !== '', function ($b) use ($q) {
                    $like = '%'.$q.'%';
                    $b->where('invoice_number', 'ilike', $like)
                      ->orWhere('institution_name', 'ilike', $like)
                      ->orWhere('application_reference', 'ilike', $like);
                })
                ->orderByDesc('invoice_date')
                ->limit(1000)
                ->get()
            : collect();

        return Inertia::render('Admin/Invoices', ['invoices' => $invoices, 'q' => $q]);
    }

    public function show(string $crmId): Response
    {
        abort_unless(Schema::hasTable('invoices'), 404);
        $invoice = DB::table('invoices')->where('crm_id', $crmId)->first();
        abort_if($invoice === null, 404);

        return Inertia::render('Admin/InvoiceView', ['invoice' => $invoice]);
    }

    public function receipt(string $crmId): BinaryFileResponse
    {
        abort_unless(Schema::hasTable('invoices'), 404);
        $invoice = DB::table('invoices')->where('crm_id', $crmId)->first();
        abort_if($invoice === null, 404);

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
