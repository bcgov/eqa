<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $contact_id
 * @property int|null $institution_id
 * @property string|null $title
 * @property string|null $department
 * @property string $first_name
 * @property string $last_name
 * @property int|null $address_id
 * @property string|null $phone_number_1
 * @property string|null $phone_number_2
 * @property string|null $fax
 * @property string|null $email
 * @property string|null $signature
 * @property string|null $password
 * @property bool $temp_password
 * @property int|null $contact_type_id
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $updated_by
 */
class Contact extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'contact';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'contact_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are