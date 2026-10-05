<?php
declare(strict_types=1);
require_once __DIR__.'/decision-ledger.php';

function commercialMoney(float $value): string { return number_format($value,2,',',' ').' zł'; }
function contractCommercialSnapshot(array $session): array {
    $offer=$session['offer']??[]; $pricing=$offer['pricing']??[]; $payment=$offer['payment']??[];
    $net=(float)($pricing['net']??0); $vatRate=(float)($pricing['vatRate']??23);
    $gross=round($net*(1+$vatRate/100),2); $depositRate=(float)($payment['depositRate']??0);
    $agreement=is_array($offer['agreement']??null)?$offer['agreement']:[];
    $scope=contractOfferScopeSections($offer);
    $exclusions=array_values(array_filter($offer['sections']??[],static fn($s)=>is_array($s)&&preg_match('/poza|opcjonal|później|optional|out of scope/iu',$s['title']??'')));
    $scopeText=[]; foreach($scope as $s) $scopeText[]=($s['title']??'Zakres')."\n".implode("\n",$s['items']??[]);
    $deadline=$agreement['deliverySchedule']??'';
    if(trim($deadline)==='') foreach($offer['sections']??[] as $s) if(preg_match('/termin/iu',$s['title']??'')) $deadline=implode("\n",$s['items']??[]);
    if(trim($deadline)==='') $deadline=(string)($offer['sourceSnapshot']['projectState']['deadline']??$session['projectState']['deadline']??'[DO UZUPEŁNIENIA: harmonogram w ofercie]');
    $fields=[
        'scope'=>"Zamawiający zleca, a Wykonawca zobowiązuje się wykonać i przekazać projekt w następującym zakresie:\n".($scopeText?implode("\n",$scopeText):'[DO UZUPEŁNIENIA: zakres w ofercie]'),
        'price'=>commercialMoney($net).' netto; VAT '.$vatRate.'%; '.commercialMoney($gross).' brutto.',
        'deposit'=>'Zaliczka: '.$depositRate.'% ceny brutto, tj. '.commercialMoney(round($gross*$depositRate/100,2)).' brutto.'.(trim($agreement['paymentSchedule']??'')!==''?"\n".$agreement['paymentSchedule']:''),
        'deadline'=>$deadline,
    ];
    $decisions=[];
    foreach($offer['decisionCoverage']??[] as $id=>$entry) if(($entry['target']??'')!=='') $decisions[$id]=$entry;
    $data=['policy'=>'2026-10-05.1','offerVersion'=>(int)($offer['version']??0),'offerId'=>$offer['offerId']??'',
        'scope'=>$scope,'exclusions'=>$exclusions,'pricing'=>['currency'=>'PLN','netCents'=>(int)round($net*100),'vatRate'=>$vatRate,'vatCents'=>(int)round(($gross-$net)*100),'grossCents'=>(int)round($gross*100)],
        'payment'=>['depositRate'=>$depositRate,'depositGrossCents'=>(int)round($gross*$depositRate)],'agreement'=>$agreement,'additionalTerms'=>(string)($offer['contractTerms']??''),'decisions'=>$decisions,'fields'=>$fields];
    if(($offer['status']??'')==='ACCEPTED'&&!empty($offer['acceptedPdfBase64'])) {
        $pdf=base64_decode($offer['acceptedPdfBase64'],true);
        if(!is_string($pdf)||!str_starts_with($pdf,'%PDF-')) throw new RuntimeException('Niepoprawny PDF zaakceptowanej oferty.');
        $data['offerPdfBase64']=$offer['acceptedPdfBase64'];$data['offerPdfSha256']=hash('sha256',$pdf);
        $data['fields']['scope'].="\nZakres i warunki realizacji określa także zaakceptowana oferta ".($offer['offerId']??'').' v'.(int)($offer['version']??0).', stanowiąca Załącznik 1 do umowy.';
    }
    $data['hash']=hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    return $data;
}
function contractCommercialDifferences(array $session,array $fields,array $facts,string $binding): array {
    $snapshot=contractCommercialSnapshot($session); $differences=[];
    foreach(contractTemplateFields() as $key) if(str_contains((string)($fields[$key]??''),'Ustalenia zaakceptowanej oferty:')) $differences[]='Usuń stare dopisane warunki z pola „'.contractFields()[$key].'”; aktualne są w załączniku oferty';
    if(!hash_equals($snapshot['hash'],$binding)) $differences[]='Wersja / pakiet zaakceptowanej oferty — wczytaj aktualne ustalenia';
    foreach($snapshot['fields'] as $key=>$expected) if(trim((string)($fields[$key]??''))!==trim($expected)) $differences[]=contractFields()[$key];
    $differences=array_merge($differences,contractOfferFactDifferences($session,$facts));
    foreach(['ipMode','dataRole','rightsTerms'] as $key) {
        $expected=$snapshot['agreement'][$key]??'';
        if($expected!==''&&trim((string)($facts[$key]??''))!==trim($expected)) $differences[]=contractFacts()[$key]['label'];
    }
    // Explicit contradictions in historical text must be resolved in the offer, not silently accepted.
    $rights=mb_strtolower($snapshot['agreement']['rightsSummary']??'');
    if(preg_match('/licencj[aię]\s+niewyłącz/iu',$rights)&&($facts['ipMode']??'')!=='nonexclusive') $differences[]='Opis praw w ofercie wymaga licencji niewyłącznej';
    if(preg_match('/licencj[aię]\s+wyłącz/iu',$rights)&&($facts['ipMode']??'')!=='exclusive') $differences[]='Opis praw w ofercie wymaga licencji wyłącznej';
    if(preg_match('/przeniesieni[ae]\s+(?:autorskich\s+)?praw/iu',$rights)&&($facts['ipMode']??'')!=='transfer') $differences[]='Opis praw w ofercie wymaga przeniesienia praw';
    $data=$snapshot['agreement']['dataPlan']??'';
    if(preg_match('/(?:powierzenie (?:jest )?wymagane|wymagane (?:jest )?powierzenie|wykonawca przetwarza dane w imieniu klienta)/iu',$data)&&($facts['dataRole']??'')!=='processor') $differences[]='Opis danych w ofercie wymaga ustalenia powierzenia';
    if(preg_match('/(?:bez przetwarzania|brak przetwarzania) w imieniu klienta/iu',$data)&&($facts['dataRole']??'')!=='none') $differences[]='Opis danych w ofercie wskazuje brak przetwarzania w imieniu klienta';
    return array_values(array_unique($differences));
}
function contractCommercialSections(array $snapshot): array {
    require_once __DIR__.'/offer-agreement.php';
    $sections=['Źródło'=>'Zaakceptowana oferta '.($snapshot['offerId']??'').' v'.($snapshot['offerVersion']??0).'. Identyfikator uzgodnień: '.($snapshot['hash']??'')];
    if(!empty($snapshot['offerPdfSha256'])) $sections['Oryginalna oferta — załącznik do umowy']='PDF zaakceptowanej oferty dołączony na końcu pakietu. SHA-256: '.$snapshot['offerPdfSha256'].'. Umowa opiera się na wskazanym numerze i wersji oferty, nie na późniejszych zmianach szkicu.';
    foreach(['scope'=>'Zakres realizacji','price'=>'Wynagrodzenie','deposit'=>'Płatności','deadline'=>'Harmonogram'] as $key=>$label) $sections[$label]=$snapshot['fields'][$key]??'';
    $outside=[]; foreach($snapshot['exclusions']??[] as $s) $outside[]=($s['title']??'Wyłączenia').":\n- ".implode("\n- ",$s['items']??[]);
    $sections['Poza bieżącym zakresem']=implode("\n",$outside)?:'Elementy niewymienione w zamawianym zakresie wymagają osobnego uzgodnienia.';
    if(trim($snapshot['additionalTerms']??'')!=='') $sections['Dodatkowe warunki oferty']=$snapshot['additionalTerms'];
    foreach($snapshot['agreement']??[] as $key=>$value) {
        if(!is_string($value)||trim($value)==='') continue;
        $field=offerAgreementFields()[$key]??['label'=>$key];
        if(!offerAgreementApplicable($key,$snapshot['agreement'])) continue;
        $sections[$field['label']]=$field['options'][$value]??$value;
    }
    $number=0;
    foreach($snapshot['decisions']??[] as $id=>$entry) $sections['Ustalenie '.(++$number).' — '.($entry['targetLabel']??$entry['target'])]=($entry['question']??'')."\n".preg_replace('/^\s*Hipoteza do zatwierdzenia:\s*/iu','',(string)($entry['answer']??''));
    return $sections;
}
function contractCommercialEditor(array $snapshot, array $saved, string $sessionId): string {
    $e='contractEscape'; $old=$saved['commercialSnapshot']['hash']??'';
    $binding=$saved?$old:$snapshot['hash'];
    $html='<section data-commercial-panel style="padding:16px;border:1px solid #5368c7;border-radius:12px"><h4>Warunki zaakceptowanej oferty</h4><p>Oferta v'.(int)$snapshot['offerVersion'].'. Zakres, cena, płatności i harmonogram pochodzą z oferty. Zmiany negocjuj w nowej wersji oferty. Klauzule wzoru są edytowane osobno.</p>';
    $html.='<input type="hidden" name="commercialHash" value="'.$e($binding).'">';
    if($binding!==$snapshot['hash']) $html.='<p data-commercial-warning style="color:#ffba80">Zapisana umowa nie jest powiązana z aktualnym pakietem oferty. PDF jest zablokowany do uzgodnienia różnic.</p>';
    $html.='<button class="button" type="submit" name="contract_action" value="sync-offer" formnovalidate>Wczytaj aktualne ustalenia oferty i sprawdź różnice</button><div data-commercial-diff></div><details><summary>Pełny pakiet warunków — trafi do załącznika umowy</summary><div data-commercial-preview>';
    foreach(contractCommercialSections($snapshot) as $label=>$text) $html.='<h5>'.$e($label).'</h5><p style="white-space:pre-wrap">'.$e($text).'</p>';
    return $html.'</div></details></section>';
}
