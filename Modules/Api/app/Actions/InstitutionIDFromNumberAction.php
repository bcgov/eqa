<?php

namespace Modules\Api\Actions;

use Illuminate\Support\Facades\DB;

class InstitutionIDFromNumberAction
{
    /**
     * Resolve the Institution ID for a given account number by querying the
     * FilteredAccount table.
     *
     * @param string $accountNumber
     * @return int|null
     */
    public function __invoke(string $accountNumber): ?int
    {
        $result = DB::table('FilteredAccount')
            ->select('InstitutionID')
            ->where('AccountNumber', $accountNumber)
            ->first();

        if ($result === null) {
            return null;
        }

        return (int) $result->InstitutionID;
    }
}