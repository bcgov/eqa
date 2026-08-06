<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $invoice_id
 * @property int $institution_id
 * @property int|null $application_id
 * @property int|null $payment_id
 * @property string $invoice_date
 * @property string $invoice_amount
 * @property string|null $invoice_status
 * @property string|null $order_id
 * @property resource|string|null $receipt_pdf
 * @property string|null $created_at
 * @property string|null $created_by
 * @property string|null $updated_at
 * @property string|null $updated_by
 * @property string|null $issued_by
 */
class Invoice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoice';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'invoice_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model should be timestamped.
     *
     * The source table stores created_at/updated_at as free-form varchar
     * columns rather than native timestamps, so Eloquent's automatic
     * timestamp management is disabled and the values are cast manually.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'application_id',
        'payment_id',
        'invoice_date',
        'invoice_amount',
        'invoice_status',
        'order_id',
        'receipt_pdf',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'issued_by',
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
            'institution_id' => 'integer',
            'application_id' => 'integer',
            'payment_id' => 'integer',
            'invoice_date' => 'string',
            'invoice_amount' => 'decimal:2',
            'invoice_status' => 'string',
            'order_id' => 'string',
            'created_at' => 'string',
            'created_by' => 'string',
            'updated_at' => 'string',
            'updated_by' => 'string',
            'issued_by' => 'string',
        ];
    }

    /**
     * Get the application associated with the invoice.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id', 'application_id');
    }

    /**
     * Get the payment associated with the invoice.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }
}