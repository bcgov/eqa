<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccreditationDownload extends Model
{
    /**
     * Source table has no timestamp columns tracked by Eloquent conventions.
     */
    public $timestamps = false;

    /**
     * The table is not backed by an auto-incrementing identity column.
     */
    public $incrementing = false;

    protected $table = 'accreditation_download';

    protected $primaryKey = 'download_id';

    protected $keyType = 'int';

    protected $fillable = [
        'download_id',
        'accreditation_agency_id',
        'pctia_id',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'download_id' => 'integer',
            'accreditation_agency_id' => 'integer',
            'pctia_id' => 'integer',
            'status' => 'integer',
            'created_at' => 'date',
        ];
    }

    public function accreditationAgency(): BelongsTo
    {
        return $this->belongsTo(AccreditationAgency::class, 'accreditation_agency_id');
    }
}