<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $id = $this->startApplication(['intl_students_permit' => 40, 'per_student_fee_enrolment' => 40]);

        $this->assertSame('Draft', $this->application($id)->status);
        $this->assertSame(0, DB::table('invoices')->count(), 'No fee is invoiced while the application is a draft.');

        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Application Fee Required', $application->status);
        $this->assertSame('fee_required', $application->workflow_stage);
        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertEquals(7500, $fee->total_charges);
        $this->assertSame('New Application Fee (fixed): $7,500.00', $fee->description);
        $this->assertSame(0, DB::table('sent_emails')->where('template_key', 'application_submission')->count(), 'Receipt is only acknowledged once the fee is paid.');

        $this->payOnline($fee);
        $this->assertEmailed('application_submission', $this->application($id)->reference);
        $this->assertSame('Paid', $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW)->invoice_status);
        $application = $this->application($id);
        $this->assertEquals(1, $application->fees_payment_received);
        $this->assertSame('fees_payment', $application->workflow_stage);
        $this->assertSame('Fee Payment Received', $application->status);

        $this->advance($id, 'confirm_payment')->assertSessionHas('success');
        $application = $this->application($id);
        $this->assertSame('pending_review', $application->workflow_stage);
        $this->assertSame('Pending Review', $application->status);

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
        $this->assertSame('Designated', $this->institution()->eqa_status, 'A first-time applicant is Designated on approval.');

        $this->payOnline($adminFee);

        $application = $this->application($id);
        $this->assertSame('end', $application->workflow_stage);
        $this->assertEquals(1, $application->process_completed);
        $institution = $this->institution();
        $this->assertSame('Designated', $institution->eqa_status);
        $this->assertSame($application->approved_date, $institution->designation_start);
        $this->assertEmailed('application_approved');
    }

    public function test_application_fee_can_be_paid_by_eft_and_recorded_by_ministry(): void
    {
        $id = $this->submittedApplication();

        $tomorrow = Carbon::now('America/Vancouver')->addDay()->toDateString();
        $this->advance($id, 'record_payment', ['payment_method' => 'EFT', 'payment_date' => $tomorrow])->assertSessionHasErrors('payment_date');
        $this->assertSame('fee_required', $this->application($id)->workflow_stage);

        $this->advance($id, 'record_payment', [
            'payment_method' => 'EFT',
            'payment_date' => '2026-11-02',
            'payment_reference' => ' EFT-778899 ',
            'payment_notes' => 'Received from RBC, payer Process College Ltd.',
        ])->assertSessionHas('success');

        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertSame('Paid', $fee->invoice_status);
        $this->assertSame('EFT', $fee->payment_method);
        $this->assertSame('2026-11-02', $fee->payment_received_date);
        $this->assertSame('EFT-778899', $fee->transaction_reference);
        $this->assertSame('Received from RBC, payer Process College Ltd.', $fee->payment_notes);
        $application = $this->application($id);
        $this->assertSame('fees_payment', $application->workflow_stage);
        $this->assertSame('Fee Payment Received', $application->status);
        $this->assertSame(0, DB::table('payment_transactions')->count());

        $this->advance($id, 'record_payment', ['payment_method' => 'Bitcoin'])->assertSessionHas('error');
    }

    public function test_paid_fee_emails_the_institution_its_receipt_once_with_the_pdf_attached(): void
    {
        $attachments = [];
        Event::listen(MessageSent::class, function (MessageSent $event) use (&$attachments): void {
            foreach ($event->message->getAttachments() as $part) {
                $attachments[] = [(string) $event->message->getSubject(), $part->getFilename(), $part->getMediaType().'/'.$part->getMediaSubtype()];
            }
        });
        $id = $this->submittedApplication();

        $this->advance($id, 'record_payment', ['payment_method' => 'EFT'])->assertSessionHas('success');
        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertEmailed('payment_receipt', $fee->invoice_number);

        $this->assertSame([['EQA Payment Received | Invoice '.$fee->invoice_number, 'EQA_Receipt_'.$fee->invoice_number.'.pdf', 'application/pdf']], $attachments);

        // Confirming an already-paid invoice again does not resend the receipt.
        app(ApplicationFeeService::class)->recordManualPayment($fee, 'EFT');
        $this->assertSame(1, DB::table('sent_emails')->where('template_key', 'payment_receipt')->count());
    }

    public function test_receipt_is_acknowledged_once_when_the_fee_is_recorded_and_not_confirmable_before(): void
    {
        $id = $this->submittedApplication();

        $this->asMinistry()->put("/admin/applications/{$id}", ['receipt_confirmation' => true])->assertSessionHasErrors('receipt_confirmation');
        $this->assertEquals(0, $this->application($id)->receipt_confirmation);
        $this->assertSame(0, DB::table('sent_emails')->where('template_key', 'application_submission')->count());

        $this->advance($id, 'record_payment', ['payment_method' => 'EFT'])->assertSessionHas('success');
        $this->assertEmailed('application_submission', $this->application($id)->reference);

        $this->asMinistry()->put("/admin/applications/{$id}", ['receipt_confirmation' => true])->assertSessionHasNoErrors();
        $this->assertEquals(1, $this->application($id)->receipt_confirmation);
    }

    public function test_application_acknowledged_on_submission_under_the_old_flow_is_not_acknowledged_again(): void
    {
        $id = $this->submittedApplication();
        DB::table('sent_emails')->insert([
            'institution_crm_id' => self::INSTITUTION,
            'template_key' => 'application_submission',
            'body' => 'Please refer to order reference '.$this->application($id)->reference.'.',
            'status' => 'sent',
        ]);

        $this->advance($id, 'record_payment', ['payment_method' => 'EFT'])->assertSessionHas('success');

        $this->assertSame(1, DB::table('sent_emails')->where('template_key', 'application_submission')->count());
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

        $this->advance($id, 'begin_review')->assertSessionHas('error');
        $this->assertSame('fee_required', $this->application($id)->workflow_stage, 'Review cannot begin before the fee is paid.');

        $this->advance($id, 'record_payment', ['payment_method' => 'Cheque'])->assertSessionHas('success');
        DB::table('applications')->where('crm_id', $id)->update(['fees_payment_received' => false]);
        $this->advance($id, 'confirm_payment')->assertSessionHas('error');
        $this->assertSame('fees_payment', $this->application($id)->workflow_stage);

        $this->asMinistry()->put("/admin/applications/{$id}", ['fees_payment_received' => true]);
        $this->advance($id, 'confirm_payment')->assertSessionHas('success');

        $this->advance($id, 'begin_review')->assertSessionHas('error');
        $this->assertSame('pending_review', $this->application($id)->workflow_stage);

        $this->asMinistry()->put("/admin/applications/{$id}", ['receipt_confirmation' => true, 'ready_for_review' => true]);
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

        $id = $this->submittedApplication(['intl_students_permit' => 20, 'per_student_fee_enrolment' => 20]);

        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_RENEWAL);
        $this->assertNotNull($fee);
        $this->assertNull($this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW));
        $this->assertEquals(5500, $fee->total_charges);
        $this->assertSame('Renewal Application Fee (fixed): $5,500.00', $fee->description);

        $this->payOnline($fee);
        $this->assertEmailed('reapplication_submission');
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

    // Locations, address for service and previous operating names -----------------------

    public function test_application_records_address_for_service_and_previous_operating_names(): void
    {
        $id = $this->startApplication(['annual_report_filed' => true, 'previous_names' => [
            ['name' => 'Process Academy', 'used_from' => $this->vancouverDate(-900), 'used_to' => $this->vancouverDate(-30)],
            ['name' => 'Process Institute', 'used_from' => $this->vancouverDate(-400), 'used_to' => ''],
        ]]);

        $application = $this->application($id);
        $this->assertSame('100 Main Street', $application->service_street1);
        $this->assertEquals(1, $application->service_address_confirmed);
        $this->assertEquals(1, $application->annual_report_filed);
        $this->assertSame(2, \Modules\Admin\Models\Application::find($id)->previousOperatingNames()->count());
        $this->assertSame(['Process Institute', 'Process Academy'], \Modules\Admin\Models\Institution::find(self::INSTITUTION)->previousOperatingNames->pluck('name')->all());
        $this->assertNull(DB::table('previous_operating_names')->where('name', 'Process Institute')->value('used_to'));

        // Editing the draft replaces the names declared on it.
        $this->asInstitution()->put("/web/applications/{$id}", $this->applicationForm([
            'annual_report_filed' => false,
            'previous_names' => [['name' => 'Process Academy', 'used_from' => $this->vancouverDate(-900), 'used_to' => $this->vancouverDate(-30)]],
        ]))->assertSessionHas('success');
        $this->assertSame(['Process Academy'], DB::table('previous_operating_names')->where('application_crm_id', $id)->pluck('name')->all());
        $this->assertEquals(0, $this->application($id)->annual_report_filed, 'A draft can be saved unticked.');

        $this->asInstitution()->post("/web/applications/{$id}/submit")
            ->assertSessionHasErrors(['annual_report_filed' => 'Confirm the institution is in compliance with BC Registry Services and up to date on filing of annual reports.']);
    }

    public function test_address_for_service_must_be_confirmed_and_names_must_be_from_the_last_five_years(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm([
            'intent' => 'submit',
            'service_address_confirmed' => false,
            'service_city' => '',
            'previous_names' => [
                ['name' => 'Old College', 'used_from' => $this->vancouverDate(-3000), 'used_to' => $this->vancouverDate(-2000)],
                ['name' => 'Backwards College', 'used_from' => $this->vancouverDate(-100), 'used_to' => $this->vancouverDate(-200)],
            ],
        ]))->assertSessionHasErrors(['service_address_confirmed', 'service_city', 'previous_names.0', 'previous_names.1.used_to']);

        $this->assertSame(0, DB::table('applications')->count());
        $this->assertSame(0, DB::table('previous_operating_names')->count());
    }

    public function test_new_application_does_not_prefill_institution_campuses_but_prefills_recent_previous_names(): void
    {
        DB::table('campuses')->insert([
            ['crm_id' => (string) Str::uuid(), 'institution_crm_id' => self::INSTITUTION, 'name' => 'Downtown', 'street1' => '1 Main St', 'city' => 'Victoria', 'status' => 'Active'],
        ]);
        DB::table('previous_operating_names')->insert([
            ['institution_crm_id' => self::INSTITUTION, 'name' => 'Recent Name', 'used_from' => $this->vancouverDate(-700), 'used_to' => $this->vancouverDate(-10)],
            ['institution_crm_id' => self::INSTITUTION, 'name' => 'Ancient Name', 'used_from' => $this->vancouverDate(-4000), 'used_to' => $this->vancouverDate(-3000)],
        ]);
        $version = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $this->asInstitution()->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->get('/web/applications/new')
            ->assertOk()
            ->assertJsonMissingPath('props.campusLocations')
            ->assertJsonMissingPath('props.campuses')
            ->assertJsonPath('props.previousNames', [['name' => 'Recent Name', 'used_from' => $this->vancouverDate(-700), 'used_to' => $this->vancouverDate(-10)]]);
    }

    public function test_application_campus_locations_are_its_own_and_leave_institution_campuses_alone(): void
    {
        DB::table('campuses')->insert(['crm_id' => (string) Str::uuid(), 'institution_crm_id' => self::INSTITUTION, 'name' => 'Downtown', 'street1' => '1 Main St', 'city' => 'Victoria', 'status' => 'Active']);

        $id = $this->startApplication(['campus_locations' => [
            ['location_name' => 'Harbour Campus', 'street1' => '5 Wharf St', 'city' => 'Victoria', 'province' => 'BC', 'postal_code' => 'V8W 1T3', 'email' => 'harbour@process.example', 'primary_location' => true],
            ['location_name' => 'Online Hub', 'street1' => '9 Cloud Ave', 'city' => 'Kelowna', 'province' => 'BC', 'postal_code' => 'V1Y 1A1', 'primary_location' => false],
        ]]);

        $locations = \Modules\Admin\Models\Application::find($id)->campusLocations;
        $this->assertSame(['Harbour Campus', 'Online Hub'], $locations->pluck('location_name')->all());
        $this->assertTrue($locations[0]->primary_location);
        $this->assertSame(1, DB::table('campuses')->where('institution_crm_id', self::INSTITUTION)->count(), 'Institution campuses are untouched.');
        $this->assertSame('Downtown', DB::table('campuses')->where('institution_crm_id', self::INSTITUTION)->value('name'));

        $this->viewApplication($id)->assertJsonPath('props.campusLocations.1.location_name', 'Online Hub')->assertJsonMissingPath('props.campusLocations.0.status');
    }

    public function test_campus_locations_are_required_with_at_most_one_primary(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm(['intent' => 'submit', 'campus_locations' => []]))
            ->assertSessionHasErrors(['campus_locations' => 'Add at least one campus location.']);

        $this->asInstitution()->post('/web/applications', $this->applicationForm(['campus_locations' => [
            ['location_name' => 'A', 'street1' => '1 St', 'city' => 'Victoria', 'province' => 'BC', 'postal_code' => 'V8W 1A1', 'primary_location' => true],
            ['location_name' => '', 'street1' => '', 'city' => 'Victoria', 'province' => 'BC', 'postal_code' => 'V8W 1A1', 'primary_location' => true],
        ]]))->assertSessionHasErrors(['campus_locations', 'campus_locations.1.location_name', 'campus_locations.1.street1']);

        $this->assertSame(0, DB::table('applications')->count());
        $this->assertSame(0, DB::table('application_campus_locations')->count());
    }

    // Steps: save a draft at any point, submit when complete ---------------------------------

    public function test_a_partly_completed_draft_can_be_saved_and_returns_to_the_same_step(): void
    {
        $this->asInstitution()->post('/web/applications', ['total_enrolment' => 80, 'step' => 2, 'intent' => 'save'])
            ->assertSessionHas('success');

        $application = DB::table('applications')->where('institution_crm_id', self::INSTITUTION)->first();
        $this->assertSame('draft', $application->workflow_stage);
        $this->assertEquals(80, $application->total_enrolment);
        $this->assertNull($application->per_student_fee_enrolment);
        $this->assertSame(0, DB::table('invoices')->count());

        $this->asInstitution()->put("/web/applications/{$application->crm_id}", ['total_enrolment' => 90, 'step' => 5])
            ->assertRedirect("/web/applications/{$application->crm_id}/edit?step=5");
        $this->assertEquals(90, $this->application($application->crm_id)->total_enrolment);

        // Even a draft must enter well-formed values.
        $this->asInstitution()->put("/web/applications/{$application->crm_id}", ['website_compliance' => [
            DB::table('website_compliance_items')->value('id') => ['url' => 'not a link'],
        ]])->assertSessionHasErrors();
    }

    public function test_incomplete_draft_cannot_be_submitted(): void
    {
        $this->asInstitution()->post('/web/applications', ['total_enrolment' => 80]);
        $id = (string) DB::table('applications')->value('crm_id');

        $this->asInstitution()->post("/web/applications/{$id}/submit")
            ->assertRedirect("/web/applications/{$id}/edit")
            ->assertSessionHas('error')
            ->assertSessionHasErrors(['campus_locations', 'service_street1', 'per_student_fee_enrolment', 'affirm_authorized', 'representative_signature']);

        $this->assertSame('draft', $this->application($id)->workflow_stage);
        $this->assertSame(0, DB::table('invoices')->count());
    }

    public function test_submit_application_on_the_last_step_saves_and_submits(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm(['intent' => 'submit']))
            ->assertSessionHas('success');

        $application = DB::table('applications')->where('institution_crm_id', self::INSTITUTION)->first();
        $this->assertSame('fee_required', $application->workflow_stage);
        $this->assertNotNull($this->invoice($application->crm_id, ApplicationFeeService::FEE_APPLICATION_NEW));
    }

    // Website Compliance -----------------------------------------------------------------

    public function test_website_compliance_needs_a_link_or_a_comment_for_every_item(): void
    {
        [$first, $second] = DB::table('website_compliance_items')->orderBy('section_id')->orderBy('sort_order')->limit(2)->pluck('id')->all();
        $answers = $this->websiteComplianceAnswers();
        $answers[$first] = ['url' => '', 'comment' => ''];
        $answers[$second] = ['url' => 'not a web address', 'comment' => ''];

        $this->asInstitution()->post('/web/applications', $this->applicationForm(['intent' => 'submit', 'website_compliance' => $answers]))
            ->assertSessionHasErrors([
                "website_compliance.{$first}.url" => 'Enter a link or a comment.',
                "website_compliance.{$second}.url" => 'Enter a full web address starting with http:// or https://.',
            ]);
        $this->assertSame(0, DB::table('applications')->count());

        // A comment alone is enough.
        $answers[$first] = ['url' => '', 'comment' => 'See the Programs page in the student handbook.'];
        $answers[$second] = ['url' => 'https://process.example/fees', 'comment' => ''];
        $this->asInstitution()->post('/web/applications', $this->applicationForm(['website_compliance' => $answers]))->assertSessionHas('success');
    }

    public function test_submitted_application_keeps_its_copy_of_the_website_compliance_checklist(): void
    {
        $items = DB::table('website_compliance_items')->where('is_active', true)->orderBy('sort_order')->get();
        $id = $this->startApplication();
        $this->assertSame($items->count(), \Modules\Admin\Models\Application::find($id)->webCompliances()->count());
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');

        // The admin rewords an item, deactivates another and adds a new one.
        $reworded = $items[0];
        $this->asMinistry()->put("/admin/maintenance/website-compliance/items/{$reworded->id}", [
            'section_id' => $reworded->section_id, 'label' => 'Reworded item', 'help_text' => null, 'applies_to' => 'all', 'is_active' => true,
        ])->assertSessionHas('success');
        $this->asMinistry()->put("/admin/maintenance/website-compliance/items/{$items[1]->id}", [
            'section_id' => $items[1]->section_id, 'label' => $items[1]->label, 'applies_to' => 'all', 'is_active' => false,
        ])->assertSessionHas('success');
        $this->asMinistry()->post('/admin/maintenance/website-compliance/items', [
            'section_id' => $reworded->section_id, 'label' => 'Brand new item', 'applies_to' => 'all', 'is_active' => true,
        ])->assertSessionHas('success');

        $copy = $this->viewApplication($id)->json('props.websiteCompliance');
        $labels = collect($copy)->flatMap(fn (array $s): array => $s['items'])->pluck('label');
        $this->assertSame($items->count(), $labels->count());
        $this->assertContains($reworded->label, $labels);
        $this->assertContains($items[1]->label, $labels);
        $this->assertNotContains('Brand new item', $labels);
        $this->assertSame('https://process.example/info/'.$reworded->id, $copy[0]['items'][0]['url']);

        // Returned for additional information: still edited against its own copy.
        DB::table('applications')->where('crm_id', $id)->update(['workflow_stage' => 'changes_requested']);
        $edit = $this->inertiaGet("/web/applications/{$id}/edit")->json('props.websiteCompliance');
        $this->assertSame($labels->all(), collect($edit)->flatMap(fn (array $s): array => $s['items'])->pluck('label')->all());
    }

    public function test_draft_follows_the_current_checklist_and_ptiru_only_items_need_ptiru_qa(): void
    {
        $section = DB::table('website_compliance_sections')->orderBy('sort_order')->value('id');
        $this->asMinistry()->post('/admin/maintenance/website-compliance/items', [
            'section_id' => $section, 'label' => 'Are programs approved or ANR?', 'applies_to' => 'ptiru', 'is_active' => true,
        ])->assertSessionHas('success');
        $labels = fn (): array => collect($this->inertiaGet('/web/applications/new')->json('props.websiteCompliance'))
            ->flatMap(fn (array $s): array => $s['items'])->pluck('label')->all();

        $this->assertNotContains('Are programs approved or ANR?', $labels());

        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['qa_met_through' => DesignationService::PTIRU_QA_MET_THROUGH]);
        $this->assertContains('Are programs approved or ANR?', $labels());
    }

    public function test_only_ministry_admins_maintain_the_website_compliance_checklist(): void
    {
        $staff = $this->user('wc-user@process.example', Role::MINISTRY_USER, ['idir_user_guid' => 'WCUSER']);
        $this->actingAs($staff)->withSession(['portal_role' => 'ministry'])->get('/admin/maintenance/website-compliance')->assertForbidden();

        $section = DB::table('website_compliance_sections')->orderBy('sort_order')->value('id');
        [$first, $second] = DB::table('website_compliance_items')->where('section_id', $section)->orderBy('sort_order')->limit(2)->pluck('id')->all();
        $this->asMinistry()->post("/admin/maintenance/website-compliance/items/{$second}/move", ['direction' => 'up'])->assertRedirect();

        $this->assertSame([$second, $first], DB::table('website_compliance_items')->where('section_id', $section)->orderBy('sort_order')->limit(2)->pluck('id')->all());
    }

    // Designation on approval and enrolment on the profile ---------------------------------

    public function test_first_time_applicant_is_designated_on_approval_and_its_enrolment_copied_to_the_profile(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['eqa_status' => 'Pending', 'total_enrolment' => 10]);
        $id = $this->applicationAt('application_approval', [
            'total_enrolment' => 640, 'intl_students_permit' => 25, 'in_person_students' => 600, 'online_students' => 40, 'designation_expiry' => '2028-03-31',
        ]);

        $this->advance($id, 'approve')->assertSessionHas('success', fn (string $m): bool => str_contains($m, 'the institution is Designated'));

        $institution = $this->institution();
        $this->assertSame('Designated', $institution->eqa_status, 'Before the administrative fee is paid.');
        $this->assertSame('2028-03-31', $institution->designation_expiry);
        $this->assertSame([640, 25, 600, 40], [(int) $institution->total_enrolment, (int) $institution->intl_students_permit, (int) $institution->in_person_students, (int) $institution->online_students]);
        $this->assertSame('Pending', $this->application($id)->prior_eqa_status);

        // The ministry can still edit the profile enrolment by hand.
        $this->asMinistry()->put('/admin/institutions/'.self::INSTITUTION, ['name' => 'Process College', 'total_enrolment' => 700])->assertSessionHas('success');
        $this->assertEquals(700, $this->institution()->total_enrolment);
    }

    public function test_profile_enrolment_saved_as_zero_is_filled_from_the_latest_approved_application(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['total_enrolment' => 0, 'intl_students_permit' => 0, 'in_person_students' => 12, 'online_students' => null]);
        $this->applicationAt('end', ['status' => 'Approved', 'approved_date' => '2024-02-01', 'total_enrolment' => 300, 'intl_students_permit' => 9, 'in_person_students' => 280, 'online_students' => 20]);
        $latest = $this->applicationAt('end', ['status' => 'Approved', 'approved_date' => '2025-02-01', 'reference' => 'APP-LATEST', 'total_enrolment' => 5, 'intl_students_permit' => 2, 'in_person_students' => 8, 'online_students' => 99]);

        (require database_path('migrations/2026_11_26_000002_fill_zero_enrolment_from_latest_approved_application.php'))->up();

        $institution = $this->institution();
        $this->assertSame([5, 2, 12, 99], [(int) $institution->total_enrolment, (int) $institution->intl_students_permit, (int) $institution->in_person_students, (int) $institution->online_students],
            'Empty or 0 values come from the latest approved application; a value entered on the profile is kept.');

        $version = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $this->asMinistry()->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->get('/admin/institutions/'.self::INSTITUTION)
            ->assertJsonPath('props.enrolmentSource.crm_id', $latest)
            ->assertJsonPath('props.enrolmentSource.reference', 'APP-LATEST');
    }

    public function test_unpaid_administrative_fee_undoes_a_designation_made_on_approval(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['eqa_status' => 'Pending']);
        $id = $this->applicationAt('application_approval');
        $this->advance($id, 'approve');
        $this->assertSame('Designated', $this->institution()->eqa_status);

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $institution = $this->institution();
        $this->assertSame('Not Approved', $institution->eqa_status);
        $this->assertNull($institution->designation_start);
        $this->assertNull($institution->designation_expiry);
    }

    public function test_approval_warns_when_ptiru_standing_prevents_the_designation(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update([
            'eqa_status' => 'Pending', 'qa_met_through' => DesignationService::PTIRU_QA_MET_THROUGH, 'ptib_standing' => 'Not in Good Standing',
        ]);
        $id = $this->applicationAt('application_approval');

        $this->advance($id, 'approve')
            ->assertSessionHas('success')
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'could not be Designated'));
        $this->assertSame('Pending', $this->institution()->eqa_status);
    }

    public function test_notifications_go_to_the_primary_contact_email_when_one_is_recorded(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['primary_contact' => 'Robin Primary', 'primary_contact_email' => 'robin@process.example']);
        $id = $this->applicationAt('under_review');

        $this->advance($id, 'request_changes', ['changes' => 'Upload the policy manual.'])->assertSessionHas('success');

        $email = DB::table('sent_emails')->where('template_key', 'application_changes_requested')->first();
        $this->assertSame('robin@process.example', $email->intended_recipient);
        $this->assertStringContainsString('Robin Primary', $email->body);
    }

    // Per Student Fee Payment Enrollment, declarations and help text ---------------------

    public function test_per_student_fee_enrolment_is_required_and_drives_the_international_student_fee(): void
    {
        $this->asInstitution()->post('/web/applications', $this->applicationForm(['intent' => 'submit', 'per_student_fee_enrolment' => null]))
            ->assertSessionHasErrors(['per_student_fee_enrolment' => 'Enter the number of international students for the per student fee.']);

        $id = $this->submittedApplication(['intl_students_permit' => 100, 'per_student_fee_enrolment' => 35]);
        $this->reviewUntil($id, 'under_review');
        $this->advance($id, 'eligible', ['eqa_good_standing' => true]);
        $this->advance($id, 'approve')->assertSessionHas('success');

        $adminFee = $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE);
        $this->assertEquals(70, $adminFee->total_charges, '35 students x $2, not the 100 in the enrolment section.');
    }

    public function test_retired_declarations_are_no_longer_asked_or_written(): void
    {
        $id = $this->startApplication(['affirm_branding_guide' => true, 'affirm_understands_comply' => true]);

        $application = $this->application($id);
        $this->assertEquals(1, $application->affirm_policy_manual);
        $this->assertEquals(1, $application->affirm_authorized);
        $this->assertNull($application->affirm_branding_guide);
        $this->assertNull($application->affirm_understands_comply);
    }

    public function test_form_help_text_comes_from_maintenance_utils(): void
    {
        \App\Models\Util::where('field_key', 'help_per_student_fee_enrolment')->firstOrFail()
            ->update(['field_name' => 'Count study permit students from last calendar year.', 'active_flag' => true]);

        $this->inertiaGet('/web/applications/new')
            ->assertJsonPath('props.settings.help.per_student_fee_enrolment', 'Count study permit students from last calendar year.')
            ->assertJsonPath('props.settings.help.total_enrolment', config('eqa.help.total_enrolment'));
    }

    // Fee exempt --------------------------------------------------------------------------

    public function test_retired_enrolment_fields_are_no_longer_collected(): void
    {
        $id = $this->startApplication(['intl_students_permit' => 12, 'intl_students_other' => 30, 'enrolment_type' => 'FTE']);

        $application = $this->application($id);
        $this->assertEquals(12, $application->intl_students_permit);
        $this->assertNull($application->intl_students_other, 'No longer relevant to the Act: never written.');
        $this->assertNull($application->enrolment_type, 'Total Institution Enrolment says whether to use FTE or headcount.');
    }

    public function test_fee_exempt_institution_is_never_charged_from_submission_to_designation(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['fee_exempt' => true]);
        $id = $this->startApplication(['intl_students_permit' => 40, 'per_student_fee_enrolment' => 40]);
        $this->assertEquals(0, $this->application($id)->application_fee);

        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');

        $fee = $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertEquals(0, $fee->total_charges);
        $this->assertSame('Paid', $fee->invoice_status);
        $this->assertSame(ApplicationFeeService::METHOD_FEE_EXEMPT, $fee->payment_method);
        $this->assertSame('New Application Fee (waived, fee exempt institution): $0.00', $fee->description);
        $this->assertSame('pending_review', $this->application($id)->workflow_stage, 'No payment for the ministry to confirm.');
        $this->assertEmailed('application_submission', $this->application($id)->reference);
        $this->assertSame(0, DB::table('sent_emails')->where('template_key', 'payment_receipt')->count(), 'Nothing was paid, so no receipt.');

        $this->reviewUntil($id, 'under_review');
        $this->advance($id, 'eligible', ['eqa_good_standing' => true])->assertSessionHas('success');
        $this->advance($id, 'approve')->assertSessionHas('success');

        $adminFee = $this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE);
        $this->assertEquals(0, $adminFee->total_charges, '40 study permit students would otherwise be charged.');
        $this->assertSame(ApplicationFeeService::METHOD_FEE_EXEMPT, $adminFee->payment_method);
        $this->assertSame('end', $this->application($id)->workflow_stage);
        $this->assertSame('Designated', $this->institution()->eqa_status);
        $this->assertSame(0, DB::table('sent_emails')->where('template_key', 'application_approved_fee_due')->count());
    }

    public function test_fee_exempt_renewal_and_appeal_are_not_charged(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update([
            'fee_exempt' => true, 'eqa_status' => 'Designated', 'designation_start' => '2020-04-01',
        ]);
        $renewal = $this->submittedApplication();
        $this->assertEquals(0, $this->invoice($renewal, ApplicationFeeService::FEE_APPLICATION_RENEWAL)->total_charges);

        $id = $this->declinedApplication();
        $this->asInstitution()->post("/web/applications/{$id}/appeal")->assertSessionHas('success');

        $appealFee = $this->invoice($id, ApplicationFeeService::FEE_APPEAL);
        $this->assertEquals(0, $appealFee->total_charges);
        $this->assertSame('Paid', $appealFee->invoice_status);
        $this->assertSame('appeal_review', $this->application($id)->workflow_stage);
    }

    public function test_marking_fee_exempt_waives_what_the_institution_still_owes(): void
    {
        $submitted = $this->submittedApplication();
        $approved = $this->applicationAt('application_approval');
        $this->advance($approved, 'approve');
        $underReview = $this->declinedApplication();
        $this->asInstitution()->post("/web/applications/{$underReview}/appeal");
        $appealFee = $this->invoice($underReview, ApplicationFeeService::FEE_APPEAL);
        DB::table('payment_transactions')->insert([
            'order_number' => 1234567, 'invoice_crm_id' => $appealFee->crm_id, 'amount' => 250,
            'status' => \App\Services\ExpressPayService::REVIEW, 'hash_expires_at' => now(),
        ]);

        $this->asMinistry()->put('/admin/institutions/'.self::INSTITUTION.'/fee-exempt', ['fee_exempt' => true])
            ->assertSessionHas('success', 'Institution marked fee exempt. No fees will be charged; 2 outstanding invoices were waived. 1 invoice has an Express Pay payment under review and was not waived.');

        $fee = $this->invoice($submitted, ApplicationFeeService::FEE_APPLICATION_NEW);
        $this->assertEquals(0, $fee->total_charges);
        $this->assertSame('Paid', $fee->invoice_status);
        $this->assertSame(ApplicationFeeService::METHOD_FEE_EXEMPT, $fee->payment_method);
        $this->assertStringContainsString('originally $7,500.00', $fee->payment_notes);
        $this->assertSame('New Application Fee (waived, fee exempt institution): $0.00', $fee->description);
        $this->assertSame('pending_review', $this->application($submitted)->workflow_stage);
        $this->assertEquals(0, $this->application($submitted)->total_due);

        $this->assertSame('Paid', $this->invoice($approved, ApplicationFeeService::FEE_ADMIN_VARIABLE)->invoice_status);
        $this->assertSame('end', $this->application($approved)->workflow_stage);
        $this->assertSame('Designated', $this->institution()->eqa_status);

        $this->assertEquals(250, $this->invoice($underReview, ApplicationFeeService::FEE_APPEAL)->total_charges, 'A payment under review is not waived.');
    }

    public function test_only_a_ministry_admin_can_set_fee_exempt(): void
    {
        $staff = $this->user('user@process.example', Role::MINISTRY_USER, ['idir_user_guid' => 'PROCESSUSER']);
        $this->actingAs($staff)->withSession(['portal_role' => 'ministry'])
            ->put('/admin/institutions/'.self::INSTITUTION.'/fee-exempt', ['fee_exempt' => true])
            ->assertForbidden();
        $this->assertEquals(0, $this->institution()->fee_exempt);

        $this->asMinistry()->put('/admin/institutions/'.self::INSTITUTION.'/fee-exempt', ['fee_exempt' => true])->assertSessionHas('success');
        $this->assertEquals(1, $this->institution()->fee_exempt);
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
        $this->assertSame(0, DB::table('sent_emails')->count(), 'No decline email: the institution receives a formal letter.');
        $this->viewApplication($id)->assertJsonPath('props.canAppeal', true);
    }

    public function test_declined_renewal_de_designates_the_institution(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update(['eqa_status' => 'Designated', 'designation_start' => '2020-04-01']);

        $this->declinedApplication();

        $this->assertSame('De-Designated', $this->institution()->eqa_status);
        $this->assertSame($this->vancouverDate(0), $this->institution()->de_designated_date);
    }

    public function test_daily_job_de_designates_a_lapsed_designation(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update([
            'eqa_status' => 'Designated', 'designation_start' => '2020-04-01', 'designation_expiry' => $this->vancouverDate(0),
        ]);

        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('Designated', $this->institution()->eqa_status, 'Still designated on its expiry date.');

        $this->travel(1)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $this->assertSame('De-Designated', $this->institution()->eqa_status);
        $this->assertSame($this->vancouverDate(0), $this->institution()->de_designated_date);
    }

    public function test_approved_renewal_awaiting_admin_fee_holds_the_designation_until_it_expires(): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update([
            'eqa_status' => 'Designated', 'designation_start' => '2020-04-01', 'designation_expiry' => $this->vancouverDate(-1),
        ]);
        $id = $this->applicationAt('application_approval');
        $this->advance($id, 'approve');

        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('Designated', $this->institution()->eqa_status);

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $this->assertSame('Expired', $this->application($id)->status);
        $this->assertSame('De-Designated', $this->institution()->eqa_status);
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

        $this->payOnline($this->invoice($id, ApplicationFeeService::FEE_ADMIN_VARIABLE));
        $this->assertSame('end', $this->application($id)->workflow_stage);
        $this->assertSame(0, DB::table('sent_emails')->whereIn('template_key', ['application_approved_fee_due', 'application_approved'])->count(),
            'An appeal decision is sent by the ADM as a formal letter, not emailed.');
    }

    public function test_appeal_upheld_declines_the_application(): void
    {
        $id = $this->appealUnderReview();
        $emailsBefore = DB::table('sent_emails')->count();

        $this->advance($id, 'appeal_uphold')->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('Declined', $application->status);
        $this->assertSame('Upheld', $application->appeal_decision);
        $this->assertSame('end', $application->workflow_stage);
        $this->assertSame($emailsBefore, DB::table('sent_emails')->count(), 'No appeal decision email.');
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
        $this->assertSame('Additional Information Required', $application->status);
        $this->assertSame('changes_requested', $application->workflow_stage);
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

    public function test_retired_sabc_designation_is_kept_on_older_applications_when_edited(): void
    {
        $draft = $this->applicationAt('draft', ['status' => 'Draft', 'sabc_designation' => true]);
        $review = $this->applicationAt('pending_review', ['sabc_designation' => true]);

        $this->asInstitution()->put("/web/applications/{$draft}", $this->applicationForm(['sabc_designation' => false]))->assertRedirect();
        $this->asMinistry()->put("/admin/applications/{$review}", ['receipt_confirmation' => true, 'sabc_designation' => false])->assertRedirect();
        $this->asInstitution()->post('/web/applications', $this->applicationForm(['sabc_designation' => true]));

        $this->assertEquals(1, $this->application($draft)->sabc_designation);
        $this->assertEquals(1, $this->application($review)->sabc_designation);
        $this->assertNull(DB::table('applications')->whereNotIn('crm_id', [$draft, $review])->value('sabc_designation'));
    }

    public function test_missed_resubmission_deadline_waits_for_the_ministry_to_confirm_the_expiry(): void
    {
        $id = $this->applicationAt('under_review');
        $this->advance($id, 'request_changes', ['changes' => 'Missing info.']);

        $this->travel(31)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();

        $application = $this->application($id);
        $this->assertSame('Pending Admin Confirm Expiry', $application->status);
        $this->assertSame('pending_expiry', $application->workflow_stage);
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertForbidden();
        $this->asInstitution()->post('/web/applications', $this->applicationForm())->assertSessionHas('error');

        $this->advance($id, 'confirm_expiry')->assertSessionHas('success');

        $this->assertSame('Expired', $this->application($id)->status);
        $this->assertSame('end', $this->application($id)->workflow_stage);
    }

    public function test_resubmission_deadline_can_be_set_per_application_and_the_job_uses_it(): void
    {
        $id = $this->applicationAt('under_review');

        $this->advance($id, 'request_changes', ['changes' => 'Missing info.', 'changes_due_date' => $this->vancouverDate(0)])->assertSessionHasErrors('changes_due_date');
        $this->advance($id, 'request_changes', ['changes' => 'Missing info.', 'changes_due_date' => $this->vancouverDate(400)])->assertSessionHasErrors('changes_due_date');
        $this->assertSame('under_review', $this->application($id)->workflow_stage);

        $this->advance($id, 'request_changes', ['changes' => 'Missing info.', 'changes_due_date' => $this->vancouverDate(60)])->assertSessionHas('success');
        $this->assertSame($this->vancouverDate(60), $this->application($id)->changes_due_date);
        $this->assertEmailed('application_changes_requested', $this->vancouverDate(60));

        // Past the 30-day default but within this application's own deadline: not flagged.
        $this->travel(45)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('changes_requested', $this->application($id)->workflow_stage);

        $this->travel(16)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('pending_expiry', $this->application($id)->workflow_stage);
    }

    public function test_ministry_can_change_the_deadline_while_waiting_or_extend_a_missed_one(): void
    {
        $id = $this->applicationAt('under_review');
        $this->advance($id, 'request_changes', ['changes' => 'Missing info.']);

        $this->advance($id, 'extend_changes', ['changes_due_date' => $this->vancouverDate(45)])->assertSessionHas('success');
        $this->assertSame($this->vancouverDate(45), $this->application($id)->changes_due_date);
        $this->assertSame('changes_requested', $this->application($id)->workflow_stage);

        $this->travel(46)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('pending_expiry', $this->application($id)->workflow_stage);

        $this->advance($id, 'extend_changes', ['changes_due_date' => $this->vancouverDate(14)])->assertSessionHas('success');
        $application = $this->application($id);
        $this->assertSame('Additional Information Required', $application->status);
        $this->assertSame('changes_requested', $application->workflow_stage);
        $this->assertSame($this->vancouverDate(14), $application->changes_due_date);
        $this->assertSame('Missing info.', $application->changes_requested);
        $this->assertEmailed('application_changes_requested', $this->vancouverDate(14));

        $this->asInstitution()->put("/web/applications/{$id}", $this->applicationForm())->assertRedirect();
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');
        $this->assertSame('under_review', $this->application($id)->workflow_stage);
    }

    public function test_a_normal_draft_deadline_cannot_be_set_by_the_ministry(): void
    {
        $id = $this->startApplication();

        $this->advance($id, 'extend_changes', ['changes_due_date' => $this->vancouverDate(10)])->assertSessionHas('error');

        $this->assertNull($this->application($id)->changes_due_date);
    }

    public function test_application_must_be_submitted_and_paid_within_the_completion_deadline(): void
    {
        $draft = $this->startApplication();
        $this->assertSame($this->vancouverDate(30), $this->application($draft)->completion_due_date);
        $this->viewApplication($draft)->assertJsonPath('props.application.completion_due_date', $this->vancouverDate(30));

        // Submitted a week later: the application fee is due by the same completion deadline.
        $this->travel(7)->days();
        $this->asInstitution()->post("/web/applications/{$draft}/submit")->assertSessionHas('success');
        $this->assertSame($this->application($draft)->completion_due_date, $this->invoice($draft, ApplicationFeeService::FEE_APPLICATION_NEW)->due_date);

        // A paid application is past the deadline's reach.
        $paid = $this->applicationAt('fees_payment', ['completion_due_date' => $this->vancouverDate(1), 'institution_crm_id' => (string) Str::uuid()]);

        $this->travel(23)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('fee_required', $this->application($draft)->workflow_stage, 'Not expired on the due date itself.');

        $this->travel(1)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $application = $this->application($draft);
        $this->assertSame('end', $application->workflow_stage);
        $this->assertSame('Expired', $application->status);
        $this->assertSame('Expired', $this->invoice($draft, ApplicationFeeService::FEE_APPLICATION_NEW)->invoice_status);
        $this->assertSame('fees_payment', $this->application($paid)->workflow_stage);

        // The institution can start again.
        $this->asInstitution()->get('/web/applications/new')->assertOk();
    }

    public function test_unsubmitted_draft_expires_and_reminder_is_emailed_once_before_the_completion_deadline(): void
    {
        $id = $this->startApplication();

        $this->travel(22)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame(0, DB::table('sent_emails')->where('template_key', 'application_completion_reminder')->count(), 'Too early for the 7-day reminder.');

        $this->travel(1)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame(1, DB::table('sent_emails')->where('template_key', 'application_completion_reminder')->count());
        $this->assertEmailed('application_completion_reminder', $this->application($id)->completion_due_date);
        $this->assertNotNull($this->application($id)->completion_reminder_sent_at);

        $this->travel(8)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('Expired', $this->application($id)->status);
        $this->assertNull($this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW), 'A draft was never invoiced.');
    }

    public function test_ministry_can_change_the_completion_deadline_of_a_draft_or_unpaid_application(): void
    {
        $id = $this->submittedApplication();
        DB::table('applications')->where('crm_id', $id)->update(['completion_reminder_sent_at' => Carbon::now()]);

        $this->advance($id, 'extend_completion', ['completion_due_date' => $this->vancouverDate(0)])->assertSessionHasErrors('completion_due_date');
        $this->advance($id, 'extend_completion', ['completion_due_date' => $this->vancouverDate(60)])->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame($this->vancouverDate(60), $application->completion_due_date);
        $this->assertNull($application->completion_reminder_sent_at, 'The reminder is sent again before the new date.');
        $this->assertSame('fee_required', $application->workflow_stage);
        $this->assertSame($this->vancouverDate(60), $this->invoice($id, ApplicationFeeService::FEE_APPLICATION_NEW)->due_date);

        $this->travel(45)->days();
        $this->artisan('eqa:enforce-deadlines')->assertSuccessful();
        $this->assertSame('fee_required', $this->application($id)->workflow_stage);

        // Only while the application is unfinished.
        $review = $this->applicationAt('under_review');
        $this->advance($review, 'extend_completion', ['completion_due_date' => $this->vancouverDate(10)])->assertSessionHas('error');
    }

    public function test_institution_sees_and_resubmits_an_application_returned_for_additional_information(): void
    {
        $id = $this->applicationAt('under_review');
        $this->advance($id, 'request_changes', ['changes' => 'Upload the current policy manual.']);

        $this->viewApplication($id)
            ->assertJsonPath('props.application.status', 'Additional Information Required')
            ->assertJsonPath('props.canEdit', true)
            ->assertJsonPath('props.awaitingSubmission', false);
        $this->asInstitution()->get("/web/applications/{$id}/edit")->assertOk();

        // Returned before the newer sections existed: submitting first asks for what is missing.
        $this->asInstitution()->post("/web/applications/{$id}/submit")
            ->assertRedirect("/web/applications/{$id}/edit")
            ->assertSessionHasErrors(['campus_locations', 'per_student_fee_enrolment']);
        $this->assertSame('changes_requested', $this->application($id)->workflow_stage);

        $this->asInstitution()->put("/web/applications/{$id}", $this->applicationForm())->assertRedirect();
        $this->asInstitution()->post("/web/applications/{$id}/submit")->assertSessionHas('success');

        $application = $this->application($id);
        $this->assertSame('under_review', $application->workflow_stage);
        $this->assertEquals(0, $application->need_additional_details);
        $this->assertSame(0, DB::table('invoices')->count(), 'Resubmitting additional information is not charged a fee.');
        $this->asInstitution()->get("/web/applications/{$id}/edit")->assertForbidden();
    }

    public function test_applications_already_returned_as_drafts_move_to_the_new_stage(): void
    {
        $returned = $this->applicationAt('draft', ['status' => 'Draft', 'need_additional_details' => true]);
        $neverSubmitted = $this->applicationAt('draft', ['status' => 'Draft', 'need_additional_details' => false]);

        (require database_path('migrations/2026_11_23_000006_add_changes_requested_stage.php'))->up();

        $this->assertSame('changes_requested', $this->application($returned)->workflow_stage);
        $this->assertSame('Additional Information Required', $this->application($returned)->status);
        $this->assertSame('draft', $this->application($neverSubmitted)->workflow_stage);
        $this->assertSame('Draft', $this->application($neverSubmitted)->status);
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

    /** Ministry moves a submitted application forward to $stage, recording the fee by EFT if it is still unpaid. */
    private function reviewUntil(string $id, string $stage): void
    {
        if ($this->application($id)->workflow_stage === 'fee_required') {
            $this->advance($id, 'record_payment', ['payment_method' => 'EFT'])->assertSessionHas('success');
        }
        if ($this->application($id)->workflow_stage === 'fees_payment') {
            $this->advance($id, 'confirm_payment')->assertSessionHas('success');
        }
        if ($stage === 'pending_review') {
            return;
        }

        $this->asMinistry()->put("/admin/applications/{$id}", ['receipt_confirmation' => true, 'ready_for_review' => true]);
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
        return $this->inertiaGet("/web/applications/{$id}");
    }

    private function inertiaGet(string $url): TestResponse
    {
        $version = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->asInstitution()
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->get($url)
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
            'service_street1' => '100 Main Street',
            'service_city' => 'Victoria',
            'service_province' => 'BC',
            'service_postal_code' => 'V8W 1A1',
            'service_country' => 'Canada',
            'service_address_confirmed' => true,
            'campus_locations' => [['location_name' => 'Main Campus', 'street1' => '100 Main Street', 'city' => 'Victoria', 'province' => 'BC', 'postal_code' => 'V8W 1A1', 'primary_location' => true]],
            'website_compliance' => $this->websiteComplianceAnswers(),
            'per_student_fee_enrolment' => 0,
            'annual_report_filed' => true,
        ], $overrides);
    }

    /** @return array<int, array{url: string}> a link for every active Website Compliance item */
    private function websiteComplianceAnswers(): array
    {
        return DB::table('website_compliance_items')->where('is_active', true)->pluck('id')
            ->mapWithKeys(fn ($id): array => [$id => ['url' => 'https://process.example/info/'.$id]])->all();
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
