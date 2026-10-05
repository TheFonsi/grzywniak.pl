<?php
declare(strict_types=1);

/** Human decisions outlive questions. Each edit appends a revision rather than deleting evidence. */
function decisionLedgerSync(array &$session): void {
    foreach($session['adminDecisions']??[] as $question=>$decision) {
        if(!is_array($decision)||($decision['source']??'')!=='HUMAN'||trim((string)($decision['answer']??''))==='') continue;
        $question=trim((string)$question); $answer=trim($decision['answer']);
        $id=substr(hash('sha256',$question),0,24);
        $previous=$session['decisionLedger'][$id]??null;
        if(is_array($previous)&&$previous['answer']===$answer) continue;
        $entry=['id'=>$id,'question'=>$question,'answer'=>$answer,'source'=>'HUMAN','status'=>'active',
            'version'=>(int)($previous['version']??0)+1,'author'=>(string)($decision['author']??$_SERVER['PHP_AUTH_USER']??'administrator'),
            'createdAt'=>(int)($previous['createdAt']??$decision['at']??time()),'updatedAt'=>(int)($decision['at']??time()),
            'analysisVersion'=>(int)($decision['analysisVersion']??$session['internalAnalysis']['version']??0)];
        if(is_array($previous)) $session['decisionLedgerHistory'][]=$previous;
        $session['decisionLedger'][$id]=$entry;
    }
}
function decisionLedgerActive(array $session): array {
    decisionLedgerSync($session);
    return array_filter($session['decisionLedger']??[],static fn($entry)=>($entry['status']??'')==='active');
}
function decisionLedgerPanel(array $session): string {
    $entries=decisionLedgerActive($session); if(!$entries) return '';
    $e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $html='<details class="analysis" data-decision-ledger><summary>Trwałe ustalenia administratora ('.count($entries).')</summary><p>Ustalenia pozostają po ponownej analizie. Przypisz każde do oferty w „Warunki realizacji i dane do umowy”.</p>';
    foreach($entries as $id=>$entry) {
        $mapping=$session['offer']['decisionCoverage'][$id]??[];
        $target=($mapping['version']??0)===$entry['version']?($mapping['targetLabel']??'Nieprzypisane'): 'Wymaga ponownego przypisania';
        $html.='<section><strong>'.$e($entry['question']).'</strong><p style="white-space:pre-wrap">'.$e($entry['answer']).'</p><small>Ustalenie '.$e($id).' · wersja '.(int)$entry['version'].' · autor: '.$e($entry['author']).' · '.$e($target).'</small></section>';
    }
    return $html.'</details>';
}
