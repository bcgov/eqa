Modules\Api\Models\InvoiceFee.php:
<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InvoiceFee
 *
 * Pivot-like table linking invoices to fees. The source table has no
 * declared primary key or identity column, so Eloquent's implicit
 * incrementing integer key handling is disabled.
 *
 * @property int $invoice_id
 * @property int $fee_id
 */
class InvoiceFee extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'invoice_fee';

    /**
     * The source table has no primary key column; disable Eloquent's
     * default auto-incrementing integer key assumptions.
     */
    protected $primaryKey = null;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     */
    protected $keyType = 'int';

    /**
     * The source table has no timestamp columns.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'fee_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'fee_id' => 'integer',
        ];
    }

    /**
     * The invoice this fee is attached to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * The fee attached to the invoice.
     */
    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class, 'fee_id');
    }
}