<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * InstitutionCollection
 *
 * Migrated from EQA_API.Models.v1.InstitutionCollection (EQA API/Models/v1/ListModels.cs).
 * Represents a paginated/aggregated collection wrapper around Institution records.
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $total_count
 * @property int|null $page
 * @property int|null $page_size
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class InstitutionCollection extends Model
{
    /**
     * @var string
     */
    protected $table = 'institution_collections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'total_count',
        'page',
        'page_size',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'total_count' => 'integer',
        'page' => 'integer',
        'page_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return HasMany<Institution, InstitutionCollection>
     */
    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class, 'institution_collection_id');
    }

    /**
     * @param Builder<InstitutionCollection> $query
     * @return Builder<InstitutionCollection>
     */
    public function scopePaged(Builder $query, int $page, int $pageSize): Builder
    {
        return $query->forPage($page, $pageSize);
    }
}