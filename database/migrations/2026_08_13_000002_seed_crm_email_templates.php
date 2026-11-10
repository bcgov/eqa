<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Seeds the REAL EQA notification email templates recovered from the legacy
// Dynamics CRM backup (EQADEV_MSCRM-20260731.bak). These supersede the initial
// best-guess seeds: the bodies/subjects are the actual CRM content (composed by
// the EQA workflows), with the personalised values turned into {{placeholders}}.
// Idempotent upsert by key; additive and NOT owned by the DynamicsDataMigrator.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        foreach ($this->templates() as $tpl) {
            $existing = DB::table('email_templates')->where('key', $tpl['key'])->first();
            $payload = array_merge($tpl, ['updated_at' => now()]);

            if ($existing === null) {
                DB::table('email_templates')->insert(array_merge($payload, [
                    'is_active' => true,
                    'created_at' => now(),
                ]));
            } else {
                // Refresh the migrated content; preserve the admin's active flag.
                DB::table('email_templates')->where('key', $tpl['key'])->update($payload);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: leave the templates in place on rollback.
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function templates(): array
    {
        $eqa = 'EQA@gov.bc.ca';

        $signature =
            '<p>Sincerely,</p>'.
            '<p><b><i>Education Quality Assurance</i></b><br>'.
            '<b><i>Governance, Quality Assurance and Strategic Policy Branch</i></b><br>'.
            '<b><i>Ministry of Advanced Education, Skills and Training</i></b></p>'.
            '<p>3rd Floor, 835 Humboldt St<br>'.
            'PO Box 9157 Stn Prov Govt<br>'.
            'Victoria BC V8W 9H2<br>'.
            'Email: <a href="mailto:EQA@gov.bc.ca">EQA@gov.bc.ca</a></p>';

        return [
            // 1. Annual reapplication received (the "Application Submission Confirmation" workflow).
            [
                'key' => 'reapplication_submission',
                'name' => 'Application — Reapplication Submission Received',
                'category' => 'Application Confirmation',
                'subject' => 'EQA Reapplication Submission | {{institution_name}}',
                'recipients' => $eqa,
                'description' => 'Acknowledges receipt of an annual EQA Designation reapplication. Recovered from the "Application Submission Confirmation" CRM workflow.',
                'variables' => 'contact_name, institution_name, order_receipt',
                'body' =>
                    '<p>Dear {{contact_name}}</p>'.
                    "<p>Thank you for your submission to reapply for your institution's Education Quality Assurance (EQA) Designation. Your EQA Annual Designation reapplication has been received and will be reviewed by the Ministry.</p>".
                    '<p>EQA Annual Designation reapplications will be processed in the order received. If the reapplication is missing required information, EQA staff will return the submission to the institution with a written explanation of what was missing or incomplete.</p>'.
                    '<p>If you need to follow up on your submission, please refer to order receipt {{order_receipt}}.</p>'.
                    '<ul>'.
                    '<li>Institutions that continue to meet EQA eligibility requirements will receive an official EQA Designation Confirmation Letter granting their institution permission, on an annual basis, to retain their EQA Designation and use of the EQA brand.</li>'.
                    '<li>Institutions that have failed to maintain EQA Annual Designation eligibility requirements will be notified in writing of the eligibility deficiency by EQA staff.</li>'.
                    '</ul>'.
                    '<p>Thank you again for your continued interest and participation in the provincial Education Quality Assurance Designation. Should your institution have any questions about your submission, please do not hesitate to contact our office.</p>'.
                    $signature,
            ],

            // 2. New (first-time) application received.
            [
                'key' => 'application_submission',
                'name' => 'Application — Submission Received (New)',
                'category' => 'Application Confirmation',
                'subject' => 'EQA Application Submission | {{institution_name}}',
                'recipients' => $eqa,
                'description' => 'Acknowledges receipt of a new EQA Designation application (order receipt attached). Recovered from CRM.',
                'variables' => 'contact_name, institution_name, order_reference',
                'body' =>
                    '<p>Dear {{contact_name}}</p>'.
                    '<p>Thank you for your application for EQA. Please note that your application is currently under review by the Ministry. You will receive an official notification once a decision has been made. If you need to follow up on your application, please refer to order reference {{order_reference}}. A copy of your order receipt is attached for your reference.</p>'.
                    '<p>EQA applications will be processed in the order received. If the application is missing required information, EQA staff will return the submission to the institution with a written explanation of what is missing or incomplete. Review of EQA applications will include contact with applicable provincial quality assurance body(ies) to ensure all eligibility requirements have been met and your institution is in good standing.</p>'.
                    '<p>Institutions will be informed of the decision regarding their application in writing once the Ministry completes its review.</p>'.
                    '<p>Thank you again for your application for EQA Designation. Should your institution have any questions about your application for the EQA Designation, please do not hesitate to contact our office.</p>'.
                    $signature,
            ],

            // 3. Application approved (the "Application Approval Notification" workflow).
            //    Keeps the key the ApplicationWorkflowController already sends on approval.
            [
                'key' => 'application_approved',
                'name' => 'Application — EQA Designation Approved (Letter)',
                'category' => 'Application Decision',
                'subject' => 'EQA Designation Application | {{institution_name}}',
                'recipients' => $eqa,
                'description' => 'Formal approval letter confirming EQA designation for the designation year. Recovered from the "Application Approval Notification" CRM workflow.',
                'variables' => 'contact_name, institution_name, application_reference, designation_expiry',
                'body' =>
                    '<p>File #: {{application_reference}}</p>'.
                    '<p>Dear {{contact_name}}:</p>'.
                    "<p>I am writing regarding {{institution_name}}'s application for Education Quality Assurance (EQA) designation in British Columbia. In order to ensure that the integrity of the EQA brand is maintained, the Ministry conducts thorough reviews of all institutions as outlined in the EQA Policy and Procedures Manual.</p>".
                    '<p>I am happy to inform you that <b>{{institution_name}}</b> has been approved for EQA designation for the designation year ending <b>{{designation_expiry}}</b>.</p>'.
                    '<p>Please note that the institutions are advised to stay up to date with the requirements for maintaining EQA designation status and related responsibilities. For current information, please refer to the EQA Policy and Procedures Manual which is updated regularly and available online at:</p>'.
                    '<p><a href="http://www2.gov.bc.ca/gov/content/education-training/post-secondary-education/institution-resources-administration/education-quality-assurance">http://www2.gov.bc.ca/gov/content/education-training/post-secondary-education/institution-resources-administration/education-quality-assurance</a></p>'.
                    '<p><b><i>Education Quality Assurance</i></b><br>'.
                    '<b><i>Governance, Quality Assurance and Strategic Policy Branch</i></b><br>'.
                    '<b><i>Ministry of Advanced Education, Skills and Training</i></b></p>'.
                    '<p>3rd Floor, 835 Humboldt St<br>PO Box 9157 Stn Prov Govt<br>Victoria BC V8W 9H2<br>Email: <a href="mailto:EQA@gov.bc.ca">EQA@gov.bc.ca</a></p>',
            ],

            // 4. Annual reapplication required (the Renewal Notification workflows).
            [
                'key' => 'reapplication_required',
                'name' => 'Renewal — Annual Reapplication Required',
                'category' => 'Renewal Reminder',
                'subject' => 'EQA Reapplication Required | {{institution_name}}',
                'recipients' => $eqa,
                'description' => 'Reminder that the EQA designation is expiring and the institution must reapply. Recovered from the "Renewal Notification" CRM workflows (also sent at 90 / 30 days).',
                'variables' => 'contact_name, institution_name, designation_expiry',
                'body' =>
                    '<p>Dear {{contact_name}},</p>'.
                    "<p>Your institution's current Education Quality Assurance (EQA) Designation expires on <b>{{designation_expiry}}</b>. The application requirements and process are outlined in the <a href=\"http://www2.gov.bc.ca/gov/content/education-training/post-secondary-education/institution-resources-administration/education-quality-assurance\"><i>EQA Policy and Procedures Manual</i></a>.</p>".
                    '<p>Institutions are required to have EQA designation in order to be placed on the Designated Learning Institution ("DLI") list maintained by Immigration, Refugees and Citizenship Canada. Institutions on the DLI list may host international students on study permits.</p>'.
                    '<p>To re-apply for EQA Designation, your institution is required to:</p>'.
                    '<ul>'.
                    '<li>Confirm that you have read and understood the EQA Policy and Procedures Manual;</li>'.
                    "<li>Confirm that the institution's website is in compliance with the EQA Policy and Procedures Manual and that all policies listed in the Manual are readily available to the public on the website;</li>".
                    '<li>Confirm that the institution is currently operating and continuously delivering at least one educational program to students in British Columbia for at least 8 months of the year;</li>'.
                    '<li>Ensure your institution is in good standing and active registration with the Corporate Registry (if applicable);</li>'.
                    '<li>If applicable, ensure compliance with the <a href="http://www.privatetraininginstitutions.gov.bc.ca/sites/www.privatetraininginstitutions.gov.bc.ca/files/files/policy-manual.pdf"><i>Private Training Act</i> Policy Manual</a>;</li>'.
                    "<li>Update your Institutional Profile in the EQA online system. Please ensure that you review and update your institution's corporate registry details (corporate name, incorporation number, legal name of all owners, etc.), EQA user(s), campus location(s) and web address(es);</li>".
                    '<li>Understand and agree to the requirements outlined in the <i>EQA Policy and Procedures Manual</i>, the <i>Representation Requirements</i> and <i>EQA Certification Mark Terms of Use</i> Agreement (indicated when you type your name and hit "submit application" on the final screen for online renewal); and,</li>'.
                    '<li>Remit the EQA Designation Fee (if applicable).</li>'.
                    '</ul>'.
                    '<p>The Ministry will process applications in the order in which they are received. Application processing times cannot be predicted due to high volumes during the re-application period. However, institutions that already have EQA will maintain their designation until a decision is made on their application.</p>'.
                    '<p>Institutions that do <u>not</u> re-apply will let their EQA Designation lapse and will receive written confirmation indicating that they are no longer EQA-designated and must discontinue use of the EQA brand.</p>'.
                    '<p style="background:#000;color:#fff;padding:2px 6px;"><b>INSTRUCTIONS</b></p>'.
                    '<p>To re-apply, please log onto the EQA online system at <a href="http://admin.bceqa.gov.bc.ca/">http://admin.bceqa.gov.bc.ca</a></p>'.
                    "<p>Your institution's EQA User ID will be the Business BCeID assigned to your EQA contact. If you have forgotten your BCeID User ID or password, please click \"Forgot your User ID or Password\" and follow the instructions. All inquiries regarding the BCeID must be directed to the Help Desk.</p>".
                    '<p>Once logged onto the EQA online system, please click the "Applications" button at the top. You will be able to view all your previous EQA applications.</p>'.
                    '<p>To start a new application, select the "New Application" button at the bottom and fill-in/update the applicable fields and follow the application steps.</p>'.
                    '<p>Once the application is submitted, the Ministry will assess eligibility, good standing, designation suitability and determine whether the institution is in compliance with the EQA Designation Requirements. Institutions will be informed of the decision in writing once the review is complete.</p>'.
                    '<p>Should your institution have any questions about your submission, please do not hesitate to contact our office.</p>'.
                    $signature,
            ],

            // 5. Portal welcome / authorization (the "Portal Profile: Send Authorization Email" workflow).
            [
                'key' => 'portal_welcome',
                'name' => 'Portal — Welcome / Authorization',
                'category' => 'Portal',
                'subject' => 'Welcome to the Ministry of Advanced Education, Skills and Training Portal',
                'recipients' => $eqa,
                'description' => 'Sent to a new portal user granting access to the EQA portal. Recovered from the "Portal Profile: Send Authorization Email" CRM workflow.',
                'variables' => 'contact_name',
                'body' =>
                    '<p><strong>PLEASE DO NOT RESPOND TO THIS EMAIL; IT IS AUTO GENERATED FROM THE EDUCATION QUALITY ASSURANCE PORTAL.</strong></p>'.
                    '<p>Dear {{contact_name}},</p>'.
                    '<p>Welcome to the Ministry of Advanced Education, Skills and Training Education Quality Assurance Portal.</p>'.
                    '<p>Please login at <a href="https://admin.bceqa.gov.bc.ca/">https://admin.bceqa.gov.bc.ca</a></p>'.
                    '<p>Thank you,</p>'.
                    '<p><strong><em>Education Quality Assurance<br>Ministry of Advanced Education, Skills and Training</em></strong></p>'.
                    '<p>Email: <a href="mailto:EQA@gov.bc.ca">EQA@gov.bc.ca</a></p>',
            ],

            // 6. New portal administrator notice.
            [
                'key' => 'portal_new_admin',
                'name' => 'Portal — New Administrator',
                'category' => 'Portal',
                'subject' => 'New Portal Administrator | {{institution_name}}',
                'recipients' => $eqa,
                'description' => 'Notifies that a new portal administrator has been assigned for an institution. Recovered from CRM.',
                'variables' => 'institution_name, first_name, last_name',
                'body' =>
                    '<p>Institution: {{institution_name}}</p>'.
                    '<p>{{first_name}} {{last_name}} is a new administrator</p>',
            ],

            // 7. PTIB certificate expiry (legacy; DB column retained though removed from the UI).
            [
                'key' => 'ptib_cert_expiry',
                'name' => 'Notification — PTIB Certificate Expiry',
                'category' => 'Notification',
                'subject' => 'PTIB Certificate Expiry | {{institution_name}}',
                'recipients' => $eqa,
                'description' => 'Automatic notice that an institution\'s PTIB certificate expiry date has been reached. Recovered from the "PTIB Certificate Expiry Notification" CRM workflow.',
                'variables' => 'institution_name, expiry_date',
                'body' =>
                    '<p><u>This is an automatic EQA notification</u></p>'.
                    '<p>Please note that PTIB certificate expiry date of {{expiry_date}} has been reached for {{institution_name}}.</p>',
            ],

            // 8. New DBA created — notification to the EQA mailbox.
            [
                'key' => 'dba_created',
                'name' => 'Notification — New Institution DBA Created',
                'category' => 'Change Notification',
                'subject' => 'New Institution DBA Created',
                'recipients' => $eqa,
                'description' => 'Notifies the EQA mailbox that a new DBA (Doing Business As) record was created. Recovered from the "Send Notification when new DBA created" CRM workflow.',
                'variables' => 'institution_name, modified_by, modified_on, dba_name, email, website, street1, street2, street3, city, province, postal_code, country, description',
                'body' =>
                    '<p><b>Institution Name:</b> {{institution_name}}</p>'.
                    '<p><b>DBA</b></p>'.
                    '<p><b>Modified by:</b> {{modified_by}} &nbsp; <b>Modified On:</b> {{modified_on}}</p>'.
                    '<p><b>DBA Name:</b> {{dba_name}}<br>'.
                    '<b>Email:</b> {{email}}<br>'.
                    '<b>Website URL:</b> {{website}}</p>'.
                    '<p><b>Address</b><br>'.
                    '<b>Street1:</b> {{street1}}<br>'.
                    '<b>Street2:</b> {{street2}}<br>'.
                    '<b>Street3:</b> {{street3}}<br>'.
                    '<b>City:</b> {{city}}<br>'.
                    '<b>Province:</b> {{province}}<br>'.
                    '<b>Postal Code:</b> {{postal_code}}<br>'.
                    '<b>Country:</b> {{country}}<br>'.
                    '<b>Description:</b> {{description}}</p>',
            ],

            // 9. New Campus/Location created — notification to the EQA mailbox.
            [
                'key' => 'campus_created',
                'name' => 'Notification — New Institution Campus Created',
                'category' => 'Change Notification',
                'subject' => 'New Institution Campus Created',
                'recipients' => $eqa,
                'description' => 'Notifies the EQA mailbox that a new campus (location) record was created. Recovered from the "Send Notification when new Campus created" CRM workflow.',
                'variables' => 'institution_name, modified_by, modified_on, primary_location, email, website, street1, street2, street3, city, province, postal_code, country, description',
                'body' =>
                    '<p><i>Institution Name</i>: {{institution_name}}</p>'.
                    '<p><b>Institution</b></p>'.
                    '<p><i>Modified by</i>: {{modified_by}} &nbsp; Modified On: {{modified_on}}</p>'.
                    '<p><i>Primary Location</i>: {{primary_location}}<br>'.
                    '<i>Email</i>: {{email}}<br>'.
                    '<i>Website URL</i>: {{website}}</p>'.
                    '<p><b>Address:</b><br>'.
                    '<i>Street1:</i> {{street1}}<br>'.
                    '<i>Street2:</i> {{street2}}<br>'.
                    '<i>Street3:</i> {{street3}}<br>'.
                    '<i>City:</i> {{city}}<br>'.
                    '<i>Province:</i> {{province}}<br>'.
                    '<i>Postal Code:</i> {{postal_code}}<br>'.
                    '<i>Country:</i> {{country}}<br>'.
                    '<i>Description:</i> {{description}}</p>',
            ],
        ];
    }
};
