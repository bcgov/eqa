<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Api\Database\Factories\AccreditationAgencyContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $accreditation_agency_contact_id
 * @property int $agency_id
 * @property int $contact_id
 * @property string|null $contact_type
 */
class AccreditationAgencyContact extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'accreditation_agency_contact';

    protected $primaryKey = 'accreditation_agency_contact_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'agency_id',
        'contact_id',
        'contact_type',
    ];

    protected $casts = [
        'accreditation_agency_contact_id' => 'integer',
        'agency_id' => 'integer',
        'contact_id' => 'integer',
        'contact_type' => 'string',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(AccreditationAgency::class, 'agency_id');
    }

    protected static function newFactory(): AccreditationAgencyContactFactory
    {
        return AccreditationAgencyContactFactory::new();
    }
}