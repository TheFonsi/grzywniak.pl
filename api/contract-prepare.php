<?php
declare(strict_types=1);
require_once __DIR__.'/offer-defaults.php';

function contractDraftValueMissing(mixed $value):bool {
    return trim((string)$value)===''||preg_match('/\[DO (?:UZUPEŁNIENIA|UZGODNIENIA)(?::|\])|^(?:do ustalenia|wybierz)$/iu',trim((string)$value))===1;
}

/** Complete draft proposals; never write or approve a document on opening it. */
function contractCompleteDraftFacts(array $session,array $profile,array $facts):array {
    $offer=$session['offer']??[];
    $defaults=offerAgreementDefaultPackage($session,$offer);
    // Do not silently choose publication, IP mode or data roles for an accepted offer.
    $allowed=['acceptanceCriteria','acceptanceDays','cooperationTerms','privacyRetention'];
    $defaults['privacyRetention']=$profile['privacyRetention']??'';
    if(contractDraftValueMissing($defaults['privacyRetention'])) $defaults['privacyRetention']='Dane umowne przechowujemy przez okres niezbędny do wykonania umowy, rozliczeń i dochodzenia roszczeń, a dokumenty księgowe przez okres wymagany przepisami. Nie przechowujemy zbędnych kopii. Dane reprezentantów otrzymujemy od klienta w toku zawierania i realizacji umowy.';
    foreach($allowed as $key) if(contractDraftValueMissing($facts[$key]??'')) {
        $agreed=$offer['agreement'][$key]??'';
        $facts[$key]=!contractDraftValueMissing($agreed)?$agreed:$defaults[$key];
    }
    if(empty($facts['obligationKind'])) $facts['obligationKind']=in_array($facts['publicationDestination']??'',['agency','agency_purchase'],true)?'mixed':'result';
    foreach(['legalStatusBasis','rightsInventory','privacyRecipients','processingLocations','processingSubprocessors'] as $key) if(contractDraftValueMissing($facts[$key]??'')) {
        $known=$session['projectState'][$key]??$profile[$key]??'';
        if(!contractDraftValueMissing($known)) $facts[$key]=$known;
    }
    return $facts;
}

function contractContextSuggestions(string $key,array $session,array $facts):array {
    $complete=contractCompleteDraftFacts($session,[],[]);
    $suggestions=[];
    if(in_array($key,['acceptanceCriteria','acceptanceDays','cooperationTerms','privacyRetention'],true)&&!contractDraftValueMissing($complete[$key]??'')) $suggestions['Zapis na podstawie oferty i standardu realizacji']=$complete[$key];
    foreach(contractFieldSuggestions($key) as $title=>$value) if(!contractDraftValueMissing($value)) $suggestions[$title]=$value;
    return $suggestions;
}

function contractPreparedProposal(array $session,array $profile,array $template,array $current=[]):array {
    $offer=$session['offer']??[];$snapshot=contractCommercialSnapshot($session);
    $facts=is_array($current['facts']??null)?$current['facts']:[];
    foreach($snapshot['agreement'] as $key=>$value) if(isset(contractFacts()[$key])) $facts[$key]=$value;
    $facts=contractCompleteDraftFacts($session,$profile,$facts);
    $state=$session['projectState']??[];$contact=$offer['contact']??[];
    foreach(['clientAddress'=>['contactAddress','address'],'clientTaxId'=>['contactTaxId','taxId'],'clientRepresentative'=>['contactRepresentative','representative']] as $key=>[$source,$contactKey]) {
        if(trim((string)($facts[$key]??''))==='') $facts[$key]=(string)($contact[$contactKey]??$state[$source]??'');
    }
    if(empty($facts['contractDate'])) $facts['contractDate']=(new DateTimeImmutable('now',new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
    if(empty($facts['signing'])) $facts['signing']='paper';
    if(empty($facts['obligationKind'])) $facts['obligationKind']=in_array($facts['publicationDestination']??'',['agency','agency_purchase'],true)?'mixed':'result';
    if(empty($facts['privacyRetention'])) $facts['privacyRetention']='Dane umowne przechowujemy przez okres niezbędny do wykonania umowy, rozliczeń i dochodzenia roszczeń, a dokumenty księgowe przez okres wymagany przepisami. Nie przechowujemy zbędnych kopii. Dane reprezentantów otrzymujemy od klienta w toku zawierania i realizacji umowy.';
    $fields=array_replace(contractTemplateProposal($template,$facts),$snapshot['fields']);
    $fields['provider']=trim((string)($current['provider']??''))!==''?$current['provider']:contractProviderText($profile);
    $fields['party']=trim((string)($current['party']??''))!==''?$current['party']:implode("\n",array_filter([$contact['name']??'',!empty($facts['clientAddress'])?'Adres: '.$facts['clientAddress']:'',!empty($facts['clientTaxId'])?'NIP: '.$facts['clientTaxId']:'',!empty($facts['clientRepresentative'])?'Reprezentacja: '.$facts['clientRepresentative']:'',!empty($contact['email'])?'E-mail: '.$contact['email']:'',!empty($contact['phone'])?'Telefon: '.$contact['phone']:'' ]));
    $fields['paymentDetails']=trim((string)($current['paymentDetails']??''))!==''?$current['paymentDetails']:(!empty($profile['bankAccount'])?'Przelew na rachunek: '.$profile['bankAccount'].'.':'Przelew na rachunek wskazany na fakturze.');
    foreach(['clientType','legalStatusBasis','rightsInventory','privacyRecipients','processingLocations','processingSubprocessors'] as $key) if(trim((string)($facts[$key]??''))==='') $facts[$key]=(string)($state[$key]??$profile[$key]??'');
    if(!empty($profile['privacyRetention'])&&empty($current['facts']['privacyRetention'])) $facts['privacyRetention']=$profile['privacyRetention'];
    $facts=contractReadFacts($facts,$facts,$template);
    return ['fields'=>$fields,'facts'=>$facts,'replaceTemplate'=>true,'replaceCommercial'=>true,'commercialHash'=>$snapshot['hash'],'commercialSections'=>contractCommercialSections($snapshot),'differences'=>[],
        'template'=>['id'=>$template['id'],'title'=>$template['title'],'revision'=>$template['revision'],'version'=>$template['version']],
        'missing'=>array_values(array_unique(array_merge(contractProfileMissing($profile),contractFactsMissing($facts))))];
}
