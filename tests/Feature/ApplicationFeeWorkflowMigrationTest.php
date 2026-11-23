<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicationFeeWorkflowMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_applications_move_onto_the_new_fee_stages(): void
    {
        $unpaid = $this->application('pending_review', ['fees_payment_received' => null]);
        $paidOnline = $this->application('pending_review', ['fees_payment_received' => true]);
        $paidInvoice = $this->application('pending_review', ['fees_payment_received' => false]);
        DB::table('invoices')->insert([
            'crm_id' => (string) Str::uuid(),
            'application_crm_id' => $paidInvoice,
            'invoice_number' => 'INV-00000001',
            'fee_type' => 'application_new',
            'invoice_status' => 'Paid',
        ]);
        $recorded = $this->application('fees_payment', ['fees_payment_received' => true]);
        $underReview = $this->application('under_review', ['status' => 'Under Review']);

        (require database_path('migrations/2026_11_23_000001_reorder_application_fee_workflow.php'))->up();

        $this->assertStage($unpaid, 'fee_required', 'Application Fee Required');
        $this->assertStage($paidOnline, 'pending_review', 'Pending Review');
        $this->assertStage($paidInvoice, 'pending_review', 'Pending Review');
        $this->assertStage($recorded, 'pending_review', 'Pending Review');
        $this->assertStage($underReview, 'under_review', 'Under Review');
    }

    /** @param  array<string, mixed>  $attributes */
    private function application(string $stage, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('applications')->insert(array_merge([
            'crm_id' => $id,
            'reference' => 'APP-'.random_int(10000000, 99999999),
            'status' => 'Pending Review',
            'workflow_stage' => $stage,
        ], $attributes));

        return $id;
    }

    private function assertStage(string $id, string $stage, string $status): void
    {
        $application = DB::table('applications')->where('crm_id', $id)->first();
        $this->assertSame($stage, $application->workflow_stage);
        $this->assertSame($status, $application->status);
    }
}
