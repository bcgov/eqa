<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the legacy EQADM.dbo.accreditation_agency table.
 *
 * @property int $accreditation_agency_id
 * @property int|null $agency_code
 * @property string|null $agency_name
 * @property string|null $agency_url
 * @property \Illuminate\Support\Carbon|null $effective_date
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $updated_by
 */
class AccreditationAgency extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'accreditation_agency';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'accreditation_agency_id';

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'accreditation_agency_id',
        'agency_code',
        'agency_name',
        'agency_url',
        'effective_date',
        'expiry_date',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accreditation_agency_id' => 'integer',
            'agency_code' => 'integer',
            'agency_name' => 'string',
            'agency_url' => 'string',
            'effective_date' => 'date',
            'expiry