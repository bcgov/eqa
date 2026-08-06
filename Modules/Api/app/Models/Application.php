<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for the legacy EQADM.dbo.application table.
 *
 * The source table declares no primary key and stores all
 * date/time values as free-form varchar strings, so Eloquent's
 * automatic timestamp management is disabled and the assumed
 * identifier column is treated as non-incrementing.
 */
class Application extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'application';

    protected $primaryKey = 'application_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'institution_id',
        'representative_signature',
        'application_workflow_id',
        'sabc_designation',
        'invoice_id',
        'application_status',
        'renewal_due',
        'renewed',
        'logo_terms_checked',
        'logo_terms_checked2',
        'logo_terms_checked3',
        'otherLogos',
        'affiliations',
        'brand_signature',
        'approved_date',
        'approved_by',
        'created_at',
        'created_by',
        'created_id',
        'submitted_at',
        'submitted_by',
        'updated_at',
        'updated_by',
        'multiple_year',
        'valid_year',
    ];

    protected $casts = [
        'application_id' => 'integer',
        'institution_id' => 'integer',
        'representative_signature' => 'string',
        'application_workflow_id' => 'integer',
        'sabc_designation' => 'boolean',
        'invoice_id' => 'integer',
        'application_status' => 'integer',
        'renewal_due' => 'boolean',
        'renewed' => 'boolean',
        'logo_terms_checked' => 'boolean',
        'logo_terms_checked2' => 'boolean',
        'logo_terms_checked3' => 'boolean',
        'otherLogos' => 'string',
        'affiliations' => 'string',
        'brand_signature' => 'string',
        'approved_date' => 'string',
        'approved_by' => 'string',
        'created_at' => 'string',
        'created_by' => 'string',
        'created_id' => 'integer',
        'submitted_at' => 'string',
        'submitted_by' => 'string',
        'updated_at' => 'string',
        'updated_by' => 'string',
        'multiple_year' => 'boolean',
        'valid_year' => 'string',
    ];

    /**
     * Invoice associated with this application.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }
}