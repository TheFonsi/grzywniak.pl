<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../api/offer-agreement.php';
require_once __DIR__.'/../api/offer-pdf.php';
function agreementAssert(bool $ok,string $message):void {if(!$ok) throw new RuntimeException($message);}
$s=['projectState'=>['materialsTerms'=>'Klient dostarcza logo.','publicationPreference'=>'agency','deadline'=>'Do 30 dni po otrzymaniu materiałów.'],'internalAnalysis'=>['recommendedScope'=>['Strona z numerem kontaktowym']]];
$a=offerAgreementDraft($s);
agreementAssert($a['cooperationTerms']==='Klient dostarcza logo.','Materials from brief');
agreementAssert($a['publicationDestination']==='agency','Known publication preference');
agreementAssert(offerAgreementMissing(['agreement'=>$a])!==[],'Incomplete commercial terms cannot be reviewed');
foreach(offerAgreementFields() as $key=>$field) $a[$key]=isset($field['options'])?'agency':'Przykładowe uzgodnione warunki: '.$field['label'];
$a['acceptanceDays']='7';$a['productionDomain']='example.test';
agreementAssert(offerAgreementMissing(['agreement'=>$a])===[],'Complete agency commercial terms');
$handoff=$a;$handoff['publicationDestination']='client_handoff';$handoff['productionHosting']='';
agreementAssert(offerAgreementMissing(['agreement'=>$handoff])!==[],'Client hosting destination needs hosting details');
$bad=$a;$bad['acceptanceDays']='7 dni';agreementAssert(offerAgreementMissing(['agreement'=>$bad])!==[],'Day count must be numeric');
agreementAssert(offerAgreementMissing(['status'=>'ACCEPTED'])===[],'Legacy offer remains readable');
$offer=['offerId'=>'OF-PRZYKLAD','version'=>1,'status'=>'DRAFT','createdAt'=>1791100000,'project'=>'Strona firmy budowlanej — przykład','summary'=>'Przejrzysta strona prezentująca ofertę oraz kontakt telefoniczny. Dane i ceny przykładowe.','contact'=>['name'=>'Przykładowa firma','email'=>'klient@example.test'],'sections'=>[['title'=>'Cel i kontekst','items'=>['Prezentacja firmy dla inwestorów.']],['title'=>'Zakres realizacji','items'=>['Jedna strona z sekcjami oferty, realizacji i kontaktu.','Widoki dostosowane do telefonu i komputera.']],['title'=>'Poza zakresem','items'=>['Sklep i panel klienta.']]],'pricing'=>['net'=>1500,'gross'=>1845,'vatRate'=>23,'vat'=>345],'payment'=>['depositRate'=>10],'agreement'=>$a,'note'=>'Dokument przykładowy do oceny układu.'];
$pdf=offerPdf($offer);agreementAssert(str_starts_with($pdf,'%PDF-'),'Professional PDF generated');
if(in_array('--sample',$argv,true)) {file_put_contents(__DIR__.'/../output/pdf/oferta-przyklad.pdf',$pdf);}
echo "Offer agreement checks passed\n";
