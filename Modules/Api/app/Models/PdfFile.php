<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Source lineage: EQADM/pdf_files (sql_schema/table)
 *
 * @property string|null $id
 * @property string|null $fileContent
 */
class PdfFile extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pdf_files';

    /**
     * The primary key associated with the table.
     *
     * @var string|null
     */
    protected $primaryKey = null;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'fileContent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'fileContent' => 'string',
        ];
    }
}