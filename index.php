<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

require "./TessCardReader/TessCardReader.php";

//$added = TessCardReader::trainData('names-last', 'bigbigfart');
//echo "<pre>"; print_r($added); exit;		

$tcr = TessCardReader::fromImage('examplecard.jpg');

if($tcr->hasError()){
	echo $tcr->getError();
}

echo "<pre><b>full output</b>\n" . $tcr->rawText();

echo "<pre><b>auto-corrected</b>\n" . $tcr->autoCorrect();

echo "\n\n<b>groupings</b>\n"; print_r($tcr->getGroups());

echo "\n\n<b>lines</b>\n"; print_r($tcr->getLines());

echo "\n\n<b>contact names</b>\n"; print_r($tcr->extractNames());

echo "\n\n<b>phone numbers</b>\n"; print_r($tcr->extractPhoneNumbers());

echo "\n\n<b>company names</b>\n"; print_r($tcr->extractCompanyNames());

echo "\n\n<b>websites</b>\n"; print_r($tcr->extractWebsites());

echo "\n\n<b>emails</b>\n"; print_r($tcr->extractEmails());

echo "\n\n<b>addresses</b>\n"; print_r($tcr->extractStreetAddress());
