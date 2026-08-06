<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $environment
 * @property string|null $institution_number
 * @property string|null $user_id
 * @property string $source
 * @property string $controller
 * @property string $route_data
 * @property string $action
 * @property string $message
 * @property string $stack_trace
 */
class ErrorLog extends Model
{
    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'sqlsrv_eqa_tracking';

    /**
     * The table associated with the model.
     */
    protected $table = 'ErrorLog';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'ID';

    /**
     * The "type" of the auto-incrementing ID.
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'Date',
        'Environment',
        'InstitutionNumber',
        'UserID',
        'Source',
        'Controller',
        'RouteData',
        'Action',
        'Message',
        'StackTrace',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ID' => 'integer',
            'Date' => 'datetime',
            'Environment' => 'string',
            'InstitutionNumber' => 'string',
            'UserID' => 'string',
            'Source' => 'string',
            'Controller' => 'string',
            'RouteData' => 'string',
            'Action' => 'string',
            'Message' => 'string',
            'StackTrace' => 'string',
        ];
    }
}