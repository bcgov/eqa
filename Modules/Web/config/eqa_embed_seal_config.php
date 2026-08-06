'services' => [

    // EQA Web Embed Seal verification service (migrated from EQA WEB/Web.config::service_endpoint::EqaEmbedSeal)
    'eqa_embed_seal' => [
        'base_url' => env('EQA_EMBED_SEAL_BASE_URL', 'https://admin.bceqa.gov.bc.ca/apply/VerifySeal'),
        'crm_id_param' => env('EQA_EMBED_SEAL_CRM_ID_PARAM', 'crmId'),
    ],

],

EQA_EMBED_SEAL_BASE_URL=https://admin.bceqa.gov.bc.ca/apply/VerifySeal
EQA_EMBED_SEAL_CRM_ID_PARAM=crmId