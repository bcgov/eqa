<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalError extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'portal_errors';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'error_code',
        'error_message',
        'error_detail',
        'source',
        'status_code',
        'trace_id',
        'occurred_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}