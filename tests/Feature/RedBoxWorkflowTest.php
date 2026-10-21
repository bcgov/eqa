<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Admin\Services\ApplicationFeeService;
use Tests\TestCase;

/**
 * One scenario per red box in the EQA flowchart (DRAFT V1.1). Notifications go out for
 * real through the mailer configured in .env, diverted to TESTING_EMAIL_ADDRESS.
 */
class RedBoxWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const INSTITUTION = '11111111-1111-1111-1111-111111111111';

    private const CONTACT_EMAIL = 'contact@institution.example';

    private string $testingAddress;

    private User $institutionUser;

    private User $ministryUser;

    /** @var array<int, array{to: string, subject: string}> */
    private array $delivered = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingAddress = trim((string) config('mail.testing_address'));
        if ($this->testingAddress === '') {
            $this->markTestSkipped('Set TESTING_EMAIL_ADDRESS in .env to run the red-box workflow tests.');
        }

        // phpunit.xml forces the array mailer; use the real one from .env so the emails are delivered.
        $dotenv = is_file(base_path('.env')) ? Dotenv::parse((string) file_get_contents(base_path('.env'))) : [];
        config(['mail.default' => $dotenv['MAIL_MAILER'] ?? 'smtp']);

        Event::listen(MessageSent::class, function (MessageSent $event): void {
            foreach ($event->message->getTo() as $address) {
                $this->delivered[] = ['to' => $address->getAddress(), 'subject' => (string) $event->message->getSubject()];
            }
        });

        config([
            'services.expresspay.enabled' => true,
            'services.expresspay.merchant_id' => '378129723',
            'services.expresspay.hash_key' => 'test-hash-key',
        ]);
        $this->fakeWorldlineApprovesEveryQuery();

        // Master switch OFF + no saved testing address: everything diverts to TESTING_EMAIL_ADDRESS.
        DB::table('email_settings')->update(['sending_enabled' => false, 'testing_email' => null]);

        DB::table('institutions')->insert(['crm_id' => self::INSTITUTION, 'name' => 'Red Box College', 'primary_contact' => 'Pat Contact']);
        DB::table('institution_users')->insert([
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => self::INSTITUTION,
            'full_name' => 'Pat Contact',
            'email' => self::CONTACT_EMAIL,
            'role' => 'Admin',
            'web_user_active' => true,
        ]);

        $this->institutionUser = $this->user('inst@example.test', Role::INSTITUTION_ADMIN, ['bceid_user_guid' => 'BCEIDGUID']);
        $this->ministryUser = $this->user('staff@example.test', Role::MINISTRY_ADMIN, ['idir_user_guid' => 'IDIRGUID']);
    }

    // Phase 1 ----------------------------------------------------------------

    public function test_pay_new_application_fee(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm())->assertRedirect();

        $application = DB::table('applications')->first();
        $this->assertSame('Draft', $application->status);
        $this->assertSame(0, DB::table('invoices')->count(), 'No fee is requested before the application is submitted.');

        $this->asInstitution()->post("/web/applications/{$application->crm_id}/submit")->assertSessionHas('success');
        $invoice = $this->invoice($application->crm_id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertEquals(config('eqa.fees.application_new'), $invoice->total_charges);
        $this->assertNotified('application_submission');

        $this->payOnline($invoice);

        $application = $this->application($application->crm_id);
        $this->assertSame('Pending Review', $application->status);
        $this->assertSame('pending_review', $application->workflow_stage);
        $this->assertEquals(1, $application->fees_payment_received);
        $this->assertSame('Paid', $this->invoice($application->crm_id, ApplicationFeeService::FEE_APPLICATION_NEW)->invoice_status);
    }

    public function test_draft_application_fee_cannot_be_paid_before_submission(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm());
        $application = DB::table('applications')->first();
        // Drafts saved before this rule already have a fee invoice.
        $invoice = app(ApplicationFeeService::class)->createInvoice($application, ApplicationFeeService::FEE_APPLICATION_NEW, 1000);

        $this->asInstitution()->post("/web/invoices/{$invoice->crm_id}/pay")->assertSessionHas('error');

        $this->assertSame(0, DB::table('payment_transactions')->count());
        $this->viewApplication($application->crm_id)
            ->assertJsonPath('props.awaitingSubmission', true)
            ->assertJsonPath('props.invoices.0.payable', false);
    }

    public function test_ministry_cannot_submit_a_draft(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm());
        $id = DB::table('applications')->value('crm_id');

        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'submit'])->assertSessionHas('error');

        $this->assertSame('draft', $this->application($id)->workflow_stage);
        $this->assertSame(0, DB::table('invoices')->count());
    }

    public function test_pay_renewal_application_fee(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update([
            'eqa_status' => 'Designated',
            'designation_start' => '2025-01-01',
            'designation_expiry' => Carbon::now()->addMonth()->toDateString(),
        ]);

        $this->asInstitution()->post('/web/applications', $this->applicationForm())->assertRedirect();

        $application = DB::table('applications')->first();
        $this->asInstitution()->post("/web/applications/{$application->crm_id}/submit");
        $invoice = $this->invoice($application->crm_id, ApplicationFeeService::FEE_APPLICATION_RENEWAL);
        $this->assertEquals(config('eqa.fees.application_renewal'), $invoice->total_charges);

        $this->payOnline($invoice);

        $this->assertSame('Pending Review', $this->application($application->crm_id)->status);
        $this->assertNotified('reapplication_submission');
    }

    // Phase 2 ----------------------------------------------------------------

    public function test_confirm_payment_uses_the_application_fee_invoice(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm());
        $id = DB::table('applications')->value('crm_id');
        // Submit first, then pay by cheque.
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertRedirect();
        DB::table('applications')->where('crm_id', $id)->update(['receipt_confirmation' => true]);

        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'record_payment', 'payment_method' => 'Cheque'])
            ->assertSessionHas('success');

        $this->assertSame(1, DB::table('invoices')->where('application_crm_id', $id)->count());
        $invoice = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertSame('Paid', $invoice->invoice_status);
        $this->assertSame('Cheque', $invoice->payment_method);
        $this->assertSame('fees_payment', $this->application($id)->workflow_stage);
        $this->assertEquals(1, $this->application($id)->fees_payment_received);
    }

    public function test_open_application_for_changes(): void
    {
        $id = $this->applicationAt('under_review', 'Under Review');

        $this->asMinistry()->post("/admin/applications/{$id}/advance", [
            'action' => 'request_changes',
            'changes' => 'Upload the current EQA policy manual.',
        ])->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Draft', $application->status);
        $this->assertSame('draft', $application->workflow_stage);
        $this->assertEquals(1, $application->need_additional_details);
        $this->assertSame(Carbon::now('America/Vancouver')->addDays(30)->toDateString(), $application->changes_due_date);
        $this->assertNotified('application_changes_requested', 'Upload the current EQA policy manual.');

        // Inst. provides more info within deadline -> back to Under Review.
        $this->asInstitution()->put("/web/applications/{$id}", $this->applicationForm(['total_enrolment' => 250]))->assertRedirect();
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertRedirect();

        $application = $this->application($id);
        $this->assertSame('Under Review', $application->status);
        $this->assertEquals(250, $application->total_enrolment);
    }

    public function test_open_application_for_changes_expires_when_not_resubmitted(): void
    {
        $id = $this->applicationAt('under_review', 'Under Review');
        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'request_changes', 'changes' => 'Missing info.']);

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $this->assertSame('Expired', $this->application($id)->status);
    }

    public function test_approve_with_compliance_order(): void
    {
        $id = $this->applicationAt('application_approval', 'Under Review');

        $this->asMinistry()->post("/admin/applications/{$id}/advance", [
            'action' => 'approve',
            'admin_fee' => 750,
            'compliance_order' => true,
            'compliance_order_notes' => 'Correct the refund policy within 60 days.',
        ])->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Approved (Under Compliance Order)', $application->status);
        $this->assertEquals(1, $application->compliance_order);
        $this->assertSame('admin_fee', $application->workflow_stage);
        $this->assertNotified('application_approved_fee_due', 'Correct the refund policy within 60 days.');
    }

    public function test_approve_without_compliance_order(): void
    {
        $id = $this->applicationAt('application_approval', 'Under Review');

        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'approve', 'admin_fee' => 750])
            ->assertSessionHas('success');

        $this->assertSame('Approved', $this->application($id)->status);
        $this->assertNotified('application_approved_fee_due');
    }

    // Phase 3 ----------------------------------------------------------------

    public function test_generate_invoice_for_admin_variable_fee(): void
    {
        $id = $this->applicationAt('application_approval', 'Under Review');

        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'approve', 'admin_fee' => 812.50]);

        $invoice = $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE);
        $this->assertNotNull($invoice);
        $this->assertEquals(812.50, $invoice->total_charges);
        $this->assertSame('Unpaid', $invoice->invoice_status);
        $this->assertSame(Carbon::now('America/Vancouver')->addDays(30)->toDateString(), $invoice->due_date);
        // Designation waits for the fee.
        $this->assertNull(DB::table('institutions')->where('crm_id', self::INSTITUTION)->value('eqa_status'));
        $this->assertNotified('application_approved_fee_due', $invoice->invoice_number);
    }

    public function test_inst_pays_admin_variable_fee(): void
    {
        $id = $this->applicationAt('application_approval', 'Under Review');
        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'approve', 'admin_fee' => 500]);

        $this->payOnline($this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE));

        $this->assertSame('end', $this->application($id)->workflow_stage);
        $this->assertSame('Paid', $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE)->invoice_status);
        $this->assertSame('Designated', DB::table('institutions')->where('crm_id', self::INSTITUTION)->value('eqa_status'));
        $this->assertNotified('application_approved');
    }

    public function test_inst_does_not_pay_admin_variable_fee_expires(): void
    {
        $id = $this->applicationAt('application_approval', 'Under Review');
        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'approve', 'admin_fee' => 500]);

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $this->assertSame('Expired', $this->application($id)->status);
        $this->assertSame('Expired', $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE)->invoice_status);
        $this->assertNull(DB::table('institutions')->where('crm_id', self::INSTITUTION)->value('eqa_status'));
    }

    public function test_appeal_button_shows_up_within_deadline(): void
    {
        $id = $this->declinedApplication();

        $this->assertNotified('application_not_approved');
        $this->viewApplication($id)->assertJsonPath('props.canAppeal', true);

        $this->travel(31)->days();
        $this->viewApplication($id)->assertJsonPath('props.canAppeal', false);
        $this->asInstitution()->post("/web/applications/{$id}/appeal")->assertSessionHas('error');
        $this->assertSame('Not Approved', $this->application($id)->status);
    }

    public function test_appeal_pays_appeal_fee_pending_appeal(): void
    {
        $id = $this->declinedApplication();

        $this->asInstitution()->post("/web/applications/{$id}/appeal")->assertSessionHas('success');

        $application = $this->application($id);
        $invoice = $this->invoice($id, ApplicationFeeService::FEE_APPEAL);
        $this->assertSame('Pending Appeal', $application->status);
        $this->assertEquals(config('eqa.fees.appeal'), $invoice->total_charges);

        $this->payOnline($invoice);

        $this->assertSame('appeal_review', $this->application($id)->workflow_stage);
        $this->assertNotified('appeal_submission', $invoice->invoice_number);
    }

    public function test_appeal_upheld_sets_application_status_declined(): void
    {
        $id = $this->appealUnderReview();

        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'appeal_uphold'])->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Declined', $application->status);
        $this->assertSame('Upheld', $application->appeal_decision);
        $this->assertSame('end', $application->workflow_stage);
    }

    public function test_appeal_overturned_returns_to_approve_with_compliance_order(): void
    {
        $id = $this->appealUnderReview();

        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'appeal_overturn'])->assertSessionHas('success');
        $this->assertSame('application_approval', $this->application($id)->workflow_stage);

        $this->asMinistry()->post("/admin/applications/{$id}/advance", [
            'action' => 'approve',
            'admin_fee' => 500,
            'compliance_order' => true,
            'compliance_order_notes' => 'Quarterly reporting for one year.',
        ]);

        $this->assertSame('Approved (Under Compliance Order)', $this->application($id)->status);
        $this->assertNotified('application_approved_fee_due', 'Quarterly reporting for one year.');
    }

    // Helpers ------------------------------------------------------------------

    /** The notification was delivered to TESTING_EMAIL_ADDRESS instead of the institution contact. */
    private function assertNotified(string $templateKey, ?string $bodyContains = null): void
    {
        $email = DB::table('sent_emails')->where('template_key', $templateKey)->orderByDesc('id')->first();

        $this->assertNotNull($email, "No '{$templateKey}' notification was sent.");
        $this->assertSame('sent', $email->status, "'{$templateKey}' failed to send: ".$email->error);
        $this->assertSame($this->testingAddress, $email->recipient);
        $this->assertSame(self::CONTACT_EMAIL, $email->intended_recipient);
        $this->assertContains(
            ['to' => $this->testingAddress, 'subject' => $email->subject],
            $this->delivered,
            "'{$templateKey}' was not handed to the mailer for {$this->testingAddress}.",
        );
        if ($bodyContains !== null) {
            $this->assertStringContainsString($bodyContains, $email->body);
        }
    }

    private function payOnline(object $invoice): void
    {
        $this->asInstitution()->post("/web/invoices/{$invoice->crm_id}/pay")->assertRedirect();

        $order = DB::table('payment_transactions')->where('invoice_crm_id', $invoice->crm_id)->orderByDesc('id')->value('order_number');
        $this->assertNotNull($order, 'No Express Pay transaction was started.');

        $this->get('/payments/express-pay/receipt?trnOrderNumber='.$order)->assertRedirect();
        $this->assertSame('approved', DB::table('payment_transactions')->where('order_number', $order)->value('status'));
    }

    private function fakeWorldlineApprovesEveryQuery(): void
    {
        Http::fake(function (HttpRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $amount = DB::table('payment_transactions')->where('order_number', $query['trnOrderNumber'] ?? 0)->value('amount');

            return Http::response(http_build_query([
                'trnApproved' => '1',
                'trnId' => '10000016',
                'authCode' => 'TEST',
                'messageId' => '1',
                'messageText' => 'Approved',
                'trnAmount' => number_format((float) $amount, 2, '.', ''),
                'cardType' => 'VI',
                'trnOrderNumber' => $query['trnOrderNumber'] ?? '',
            ]));
        });
    }

    private function declinedApplication(): string
    {
        $id = $this->applicationAt('non_approval_reasons', 'Under Review');
        $this->asMinistry()->post("/admin/applications/{$id}/advance", ['action' => 'finalize'])->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Not Approved', $application->status);
        $this->assertSame(Carbon::now('America/Vancouver')->addDays(30)->toDateString(), $application->appeal_deadline);

        return $id;
    }

    private function appealUnderReview(): string
    {
        $id = $this->declinedApplication();
        $this->asInstitution()->post("/web/applications/{$id}/appeal");
        $this->payOnline($this->invoice($id, ApplicationFeeService::FEE_APPEAL));
        $this->assertSame('appeal_review', $this->application($id)->workflow_stage);

        return $id;
    }

    private function applicationAt(string $stage, string $status): string
    {
        $id = (string) Str::uuid();
        DB::table('applications')->insert([
            'crm_id' => $id,
            'reference' => 'APP-'.random_int(10000000, 99999999),
            'status' => $status,
            'workflow_stage' => $stage,
            'institution_crm_id' => self::INSTITUTION,
            'institution_name' => 'Red Box College',
            'application_date' => Carbon::now()->toDateString(),
            'fees_payment_received' => true,
        ]);

        return $id;
    }

    private function viewApplication(string $id)
    {
        $version = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->asInstitution()
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->get("/web/applications/{$id}")
            ->assertOk();
    }

    private function asInstitution(): static
    {
        return $this->actingAs($this->institutionUser)->withSession(['portal_institution' => self::INSTITUTION, 'portal_role' => 'institution']);
    }

    private function asMinistry(): static
    {
        return $this->actingAs($this->ministryUser)->withSession(['portal_role' => 'ministry']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function user(string $email, string $role, array $attributes): User
    {
        $user = User::create(array_merge(['name' => $email, 'email' => $email, 'password' => Str::random(32)], $attributes));
        $user->roles()->attach(Role::where('name', $role)->value('id'));

        return $user->load('roles');
    }

    /** @param  array<string, mixed>  $overrides */
    private function applicationForm(array $overrides = []): array
    {
        return array_merge([
            'total_enrolment' => 120,
            'enrolment_type' => 'FTE',
            'program_career_training' => true,
            'affirm_policy_manual' => true,
            'affirm_website_compliance' => true,
            'affirm_written_permission' => true,
            'affirm_branding_guide' => true,
            'affirm_understands_comply' => true,
            'affirm_authorized' => true,
            'representative_signature' => 'Pat Contact',
        ], $overrides);
    }

    private function application(string $id): object
    {
        return DB::table('applications')->where('crm_id', $id)->first();
    }

    private function invoice(string $applicationId, string $feeType): ?object
    {
        return DB::table('invoices')->where('application_crm_id', $applicationId)->where('fee_type', $feeType)->first();
    }
}
