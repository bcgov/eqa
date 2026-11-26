<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Admin\Services\ApplicationFeeService;

class EnforceApplicationDeadlinesCommand extends Command
{
    protected $signature = 'eqa:enforce-deadlines';

    protected $description = 'Expire applications whose admin fee or appeal fee is past due, or that were not submitted and paid by their completion deadline; send completion reminders; flag missed resubmission deadlines for the ministry to confirm; de-designate lapsed designations.';

    public function handle(ApplicationFeeService $fees): int
    {
        $counts = $fees->enforceDeadlines();

        $this->info(sprintf(
            'Deadlines enforced — admin fee expired: %d, change requests awaiting expiry confirmation: %d, appeals lapsed: %d, designations lapsed: %d, incomplete applications expired: %d, completion reminders sent: %d.',
            $counts['admin_fee'],
            $counts['changes'],
            $counts['appeal'],
            $counts['lapsed'],
            $counts['incomplete'],
            $counts['reminders'],
        ));

        return self::SUCCESS;
    }
}
