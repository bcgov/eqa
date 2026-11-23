<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ExpressPayService;
use App\Services\InvoicePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ministry (IDIR) invoices / financial summaries, migrated from the legacy
 * Dynamics eqa_financialsummary entity, plus the payment receipt PDF for paid
 * invoices (App\Services\InvoicePdf, shared with the institution portal).
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

    /** Staff found this payment in the Worldline report: apply it to the invoice. */
    public function acceptPayment(Request $request, ExpressPayService $expressPay, string $crmId, string $orderNumber): RedirectResponse
    {
        $data = $request->validate([
            'trn_id' => ['required', 'regex:/^\d{1,20}$/'],
            'auth_code' => ['nullable', 'string', 'max:32'],
        ]);

        $invoice = DB::table('invoices')->where('crm_id', $crmId)->first();
        if (($invoice->invoice_status ?? null) === 'Paid') {
            return back()->with('error', 'This invoice is already paid. Refund the duplicate charge in Worldline instead.');
        }

        $accepted = $expressPay->acceptUnconfirmed($this->reviewTransaction($crmId, $orderNumber), $data['trn_id'], $data['auth_code'] ?? null);

        return back()->with($accepted ? 'success' : 'error', $accepted ? 'Payment accepted and applied to the invoice.' : 'This transaction can no longer be accepted.');
    }

    /** Staff confirmed in Worldline that this attempt was not charged (or was refunded). */
    public function dismissPayment(ExpressPayService $expressPay, string $crmId, string $orderNumber): RedirectResponse
    {
        $updated = DB::table('payment_transactions')
            ->where('id', $this->reviewTransaction($crmId, $orderNumber)->id)
            ->where('status', ExpressPayService::REVIEW)
            ->update([
                'status' => ExpressPayService::CANCELLED,
                'message_text' => 'Dismissed by EQA staff: not charged or refunded in Worldline',
                'updated_at' => now(),
            ]);

        return back()->with($updated ? 'success' : 'error', $updated ? 'Transaction dismissed.' : 'This transaction is no longer under review.');
    }

    private function reviewTransaction(string $crmId, string $orderNumber): object
    {
        abort_unless(ctype_digit($orderNumber), 404);
        $transaction = DB::table('payment_transactions')
            ->where('invoice_crm_id', $crmId)
            ->where('order_number', (int) $orderNumber)
            ->first();
        abort_if($transaction === null, 404);

        return $transaction;
    }

    /** The payment receipt PDF for a paid invoice (the same document the institution receives). */
    public function receipt(string $crmId, InvoicePdf $pdf): HttpResponse
    {
        abort_unless(Schema::hasTable('invoices'), 404);
        $invoice = DB::table('invoices')->where('crm_id', $crmId)->first();
        abort_if($invoice === null, 404);
        abort_unless($invoice->invoice_status === 'Paid', 403, 'The receipt is available once the invoice is paid.');

        return response($pdf->render($invoice, true), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice, true).'"',
        ]);
    }
}
