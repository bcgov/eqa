<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Util;
use App\Services\InvoicePdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maintenance menu (Ministry Admins): Utils (application settings), Email Templates and the
 * Invoice wording, so these can be changed without editing .env.
 */
class MaintenanceController extends Controller
{
    private const CATEGORY_ORDER = ['Ministry', 'Fees', 'Deadlines'];

    private const INVOICE_CATEGORY = 'Invoice';

    public function utils(): Response
    {
        return Inertia::render('Admin/MaintenanceUtils', $this->grouped(
            Util::query()->where('field_type', '!=', self::INVOICE_CATEGORY),
        ));
    }

    public function invoice(): Response
    {
        return Inertia::render('Admin/MaintenanceInvoice', $this->grouped(
            Util::query()->where('field_type', self::INVOICE_CATEGORY),
        ) + ['placeholders' => InvoicePdf::PLACEHOLDERS]);
    }

    /** Sample invoice with the current wording, opened in the browser. */
    public function previewInvoice(InvoicePdf $pdf): HttpResponse
    {
        return response($pdf->render($pdf->sample()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="EQA_Invoice_Preview.pdf"',
        ]);
    }

    public function updateUtil(Request $request, Util $util): RedirectResponse
    {
        $valueRules = match ($util->field_format) {
            'money' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'integer' => ['required', 'integer', 'min:1', 'max:3650'],
            'email' => ['required', 'email', 'max:255'],
            'multiline' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            default => ['required', 'string', 'max:255'],
        };

        $data = $request->validate([
            'field_name' => $valueRules,
            'field_description' => ['nullable', 'string', 'max:1000'],
            'active_flag' => ['required', 'boolean'],
        ], [], ['field_name' => 'value', 'field_description' => 'description']);

        $util->update([
            'field_name' => trim((string) ($data['field_name'] ?? '')),
            'field_description' => $data['field_description'] ?? null,
            'active_flag' => (bool) $data['active_flag'],
        ]);

        return back()->with('success', $util->field_label.' updated.');
    }

    /**
     * @param  Builder<Util>  $query
     * @return array{categories: Collection<int, string>, results: Collection<string, mixed>}
     */
    private function grouped($query): array
    {
        $utils = $query->orderBy('sort_order')->orderBy('field_label')->get([
            'id', 'field_type', 'field_key', 'field_label', 'field_name', 'field_description', 'field_format', 'active_flag', 'updated_at',
        ]);

        $rank = fn (string $type): int => ($i = array_search($type, self::CATEGORY_ORDER, true)) === false ? PHP_INT_MAX : $i;

        return [
            'categories' => $utils->pluck('field_type')->unique()->sortBy($rank)->values(),
            'results' => $utils->groupBy('field_type'),
        ];
    }
}
