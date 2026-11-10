<?php

// EQA fees and deadlines for the payment / appeal workflow.
// Deadlines are PLACEHOLDER values until the ministry confirms the windows.
return [

    'fees' => [
        'application_new' => (float) env('APPLICATION_NEW_FEE', 7500),
        'application_renewal' => (float) env('APPLICATION_RENEWAL_FEE', 5500),
        'appeal' => (float) env('APPLICATION_APPEAL_FEE', 250),
        // Administrative fee components: private institutions with more than one active location, plus per international student.
        'multi_location' => (float) env('APPLICATION_MULTI_LOCATION_FEE', 3000),
        'per_international_student' => (float) env('APPLICATION_PER_INTERNATIONAL_STUDENT_FEE', 2),
    ],

    'deadlines' => [
        'admin_fee_days' => (int) env('EQA_ADMIN_FEE_DAYS', 30),
        'appeal_days' => (int) env('EQA_APPEAL_DAYS', 30),
        'changes_days' => (int) env('EQA_CHANGES_DAYS', 30),
    ],

];
