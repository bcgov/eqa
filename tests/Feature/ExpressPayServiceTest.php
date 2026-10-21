<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ExpressPayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExpressPayServiceTest extends TestCase
{
    private ExpressPayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.expresspay.enabled' => true,
            'services.expresspay.merchant_id' => '378129723',
            'services.expresspay.hash_key' => 'secret',
            'services.expresspay.payment_url' => 'https://test.pcir.gov.bc.ca/scripts/payment/payment.asp',
            'services.expresspay.query_url' => 'https://test.pcir.gov.bc.ca/scripts/process_transaction.asp',
            'services.expresspay.hash_expiry_minutes' => 30,
        ]);

        // Only the tables the service touches; the full migration set targets Postgres.
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('crm_id')->primary();
            $table->uuid('application_crm_id')->nullable();
            $table->uuid('institution_crm_id')->nullable();
            $table->decimal('invoice_amount', 12, 2)->nullable();
            $table->decimal('total_charges', 12, 2)->nullable();
            $table->decimal('invoice_balance', 12, 2)->nullable();
            $table->string('invoice_status')->nullable();
            $table->decimal('payment_amount', 12, 2)->nullable();
            $table->date('payment_received_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('transaction_reference')->nullable();
            $table->text('transaction_authorization')->nullable();
        });
        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('crm_id')->primary();
            $table->boolean('fees_payment_received')->nullable();
        });
        (require database_path('migrations/2026_10_09_000001_create_payment_transactions_table.php'))->up();

        DB::table('applications')->insert(['crm_id' => 'app-1', 'fees_payment_received' => false]);
        DB::table('invoices')->insert([
            'crm_id' => 'inv-1',
            'application_crm_id' => 'app-1',
            'institution_crm_id' => 'inst-1',
            'total_charges' => 80,
            'invoice_balance' => 80,
            'invoice_status' => 'Unpaid',
        ]);

        $this->service = app(ExpressPayService::class);
    }

    public function test_sign_appends_uppercase_md5_of_query_and_hash_key(): void
    {
        $signed = $this->service->sign(['merchant_id' => '1', 'ref1' => 'https://x.gov.bc.ca/r']);

        $query = 'merchant_id=1&ref1=https%3A%2F%2Fx.gov.bc.ca%2Fr';
        $this->assertSame($query.'&hashValue='.strtoupper(md5($query.'secret')), $signed);
    }

    public function test_begin_creates_pending_transaction_and_signed_payment_url(): void
    {
        $payment = $this->service->begin($this->invoice(), 'https://eqa.gov.bc.ca/payments/express-pay/receipt', 7);

        $this->assertSame(ExpressPayService::ORDER_NUMBER_BASE + 1, $payment['order_number']);
        $this->assertLessThanOrEqual(10, strlen((string) $payment['order_number']));
        $this->assertStringStartsWith('https://test.pcir.gov.bc.ca/scripts/payment/payment.asp?merchant_id=378129723&trnType=P&trnOrderNumber=500000001&ref1=https%3A%2F%2Feqa.gov.bc.ca', $payment['url']);
        $this->assertStringContainsString('&trnAmount=80.00&hashExpiry=', $payment['url']);
        $this->assertDatabaseHas('payment_transactions', ['order_number' => 500000001, 'status' => 'pending', 'amount' => 80, 'user_id' => 7]);
    }

    public function test_order_number_base_is_configurable_per_environment(): void
    {
        config(['services.expresspay.order_number_base' => 600_000_000]);

        $payment = $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $this->assertSame(600_000_001, $payment['order_number']);
    }

    public function test_approved_query_marks_transaction_and_invoice_paid(): void
    {
        Http::fake(['*' => Http::response('trnApproved=1&trnId=10000016&authCode=TEST&messageId=1&messageText=Approved&trnAmount=80.00&cardType=VI&trnOrderNumber=500000001')]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $status = $this->service->reconcile($this->transaction());

        $this->assertSame(ExpressPayService::APPROVED, $status);
        $this->assertDatabaseHas('payment_transactions', ['order_number' => 500000001, 'status' => 'approved', 'trn_id' => '10000016', 'auth_code' => 'TEST']);
        $invoice = $this->invoice();
        $this->assertSame('Paid', $invoice->invoice_status);
        $this->assertEquals(0, $invoice->invoice_balance);
        $this->assertSame('10000016', $invoice->transaction_reference);
        $this->assertEquals(1, DB::table('applications')->where('crm_id', 'app-1')->value('fees_payment_received'));
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'process_transaction.asp?requestType=BACKEND&merchant_id=378129723&trnType=Q&trnOrderNumber=500000001&hashValue='));
    }

    public function test_settling_twice_does_not_double_apply_payment(): void
    {
        Http::fake(['*' => Http::response('trnApproved=1&trnId=10000016&authCode=TEST&messageId=1&trnAmount=80.00')]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);
        $stale = $this->transaction();

        $this->service->reconcile($stale);
        $this->service->settle($stale, ['trnApproved' => '1', 'trnId' => '10000016', 'trnAmount' => '80.00']);

        $this->assertEquals(80, $this->invoice()->payment_amount);
    }

    public function test_amount_mismatch_is_flagged_for_review_and_invoice_untouched(): void
    {
        Http::fake(['*' => Http::response('trnApproved=1&trnId=10000016&trnAmount=1.00')]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $this->assertSame(ExpressPayService::REVIEW, $this->service->reconcile($this->transaction()));
        $this->assertSame('Unpaid', $this->invoice()->invoice_status);
    }

    public function test_outcome_mapping(): void
    {
        $future = Carbon::now()->addMinutes(10);
        $past = Carbon::now()->subMinute();

        $this->assertSame('approved', $this->service->outcome(['trnApproved' => '1'], $future));
        $this->assertSame('declined', $this->service->outcome(['trnApproved' => '0', 'messageId' => '7'], $future));
        $this->assertSame('cancelled', $this->service->outcome(['trnApproved' => '0', 'messageId' => '761'], $future));
        $this->assertSame('pending', $this->service->outcome(['trnApproved' => '0', 'messageId' => '559'], $future));
        $this->assertSame('abandoned', $this->service->outcome(['trnApproved' => '0', 'messageId' => '559'], $past));
        $this->assertSame('review', $this->service->outcome(['trnApproved' => '0', 'messageId' => '760'], $future));
        $this->assertSame('pending', $this->service->outcome(['trnApproved' => '0', 'errorType' => 'U', 'messageText' => 'Invalid hash'], $future));
        $this->assertSame('pending', $this->service->outcome([], $future));
    }

    public function test_gateway_failure_leaves_transaction_pending(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $this->assertSame(ExpressPayService::PENDING, $this->service->reconcile($this->transaction()));
        $this->assertSame('Unpaid', $this->invoice()->invoice_status);
    }

    public function test_unconfirmable_attempt_blocks_new_payment_only_until_it_expires(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $this->assertTrue($this->service->stillInProgress($this->transaction()));

        $this->travel(31)->minutes();
        $this->assertFalse($this->service->stillInProgress($this->transaction()));
        $this->assertSame('pending', $this->transaction()->status);
    }

    public function test_unprocessed_attempt_does_not_block_new_payment(): void
    {
        Http::fake(['*' => Http::response('trnApproved=0&messageId=559&messageText=Not+found')]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $this->assertFalse($this->service->stillInProgress($this->transaction()));
    }

    public function test_payment_under_review_blocks_paying_again(): void
    {
        Http::fake(['*' => Http::response('trnApproved=0&messageId=760&messageText=Multiple+Transaction+Matches+Found')]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $this->assertTrue($this->service->isPayable($this->invoice()));
        $this->assertSame(ExpressPayService::REVIEW, $this->service->reconcile($this->transaction()));

        $this->assertTrue($this->service->hasPaymentUnderReview('inv-1'));
        $this->assertFalse($this->service->isPayable($this->invoice()));
    }

    public function test_ambiguous_query_settles_from_hash_verified_receipt_redirect(): void
    {
        Http::fake(['*' => Http::response('trnApproved=0&messageId=760&messageText=Multiple+Transaction+Matches+Found')]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);

        $body = 'trnApproved=1&trnId=10000020&messageId=1&messageText=Approved&authCode=TEST&trnAmount=80.00&trnOrderNumber=500000001';
        $redirect = $this->service->verifiedRedirect($body.'&hashValue='.md5($body.'secret'));

        $this->assertSame(ExpressPayService::APPROVED, $this->service->reconcile($this->transaction(), $redirect));
        $this->assertSame('Paid', $this->invoice()->invoice_status);
        $this->assertSame('10000020', $this->invoice()->transaction_reference);
    }

    public function test_tampered_or_unsigned_receipt_redirect_is_ignored(): void
    {
        $body = 'trnApproved=1&trnId=10000020&trnAmount=80.00&trnOrderNumber=500000001';

        $this->assertNull($this->service->verifiedRedirect($body));
        $this->assertNull($this->service->verifiedRedirect($body.'&hashValue='.md5('trnApproved=0'.'secret')));
        $this->assertNull($this->service->verifiedRedirect(str_replace('80.00', '1.00', $body).'&hashValue='.md5($body.'secret')));
    }

    public function test_staff_can_accept_a_payment_under_review(): void
    {
        Http::fake(['*' => Http::response('trnApproved=0&messageId=760')]);
        $this->service->begin($this->invoice(), 'https://example.test/receipt', null);
        $this->service->reconcile($this->transaction());

        $this->assertTrue($this->service->acceptReviewed($this->transaction(), '10000021', 'TEST'));
        $this->assertFalse($this->service->acceptReviewed($this->transaction(), '10000021', 'TEST'));

        $this->assertSame('approved', $this->transaction()->status);
        $this->assertSame('Paid', $this->invoice()->invoice_status);
        $this->assertEquals(80, $this->invoice()->payment_amount);
        $this->assertEquals(1, DB::table('applications')->where('crm_id', 'app-1')->value('fees_payment_received'));
    }

    private function invoice(): object
    {
        return DB::table('invoices')->where('crm_id', 'inv-1')->first();
    }

    private function transaction(): object
    {
        return DB::table('payment_transactions')->where('order_number', 500000001)->first();
    }
}
