<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'receipts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'receipt_number',
        'payment_id',
        'account_id',
        'customer_id',
        'amount',
        'balance',
        'payment_method',
        'transaction_reference',
        'status',
        'issued_at',
        'received_at',
        'notes',
        'confirmation_email',
        'is_voided',
        'voided_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'payment_id' => 'integer',
            'account_id' => 'integer',
            'customer_id' => 'integer',
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'issued_at' => 'datetime',
            'received_at' => 'datetime',
            'is_voided' => 'boolean',
            'voided_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}