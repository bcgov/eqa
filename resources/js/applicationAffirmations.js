// Declarations the institution confirms on the application (all required), shared by the form and
// the read-only application view. Each label is a list of [text, italic] parts.
// affirm_branding_guide and affirm_understands_comply are retired (folded into affirm_written_permission).
export const affirmations = [
    ['affirm_policy_manual', [
        ['I confirm that I have read and understood the ', false],
        ['Post-Secondary International Education (Designated Institutions) Act', true],
        [' (“the Act”) and the accompanying Regulations and Administrator’s Rules.', false],
    ]],
    ['affirm_website_compliance', [
        ['I confirm that my institution\'s website is in compliance with the Act, Regulations and Administrator’s Rules and that all policies and information listed in the Administrator’s Rules are readily available to the public on the website.', false],
    ]],
    ['affirm_written_permission', [
        ['Institutions require the written permission of the Ministry in order to use the designation mark. Permission to use the designation mark will be provided to institutions that have provided the necessary institutional profile information and that have been approved for application or re-application of their designation under the Act. Any Questions relating to the use of the EQA brand should be directed to EQA@gov.bc.ca.', false],
    ]],
    ['affirm_authorized', [
        ['I am the person assigned by my organization as the institutional contact, authorized to submit the application.', false],
    ]],
]
