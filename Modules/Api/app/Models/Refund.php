<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $table = 'refund';

    protected $primaryKey = 'refund_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'payment_id',
        'refund_amount',
        'refund_date',
        'transaction_ref_number',
        'auth_code',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'refund_pdf',
    ];

    protected $casts = [
        'refund_id' => 'integer',
        'invoice_id' => 'integer',
        'payment_id' => 'integer',
        'refund_amount' => 'decimal:2',
        'refund_date' => 'string',
        'transaction_ref_number' => 'string',
        'auth_code' => 'string',
        'created_at' => 'string',
        'created_by' => 'string',
        'updated_at' => 'string',
        'updated_by' => 'string',
        'refund_pdf' => 'string',
    ];

    /**
     * @return BelongsTo<Invoice, Refund>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<Payment, Refund>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}