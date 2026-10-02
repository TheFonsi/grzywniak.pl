<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/discovery-summary.php';

function checkSummary(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
$summary=buildSummary([
    'contactName'=>'JAR-BUD Grzywniak Małgorzata',
    'contactPhone'=>'664 870 311',
    'contactEmail'=>'',
    'businessGoals'=>'Przyciągać inwestorów i firmy zlecające budowę.',
    'niceToHaveFeatures'=>'Brak potrzeby samodzielnej edycji treści po uruchomieniu strony.',
    'integrations'=>'Do ustalenia',
]);
$sections=[];
foreach($summary['sections'] as $section) $sections[$section['title']]=$section['content'];
checkSummary(!isset($sections['E-mail kontaktowy']),'Brief must not show an empty e-mail as "Do ustalenia".');
checkSummary(!isset($sections['Integracje']),'Brief must omit an integration placeholder when no integration was given.');
checkSummary(($sections['Dodatkowe potrzeby i założenia'][0]??'')==='Brak potrzeby samodzielnej edycji treści po uruchomieniu strony.','Additional requirements must not be presented as a promised future phase.');
checkSummary(!str_contains(implode(' ', $sections['Kolejne kroki']),'Wyślemy'),'Generic next steps must not imply that the offer will be sent by e-mail.');
$empty=buildSummary(['contactEmail'=>'Do ustalenia','niceToHaveFeatures'=>null]);
checkSummary(count($empty['sections'])===1 && $empty['sections'][0]['title']==='Kolejne kroki','Empty or undecided optional fields must not produce filler sections.');
echo "Discovery brief summary checks passed\n";
