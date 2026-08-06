<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class InstitutionNotificationEmail extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'institution_notification_emails';

    protected $fillable = [
        'institution_id',
        'email_address',
        'subject',
        'body',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'email_address' => 'string',
            'subject' => 'string',
            'body' => 'string',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }
}