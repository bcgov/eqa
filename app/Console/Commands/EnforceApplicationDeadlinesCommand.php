<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Admin\Services\ApplicationFeeService;

class EnforceApplicationDeadlinesCommand extends Command
{
    protected $signature = 'eqa:enforce-deadlines';

    protected $description = 'Expire applications whose admin fee or appeal fee is past due; flag missed resubmission deadlines for the ministry to confirm; de-designate lapsed designations.';

    public function handle(ApplicationFeeService $fees): int
    {
        $counts = $fees->enforceDeadlines();

        $this->info(sprintf(
            'Deadlines enforced — admin fee expired: %d, change requests awaiting expiry confirmation: %d, appeals lapsed: %d, designations lapsed: %d.',
            $counts['admin_fee'],
            $counts['changes'],
            $counts['appeal'],
            $counts['lapsed'],
        ));

        return self::SUCCESS;
    }
}
