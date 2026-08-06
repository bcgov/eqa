<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $contact_type_id
 * @property string $description
 */
class ContactType extends Model
{
    protected $table = 'contact_type';

    protected $primaryKey = 'contact_type_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'contact_type_id',
        'description',
    ];

    protected $casts = [
        'contact_type_id' => 'integer',
        'description' => 'string',
    ];
}