<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const INSTITUTION = '44444444-4444-4444-4444-444444444444';

    private const OTHER_INSTITUTION = '55555555-5555-5555-5555-555555555555';

    public function test_institution_downloads_its_invoice_as_a_pdf(): void
    {
        $crmId = $this->invoice(self::INSTITUTION, [
            'invoice_number' => 'INV-00000042',
            'line_items' => json_encode([['code' => 'application_new', 'label' => 'New Application Fee (fixed)', 'quantity' => 1, 'unit_price' => 7500, 'amount' => 7500]]),
        ]);

        $response = $this->asInstitution()->get("/web/invoices/{$crmId}/download");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('EQA_Invoice_INV-00000042.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_invoice_document_shows_billing_details_items_and_payment_instructions(): void
    {
        $crmId = $this->invoice(self::INSTITUTION, [
            'invoice_number' => 'INV-00000043',
            'due_date' => '2026-12-31',
            'line_items' => json_encode([['label' => 'International Student Fee (study permit students)', 'quantity' => 40, 'unit_price' => 2, 'amount' => 80]]),
            'invoice_amount' => 80,
            'total_charges' => 80,
            'invoice_balance' => 80,
            'fee_type' => 'admin_variable',
        ]);

        $html = $this->renderedHtml($crmId);

        $this->assertStringContainsString('Download College Ltd.', $html);
        $this->assertStringContainsString('International Student Fee (study permit students)', $html);
        $this->assertStringContainsString('$80.00', $html);
        $this->assertStringContainsString('December 31, 2026', $html);
        $this->assertStringContainsString('How to pay', $html);
    }

    public function test_institution_cannot_download_another_institutions_invoice(): void
    {
        $crmId = $this->invoice(self::OTHER_INSTITUTION, ['invoice_number' => 'INV-00000099']);

        $this->asInstitution()->get("/web/invoices/{$crmId}/download")->assertNotFound();
    }

    private function renderedHtml(string $crmId): string
    {
        $data = null;
        view()->composer('invoices.invoice', function ($view) use (&$data): void {
            $data ??= $view->getData();
        });
        $this->asInstitution()->get("/web/invoices/{$crmId}/download")->assertOk();

        return view('invoices.invoice', $data)->render();
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoice(string $institutionCrmId, array $attributes): string
    {
        $crmId = (string) Str::uuid();
        DB::table('invoices')->insert(array_merge([
            'crm_id' => $crmId,
            'institution_crm_id' => $institutionCrmId,
            'institution_name' => 'Download College',
            'application_reference' => 'APP-00000001',
            'invoice_date' => '2026-11-23',
            'invoice_amount' => 7500,
            'taxes' => 0,
            'total_charges' => 7500,
            'invoice_balance' => 7500,
            'invoice_status' => 'Unpaid',
            'status' => 'Active',
            'fee_type' => 'application_new',
        ], $attributes));

        return $crmId;
    }

    private function asInstitution(): static
    {
        DB::table('institutions')->insertOrIgnore([
            ['crm_id' => self::INSTITUTION, 'name' => 'Download College', 'legal_name' => 'Download College Ltd.', 'street1' => '1 Main St', 'city' => 'Victoria', 'province' => 'BC', 'postal_code' => 'V8W 1A1'],
            ['crm_id' => self::OTHER_INSTITUTION, 'name' => 'Other College', 'legal_name' => null, 'street1' => null, 'city' => null, 'province' => null, 'postal_code' => null],
        ]);
        $user = User::firstOrCreate(['email' => 'inst@download.example'], ['name' => 'Inst', 'password' => Str::random(32), 'bceid_user_guid' => 'DOWNLOADBCEID']);
        $user->roles()->syncWithoutDetaching([Role::where('name', Role::INSTITUTION_ADMIN)->value('id')]);

        return $this->actingAs($user->load('roles'))->withSession(['portal_institution' => self::INSTITUTION, 'portal_role' => 'institution']);
    }
}
