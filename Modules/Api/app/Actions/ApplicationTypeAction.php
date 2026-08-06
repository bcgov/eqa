<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Retrieves application type records sourced from the legacy
 * dbo.Filteredcatapult_applicationtype SQL query
 * (EQA API/SQL Queries/Portal/ApplicationType.sql).
 */
class ApplicationTypeAction
{
    public function __invoke(): \Illuminate\Support\Collection
    {
        return DB::table('catapult_applicationtype')
            ->select([
                'catapult_applicationtypeid',
                'catapult_name',
                'statecode',
                'statuscode',
            ])
            ->where('statecode', 0)
            ->orderBy('catapult_name')
            ->get();
    }
}