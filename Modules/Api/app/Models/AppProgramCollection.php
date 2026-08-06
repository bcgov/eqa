<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppProgramCollection extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'app_program_collections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'code',
        'is_active',
        'metadata',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<\Modules\Api\Models\AppProgram, self>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(AppProgram::class, 'app_program_collection_id');
    }

    /**
     * @return BelongsTo<\Modules\Api\Models\Application, self>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }
}