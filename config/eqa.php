<?php

// EQA fees and deadlines for the payment / appeal workflow.
// PLACEHOLDER values until the ministry confirms the real amounts and windows.
return [

    'fees' => [
        'application_new' => (float) env('EQA_FEE_APPLICATION_NEW', 1000),
        'application_renewal' => (float) env('EQA_FEE_APPLICATION_RENEWAL', 500),
        'appeal' => (float) env('EQA_FEE_APPEAL', 250),
        // Suggested default; the ministry enters the actual admin variable fee at approval.
        'admin_variable' => (float) env('EQA_FEE_ADMIN_VARIABLE', 500),
    ],

    'deadlines' => [
        'admin_fee_days' => (int) env('EQA_ADMIN_FEE_DAYS', 30),
        'appeal_days' => (int) env('EQA_APPEAL_DAYS', 30),
        'changes_days' => (int) env('EQA_CHANGES_DAYS', 30),
    ],

];
