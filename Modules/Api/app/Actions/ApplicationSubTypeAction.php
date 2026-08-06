<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Retrieves application sub type records.
 *
 * Source lineage: EQA API/SQL Queries/Portal/ApplicationSubType.sql (sql_query)
 * Underlying table: dbo.Filteredcatapult_applicationsubtype
 */
class ApplicationSubTypeAction
{
    public function __invoke(): \Illuminate\Support\Collection
    {
        return DB::table('Filteredcatapult_applicationsubtype')
            ->select('*')
            ->get();
    }
}