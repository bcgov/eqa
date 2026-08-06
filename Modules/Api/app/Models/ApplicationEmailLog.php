<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $application_id
 * @property int|null $institution_id
 * @property string|null $type
 * @property string|null $event_date
 * @property string|null $contact_name
 * @property string|null $contact_title
 * @property string|null $institution_name
 * @property string|null $address
 * @property string|null $email
 * @property string|null $institutional_representative
 * @property string|null $email_title
 * @property string|null $designation_start_date
 * @property string|null $designation_end_date
 * @property string|null $signature
 * @property string|null $created_by
 * @property string|null $updated_at
 * @property string|null $updated_by
 */
class ApplicationEmailLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'application_email_log';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'institution_id',
        'type',
        'event_date',
        'contact_name',
        'contact_title',
        'institution_name',
        'address',
        'email',
        'institutional_representative',
        'email_title',
        'designation_start_date',
        'designation_end_date',
        'signature',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'application_id' => 'integer',
            'institution_id' => 'integer',
            'type' => 'string',
            'event_date' => 'string',
            'contact_name' => 'string',
            'contact_title' => 'string',
            'institution_name' => 'string',
            'address' => 'string',
            'email' => 'string',
            'institutional_representative' => 'string',
            'email_title' => 'string',
            'designation_start_date' => 'string',
            'designation_end_date' => 'string',
            'signature' => 'string',
            'created_by' => 'string',
            'updated_at' => 'string',
            'updated_by' => 'string',
        ];
    }

    /**
     * Get the application associated with this email log entry.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }
}