'services' => [
    // ...
    'xrm_context' => [
        'server' => env('XRM_CONTEXT_SERVER', 'http://manjula.idir.bcgov:8080/EQATEST'),
        'domain' => env('XRM_CONTEXT_DOMAIN', 'idir'),
        'username' => env('XRM_CONTEXT_USERNAME'),
        'password' => env('XRM_CONTEXT_PASSWORD'),
    ],
],

XRM_CONTEXT_SERVER=http://manjula.idir.bcgov:8080/EQATEST
XRM_CONTEXT_DOMAIN=idir
XRM_CONTEXT_USERNAME=EQAUSER
XRM_CONTEXT_PASSWORD=