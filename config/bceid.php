<?php

// BCeID SOAP Web Service (V10) credentials + parameters, reverse-engineered from
// the legacy Dynamics plugin (CGI.Plugins.BCeIDRetrieve.VerifyBCeID). Used by the
// ministry-side "Fetch BCeID Data" action to resolve a contact's BCeID username
// into their User GUID, Business GUID and Business Legal Name.
return [
    'url' => env('BceidWebServiceUrl', 'https://gws1.test.bceid.ca/webservices/Client/V10/BCeIDService.asmx'),
    'domain' => env('BceidDomain', 'IDIR'),
    'username' => env('BceidUserName', ''),
    'password' => env('BceidPassword', ''),
    'online_service_id' => env('BceidOnlineServiceId', ''),
    'requester_user_guid' => env('BceidRequesterUserGuid', ''),
    'timeout' => (int) env('BceidTimeout', 45),
];
