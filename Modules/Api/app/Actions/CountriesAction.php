<?php

declare(strict_types=1);

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Retrieves the list of countries.
 *
 * Source lineage: EQA API/SQL Queries/Portal/Countries.sql (SELECT against Filteredcatapult_country).
 */
final class CountriesAction
{
    public function __invoke(): Collection
    {
        return DB::table('catapult_country')
            ->select([
                'catapult_countryid',
                'catapult_name',
                'catapult_code',
                'statecode',
                'statuscode',
            ])
            ->where('statecode', 0)
            ->orderBy('catapult_name')
            ->get();
    }
}