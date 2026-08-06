'connections' => [

    'crm_sqlsrv' => [
        'driver' => 'sqlsrv',
        'host' => env('CRM_DB_HOST', 'MANJULA\\CRM_NAV_TEST_UAT'),
        'port' => env('CRM_DB_PORT', '9090'),
        'database' => env('CRM_DB_DATABASE', 'EQATEST_MSCRM'),
        'username' => env('CRM_DB_USERNAME', ''),
        'password' => env('CRM_DB_PASSWORD', ''),
        'charset' => env('CRM_DB_CHARSET', 'utf8'),
        'prefix' => '',
        'prefix_indexes' => true,
        'encrypt' => env('CRM_DB_ENCRYPT', 'yes'),
        'trust_server_certificate' => env('CRM_DB_TRUST_SERVER_CERTIFICATE', 'false'),
    ],

],

CRM_DB_HOST=MANJULA\CRM_NAV_TEST_UAT
CRM_DB_PORT=9090
CRM_DB_DATABASE=EQATEST_MSCRM
CRM_DB_USERNAME=
CRM_DB_PASSWORD=
CRM_DB_CHARSET=utf8
CRM_DB_ENCRYPT=yes
CRM_DB_TRUST_SERVER_CERTIFICATE=false