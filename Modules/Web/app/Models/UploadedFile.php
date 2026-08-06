<?php

declare(strict_types=1);

namespace Modules\Web\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Web\Models\User;

/**
 * Eloquent model for an uploaded document/file record.
 *
 * Migrated from EQA_WEB.Models.UploadedFile (EQA WEB/Models/DocumentModels.cs).
 */
class UploadedFile extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'uploaded_files';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'file_name',
        'file_path',
        'content_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_by' => 'integer',
            'uploaded_at' => 'datetime',
            'description' => 'string',
        ];
    }

    /**
     * Get the user who uploaded this file.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}