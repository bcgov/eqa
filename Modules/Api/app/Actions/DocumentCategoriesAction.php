<?php

declare(strict_types=1);

namespace Modules\Api\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrieves the list of document categories sourced from the legacy
 * Filteredcatapult_documentcategory query (EQA API/SQL Queries/Portal/DocumentCategories.sql).
 */
class DocumentCategoriesAction
{
    public function __invoke(): Collection
    {
        return DB::table('filtered_catapult_document_categories')
            ->select([
                'id',
                'name',
                'description',
                'is_active',
                'sort_order',
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}