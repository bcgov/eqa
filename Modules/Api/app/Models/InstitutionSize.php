<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Source lineage: EQADM/institution_size (sql_schema.table)
 */
class InstitutionSize extends Model
{
    protected $table = 'institution_size';

    protected $primaryKey = 'institution_size_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'institution_size_id',
        'value',
    ];

    protected $casts = [
        'institution_size_id' => 'integer',
        'value' => 'string',
    ];
}