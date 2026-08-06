<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadStatus extends Model
{
    /**
     * No single-column primary key exists on this legacy table.
     */
    protected $primaryKey = null;

    public $incrementing = false;

    protected $table = 'download_status';

    public $timestamps = false;

    protected $fillable = [
        'status_code',
        'status_value',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'status_value' => 'string',
        ];
    }
}