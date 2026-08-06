<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

class AuditChangeLog extends Model
{
    protected $connection = 'sqlsrv_eqadm';

    protected $table = 'audit_change_log';

    protected $primaryKey = 'audit_change_log_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'audit_change_log_id',
        'entity',
        'id',
        'field',
        'old_value',
        'new_value',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'audit_change_log_id' => 'integer',
        'entity' => 'string',
        'id' => 'integer',
        'field' => 'string',
        'old_value' => 'string',
        'new_value' => 'string',
        'created_at' => 'date',
        'created_by' => 'string',
    ];
}