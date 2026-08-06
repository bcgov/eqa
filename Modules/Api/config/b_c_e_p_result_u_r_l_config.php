'services.php' => [
    // ... existing services

    'bcep' => [
        'result_url' => env('BCEP_RESULT_URL', 'https://www.beanstream.com/scripts/process_transaction.asp'),
    ],
],

// .env
BCEP_RESULT_URL=https://www.beanstream.com/scripts/process_transaction.asp