'services.php' => [
    'crm_endpoint' => [
        'address' => env('CRM_ENDPOINT_ADDRESS', 'http://142.34.167.119:8080/EQADEV/XRMServices/2011/Organization.svc'),
        'binding' => env('CRM_ENDPOINT_BINDING'),
        'contract' => env('CRM_ENDPOINT_CONTRACT'),
        'username' => env('CRM_ENDPOINT_USERNAME'),
        'password' => env('CRM_ENDPOINT_PASSWORD'),
        'timeout' => env('CRM_ENDPOINT_TIMEOUT', 30),
        'verify' => env('CRM_ENDPOINT_VERIFY_SSL', true),
    ],
],

CRM_ENDPOINT_ADDRESS=http://142.34.167.119:8080/EQADEV/XRMServices/2011/Organization.svc
CRM_ENDPOINT_BINDING=
CRM_ENDPOINT_CONTRACT=
CRM_ENDPOINT_USERNAME=
CRM_ENDPOINT_PASSWORD=
CRM_ENDPOINT_TIMEOUT=30
CRM_ENDPOINT_VERIFY_SSL=true