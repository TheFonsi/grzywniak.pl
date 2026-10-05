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
$a['ipMode']='transfer';$a['dataRole']='none';$a['rightsTerms']='';
agreementAssert(offerAgreementMissing(['agreement'=>$a,'pricing'=>['net'=>1500,'vatRate'=>23]])===[],'Complete agency commercial terms');
$handoff=$a;$handoff['publicationDestination']='client_handoff';$handoff['productionHosting']='';
agreementAssert(offerAgreementMissing(['agreement'=>$handoff])!==[],'Client hosting destination needs hosting details');
$bad=$a;$bad['acceptanceDays']='7 dni';agreementAssert(offerAgreementMissing(['agreement'=>$bad])!==[],'Day count must be numeric');
agreementAssert(offerAgreementMissing(['status'=>'ACCEPTED','pricing'=>['net'=>1500,'vatRate'=>23]])!==[],'Legacy offer needs complete terms before a new contract PDF');
$offer=['offerId'=>'OF-PRZYKLAD','version'=>1,'status'=>'DRAFT','createdAt'=>1791100000,'project'=>'Strona firmy budowlanej — przykład','summary'=>'Przejrzysta strona prezentująca ofertę oraz kontakt telefoniczny. Dane i ceny przykładowe.','contact'=>['name'=>'Przykładowa firma','email'=>'klient@example.test'],'sections'=>[['title'=>'Cel i kontekst','items'=>['Prezentacja firmy dla inwestorów.']],['title'=>'Zakres realizacji','items'=>['Jedna strona z sekcjami oferty, realizacji i kontaktu.','Widoki dostosowane do telefonu i komputera.']],['title'=>'Poza zakresem','items'=>['Sklep i panel klienta.']]],'pricing'=>['net'=>1500,'gross'=>1845,'vatRate'=>23,'vat'=>345],'payment'=>['depositRate'=>10],'agreement'=>$a,'note'=>'Dokument przykładowy do oceny układu.'];
$pdf=offerPdf($offer);agreementAssert(str_starts_with($pdf,'%PDF-'),'Professional PDF generated');
$updated=$offer;$updated['sections'][2]['items'][]='Galeria na później';
offerMarkDependentReview($offer,$updated);
agreementAssert(!isset($updated['dependencyReview']),'Optional ideas alone must not invalidate verified delivery terms');
$updated['dependencyReview']=['pricing'=>false];$next=$updated;$next['sections'][2]['items'][]='Formularz na później';
offerMarkDependentReview($updated,$next);
agreementAssert($next['dependencyReview']['pricing']===false,'Optional changes must not clear existing checks required by real scope changes');
$updated=$offer;$updated['sections'][1]['items'][]='Galeria zamówiona przez klienta';
offerMarkDependentReview($offer,$updated);
agreementAssert($updated['dependencyReview']['pricing']===false&&$updated['dependencyReview']['acceptanceCriteria']===false,'Promoted features still require price and delivery review');
if(in_array('--sample',$argv,true)) {file_put_contents(__DIR__.'/../output/pdf/oferta-przyklad.pdf',$pdf);}
echo "Offer agreement checks passed\n";
foreach([
    'Czy firma dostarczy teksty, logo i zdjęcia?'=>'cooperationTerms',
    'Czy firma ma prawo do publikacji zdjęć realizacji i zgód osób?'=>'rightsSummary',
    'Czy w budżecie mają się znaleźć domena i hosting?'=>'externalCosts',
    'Czy klient akceptuje gotowy szablon?'=>'scope',
    'Czy numer telefonu ma być jedyną formą kontaktu?'=>'scope',
    'Jaki jest harmonogram płatności?'=>'paymentSchedule',
    'Jaki termin sprawdzenia zgłoszonej wersji?'=>'acceptanceDays',
    'Nietypowe ustalenie projektu'=>'scope',
] as $question=>$target) agreementAssert(offerDecisionSuggestion(['question'=>$question])['target']===$target,'Automatic target: '.$question);
