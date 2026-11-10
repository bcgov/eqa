<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Admin\Services\ApplicationFeeService;

class EnforceApplicationDeadlinesCommand extends Command
{
    protected $signature = 'eqa:enforce-deadlines';

    protected $description = 'Expire applications whose admin fee, requested changes or appeal fee are past due.';

    public function handle(ApplicationFeeService $fees): int
    {
        $counts = $fees->enforceDeadlines();

        $this->info(sprintf(
            'Deadlines enforced — admin fee expired: %d, change requests expired: %d, appeals lapsed: %d.',
            $counts['admin_fee'],
            $counts['changes'],
            $counts['appeal'],
        ));

        return self::SUCCESS;
    }
}
