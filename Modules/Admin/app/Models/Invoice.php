<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invoice / financial summary, migrated from the legacy Dynamics
 * eqa_financialsummary entity. Read-only projection used by the portals.
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    protected $primaryKey = 'crm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'payment_received_date' => 'date',
        'cheque_date' => 'date',
        'refund_date' => 'date',
        'invoice_amount' => 'decimal:2',
        'taxes' => 'decimal:2',
        'total_charges' => 'decimal:2',
        'invoice_balance' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_crm_id', 'crm_id');
    }
}
