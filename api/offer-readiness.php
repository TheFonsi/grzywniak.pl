<?php
declare(strict_types=1);

/** Shared source of truth for the offer and project map manual-confirmation gate. */
function missingInformationManuallyConfirmed(array $session): bool {
    $decisions=is_array($session['adminDecisions']??null)?$session['adminDecisions']:[];
    $normalized=[];
    foreach($decisions as $question=>$decision) $normalized[trim((string)$question)]=$decision;
    foreach(($session['internalAnalysis']['missingInformation']??[]) as $question) {
        $question=trim((string)$question);
        if($question==='') continue;
        $decision=$normalized[$question]??null;
        if(!is_array($decision)||($decision['source']??'')!=='HUMAN'||trim((string)($decision['answer']??''))==='') return false;
    }
    return true;
}
