<?php
declare(strict_types=1);

/** Drafts from recorded statements; never a substitute for manual legal review. */
function contractOfferScopeSections(array $offer):array {
    $sections=array_values(array_filter($offer['sections']??[],static fn($s)=>is_array($s)));
    $scope=array_values(array_filter($sections,static fn($s)=>preg_match('/^zakres realizacji$|^przedmiot projektu$|^scope$/iu',trim($s['title']??''))));
    return $scope?:array_values(array_filter($sections,static fn($s)=>!preg_match('/poza|opcjonal|później|cel i kontekst|planowany termin|oczekiwany termin|optional|out of scope/iu',$s['title']??'')));
}
function contractOfferFactDifferences(array $session,array $facts):array {
    if(($session['offer']['status']??'')!=='ACCEPTED') return [];
    $differences=[];
    foreach($session['offer']['agreement']??[] as $key=>$value) {
        if(!array_key_exists($key,contractFacts())||!is_string($value)||trim($value)==='') continue;
        if(!contractPackageApplicable($key,$facts)||!contractPublicationFieldApplicable($key,$facts)) continue;
        if(trim((string)($facts[$key]??''))!==trim($value)) $differences[]=contractFacts()[$key]['label'];
    }
    return $differences;
}
function contractBriefProposal(array $session,array $current=[]): array {
    $state=is_array($session['projectState']??null)?$session['projectState']:[];
    $facts=[]; $sources=[];
    $copy=static function(string $target,string $source) use (&$facts,&$sources,$state):void {
        $value=$state[$source]??null;
        if(!is_string($value)||trim($value)===''||preg_match('/^(do ustalenia|nie wiem|unknown|undecided)$/iu',trim($value))) return;
        $facts[$target]=mb_substr(trim($value),0,2000); $sources[$target]='Brief: '.(['materialsTerms'=>'materiały i współpraca','domainName'=>'podana nazwa domeny'][$source]??$source);
    };
    $copy('cooperationTerms','materialsTerms');
    $copy('productionDomain','domainName');
    $preference=$state['publicationPreference']??'';
    if(in_array($preference,['agency','agency_purchase','client_handoff'],true)) {
        $facts['publicationDestination']=$preference; $sources['publicationDestination']='Brief: preferowany sposób publikacji';
    }
    // Existing briefs can supply an explicitly confirmed answer from analysis.
    if(empty($facts['cooperationTerms'])) foreach(($session['adminDecisions']??[]) as $question=>$decision) {
        if(!is_array($decision)||($decision['source']??'')!=='HUMAN'||!is_string($decision['answer']??null)||trim($decision['answer'])==='') continue;
        if(preg_match('/materiał|materia[lł]|logo|zdję|tekst[yó]|content|photos/iu',(string)$question)) {
            $facts['cooperationTerms']=mb_substr(trim($decision['answer']),0,20000);
            $sources['cooperationTerms']='Ręcznie potwierdzona odpowiedź analizy: '.(string)$question; break;
        }
    }
    $scope=[];
    foreach(contractOfferScopeSections($session['offer']??[]) as $section) {
        if(!is_array($section)) continue;
        $items=array_filter($section['items']??[],static fn($v)=>is_string($v)&&trim($v)!=='');
        if($items) $scope[]=($section['title']??'Zakres').":\n- ".implode("\n- ",$items);
    }
    if($scope && ($session['offer']['status']??'')==='ACCEPTED') {
        $facts['acceptanceCriteria']=mb_substr("Propozycja kryteriów do sprawdzenia na podstawie zaakceptowanej oferty:\n".implode("\n\n",$scope),0,20000);
        $sources['acceptanceCriteria']='Zaakceptowana oferta — propozycja wymaga doprecyzowania mierzalnych wyników';
    }
    // Accepted offer is the commercial snapshot; later brief edits cannot replace it.
    if(($session['offer']['status']??'')==='ACCEPTED'&&is_array($session['offer']['agreement']??null)) {
        foreach($session['offer']['agreement'] as $key=>$value) if(array_key_exists($key,contractFacts())&&is_string($value)&&trim($value)!=='') {
            $facts[$key]=$value;$sources[$key]='Zaakceptowana oferta v'.(int)($session['offer']['version']??1).' — '.contractFacts()[$key]['label'];
        }
    }
    foreach($facts as $key=>$value) if(trim((string)($current[$key]??''))!=='') { unset($facts[$key],$sources[$key]); }
    return ['facts'=>$facts,'sources'=>$sources];
}
