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
        'receipt_title' => 'RECEIPT',
        'receipt_message' => 'Thank you. Your payment for invoice {invoice_number} has been received. Please keep this receipt for your records.',
    ],

    // "?" help bubbles on the application and institution profile forms (defaults; editable under
    // Maintenance > Utils, category Application Help Text).
    'help' => [
        'total_enrolment' => 'Domestic and international students over the last 12 months. For publics use FTE, for privates use headcount. PTIs: this is students in approved programs only.',
        'intl_students_permit' => 'International Students are students who are studying in approved programs at your institution in Canada on a study permit.',
        'in_person_students' => 'Students are deemed in-person if they are at the same physical location as the instructor for a minimum of 50% of instructional hours. Work Experience is not considered to be instructional hours.',
        'online_students' => 'Students are deemed to be online if they are not in the same physical location as the instructor for more than 50% of instructional hours. Work Experience is not considered to be instructional hours.',
        'per_student_fee_enrolment' => 'Should your application be approved, as per the regulations section 8(1)(a) you must pay $2 for each international student. The number of international students on study permits enroled in programs of instruction at the institution at any time during the calendar year that precedes the date the institution applies for the issuance or renewal of a designation certificate. Ie. If your application deadline is April 2026, provide the number of enroled students from Jan 1 2025-December 31 2025.',
    ],

];
