<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;

class InstitutionNumberFromIDAction
{
    /**
     * Retrieve the institution number for a given account ID.
     *
     * Source lineage: EQA API/SQL Queries/Portal/InstitutionNumberFromID.sql
     *
     * @param  int|string  $accountId
     * @return string|null
     */
    public function __invoke(int|string $accountId): ?string
    {
        $result = DB::table('FilteredAccount')
            ->select('InstitutionNumber')
            ->where('AccountID', $accountId)
            ->first();

        return $result?->InstitutionNumber;
    }
}