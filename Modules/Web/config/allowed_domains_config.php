'services' => [

    // ...

    'allowed_domains' => [
        'domains' => array_filter(array_map('trim', explode(',', env('ALLOWED_DOMAINS', '')))),
    ],

],