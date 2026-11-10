<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Admin\Services\ApplicationFeeService;
use Modules\Admin\Services\DesignationService;
use Tests\TestCase;

/**
 * End-to-end application process through the institution portal and the ministry workflow:
 * new and renewal applications, approval (with/without compliance order), suitability review,
 * decline, appeal and the deadline expiries. Runs offline: Worldline is faked and emails go to
 * the array mailer (recorded in sent_emails).
 */
class ApplicationProcessTest extends TestCase
{
    use RefreshDatabase;

    private const INSTITUTION = '22222222-2222-2222-2222-222222222222';

    private const CONTACT_EMAIL = 'contact@process.example';

    private const TESTING_EMAIL = 'eqa-tests@example.test';

    private User $institutionUser;

    private User $ministryUser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'eqa.fees.application_new' => 7500,
            'eqa.fees.application_renewal' => 5500,
            'eqa.fees.appeal' => 250,
            'eqa.fees.multi_location' => 3000,
            'eqa.fees.per_international_student' => 2,
            'eqa.deadlines.admin_fee_days' => 30,
            'eqa.deadlines.appeal_days' => 30,
            'eqa.deadlines.changes_days' => 30,
            'services.expresspay.enabled' => true,
            'services.expresspay.merchant_id' => '378129723',
            'services.expresspay.hash_key' => 'test-hash-key',
            'services.expresspay.payment_url' => 'https://test.pcir.gov.bc.ca/scripts/payment/payment.asp',
            'services.expresspay.query_url' => 'https://test.pcir.gov.bc.ca/scripts/process_transaction.asp',
        ]);
        $this->fakeWorldlineApprovesEveryQuery();

        // Master switch OFF + testing address: every notification is rendered, sent to the test address and audited.
        DB::table('email_settings')->update(['sending_enabled' => false, 'testing_email' => self::TESTING_EMAIL]);

        DB::table('institutions')->insert(['crm_id' => self::INSTITUTION, 'name' => 'Process College', 'primary_contact' => 'Pat Contact']);
        DB::table('institution_users')->insert([
            'crm_id' => (string) Str::uuid(),
            'institution_crm_id' => self::INSTITUTION,
            'full_name' => 'Pat Contact',
            'email' => self::CONTACT_EMAIL,
            'role' => 'Admin',
            'web_user_active' => true,
        ]);

        $this->institutionUser = $this->user('inst@process.example', Role::INSTITUTION_ADMIN, ['bceid_user_guid' => 'PROCESSBCEID']);
        $this->ministryUser = $this->user('staff@process.example', Role::MINISTRY_ADMIN, ['idir_user_guid' => 'PROCESSIDIR']);
    }

    // New application -----------------------------------------------------------

    public function test_new_application_from_draft_to_designation(): void
    {
        $id = $this->startApplication(['intl_students_permit' => 40]);

        $this->assertSame('Draft', $this->application($id)->status);
        $this->assertSame(0, DB::table('invoices')->count(), 'No fee is invoiced while the application is a draft.');

        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Pending Review', $application->status);
        $this->assertSame('pending_review', $application->workflow_stage);
        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertEquals(7500, $fee->total_charges);
        $this->assertSame('New Application Fee (fixed): $7,500.00', $fee->description);
        $this->assertEmailed('application_submission');

        $this->payOnline($fee);
        $this->assertSame('Paid', $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW)->invoice_status);
        $this->assertEquals(1, $this->application($id)->fees_payment_received);

        $this->reviewUntil($id, 'under_review');
        $this->assertSame('Under Review', $this->application($id)->status);

        $this->advance($id, 'eligible', ['eqa_good_standing' => true])->assertSessionHas('success');
        $this->assertSame('application_approval', $this->application($id)->workflow_stage);

        $this->advance($id, 'approve')->assertSessionHas('success');
        $application = $this->application($id);
        $this->assertSame('Approved', $application->status);
        $this->assertSame('admin_fee', $application->workflow_stage);
        $adminFee = $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE);
        $this->assertEquals(80, $adminFee->total_charges, '40 study permit students x $2; one location, so no multiple location fee.');
        $this->assertSame($this->vancouverDate(30), $adminFee->due_date);
        $this->assertEmailed('application_approved_fee_due', $adminFee->invoice_number);
        $this->assertNotSame('Designated', $this->institution()->eqa_status, 'Designation waits for the administrative fee.');

        $this->payOnline($adminFee);

        $application = $this->application($id);
        $this->assertSame('end', $application->workflow_stage);
        $this->assertEquals(1, $application->process_completed);
        $institution = $this->institution();
        $this->assertSame('Designated', $institution->eqa_status);
        $this->assertSame($application->approved_date, $institution->designation_start);
        $this->assertEmailed('application_approved');
    }

    public function test_application_fee_can_be_paid_by_another_method_and_confirmed_by_ministry(): void
    {
        $id = $this->submittedApplication();

        $this->asMinistry()->put("/admin/applications/{$id}", ['receipt_confirmation' => true]);
        $this->advance($id, 'record_payment', ['payment_method' => 'Cheque'])->assertSessionHas('success');

        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertSame('Paid', $fee->invoice_status);
        $this->assertSame('Cheque', $fee->payment_method);
        $this->assertSame('fees_payment', $this->application($id)->workflow_stage);
        $this->assertSame(0, DB::table('payment_transactions')->count());
    }

    public function test_draft_cannot_be_paid_or_submitted_by_ministry(): void
    {
        $id = $this->startApplication();
        $draft = $this->application($id);
        $legacyInvoice = app(ApplicationFeeService::class)->createInvoice($draft, ApplicationFeeService::FEE_APPLICATION_NEW, 7500);

        $this->asInstitution()->post("/web/invoices/{$legacyInvoice->crm_id}/pay")->assertSessionHas('error');
        $this->advance($id, 'submit')->assertSessionHas('error');

        $this->assertSame('draft', $this->application($id)->workflow_stage);
        $this->assertSame(0, DB::table('payment_transactions')->count());
    }

    public function test_only_one_open_application_at_a_time(): void
    {
        $this->startApplication();

        $this->asInstitution()->post('/web/applications', $this->applicationForm())
            ->assertRedirect('/web/applications')
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('applications')->count());
    }

    public function test_review_steps_are_gated_by_required_checks(): void
    {
        $id = $this->submittedApplication();

        $this->advance($id, 'record_payment')->assertSessionHas('error');
        $this->assertSame('pending_review', $this->application($id)->workflow_stage);

        $this->asMinistry()->put("/admin/applications/{$id}", ['receipt_confirmation' => true]);
        $this->advance($id, 'record_payment')->assertSessionHas('success');

        $this->advance($id, 'begin_review')->assertSessionHas('error');
        $this->assertSame('fees_payment', $this->application($id)->workflow_stage);

        $this->asMinistry()->put("/admin/applications/{$id}", ['ready_for_review' => true]);
        $this->advance($id, 'begin_review')->assertSessionHas('success');
        $this->assertSame('under_review', $this->application($id)->workflow_stage);
    }

    public function test_actions_outside_the_current_stage_are_rejected(): void
    {
        $id = $this->applicationAt('under_review');

        $this->advance($id, 'approve')->assertSessionHas('error');
        $this->advance($id, 'appeal_overturn')->assertSessionHas('error');

        $this->assertSame('under_review', $this->application($id)->workflow_stage);
        $this->assertSame(0, DB::table('invoices')->count());
    }

    // Renewal ---------------------------------------------------------------------

    public function test_renewal_is_charged_the_renewal_fee_and_keeps_the_original_designation_start(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update([
            'eqa_status' => 'Designated',
            'designation_start' => '2020-04-01',
            'designation_expiry' => Carbon::now()->addMonth()->toDateString(),
        ]);

        $id = $this->submittedApplication(['intl_students_permit' => 20]);

        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_RENEWAL);
        $this->assertNotNull($fee);
        $this->assertNull($this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW));
        $this->assertEquals(5500, $fee->total_charges);
        $this->assertSame('Renewal Application Fee (fixed): $5,500.00', $fee->description);
        $this->assertEmailed('reapplication_submission');

        $this->payOnline($fee);
        $this->reviewUntil($id, 'under_review');
        $this->advance($id, 'eligible');
        $this->advance($id, 'approve');
        $this->payOnline($this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE));

        $this->assertSame('Designated', $this->institution()->eqa_status);
        $this->assertSame('2020-04-01', $this->institution()->designation_start);
    }

    public function test_next_application_after_an_approval_is_a_renewal(): void
    {
        $first = $this->applicationAt('end');
        DB::table('applications')->where('crm_id', $first)->update(['status' => 'Approved', 'approved_date' => '2025-01-15']);

        $id = $this->submittedApplication();

        $this->assertNotNull($this->invoice($id, ApplicationFeeService::FEE_APPLICATION_RENEWAL));
    }

    // Accepted ----------------------------------------------------------------------

    public function test_approval_with_compliance_order(): void
    {
        $id = $this->applicationAt('application_approval');

        $this->advance($id, 'approve', [
            'compliance_order' => true,
            'compliance_order_notes' => 'Publish the refund policy within 60 days.',
        ])->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Approved (Under Compliance Order)', $application->status);
        $this->assertEquals(1, $application->compliance_order);
        $this->assertSame('Publish the refund policy within 60 days.', $application->compliance_order_notes);
        $this->assertEmailed('application_approved_fee_due', 'Publish the refund policy within 60 days.');
    }

    public function test_compliance_order_requires_details(): void
    {
        $id = $this->applicationAt('application_approval');

        $this->advance($id, 'approve', ['compliance_order' => true])->assertSessionHasErrors('compliance_order_notes');

        $this->assertSame('application_approval', $this->application($id)->workflow_stage);
    }

    public function test_approval_with_no_administrative_fee_designates_immediately(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['qa_met_through' => ApplicationFeeService::PUBLIC_QA_MET_THROUGH]);
        $id = $this->applicationAt('application_approval', ['intl_students_permit' => 0]);

        $this->advance($id, 'approve')->assertSessionHas('success');

        $adminFee = $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE);
        $this->assertSame('Paid', $adminFee->invoice_status);
        $this->assertSame('No Charge', $adminFee->payment_method);
        $this->assertSame('end', $this->application($id)->workflow_stage);
        $this->assertSame('Designated', $this->institution()->eqa_status);
    }

    public function test_ministry_can_confirm_an_administrative_fee_paid_offline(): void
    {
        $id = $this->applicationAt('application_approval');
        $this->advance($id, 'approve');

        $this->advance($id, 'confirm_admin_fee', ['payment_method' => 'EFT'])->assertSessionHas('success');

        $this->assertSame('EFT', $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE)->payment_method);
        $this->assertSame('Designated', $this->institution()->eqa_status);
    }

    public function test_ptiru_institution_not_in_good_standing_is_not_designated(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update([
            'qa_met_through' => DesignationService::PTIRU_QA_MET_THROUGH,
            'ptib_standing' => 'Not In Good Standing',
        ]);
        $id = $this->applicationAt('application_approval');

        $this->advance($id, 'approve');
        $this->payOnline($this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE));

        $this->assertSame('end', $this->application($id)->workflow_stage);
        $this->assertNotSame('Designated', $this->institution()->eqa_status);
    }

    public function test_unpaid_administrative_fee_expires_the_application(): void
    {
        $id = $this->applicationAt('application_approval');
        $this->advance($id, 'approve');

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $this->assertSame('Expired', $this->application($id)->status);
        $this->assertSame('Expired', $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE)->invoice_status);
        $this->assertNotSame('Designated', $this->institution()->eqa_status);
    }

    // Suitability review ----------------------------------------------------------------

    public function test_suitability_review_passed_leads_to_approval(): void
    {
        $id = $this->applicationAt('under_review');

        $this->advance($id, 'refer_suitability')->assertSessionHas('success');
        $this->assertSame('Suitability Review', $this->application($id)->status);
        $this->assertSame('Suitability Review Needed', $this->application($id)->eligibility);

        $this->advance($id, 'pass')->assertSessionHas('success');

        $this->assertSame('Approved', $this->application($id)->status);
        $this->assertSame('admin_fee', $this->application($id)->workflow_stage);
        $this->assertNotNull($this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE));
    }

    public function test_suitability_review_failed_is_not_approved(): void
    {
        $id = $this->applicationAt('under_review');
        $this->advance($id, 'refer_suitability');

        $this->advance($id, 'fail')->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Not Approved', $application->status);
        $this->assertSame('end', $application->workflow_stage);
        $this->assertSame($this->vancouverDate(30), $application->appeal_deadline);
    }

    // Declined --------------------------------------------------------------------------

    public function test_declined_application_records_reasons_and_opens_the_appeal_window(): void
    {
        $id = $this->declinedApplication('Missing audited financial statements.');

        $application = $this->application($id);
        $this->assertSame('Not Approved', $application->status);
        $this->assertSame('Requirements Not Met', $application->eligibility);
        $this->assertSame('Missing audited financial statements.', $application->non_approval_reasons);
        $this->assertEquals(0, $application->designation_decision);
        $this->assertSame($this->vancouverDate(30), $application->appeal_deadline);
        $this->assertSame('Not Approved', $this->institution()->eqa_status, 'A never-designated institution becomes Not Approved.');
        $this->assertEmailed('application_not_approved');
        $this->viewApplication($id)->assertJsonPath('props.canAppeal', true);
    }

    public function test_declined_renewal_keeps_an_existing_designation(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['eqa_status' => 'Designated', 'designation_start' => '2020-04-01']);

        $this->declinedApplication();

        $this->assertSame('Designated', $this->institution()->eqa_status);
    }

    public function test_a_new_application_can_start_after_a_decline_is_final(): void
    {
        $this->declinedApplication();

        $this->asInstitution()->post('/web/applications', $this->applicationForm())->assertSessionHas('success');

        $this->assertSame(2, DB::table('applications')->count());
    }

    // Appeal ----------------------------------------------------------------------------

    public function test_appeal_overturned_returns_to_approval_and_can_be_approved(): void
    {
        $id = $this->declinedApplication();

        $this->asInstitution()->post("/web/applications/{$id}/appeal")->assertSessionHas('success');
        $application = $this->application($id);
        $this->assertSame('Pending Appeal', $application->status);
        $this->assertSame('appeal_payment', $application->workflow_stage);
        $appealFee = $this->invoice($id, ApplicationFeeService::FEE_APPEAL);
        $this->assertEquals(250, $appealFee->total_charges);
        $this->assertSame('Appeal Fee (fixed): $250.00', $appealFee->description);
        $this->assertSame($application->appeal_deadline, $appealFee->due_date);

        $this->payOnline($appealFee);
        $this->assertSame('appeal_review', $this->application($id)->workflow_stage);
        $this->assertEmailed('appeal_submission', $appealFee->invoice_number);

        $this->advance($id, 'appeal_overturn')->assertSessionHas('success');
        $application = $this->application($id);
        $this->assertSame('application_approval', $application->workflow_stage);
        $this->assertSame('Overturned', $application->appeal_decision);
        $this->assertEquals(1, $application->appeal_successful);

        $this->advance($id, 'approve', ['compliance_order' => true, 'compliance_order_notes' => 'Quarterly reporting.'])->assertSessionHas('success');
        $this->assertSame('Approved (Under Compliance Order)', $this->application($id)->status);
    }

    public function test_appeal_upheld_declines_the_application(): void
    {
        $id = $this->appealUnderReview();

        $this->advance($id, 'appeal_uphold')->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Declined', $application->status);
        $this->assertSame('Upheld', $application->appeal_decision);
        $this->assertSame('end', $application->workflow_stage);
        $this->viewApplication($id)->assertJsonPath('props.canAppeal', false);
    }

    public function test_ministry_can_confirm_an_appeal_fee_paid_offline(): void
    {
        $id = $this->declinedApplication();
        $this->asInstitution()->post("/web/applications/{$id}/appeal");

        $this->advance($id, 'confirm_appeal_fee', ['payment_method' => 'Cheque'])->assertSessionHas('success');

        $this->assertSame('appeal_review', $this->application($id)->workflow_stage);
        $this->assertSame('Cheque', $this->invoice($id, ApplicationFeeService::FEE_APPEAL)->payment_method);
    }

    public function test_appeal_is_not_available_after_the_deadline(): void
    {
        $id = $this->declinedApplication();

        $this->travel(31)->days();

        $this->viewApplication($id)->assertJsonPath('props.canAppeal', false);
        $this->asInstitution()->post("/web/applications/{$id}/appeal")->assertSessionHas('error');
        $this->assertSame('Not Approved', $this->application($id)->status);
        $this->assertNull($this->invoice($id, ApplicationFeeService::FEE_APPEAL));
    }

    public function test_an_application_can_only_be_appealed_once(): void
    {
        $id = $this->appealUnderReview();
        $this->advance($id, 'appeal_uphold');

        $this->asInstitution()->post("/web/applications/{$id}/appeal")->assertSessionHas('error');

        $this->assertSame(1, DB::table('invoices')->where('application_crm_id', $id)->where('fee_type', ApplicationFeeService::FEE_APPEAL)->count());
    }

    public function test_unpaid_appeal_fee_lets_the_decline_stand(): void
    {
        $id = $this->declinedApplication();
        $this->asInstitution()->post("/web/applications/{$id}/appeal");

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $this->assertSame('Not Approved', $this->application($id)->status);
        $this->assertSame('end', $this->application($id)->workflow_stage);
        $this->assertSame('Expired', $this->invoice($id, ApplicationFeeService::FEE_APPEAL)->invoice_status);
    }

    // Changes requested -----------------------------------------------------------------

    public function test_application_reopened_for_changes_and_resubmitted(): void
    {
        $id = $this->applicationAt('under_review');

        $this->advance($id, 'request_changes', ['changes' => 'Upload the current policy manual.'])->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Draft', $application->status);
        $this->assertEquals(1, $application->need_additional_details);
        $this->assertSame($this->vancouverDate(30), $application->changes_due_date);
        $this->assertEmailed('application_changes_requested', 'Upload the current policy manual.');

        $this->asInstitution()->put("/web/applications/{$id}", $this->applicationForm(['total_enrolment' => 300]))->assertRedirect();
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Under Review', $application->status);
        $this->assertSame('under_review', $application->workflow_stage);
        $this->assertEquals(300, $application->total_enrolment);
        $this->assertEquals(0, $application->need_additional_details);
        $this->assertSame(0, DB::table('invoices')->count(), 'Resubmitting does not charge the application fee again.');
    }

    public function test_changes_not_provided_by_the_deadline_expire_the_application(): void
    {
        $id = $this->applicationAt('under_review');
        $this->advance($id, 'request_changes', ['changes' => 'Missing info.']);

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $this->assertSame('Expired', $this->application($id)->status);
        $this->assertSame('end', $this->application($id)->workflow_stage);
    }

    // Helpers ---------------------------------------------------------------------------

    /** @param  array<string, mixed>  $form */
    private function startApplication(array $form = []): string
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm($form))->assertSessionHas('success');

        return (string) DB::table('applications')->where('institution_crm_id', self::INSTITUTION)->where('workflow_stage', 'draft')->value('crm_id');
    }

    /** @param  array<string, mixed>  $form */
    private function submittedApplication(array $form = []): string
    {
        $id = $this->startApplication($form);
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');

        return $id;
    }

    /** Ministry moves a submitted application with a paid fee forward to $stage. */
    private function reviewUntil(string $id, string $stage): void
    {
        $this->asMinistry()->put("/admin/applications/{$id}", ['receipt_confirmation' => true]);
        $this->advance($id, 'record_payment')->assertSessionHas('success');
        if ($stage === 'fees_payment') {
            return;
        }

        $this->asMinistry()->put("/admin/applications/{$id}", ['ready_for_review' => true]);
        $this->advance($id, 'begin_review')->assertSessionHas('success');
    }

    private function declinedApplication(string $reasons = 'Eligibility requirements not met.'): string
    {
        $id = $this->applicationAt('under_review');

        $this->advance($id, 'ineligible')->assertSessionHas('success');
        $this->advance($id, 'record_reasons', ['reasons' => $reasons])->assertSessionHas('success');
        $this->advance($id, 'finalize')->assertSessionHas('success');

        return $id;
    }

    private function appealUnderReview(): string
    {
        $id = $this->declinedApplication();
        $this->asInstitution()->post("/web/applications/{$id}/appeal")->assertSessionHas('success');
        $this->payOnline($this->invoice($id, ApplicationFeeService::FEE_APPEAL));
        $this->assertSame('appeal_review', $this->application($id)->workflow_stage);

        return $id;
    }

    /** @param  array<string, mixed>  $attributes */
    private function applicationAt(string $stage, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('applications')->insert(array_merge([
            'crm_id' => $id,
            'reference' => 'APP-'.random_int(10000000, 99999999),
            'status' => $stage === 'under_review' ? 'Under Review' : 'Pending Review',
            'workflow_stage' => $stage,
            'institution_crm_id' => self::INSTITUTION,
            'institution_name' => 'Process College',
            'application_date' => Carbon::now()->toDateString(),
            'fees_payment_received' => true,
            'intl_students_permit' => 10,
        ], $attributes));

        return $id;
    }

    /** @param  array<string, mixed>  $data */
    private function advance(string $id, string $action, array $data = []): TestResponse
    {
        return $this->asMinistry()->post("/admin/applications/{$id}/advance", array_merge(['action' => $action], $data));
    }

    private function payOnline(object $invoice): void
    {
        $this->asInstitution()->post("/web/invoices/{$invoice->crm_id}/pay")->assertRedirect();

        $order = DB::table('payment_transactions')->where('invoice_crm_id', $invoice->crm_id)->orderByDesc('id')->value('order_number');
        $this->assertNotNull($order, 'No Express Pay transaction was started.');

        $this->asInstitution()->get('/payments/express-pay/receipt?trnOrderNumber='.$order)->assertRedirect();
        $this->assertSame('approved', DB::table('payment_transactions')->where('order_number', $order)->value('status'));
        $this->assertSame('Paid', DB::table('invoices')->where('crm_id', $invoice->crm_id)->value('invoice_status'));
    }

    private function fakeWorldlineApprovesEveryQuery(): void
    {
        Http::fake(function (HttpRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $amount = DB::table('payment_transactions')->where('order_number', $query['trnOrderNumber'] ?? 0)->value('amount');

            return Http::response(http_build_query([
                'trnApproved' => '1',
                'trnId' => '10000099',
                'authCode' => 'TEST',
                'messageId' => '1',
                'messageText' => 'Approved',
                'trnAmount' => number_format((float) $amount, 2, '.', ''),
                'cardType' => 'VI',
                'trnOrderNumber' => $query['trnOrderNumber'] ?? '',
            ]));
        });
    }

    /** The notification was rendered and sent (to the testing address) for the institution contact. */
    private function assertEmailed(string $templateKey, ?string $bodyContains = null): void
    {
        $email = DB::table('sent_emails')->where('template_key', $templateKey)->orderByDesc('id')->first();

        $this->assertNotNull($email, "No '{$templateKey}' notification was sent.");
        $this->assertSame('sent', $email->status, "'{$templateKey}' failed: ".$email->error);
        $this->assertSame(self::TESTING_EMAIL, $email->recipient);
        $this->assertSame(self::CONTACT_EMAIL, $email->intended_recipient);
        if ($bodyContains !== null) {
            $this->assertStringContainsString($bodyContains, $email->body);
        }
    }

    private function viewApplication(string $id): TestResponse
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

    private function vancouverDate(int $daysFromNow): string
    {
        return Carbon::now('America/Vancouver')->addDays($daysFromNow)->toDateString();
    }

    private function application(string $id): object
    {
        return DB::table('applications')->where('crm_id', $id)->first();
    }

    private function institution(): object
    {
        return DB::table('institutions')->where('crm_id', self::INSTITUTION)->first();
    }

    private function invoice(string $applicationId, string $feeType): ?object
    {
        return DB::table('invoices')->where('application_crm_id', $applicationId)->where('fee_type', $feeType)->first();
    }
}
