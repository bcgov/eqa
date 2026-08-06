<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Retrieves document types joined with their document categories.
 *
 * Source lineage: EQA API/SQL Queries/Portal/DocumentTypes.sql (sql_query)
 * Tables: Filteredcatapult_documenttype, Filteredcatapult_documentcategory
 */
class DocumentTypesAction
{
    /**
     * Execute the action.
     *
     * @return \Illuminate\Support\Collection
     */
    public function __invoke(): \Illuminate\Support\Collection
    {
        return DB::table('Filteredcatapult_documenttype as documenttype')
            ->leftJoin(
                'Filteredcatapult_documentcategory as documentcategory',
                'documenttype.catapult_documentcategoryid',
                '=',
                'documentcategory.catapult_documentcategoryid'
            )
            ->select([
                'documenttype.catapult_documenttypeid',
                'documenttype.catapult_name as document_type_name',
                'documenttype.statecode',
                'documenttype.statuscode',
                'documentcategory.catapult_documentcategoryid',
                'documentcategory.catapult_name as document_category_name',
            ])
            ->where('documenttype.statecode', 0)
            ->orderBy('documentcategory.catapult_name')
            ->orderBy('documenttype.catapult_name')
            ->get();
    }
}