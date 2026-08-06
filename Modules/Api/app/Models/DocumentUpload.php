<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentUpload extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'document_uploads';

    protected $fillable = [
        'document_id',
        'portal_id',
        'user_id',
        'file_name',
        'original_file_name',
        'file_path',
        'file_extension',
        'content_type',
        'file_size',
        'category',
        'description',
        'is_active',
        'is_processed',
        'processed_at',
        'checksum',
        'uploaded_by',
        'uploaded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'portal_id' => 'integer',
            'user_id' => 'integer',
            'file_size' => 'integer',
            'is_active' => 'boolean',
            'is_processed' => 'boolean',
            'processed_at' => 'datetime',
            'uploaded_by' => 'integer',
            'uploaded_at' => 'datetime',
            'metadata' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\User::class, 'user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(\Modules\Api\Models\User::class, 'uploaded_by');
    }
}