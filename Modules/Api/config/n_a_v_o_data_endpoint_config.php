'services' => [
    // Source lineage: EQA API/Web.Debug.config::service_endpoint::NAV_OData_Endpoint (NAV_OData_Endpoint)
    'nav_odata' => [
        'base_uri' => env('NAV_ODATA_BASE_URI'),
        'company' => env('NAV_ODATA_COMPANY'),
        'username' => env('NAV_ODATA_USERNAME'),
        'password' => env('NAV_ODATA_PASSWORD'),
    ],
],

NAV_ODATA_BASE_URI=http://manjula:7048/NAV2013R2_DEV/OData
NAV_ODATA_COMPANY=PCTIA-DEV
NAV_ODATA_USERNAME=
NAV_ODATA_PASSWORD=