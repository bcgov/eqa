<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Admin\Services\ApplicationFeeService;
use Modules\Admin\Services\ProcessNotifier;
use Tests\TestCase;

/** New vs renewal detection and the fee amounts / descriptors each invoice is built from. */
class ApplicationFeeRulesTest extends TestCase
{
    use RefreshDatabase;

    private const INSTITUTION = '33333333-3333-3333-3333-333333333333';

    private ApplicationFeeService $fees;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'eqa.fees.application_new' => 7500,
            'eqa.fees.application_renewal' => 5500,
            'eqa.fees.appeal' => 250,
            'eqa.fees.multi_location' => 3000,
            'eqa.fees.per_international_student' => 2,
        ]);
        DB::table('institutions')->insert(['crm_id' => self::INSTITUTION, 'name' => 'Rules College']);

        $this->fees = app(ApplicationFeeService::class);
    }

    // New vs renewal ----------------------------------------------------------------

    public function test_institution_never_designated_or_approved_applies_as_new(): void
    {
        $this->assertFalse($this->isReapplication());
    }

    public function test_designation_start_date_makes_it_a_renewal(): void
    {
        $this->institutionHas(['designation_start' => '2021-06-01']);

        $this->assertTrue($this->isReapplication());
    }

    public function test_designated_status_makes_it_a_renewal(): void
    {
        $this->institutionHas(['eqa_status' => 'Designated']);

        $this->assertTrue($this->isReapplication());
    }

    public function test_de_designated_institution_is_treated_as_a_renewal(): void
    {
        $this->institutionHas(['eqa_status' => 'De-Designated']);

        $this->assertTrue($this->isReapplication(), 'Current rule: any status containing "designat" counts as previously designated.');
    }

    public function test_not_approved_institution_applies_as_new(): void
    {
        $this->institutionHas(['eqa_status' => 'Not Approved']);

        $this->assertFalse($this->isReapplication());
    }

    public function test_a_previously_approved_application_makes_it_a_renewal(): void
    {
        $this->applicationWith(['status' => 'Approved']);

        $this->assertTrue($this->isReapplication());
    }

    public function test_an_approved_date_alone_makes_it_a_renewal(): void
    {
        $this->applicationWith(['status' => 'Expired', 'approved_date' => '2024-03-01']);

        $this->assertTrue($this->isReapplication());
    }

    public function test_a_declined_application_does_not_make_it_a_renewal(): void
    {
        $this->applicationWith(['status' => 'Not Approved']);

        $this->assertFalse($this->isReapplication());
    }

    public function test_another_institutions_approval_does_not_count(): void
    {
        $this->applicationWith(['status' => 'Approved', 'institution_crm_id' => (string) Str::uuid()]);

        $this->assertFalse($this->isReapplication());
    }

    // Fixed fees ----------------------------------------------------------------------

    public function test_application_and_appeal_fees_come_from_configuration(): void
    {
        $new = $this->fees->applicationFeeQuote(ApplicationFeeService::FEE_APPLICATION_NEW);
        $renewal = $this->fees->applicationFeeQuote(ApplicationFeeService::FEE_APPLICATION_RENEWAL);
        $appeal = $this->fees->appealFeeQuote();

        $this->assertEquals(7500, $new['amount']);
        $this->assertSame('New Application Fee (fixed): $7,500.00', $new['description']);
        $this->assertEquals(5500, $renewal['amount']);
        $this->assertSame('Renewal Application Fee (fixed): $5,500.00', $renewal['description']);
        $this->assertEquals(250, $appeal['amount']);
        $this->assertSame('Appeal Fee (fixed): $250.00', $appeal['description']);

        config(['eqa.fees.application_new' => 8000]);
        $this->assertEquals(8000, $this->fees->applicationFeeQuote(ApplicationFeeService::FEE_APPLICATION_NEW)['amount']);
    }

    public function test_quote_line_items_are_itemized(): void
    {
        $quote = $this->fees->applicationFeeQuote(ApplicationFeeService::FEE_APPLICATION_RENEWAL);

        $this->assertSame([[
            'code' => ApplicationFeeService::FEE_APPLICATION_RENEWAL,
            'label' => 'Renewal Application Fee (fixed)',
            'quantity' => 1,
            'unit_price' => 5500.0,
            'amount' => 5500.0,
        ]], $quote['line_items']);
    }

    // Administrative fee -----------------------------------------------------------------

    public function test_private_institution_with_multiple_locations_pays_both_components(): void
    {
        $this->activeCampuses(2);

        $quote = $this->fees->adminFeeQuote($this->applicationWith(['intl_students_permit' => 150, 'intl_students_other' => 40]));

        $this->assertEquals(3300, $quote['amount'], '$3,000 multiple location fee + 150 study permit students x $2.');
        $this->assertSame(
            "Multiple Location Fee (private institution, 2 active locations): \$3,000.00\nInternational Student Fee (study permit students): 150 x \$2.00 = \$300.00",
            $quote['description'],
        );
        $this->assertSame(['multi_location', 'international_students'], array_column($quote['line_items'], 'code'));
    }

    public function test_international_students_other_are_not_charged(): void
    {
        $quote = $this->fees->adminFeeQuote($this->applicationWith(['intl_students_permit' => 0, 'intl_students_other' => 500]));

        $this->assertEquals(0, $quote['amount']);
        $this->assertStringContainsString('International Student Fee not applied (0 study permit students)', $quote['description']);
    }

    public function test_single_location_private_institution_has_no_multiple_location_fee(): void
    {
        $this->activeCampuses(1);

        $quote = $this->fees->adminFeeQuote($this->applicationWith(['intl_students_permit' => 10]));

        $this->assertEquals(20, $quote['amount']);
        $this->assertStringContainsString('Multiple Location Fee not applied (private institution, 1 active location)', $quote['description']);
    }

    public function test_inactive_locations_are_not_counted(): void
    {
        $this->activeCampuses(1);
        DB::table('campuses')->insert(['crm_id' => (string) Str::uuid(), 'institution_crm_id' => self::INSTITUTION, 'name' => 'Closed', 'status' => 'Inactive']);

        $quote = $this->fees->adminFeeQuote($this->applicationWith());

        $this->assertStringContainsString('Multiple Location Fee not applied (private institution, 1 active location)', $quote['description']);
    }

    public function test_public_institution_has_no_multiple_location_fee(): void
    {
        $this->institutionHas(['qa_met_through' => ApplicationFeeService::PUBLIC_QA_MET_THROUGH]);
        $this->activeCampuses(5);

        $quote = $this->fees->adminFeeQuote($this->applicationWith(['intl_students_permit' => 25]));

        $this->assertEquals(50, $quote['amount']);
        $this->assertStringContainsString('Multiple Location Fee not applied (public institution)', $quote['description']);
    }

    public function test_any_other_qa_pathway_counts_as_private(): void
    {
        $this->institutionHas(['qa_met_through' => 'Languages Canada Accreditation']);
        $this->activeCampuses(3);

        $quote = $this->fees->adminFeeQuote($this->applicationWith(['intl_students_permit' => 0]));

        $this->assertEquals(3000, $quote['amount']);
    }

    public function test_created_invoice_stores_the_descriptor(): void
    {
        $this->activeCampuses(2);
        $application = $this->applicationWith(['intl_students_permit' => 5]);
        $quote = $this->fees->adminFeeQuote($application);

        $invoice = $this->fees->createInvoice($application, ApplicationFeeService::FEE_ADMIN_VARIABLE, $quote['amount'], null, $quote['description'], $quote['line_items']);

        $this->assertEquals(3010, $invoice->total_charges);
        $this->assertEquals(3010, $invoice->invoice_balance);
        $this->assertSame('Unpaid', $invoice->invoice_status);
        $this->assertSame($quote['description'], $invoice->description);
        $this->assertEquals($quote['line_items'], json_decode($invoice->line_items, true));
        $this->assertMatchesRegularExpression('/^INV-\d{8}$/', $invoice->invoice_number);
    }

    // Helpers ---------------------------------------------------------------------------

    private function isReapplication(): bool
    {
        return app(ProcessNotifier::class)->isReapplication(DB::table('institutions')->where('crm_id', self::INSTITUTION)->first());
    }

    /** @param  array<string, mixed>  $values */
    private function institutionHas(array $values): void
    {
        DB::table('institutions')->where('crm_id', self::INSTITUTION)->update($values);
    }

    private function activeCampuses(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('campuses')->insert(['crm_id' => (string) Str::uuid(), 'institution_crm_id' => self::INSTITUTION, 'name' => 'Campus '.$i, 'status' => 'Active']);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function applicationWith(array $attributes = []): object
    {
        $id = (string) Str::uuid();
        DB::table('applications')->insert(array_merge([
            'crm_id' => $id,
            'reference' => 'APP-'.random_int(10000000, 99999999),
            'status' => 'Under Review',
            'workflow_stage' => 'end',
            'institution_crm_id' => self::INSTITUTION,
            'institution_name' => 'Rules College',
        ], $attributes));

        return DB::table('applications')->where('crm_id', $id)->first();
    }
}
