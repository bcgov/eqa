<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the list of provinces.
 *
 * Migrated from: EQA API/SQL Queries/Portal/Provinces.sql
 * Source table: Filteredcatapult_province
 */
class ProvincesAction
{
    /**
     * Execute the action.
     */
    public function __invoke(): Collection
    {
        return DB::table('catapult_province')
            ->select([
                'catapult_provinceid',
                'catapult_name',
                'catapult_code',
            ])
            ->orderBy('catapult_name')
            ->get();
    }
}