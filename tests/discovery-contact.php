<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../api/discovery-contact.php';

function checkDiscoveryContact(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
checkDiscoveryContact(projectContactLogin(['contactEmail'=>'client@jar-bud.pl'])==='client@jar-bud.pl','A valid business e-mail should be accepted.');
checkDiscoveryContact(projectContactLogin(['contactPhone'=>'+48 600 700 800'])==='+48 600 700 800','A plausible phone should be accepted.');
checkDiscoveryContact(projectContactLogin(['contactEmail'=>'jarbud-test@example.invalid'])===null,'Reserved .invalid addresses must not unlock brief handoff.');
checkDiscoveryContact(projectContactLogin(['contactPhone'=>'+48 000 000 000'])===null,'An all-zero phone must not unlock brief handoff.');
checkDiscoveryContact(discoveryMessageHasTestContact('Dane fikcyjne tylko do testu: telefon +48 000 000 000.'),'Polish test-only contact marker was not detected.');
checkDiscoveryContact(contactFromMessage('Dane fikcyjne tylko do testu: firma: Jar-Bud, telefon +48 000 000 000, jarbud-test@example.invalid.')===[],'Test-only contacts must not be extracted from chat messages.');
$real=contactFromMessage('Firma: Jar-Bud Grzywniak, telefon: +48 600 700 800, kontakt@jar-bud.pl');
checkDiscoveryContact(($real['contactPhone']??null)==='+48 600 700 800' && ($real['contactEmail']??null)==='kontakt@jar-bud.pl','Valid contact details should still be extracted.');
echo "Discovery contact validation checks passed\n";
