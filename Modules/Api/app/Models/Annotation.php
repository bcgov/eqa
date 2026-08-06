<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * Annotation
 *
 * Source lineage: EQADEV_MSCRM/AnnotationBase (sql_schema:table)
 */
class Annotation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'annotations';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'annotation_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_on';

    /**
     * The name of the "updated at" column.
     */
    const UPDATED_AT = 'modified_on';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'annotation_id',
        'object_type_code',
        'object_id',
        'owning_business_unit',
        'subject',
        'is_document',
        'note_text',
        'mime_type',
        'lang_id',
        'document_body',
        'file_size',
        'file_name',
        'created_by',
        'is_private',
        'modified_by',
        'step_id',
        'overridden_created_on',
        'import_sequence_number',
        'created_on_behalf_by',
        'owner_id',
        'modified_on_behalf_by',
        'owner_id_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'document_body',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annotation_id' => 'string',
            'object_type_code' => 'integer',
            'object_id' => 'string',
            'owning_business_unit' => 'string',
            'subject' => 'string',
            'is_document' => 'boolean',
            'note_text' => 'string',
            'mime_type' => 'string',
            'lang_id' => 'string',
            'document_body' => 'string',
            'created_on' => 'datetime',
            'file_size' => 'integer',
            'file_name' => 'string',
            'created_by' => 'string',
            'is_private' => 'boolean',
            'modified_by' => 'string',
            'modified_on' => 'datetime',
            'version_number' => 'integer',
            'step_id' => 'string',
            'overridden_created_on' => 'datetime',
            'import_sequence_number' => 'integer',
            'created_on_behalf_by' => 'string',
            'owner_id' => 'string',
            'modified_on_behalf_by' => 'string',
            'owner_id_type' => 'integer',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Uuid::uuid4();
            }
        });