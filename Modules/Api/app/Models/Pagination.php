<create
path="C:\Users\KMARSH\AppData\Local\Temp\ai-bridge-9e335231528d\Pagination.php">
<parameter name="file_text"><?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pagination
 *
 * Migrated from EQA_API.Models.v1.Portal.BCeIDModels (C#) - Pagination.
 * Represents pagination metadata and query parameters used across
 * BCeID portal search/listing endpoints.
 *
 * @property int $id
 * @property int $page_number
 * @property int $page_size
 * @property int $total_records
 * @property int $total_pages
 * @property string|null $sort_column
 * @property string|null $sort_direction
 * @property string|null $search_criteria
 * @property string|null $filter_column
 * @property string|null $filter_value
 * @property bool $has_previous_page
 * @property bool $has_next_page
 * @property bool $is_first_page
 * @property bool $is_last_page
 * @property int $first_row_on_page
 * @property int $last_row_on_page
 * @property string|null $entity_name
 * @property string|null $request_path
 * @property string|null $requested_by
 * @property \Illuminate\Support\Carbon|null $requested_at
 * @property string|null $order_by
 * @property string|null $order_direction
 * @property int $skip
 * @property int $take
 * @property bool $include_total_count
 * @property bool $include_inactive
 * @property string|null $status_filter
 * @property string|null $organization_filter
 * @property string|null $user_type_filter
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Pagination extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'paginations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'page_number',
        'page_size',
        'total_records',
        'total_pages',
        'sort_column',
        'sort_direction',
        'search_criteria',
        'filter_column',
        'filter_value',
        'has_previous_page',
        'has_next_page',
        'is_first_page',
        'is_last_page',
        'first_row_on_page',
        'last_row_on_page',
        'entity_name',
        'request_path',
        'requested_by',
        'requested_at',
        'order_by',
        'order_direction',
        'skip',
        'take',
        'include_total_count',
        'include_inactive',
        'status_filter',
        'organization_filter',
        'user_type_filter',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'page_number' => 'integer',
        'page_size' => 'integer',
        'total_records' => 'integer',
        'total_pages' => 'integer',
        'sort_column' => 'string',
        'sort_direction' => 'string',
        'search_criteria' => 'string',
        'filter_column' => 'string',
        'filter_value' => 'string',
        'has_previous_page' => 'boolean',
        'has_next_page' => 'boolean',
        'is_first_page' => 'boolean',
        'is_last_page' => 'boolean',
        'first_row_on_page' => 'integer',
        'last_row_on_page' => 'integer',
        'entity_name' => 'string',
        'request_path' => 'string',
        'requested_by' => 'string',
        'requested_at' => 'datetime',
        'order_by' => 'string',
        'order_direction' => 'string',
        'skip' => 'integer',
        'take' => 'integer',
        'include_total_count' => 'boolean',
        'include_inactive' => 'boolean',
        'status_filter' => 'string',
        'organization_filter' => 'string',
        'user_type_filter' => 'string',
        'notes' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'page_number' => 1,
        'page_size' => 10,
        'total_records' => 0,
        'total_pages' => 0,
        'has_previous_page' => false,
        'has_next_page' => false,
        'is_first_page' => true,
        'is_last_page' => true,
        'first_row_on_page' => 0,
        'last_row_on_page' => 0,
        'skip' => 0,
        'take' => 10,
        'include_total_count' => true,
        'include_inactive' => false,
    ];

    /**
     * Scope a query to apply the configured skip/take pagination window.
     */
    public function scopeApplyWindow(Builder $query): Builder
    {
        return $query->skip($this->skip)->take($this->take);
    }

    /**
     * Scope a query to apply the configured sort/order.
     */
    public function scopeApplySort(Builder $query): Builder
    {
        $column = $this->sort_column ?? $this->order_by;
        $direction = $this->sort_direction ?? $this->order_direction ?? 'asc';

        if ($column === null) {
            return $query;
        }

        return $query->orderBy($column, $direction);
    }

    /**
     * Determine whether more pages are available beyond the current page.
     */
    public function hasMorePages(): bool
    {
        return $this->page_number < $this->total_pages;
    }
}
</parameter>
</create>