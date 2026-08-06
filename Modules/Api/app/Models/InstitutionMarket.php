<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $institution_id
 * @property int $market_id
 */
class InstitutionMarket extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'institution_market';

    protected $primaryKey = ['institution_id', 'market_id'];

    protected $keyType = 'array';

    protected $fillable = [
        'institution_id',
        'market_id',
    ];

    protected $casts = [
        'institution_id' => 'integer',
        'market_id' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id', 'id');
    }

    /**
     * Composite primary keys are not natively supported by Eloquent.
     * The following overrides make basic operations (find, save, delete)
     * function correctly against the composite (institution_id, market_id) key.
     */
    public function getKeyName(): array
    {
        return $this->primaryKey;
    }

    public function getKey(): array
    {
        $key = [];

        foreach ($this->getKeyName() as $column) {
            $key[$column] = $this->getAttribute($column);
        }

        return $key;
    }

    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->getKeyName() as $column) {
            $query->where($column, '=', $this->getAttributeFromArray($column));
        }

        return $query;
    }

    protected function setKeysForSelectQuery($query)
    {
        return $this->setKeysForSaveQuery($query);
    }
}