<?php

declare(strict_types=1);

namespace Modules\Web\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ExpressPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\ApplicationFeeService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Institution invoices and online payment through BC Express Pay. The payer is
 * redirected to the Worldline hosted page and returns to receipt(), where the
 * result is confirmed server to server before the invoice is marked paid.
 */
class PaymentController extends Controller
{
    public const UNDER_REVIEW_MESSAGE = 'Your payment may have been received but could not be confirmed automatically. Please do not pay again. EQA staff will confirm it and update your invoice; contact EQA@gov.bc.ca with any questions.';

    public function __construct(private readonly ExpressPayService $expressPay) {}

    public function invoices(Request $request): Response
    {
        $institution = $this->institution($request);

        $invoices = $institution !== null && Schema::hasTable('invoices')
            ? DB::table('invoices')
                ->where('institution_crm_id', $institution->crm_id)
                ->orderByDesc('invoice_date')
                ->get()
                ->map(function (object $invoice): object {
                    $invoice->amount_due = $this->expressPay->amountDue($invoice);
                    $invoice->payable = $this->expressPay->isPayable($invoice) && ! $this->awaitingSubmission($invoice);
                    $invoice->payment_under_review = $this->expressPay->hasPaymentUnderReview((string) $invoice->crm_id);

                    return $invoice;
                })
            : collect();

        return Inertia::render('Web/Invoices', [
            'institution' => $institution,
            'invoices' => $invoices,
            'canPayOnline' => $this->canPay($request),
        ]);
    }

    public function pay(Request $request, string $crmId): SymfonyResponse
    {
        abort_unless($this->canPay($request), 403);
        $institution = $this->institution($request);
        abort_if($institution === null, 403);

        $invoice = DB::table('invoices')
            ->where('crm_id', $crmId)
            ->where('institution_crm_id', $institution->crm_id)
            ->first();
        abort_if($invoice === null, 404);
        if ($this->awaitingSubmission($invoice)) {
            return back()->with('error', 'Submit the application before paying its fee.');
        }

        // Settle earlier attempts first so an in-flight or already-approved payment is not duplicated.
        $open = DB::table('payment_transactions')
            ->where('invoice_crm_id', $invoice->crm_id)
            ->where('status', ExpressPayService::PENDING)
            ->get();
        foreach ($open as $transaction) {
            if ($this->expressPay->stillInProgress($transaction)) {
                $retryAt = Carbon::parse($transaction->hash_expires_at)->setTimezone('America/Vancouver')->format('g:i A');

                return back()->with('error', 'A payment for this invoice was started recently and could not be confirmed yet. Please try again after '.$retryAt.'.');
            }
        }

        $invoice = DB::table('invoices')->where('crm_id', $invoice->crm_id)->first();
        if ($this->expressPay->hasPaymentUnderReview((string) $invoice->crm_id)) {
            return back()->with('error', self::UNDER_REVIEW_MESSAGE);
        }
        if (! $this->expressPay->isPayable($invoice)) {
            return back()->with('error', 'This invoice has no outstanding balance.');
        }

        $payment = $this->expressPay->begin($invoice, route('web.payments.receipt'), $request->user()?->id);
        $request->session()->put('express_pay_order', $payment['order_number']);

        return Inertia::location($payment['url']);
    }

    /** Worldline redirects the payer here after the hosted payment page (approved, declined or cancelled). */
    public function receipt(Request $request): RedirectResponse
    {
        $sessionOrder = $request->session()->pull('express_pay_order');
        $orderNumber = (string) $request->query('trnOrderNumber', (string) $sessionOrder);

        $transaction = ctype_digit($orderNumber)
            ? DB::table('payment_transactions')->where('order_number', (int) $orderNumber)->first()
            : null;

        if ($transaction === null) {
            return redirect('/web/invoices')->with('error', 'We could not find that payment. If you were charged, please contact EQA@gov.bc.ca.');
        }

        // The redirect's query string can be altered by the user, so confirm with Worldline directly;
        // the redirect itself is only trusted when its hashValue verifies.
        $signedRedirect = $this->expressPay->verifiedRedirect((string) $request->server('QUERY_STRING', ''));
        $status = $this->expressPay->reconcile($transaction, $signedRedirect);
        $returnTo = $transaction->application_crm_id ? '/web/applications/'.$transaction->application_crm_id : '/web/invoices';

        if ($transaction->institution_crm_id !== $request->session()->get('portal_institution')) {
            return redirect('/web/invoices')->with('success', 'Your payment response has been received.');
        }

        [$level, $message] = match ($status) {
            ExpressPayService::APPROVED => ['success', 'Payment approved. Thank you, your invoice has been updated.'],
            ExpressPayService::CANCELLED => ['error', 'Payment was cancelled. Your invoice has not been paid.'],
            ExpressPayService::DECLINED, ExpressPayService::ABANDONED => ['error', 'Payment was not completed. Your invoice has not been paid.'],
            ExpressPayService::PENDING => ['error', 'We are still confirming your payment with the payment provider. Please check back in a few minutes.'],
            default => ['error', self::UNDER_REVIEW_MESSAGE],
        };

        return redirect($returnTo)->with($level, $message);
    }

    private function awaitingSubmission(object $invoice): bool
    {
        $application = $invoice->application_crm_id
            ? DB::table('applications')->where('crm_id', $invoice->application_crm_id)->first()
            : null;

        return $application !== null && app(ApplicationFeeService::class)->awaitingSubmission($application);
    }

    private function canPay(Request $request): bool
    {
        return $this->expressPay->canUserPay($request->user(), $request->session()->has('impersonator_id'));
    }

    private function institution(Request $request): ?object
    {
        $id = $request->session()->get('portal_institution');

        return $id && Schema::hasTable('institutions')
            ? DB::table('institutions')->where('crm_id', $id)->first()
            : null;
    }
}
