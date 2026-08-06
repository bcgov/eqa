<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $agency_standing_id
 * @property string|null $standing_value
 */
final class AgencyStanding extends Model
{
    protected $table = 'agency_standing';

    protected $primaryKey = 'agency_standing_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'agency_standing_id',
        'standing_value',
    ];

    protected $casts = [
        'agency_standing_id' => 'integer',
        'standing_value' => 'string',
    ];
}