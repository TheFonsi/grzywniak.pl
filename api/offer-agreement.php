<?php
declare(strict_types=1);
require_once __DIR__.'/contract-model.php';

function offerAgreementFields():array {
    $facts=contractFacts();$out=[];
    foreach(['acceptanceCriteria','acceptanceDays','cooperationTerms','publicationDestination','productionDomain','domainRegistrar','domainOwnershipTerms','productionHosting','serverTarget','backupResponsibility','dnsTlsResponsibility','ipMode','rightsTerms','dataRole'] as $key) $out[$key]=$facts[$key];
    foreach(contractPackageFields() as $key=>$field) if(in_array($field['module'],['hosting','domain_purchase'],true)) $out[$key]=$field;
    foreach(['deliverySchedule'=>'Harmonogram i warunki rozpoczęcia prac','paymentSchedule'=>'Harmonogram płatności i rozliczenie zaliczki','supportPlan'=>'Wsparcie po odbiorze: zakres, okres i kontakt','rightsSummary'=>'Materiały, kod, prawa klienta i licencje zewnętrzne','externalCosts'=>'Koszty dodatkowe i zasady zmiany zakresu','dataPlan'=>'Dane aplikacji, role stron i potrzeba powierzenia'] as $key=>$label) $out[$key]=['label'=>$label];
    return $out;
}
function offerAgreementApplicable(string $key,array $facts):bool {
    if($key==='rightsTerms') return in_array($facts['ipMode']??'',['exclusive','nonexclusive'],true);
    return contractPackageApplicable($key,$facts)&&contractPublicationFieldApplicable($key,$facts);
}
function offerAgreementRead(array $raw):array {
    $out=[];
    foreach(offerAgreementFields() as $key=>$field) {
        $value=$raw[$key]??'';
        $limit=isset(contractFacts()[$key])&&!isset($field['module'])?2000:20000;
        if(!is_string($value)||mb_strlen($value)>$limit || (isset($field['options'])&&!array_key_exists($value,$field['options']))) throw new InvalidArgumentException('Niepoprawne ustalenie oferty: '.$field['label']);
        $out[$key]=$key==='productionDomain'?strtolower(trim($value)):trim($value);
    }
    if(!in_array($out['ipMode']??'',['exclusive','nonexclusive'],true)) $out['rightsTerms']='';
    return $out;
}
function offerAgreementDraft(array $session,array $previous=[]):array {
    $source=contractBriefProposal($session)['facts'];
    $scope=$session['offerAnalysis']['recommendedScope']??$session['internalAnalysis']['recommendedScope']??[];
    if($scope) $source['acceptanceCriteria']="Odbiór obejmuje elementy zakresu:\n- ".implode("\n- ",$scope)."\n[DO UZUPEŁNIENIA: konkretne scenariusze i mierzalne wyniki testów]";
    if(!empty($session['projectState']['deadline'])) $source['deliverySchedule']=$session['projectState']['deadline'];
    if(!empty($session['projectState']['supportExpectations'])) $source['supportPlan']=$session['projectState']['supportExpectations'];
    $source['externalCosts']='Prace i usługi niewymienione w zakresie wymagają osobnej wyceny oraz akceptacji klienta przed ich zleceniem. Zmianę zakresu i wpływ na termin zapisujemy w nowej wersji ustaleń.';
    return offerAgreementRead(array_replace($source,$previous));
}
function offerAgreementMissing(array $offer):array {
    $pending=[]; foreach($offer['dependencyReview']??[] as $key=>$confirmed) if($confirmed!==true) $pending[]='Sprawdź ponownie: '.(offerAgreementFields()[$key]['label']??'wycena i wpływ zakresu na cenę');
    foreach($offer['decisionCoverage']??[] as $decision) if(($decision['target']??'')==='') $pending[]='Przypisz ustalenie: '.($decision['question']??'');
    $pending=array_merge($pending,offerFinancialMissing($offer));
    if(!isset($offer['agreement'])) return array_merge($pending,['Uzupełnij pakiet warunków realizacji starszej oferty przed nową akceptacją lub PDF umowy']);
    $facts=$offer['agreement'];$missing=[];
    foreach(offerAgreementFields() as $key=>$field) {
        if(!offerAgreementApplicable($key,$facts)) continue;
        if(in_array($key,['domainRegistrar','serverTarget'],true)) continue;
        $value=trim((string)($facts[$key]??''));
        if(isset($field['options'])&&!isset($field['options'][$value])) $missing[]=$field['label'];
        if($value===''||preg_match('/\[DO (UZUPEŁNIENIA|UZGODNIENIA):/iu',$value)||preg_match('/^(do ustalenia|nie wiem)$/iu',$value)) $missing[]=$field['label'];
    }
    if(($facts['acceptanceDays']??'')!=='' && (!ctype_digit($facts['acceptanceDays'])||(int)$facts['acceptanceDays']<1||(int)$facts['acceptanceDays']>90)) $missing[]='Termin odbioru: liczba od 1 do 90';
    if(offerAgreementApplicable('productionDomain',$facts)&&trim($facts['productionDomain']??'')!==''&&!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D',$facts['productionDomain'])) $missing[]='Poprawna docelowa domena';
    return array_values(array_unique(array_merge($pending,$missing)));
}
function offerFinancialMissing(array $offer):array {
    $p=$offer['pricing']??[]; $missing=[];
    if(!is_numeric($p['net']??null)||!is_finite((float)$p['net'])||(float)$p['net']<=0) $missing[]='Poprawna cena netto';
    if(!is_numeric($p['vatRate']??null)||(float)$p['vatRate']<0||(float)$p['vatRate']>100) $missing[]='Poprawna stawka VAT';
    if(!$missing) {
        $gross=round((float)$p['net']*(1+(float)$p['vatRate']/100),2);
        if(isset($p['gross'])&&(!is_numeric($p['gross'])||abs((float)$p['gross']-$gross)>0.005)) $missing[]='Cena brutto zgodna z netto i VAT';
    }
    $rate=$offer['payment']['depositRate']??0;
    if(!is_numeric($rate)||(float)$rate<0||(float)$rate>100) $missing[]='Zaliczka: od 0 do 100%';
    return $missing;
}
function offerDecisionTargets(): array {
    $out=['scope'=>'Zakres realizacji','exclusions'=>'Poza bieżącym zakresem','information'=>'Informacja organizacyjna (bez nowego zobowiązania)'];
    foreach(offerAgreementFields() as $key=>$field) $out[$key]=$field['label'];
    return $out;
}
function offerDecisionSuggestion(array $decision):array {
    $question=mb_strtolower((string)($decision['question']??''));
    $rules=[
        'rightsSummary'=>'praw.*(zdję|foto|materiał)|zg[oó]d|licenc|autorsk',
        'dataPlan'=>'danych osobow|rodo|powierzeni|administrator.*danych',
        'paymentSchedule'=>'zaliczk|płatno|faktur|rozliczen',
        'externalCosts'=>'budże.*(domen|hosting|wdroż)|koszt|dodatkow.*opłat',
        'publicationDestination'=>'domen|hosting|serwer|publikac|wdroż',
        'cooperationTerms'=>'dostarcz|materiał|tekst|logo|zdję|współprac',
        'acceptanceDays'=>'dni.*(odbior|sprawdze)|termin.*sprawdze',
        'acceptanceCriteria'=>'kryteri|test|odbior|akceptac',
        'deliverySchedule'=>'termin|harmonogram|rozpoczę',
        'supportPlan'=>'wsparci|utrzyman|serwis|gwaranc',
        'scope'=>'szablon|projekt.*graficzn|usług|funkcj|telefon|kontakt|zakres|stron',
    ];
    foreach($rules as $target=>$pattern) if(preg_match('/'.$pattern.'/u',$question)) return ['target'=>$target,'reason'=>'Dopasowano temat ustalenia do sekcji: '.offerDecisionTargets()[$target].'.'];
    return ['target'=>'scope','reason'=>'Ustalenie dotyczące projektu zachowamy w zakresie. Sprawdź, czy pasuje do bardziej szczegółowej sekcji.'];
}
function offerDecisionCoverage(array $session,array $previous=[],?array $submitted=null): array {
    $out=[];
    foreach(decisionLedgerActive($session) as $id=>$decision) {
        $old=$previous[$id]??[];
        $target=($old['version']??0)===$decision['version']?($old['target']??''):'';
        if($submitted!==null) $target=$submitted[$id]??'';
        if(!is_string($target)||($target!==''&&!isset(offerDecisionTargets()[$target]))) throw new InvalidArgumentException('Niepoprawne przypisanie ustalenia do oferty.');
        $suggestion=offerDecisionSuggestion($decision);
        $out[$id]=$decision+['target'=>$target,'targetLabel'=>offerDecisionTargets()[$target]??'Nieprzypisane','suggestedTarget'=>$suggestion['target'],'suggestionReason'=>$suggestion['reason']];
    }
    return $out;
}
function offerMarkDependentReview(array $previous,array &$offer):void {
    unset($offer['commercialSnapshot']);
    if(!$previous) return;
    // Ideas outside the order do not change delivery obligations or pricing.
    $committedSections=static fn(array $document):array=>array_values(array_filter($document['sections']??[],static fn(array $section):bool=>preg_match('/poza.*zakres|możliwe później|opcjonal|optional|out of scope/iu',$section['title']??'')!==1));
    if($committedSections($previous)!==$committedSections($offer)||($previous['pricing']??[])!==($offer['pricing']??[])||($previous['sourceHash']??'')!==($offer['sourceHash']??'')) {
        $offer['dependencyReview']=['pricing'=>false];
        foreach(offerAgreementFields() as $key=>$field) if(offerAgreementApplicable($key,$offer['agreement']??[])) $offer['dependencyReview'][$key]=false;
    }
}
function offerAgreementSections(array $offer):array {
    $sections=[];$facts=$offer['agreement']??[];
    foreach(offerAgreementFields() as $key=>$field) {
        if(!offerAgreementApplicable($key,$facts)) continue;
        $value=$facts[$key]??'';
        if(isset($field['options'])) $value=$field['options'][$value]??'';
        if($key==='acceptanceDays'&&$value!=='') $value.=' dni kalendarzowych od skutecznego zawiadomienia i otrzymania dostępu do wskazanej wersji.';
        $sections[]=['title'=>$field['label'],'items'=>[$value?:'Do uzgodnienia przed akceptacją oferty.']];
    }
    foreach($offer['decisionCoverage']??[] as $id=>$decision) if(($decision['target']??'')!=='') $sections[]=['title'=>'Ustalenie '.substr($id,0,8).' — '.($decision['targetLabel']??$decision['target']),'items'=>[$decision['question'],$decision['answer']]];
    return $sections;
}
function offerAgreementSuggestions(string $key):array {
    $extra=[
        'deliverySchedule'=>['Etapy projektu'=>'Rozpoczęcie po potwierdzeniu umowy, wpłacie uzgodnionej zaliczki i otrzymaniu materiałów. Etapy i terminy: [DO UZUPEŁNIENIA: plan, podgląd, odbiór i publikacja]. Opóźnienia materiałów oraz zmiany zakresu wymagają uzgodnienia ich wpływu na harmonogram.'],
        'paymentSchedule'=>['Zaliczka i rozliczenie po odbiorze'=>'Zaliczka według wyceny tej wersji oferty, płatna w terminie [DO UZUPEŁNIENIA: termin i zdarzenie]. Pozostała kwota płatna po [DO UZUPEŁNIENIA: etap lub odbiór oraz termin płatności]. Rozliczenie usług cyklicznych odbywa się osobno według warunków hostingu.'],
        'supportPlan'=>['Wsparcie po odbiorze'=>'Zakres wsparcia: [DO UZUPEŁNIENIA: usuwanie wad, obsługa i wyłączenia]. Okres: [DO UZUPEŁNIENIA: okres i początek]. Kontakt i czasy reakcji: [DO UZUPEŁNIENIA: kanał, godziny i terminy]. Nowe funkcje wymagają osobnego uzgodnienia. Ustalenia nie ograniczają ustawowych uprawnień klienta.'],
        'rightsSummary'=>['Prawa i licencje'=>'Klient otrzyma kod i materiały w zakresie: [DO UZUPEŁNIENIA: elementy projektu]. Zasady praw: [DO UZUPEŁNIENIA: przeniesienie lub licencja, zakres, warunek przejścia]. Biblioteki, materiały klienta, wcześniejsze komponenty i elementy AI: [DO UZUPEŁNIENIA: wykaz i ograniczenia]. Szczegóły oraz wymagana forma podpisania zostaną określone w umowie.'],
        'externalCosts'=>['Każdy dodatkowy koszt po zgodzie'=>'Prace i usługi niewymienione w zakresie wymagają osobnej wyceny oraz akceptacji klienta przed ich zleceniem. Zmianę zakresu i wpływ na termin zapisujemy w nowej wersji ustaleń.'],
        'dataPlan'=>['Strona bez formularza'=>'Strona nie zbiera danych przez formularze, konta ani płatności. Zakres logów, pomiarów i innych danych przetwarzanych przez dostawców: [DO UZUPEŁNIENIA: faktyczny zakres i role stron]. Potrzebę powierzenia ustalamy na podstawie rzeczywistego dostępu wykonawcy, nie samej obecności formularza.', 'Aplikacja z danymi'=>'Rodzaje danych i osoby: [DO UZUPEŁNIENIA: rzeczywisty zakres]. Administrator i zakres dostępu wykonawcy: [DO UZUPEŁNIENIA: role i operacje]. Dostawcy i lokalizacje: [DO UZUPEŁNIENIA: rzeczywiści dostawcy]. Wymagane powierzenie i obowiązki informacyjne zostaną doprecyzowane przed podpisaniem umowy i przetwarzaniem danych.'],
    ];
    return $extra[$key]??contractFieldSuggestions($key);
}
