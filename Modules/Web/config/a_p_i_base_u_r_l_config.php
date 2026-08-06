'services' => [

    // Source lineage: EQA WEB/Web.config::service_endpoint::API_Base_URL
    'eqa_api' => [
        'base_url' => env('EQA_API_BASE_URL', 'http://manjula.idir.bcgov:8082'),
    ],

],

# .env
EQA_API_BASE_URL=http://manjula.idir.bcgov:8082