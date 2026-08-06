'pgsql_eqa_tracking' => [
    'driver' => 'pgsql',
    'host' => env('DB_EQA_TRACKING_HOST', 'MANJULA.idir.bcgov'),
    'port' => env('DB_EQA_TRACKING_PORT', '5432'),
    'database' => env('DB_EQA_TRACKING_DATABASE', 'EQA_TRACKING_UAT'),
    'username' => env('DB_EQA_TRACKING_USERNAME'),
    'password' => env('DB_EQA_TRACKING_PASSWORD'),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => env('DB_EQA_TRACKING_SSLMODE', 'prefer'),
],

DB_EQA_TRACKING_HOST=MANJULA.idir.bcgov
DB_EQA_TRACKING_PORT=5432
DB_EQA_TRACKING_DATABASE=EQA_TRACKING_UAT
DB_EQA_TRACKING_USERNAME=
DB_EQA_TRACKING_PASSWORD=
DB_EQA_TRACKING_SSLMODE=prefer