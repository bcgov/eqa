'intake_form_db' => [
    'driver' => 'sqlsrv',
    'host' => env('INTAKE_FORM_DB_HOST', 'MANJULA\\CRM_NAV_TEST_UAT'),
    'port' => env('INTAKE_FORM_DB_PORT', '9090'),
    'database' => env('INTAKE_FORM_DB_DATABASE', 'INTAKE_FORM_DEV'),
    'username' => env('INTAKE_FORM_DB_USERNAME', ''),
    'password' => env('INTAKE_FORM_DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'encrypt' => env('INTAKE_FORM_DB_ENCRYPT', 'yes'),
    'trust_server_certificate' => env('INTAKE_FORM_DB_TRUST_SERVER_CERTIFICATE', 'true'),
],

INTAKE_FORM_DB_HOST=MANJULA\CRM_NAV_TEST_UAT
INTAKE_FORM_DB_PORT=9090
INTAKE_FORM_DB_DATABASE=INTAKE_FORM_DEV
INTAKE_FORM_DB_USERNAME=
INTAKE_FORM_DB_PASSWORD=
INTAKE_FORM_DB_ENCRYPT=yes
INTAKE_FORM_DB_TRUST_SERVER_CERTIFICATE=true