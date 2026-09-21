<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../api/contract-model.php';
require_once __DIR__ . '/../api/contract-pdf.php';
require_once __DIR__ . '/../api/contract-ai.php';
require_once __DIR__ . '/../api/contract-review.php';
function contractCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$id = bin2hex(random_bytes(16));
$session = ['id'=>$id,'projectState'=>['contactEmail'=>'contract-test@example.com'],'offer'=>['status'=>'ACCEPTED'],'contract'=>null];
$base = ['number'=>'UM-2026-TEST1234','version'=>1,'createdAt'=>time(),'party'=>'Test Żółć','scope'=>'Zakres: aplikacja i raporty','price'=>'5000 zł netto + VAT 23%','deposit'=>'10%','deadline'=>'1–3 miesiące','terms'=>'Warunki płatności zgodnie z ofertą.'];
$pdf = contractPdf($base);
$aiFields=array_fill_keys(contractAiKeys(),'Treść projektu');
$validated=contractAiValidate(['fields'=>$aiFields,'facts'=>array_fill_keys(array_keys(contractFacts()),''),'missing'=>[]]);
contractCheck(count($validated['fields'])===count(contractFields()),'AI must cover every contract field');
$review=contractReviewState(['support'=>['value'=>'Accepted clause','accepted'=>true]],['support'=>'Changed clause'],[]);
contractCheck($review['support']['accepted']===false,'Editing invalidates acceptance');
try { contractAiValidate(['fields'=>['scope'=>'incomplete'],'missing'=>[]]); throw new LogicException('Malformed AI output accepted'); } catch(RuntimeException $expected) {}
try { contractReadFacts(['clientType'=>'invalid']); throw new LogicException('Invalid client type accepted'); } catch(InvalidArgumentException $expected) {}
contractCheck(str_contains(contractEditor(['id'=>$id,'offer'=>['status'=>'DRAFT']]), 'zaakceptowaniu'), 'Unaccepted offer must not have an editable contract');
$editor=contractEditor(array_replace($session,['contract'=>$base]));
contractCheck(str_contains($editor,'Test Żółć'), 'Editor must retain saved contract values');
contractCheck(!str_contains($editor,'view=contracts'), 'Editor must remain in the offer panel');
$long=$base; $long['scope']=str_repeat("Zażółć gęślą jaźń. Długi opis zakresu aplikacji i odbioru.\n",150);
$longPdf=contractPdf($long);
contractCheck(preg_match('~/Count ([2-9][0-9]*)~',$longPdf)===1,'Long contracts must paginate');
if(!is_dir(__DIR__.'/../tmp/pdfs')) mkdir(__DIR__.'/../tmp/pdfs',0777,true);
file_put_contents(__DIR__.'/../tmp/pdfs/contract-check.pdf',contractPdf(array_replace(contractTemplateDefaults(),$base,['provider'=>'Wykonawca Żółć, Warszawa','paymentDetails'=>'Rachunek: przykładowy rachunek testowy','facts'=>['clientType'=>'business','ipMode'=>'transfer','rightsTerms'=>'Po pełnej zapłacie wynagrodzenia','ipPayment'=>'Wliczone w wynagrodzenie','signing'=>'qualified','dataRole'=>'none','clientAddress'=>'Warszawa, Testowa 1','clientRepresentative'=>'Jan Test','contractDate'=>'2026-09-15']])));
contractCheck(str_starts_with($pdf, '%PDF-1.4'), 'Contract PDF header missing');
contractCheck(str_contains($pdf, 'CP1250') === false, 'Raw encoding marker leaked into PDF');
writeSession($session); $loaded = readSession($id); contractCheck(($loaded['offer']['status'] ?? '') === 'ACCEPTED', 'Accepted offer not persisted');
$session['contract']=$base; writeSession($session); $session['contractVersions'][]=$base; $next=$base; $next['version']=2; $session['contract']=$next; writeSession($session); $loaded=readSession($id); contractCheck(($loaded['contract']['version']??0)===2, 'Contract version not persisted'); contractCheck(count($loaded['contractVersions']??[])===1, 'Contract history not persisted');
$loaded['contract']['status']='SENT'; $loaded['contract']['sentTo']='contract-test@example.com'; $loaded['contract']['deliveryLog']=[['to'=>'contract-test@example.com','at'=>time(),'mock'=>true]]; writeSession($loaded); $loaded=readSession($id); contractCheck(($loaded['contract']['deliveryLog'][0]['mock']??false)===true, 'Delivery log not persisted');
deleteSession($id); echo "Contract workflow checks passed\n";
