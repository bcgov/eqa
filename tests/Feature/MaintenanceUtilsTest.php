<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\IsActive;
use App\Models\Role;
use App\Models\User;
use App\Models\Util;
use App\Services\InvoicePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Admin\Services\ApplicationFeeService;
use Tests\TestCase;

class MaintenanceUtilsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(IsActive::class);
    }

    public function test_admin_sees_settings_grouped_by_category(): void
    {
        $this->as(Role::MINISTRY_ADMIN)->get('/admin/maintenance/utils')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/MaintenanceUtils')
                ->where('categories', ['Ministry', 'Fees', 'Deadlines'])
                ->where('results.Fees.0.field_key', 'fee_application_new'));
    }

    public function test_ministry_user_without_admin_role_cannot_open_or_change_utils(): void
    {
        $util = $this->util('fee_application_new');

        $this->as(Role::MINISTRY_USER)->get('/admin/maintenance/utils')->assertForbidden();
        $this->as(Role::MINISTRY_USER)->put("/admin/maintenance/utils/{$util->id}", ['field_name' => '1', 'active_flag' => true])->assertForbidden();

        $this->assertNotSame('1', $util->fresh()->field_name);
    }

    public function test_changing_the_new_application_fee_is_used_for_new_invoices(): void
    {
        $util = $this->util('fee_application_new');

        $this->as(Role::MINISTRY_ADMIN)->put("/admin/maintenance/utils/{$util->id}", [
            'field_name' => '8250',
            'field_description' => 'Updated for 2027.',
            'active_flag' => true,
        ])->assertSessionHas('success');

        $this->assertEquals(8250, app(ApplicationFeeService::class)->applicationFeeQuote(ApplicationFeeService::FEE_APPLICATION_NEW)['amount']);
        $this->assertSame('Updated for 2027.', $util->fresh()->field_description);
    }

    public function test_inactive_setting_falls_back_to_the_default(): void
    {
        $util = $this->util('fee_application_renewal');
        $default = config('eqa.fees.application_renewal');

        $this->as(Role::MINISTRY_ADMIN)->put("/admin/maintenance/utils/{$util->id}", ['field_name' => '9999', 'active_flag' => true]);
        $this->assertEquals(9999, config('eqa.fees.application_renewal'));

        $this->as(Role::MINISTRY_ADMIN)->put("/admin/maintenance/utils/{$util->id}", ['field_name' => '9999', 'active_flag' => false]);
        $this->assertEquals($default, config('eqa.fees.application_renewal'));
    }

    public function test_values_are_validated_by_format(): void
    {
        $admin = $this->as(Role::MINISTRY_ADMIN);

        $admin->put('/admin/maintenance/utils/'.$this->util('fee_appeal')->id, ['field_name' => '-5', 'active_flag' => true])->assertSessionHasErrors('field_name');
        $admin->put('/admin/maintenance/utils/'.$this->util('contact_email')->id, ['field_name' => 'not-an-email', 'active_flag' => true])->assertSessionHasErrors('field_name');
        $admin->put('/admin/maintenance/utils/'.$this->util('deadline_appeal_days')->id, ['field_name' => '2.5', 'active_flag' => true])->assertSessionHasErrors('field_name');
        $admin->put('/admin/maintenance/utils/'.$this->util('ministry_name')->id, ['field_name' => '', 'active_flag' => true])->assertSessionHasErrors('field_name');
    }

    public function test_ministry_name_change_is_shared_with_pages(): void
    {
        $this->as(Role::MINISTRY_ADMIN)->put('/admin/maintenance/utils/'.$this->util('ministry_name')->id, [
            'field_name' => 'Ministry of Learning',
            'active_flag' => true,
        ]);

        $this->get('/institution-directory')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('settings.ministry_name', 'Ministry of Learning'));
    }

    public function test_invoice_page_lists_only_invoice_wording_and_previews_a_pdf(): void
    {
        $admin = $this->as(Role::MINISTRY_ADMIN);

        $admin->get('/admin/maintenance/invoice')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/MaintenanceInvoice')
                ->where('categories', ['Invoice'])
                ->where('results.Invoice.0.field_key', 'invoice_title')
                ->has('placeholders'));

        $preview = $admin->get('/admin/maintenance/invoice/preview')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $preview->getContent());

        $this->as(Role::MINISTRY_USER)->get('/admin/maintenance/invoice')->assertForbidden();
    }

    public function test_edited_invoice_wording_fills_placeholders_and_is_escaped(): void
    {
        $admin = $this->as(Role::MINISTRY_ADMIN);
        $admin->put('/admin/maintenance/utils/'.$this->util('invoice_payment_offline')->id, [
            'field_name' => "Pay by EFT to <Account 123>.\nQuote {invoice_number}.",
            'active_flag' => true,
        ])->assertSessionHasNoErrors();
        $admin->put('/admin/maintenance/utils/'.$this->util('invoice_notes')->id, ['field_name' => '', 'active_flag' => true])->assertSessionHasNoErrors();

        $data = null;
        view()->composer('invoices.invoice', function ($view) use (&$data): void {
            $data ??= $view->getData();
        });
        app(InvoicePdf::class)->render(app(InvoicePdf::class)->sample());

        $this->assertSame('Pay by EFT to &lt;Account 123&gt;.<br>'."\n".'Quote INV-00000000.', $data['text']['payment_offline']);
        $this->assertSame('', $data['text']['notes']);
    }

    public function test_email_templates_moved_under_maintenance(): void
    {
        $this->as(Role::MINISTRY_ADMIN)->get('/admin/email-templates')->assertRedirect('/admin/maintenance/email-templates');
        $this->as(Role::MINISTRY_ADMIN)->get('/admin/maintenance/email-templates')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/EmailTemplates'));
        $this->as(Role::MINISTRY_USER)->get('/admin/maintenance/email-templates')->assertForbidden();
    }

    private function util(string $key): Util
    {
        return Util::where('field_key', $key)->firstOrFail();
    }

    private function as(string $role): static
    {
        $email = Str::slug($role).'@utils.example';
        $user = User::firstOrCreate(['email' => $email], ['name' => $role, 'password' => Str::random(32)]);
        $user->roles()->syncWithoutDetaching([Role::where('name', $role)->value('id')]);

        return $this->actingAs($user->load('roles'))->withSession(['portal_role' => 'ministry']);
    }
}
