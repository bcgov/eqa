<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the eqa_denial_reason reference table (source: EQADM.dbo.eqa_denial_reason).
 */
class DenialReason extends Model
{
    /**
     * @var string
     */
    protected $table = 'eqa_denial_reason';

    /**
     * @var string
     */
    protected $primaryKey = 'denial_reason_code';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'int';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'denial_reason_code',
        'denial_reason_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'denial_reason_code' => 'integer',
            'denial_reason_value' => 'string',
        ];
    }
}