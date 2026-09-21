<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../api/offer-changes.php';
// Test pure document functions without authentication, storage or AI requests.
$source = file_get_contents(__DIR__ . '/../api/offer.php');
foreach (['function offerSourceHash' => '\nif (is_array', 'function listOf' => '\nfunction pdfText'] as $start => $end) {
    $from = strpos($source, $start);
    $to = strpos($source, str_replace('\\n', "\n", $end), $from);
    eval(substr($source, $from, $to - $from));
}
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$session = ['id' => str_repeat('a', 32), 'projectState' => ['mustHaveFeatures' => 'sklep integracja CRM'], 'internalAnalysis' => ['recommendedScope' => ['Prosta strona'], 'missingInformation' => ['Zakres']], 'adminDecisions' => ['Zakres' => ['source' => 'AI_AUTO', 'answer' => 'Dodatkowy system']]];
$offer = offerDocument($session, 150, 23);
check($offer['status'] === 'DRAFT', 'New document must be a draft');
check($offer['sections'][1]['items'] === ['Prosta strona'], 'AI hypothesis leaked into scope');
$session['projectState']['mustHaveFeatures'] = 'Inny pierwotny pomysł';
check(offerDocument($session, 150, 23)['pricing'] === $offer['pricing'], 'Pricing must follow offered scope');
check(offerSourceHash($session) !== $offer['sourceHash'], 'Changed brief must invalidate source');
$changed = $offer;
$changed['sections'][1]['items'][] = 'Nowy element';
check(in_array('sections', array_column(offerChangeLog($offer, $changed), 'key'), true), 'Scope difference missing');
echo "Offer workflow checks passed\n";
$session['adminDecisions']['Zakres'] = ['source' => 'HUMAN', 'answer' => 'Surowa odpowiedź administratora'];
$session['offerAnalysis'] = ['summary' => 'Nowe podsumowanie', 'recommendedScope' => ['Zakres opracowany po akceptacji'], 'optionalScope' => ['Etap drugi']];
$regenerated = offerDocument($session, 150, 23);
check($regenerated['summary'] === 'Nowe podsumowanie', 'Regenerated summary not used');
check($regenerated['sections'][1]['items'] === ['Zakres opracowany po akceptacji'], 'Raw decisions must not be appended');
check(!in_array('Zatwierdzone ustalenia zespołu', array_column($regenerated['sections'], 'title'), true), 'Internal decisions section leaked');
echo "Regenerated scope checks passed\n";
$patched = applyOfferUpdate($regenerated, ['summary' => null, 'sections' => [['index' => 1, 'title' => 'Zakres realizacji', 'items' => ['Zmieniony punkt']]]]);
check($patched['summary'] === $regenerated['summary'], 'Unchanged summary overwritten');
check($patched['sections'][0] === $regenerated['sections'][0], 'Unrelated section overwritten');
check($patched['pricing'] === $regenerated['pricing'], 'Price overwritten');
check($patched['sections'][1]['items'] === ['Zmieniony punkt'], 'Requested section not changed');
try { applyOfferUpdate($regenerated, ['sections' => [['index' => 999, 'title' => 'Invalid', 'items' => []]]]); throw new LogicException('Invalid index accepted'); } catch (RuntimeException $expected) {}
echo "Incremental offer update checks passed\n";
$old = ['summary'=>'Opis', 'sections'=>[['title'=>'Zakres','items'=>['A','B','C']]]];
$new = ['summary'=>'Opis', 'sections'=>[['title'=>'Zakres','items'=>['A','Nowe B','C']]]];
$new['changeLog'] = offerTextChanges($old, $new);
check(count($new['changeLog']) === 1, 'Unchanged items highlighted');
check(restoreOfferChange($new, 'text.0')['sections'] === $old['sections'], 'Restore replacement failed');
foreach ([['A','X','B','C'], ['A','C']] as $items) {
    $new['sections'][0]['items'] = $items;
    $new['changeLog'] = offerTextChanges($old, $new);
    check(count($new['changeLog']) === 1, 'Insertion/deletion creates unrelated changes');
    check(restoreOfferChange($new, 'text.0')['sections'] === $old['sections'], 'Restore insertion/deletion failed');
}
echo "Granular change and restore checks passed\n";
foreach ([[0,1],[1,0]] as $order) {
    $new['sections'][0]['items']=['A']; $new['changeLog']=offerTextChanges($old,$new);
    foreach($order as $index) $new=restoreOfferChange($new,'text.'.$index);
    check($new['sections']===$old['sections'],'Multiple deleted items restored out of order');
}
echo "Restore order checks passed\n";
