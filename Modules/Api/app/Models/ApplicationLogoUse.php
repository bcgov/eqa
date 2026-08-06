<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationLogoUse extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'application_logo_use';

    /**
     * The table does not have a single auto-incrementing primary key.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * This table has no timestamp columns.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The composite key columns for this pivot-style table.
     *
     * @var array<int, string>
     */
    protected $primaryKey = ['application_id', 'logo_use_id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'application_id',
        'logo_use_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'application_id' => 'integer',
        'logo_use_id' => 'integer',
    ];

    /**
     * Get the application associated with this logo use record.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * Get the logo use associated with this record.
     */
    public function logoUse(): BelongsTo
    {
        return $this->belongsTo(LogoUse::class, 'logo_use_id');
    }
}