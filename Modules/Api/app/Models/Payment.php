<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Source lineage: EQADM/payment (sql_schema:table)
 *
 * @property int $payment_id
 * @property int $payment_type
 * @property string $payment_amount
 * @property string $payment_date
 * @property string|null $transaction_ref_number
 * @property string|null $auth_code
 * @property string|null $created_at
 * @property string|null $created_by
 * @property string|null $updated_at
 * @property string|null $updated_by
 */
class Payment extends Model
{
    protected $table = 'payment';

    protected $primaryKey = 'payment_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'payment_type',
        'payment_amount',
        'payment_date',
        'transaction_ref_number',
        'auth_code',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'payment_type' => 'integer',
        'payment_amount' => 'decimal:2',
        'payment_date' => 'string',
        'transaction_ref_number' => 'string',
        'auth_code' => 'string',
        'created_at' => 'string',
        'created_by' => 'string',
        'updated_at' => 'string',
        'updated_by' => 'string',
    ];

    /**
     * Scope a query to a specific payment type.
     */
    public function scopeOfType(Builder $query, int $paymentType): Builder
    {
        return $query->where('payment_type', $paymentType);
    }

    /**
     * Scope a query to payments matching a transaction reference number.
     */
    public function scopeWithTransactionRef(Builder $query, string $transactionRefNumber): Builder
    {
        return $query->where('transaction_ref_number', $transactionRefNumber);
    }
}