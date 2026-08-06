<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OptionSetDatum extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'option_set_data';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'option_set_id',
        'value',
        'text',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'option_set_id' => 'integer',
            'value' => 'string',
            'text' => 'string',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function optionSet(): BelongsTo
    {
        return $this->belongsTo(OptionSet::class, 'option_set_id');
    }
}