<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Source lineage: EQADM/institution_type (sql_schema:table)
 *
 * @property int $institution_type_id
 * @property string|null $description
 */
final class InstitutionType extends Model
{
    /**
     * @var string
     */
    protected $table = 'institution_type';

    /**
     * @var string
     */
    protected $primaryKey = 'institution_type_id';

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
        'institution_type_id',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'institution_type_id' => 'integer',
            'description' => 'string',
        ];
    }
}