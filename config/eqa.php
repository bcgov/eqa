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

    // Ministry / program contact details shown around the application (invoices, footer).
    // Defaults only: Ministry Admins can change them under Maintenance > Utils.
    'ministry' => [
        'name' => env('EQA_MINISTRY_NAME', 'Ministry of Post-Secondary Education and Future Skills'),
        'branch' => env('EQA_BRANCH_NAME', 'Governance, Quality Assurance and Strategic Policy Branch'),
        'program' => env('EQA_PROGRAM_NAME', 'Education Quality Assurance'),
        'address' => env('EQA_MAILING_ADDRESS', "3rd Floor, 835 Humboldt St\nPO Box 9157 Stn Prov Govt\nVictoria BC V8W 9H2"),
        'email' => env('EQA_CONTACT_EMAIL', 'EQA@gov.bc.ca'),
        'privacy_email' => env('EQA_PRIVACY_EMAIL', 'PSFS.InformationPrivacy@gov.bc.ca'),
    ],

    // Invoice PDF wording (defaults; editable under Maintenance > Invoice). Supports the
    // placeholders listed in App\Services\InvoicePdf::PLACEHOLDERS, e.g. {invoice_number}.
    'invoice' => [
        'title' => 'INVOICE',
        'payment_online' => 'Online: sign in to the EQA portal, open Invoices and select Pay Online to pay securely by credit card through BC Express Pay.',
        'payment_offline' => 'EFT or cheque: contact {contact_email} for payment instructions. Quote invoice number {invoice_number} with your payment.',
        'due_text' => 'Payment is due by {due_date}.',
        'paid_message' => 'Paid — thank you',
        'notes' => '',
        'footer' => '{program_name} · {contact_email} · Invoice {invoice_number}',
    ],

];
