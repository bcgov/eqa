'services' => [

    // NAV SOAP Endpoint (Codeunit WebRevenue) - migrated from EQA API/Web.Debug.config::service_endpoint::NAV_SOAP_Endpoint
    'nav_soap' => [
        'host' => env('NAV_SOAP_HOST', 'manjula'),
        'port' => env('NAV_SOAP_PORT', 7047),
        'instance' => env('NAV_SOAP_INSTANCE', 'NAV2013R2_DEV'),
        'company' => env('NAV_SOAP_COMPANY', 'PCTIA-DEV'),
        'codeunit' => env('NAV_SOAP_CODEUNIT', 'WebRevenue'),
        'address' => env('NAV_SOAP_ADDRESS', 'http://manjula:7047/NAV2013R2_DEV/Ws/PCTIA-DEV/Codeunit/WebRevenue'),
        'username' => env('NAV_SOAP_USERNAME'),
        'password' => env('NAV_SOAP_PASSWORD'),
        'domain' => env('NAV_SOAP_DOMAIN'),
        'timeout' => env('NAV_SOAP_TIMEOUT', 30),
        'verify_ssl' => env('NAV_SOAP_VERIFY_SSL', true),
    ],

],

NAV_SOAP_HOST=manjula
NAV_SOAP_PORT=7047
NAV_SOAP_INSTANCE=NAV2013R2_DEV
NAV_SOAP_COMPANY=PCTIA-DEV
NAV_SOAP_CODEUNIT=WebRevenue
NAV_SOAP_ADDRESS=http://manjula:7047/NAV2013R2_DEV/Ws/PCTIA-DEV/Codeunit/WebRevenue
NAV_SOAP_USERNAME=
NAV_SOAP_PASSWORD=
NAV_SOAP_DOMAIN=
NAV_SOAP_TIMEOUT=30
NAV_SOAP_VERIFY_SSL=true