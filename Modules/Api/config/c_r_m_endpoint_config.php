'services' => [

    // ...

    'crm_endpoint' => [
        'address' => env('CRM_ENDPOINT_ADDRESS', 'http://manjula.idir.bcgov:8080/EQATEST/XRMServices/2011/Organization.svc'),
        'binding' => env('CRM_ENDPOINT_BINDING'),
        'contract' => env('CRM_ENDPOINT_CONTRACT'),
        'username' => env('CRM_ENDPOINT_USERNAME'),
        'password' => env('CRM_ENDPOINT_PASSWORD'),
        'timeout' => env('CRM_ENDPOINT_TIMEOUT', 30),
    ],

],

CRM_ENDPOINT_ADDRESS=http://manjula.idir.bcgov:8080/EQATEST/XRMServices/2011/Organization.svc
CRM_ENDPOINT_BINDING=
CRM_ENDPOINT_CONTRACT=
CRM_ENDPOINT_USERNAME=
CRM_ENDPOINT_PASSWORD=
CRM_ENDPOINT_TIMEOUT=30