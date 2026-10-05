<?php
declare(strict_types=1);

function offerSourceInputs(array $session):array {
    $analysis=$session['internalAnalysis']??[];
    // Archive references are added during persistence and are not a change of requirements.
    unset($analysis['documentRef'],$analysis['sourceRefs'],$analysis['confirmedDecisions'],$analysis['decisionLedger']);
    return [$session['projectState']??[],$analysis,$session['adminDecisions']??[]];
}
function offerStableSourceHash(array $session):string {return hash('sha256',json_encode(offerSourceInputs($session),JSON_UNESCAPED_UNICODE));}
function offerReconcileSource(array &$session):void {
    if(!is_array($session['offer']??null)) return;
    $offer=&$session['offer'];$current=offerStableSourceHash($session);
    $stored=$offer['sourceHash']??'';
    $raw=hash('sha256',json_encode([$session['projectState']??[],$session['internalAnalysis']??[],$session['adminDecisions']??[]],JSON_UNESCAPED_UNICODE));
    $matches=$stored===$current||$stored===$raw;
    if(!$matches) {
        $brief=$session['documentArchive'][$offer['sourceRefs']['brief']??'']['data']??null;
        $analysis=$session['documentArchive'][$offer['sourceRefs']['analysis']??'']['data']??null;
        $decisions=$offer['sourceSnapshot']['adminDecisions']??$analysis['confirmedDecisions']??null;
        if(is_array($brief)&&is_array($analysis)&&is_array($decisions)) $matches=offerStableSourceHash(['projectState'=>$brief['projectState']??[],'internalAnalysis'=>$analysis,'adminDecisions'=>$decisions])===$current;
    }
    if($matches) {
        $offer['sourceHash']=$current;
        $offer['analysisVersion']=(int)($session['internalAnalysis']['version']??0);
        if(($offer['status']??'')==='OUTDATED') {
            // Never restore acceptance automatically after repairing technical metadata.
            $offer['status']='DRAFT';$offer['needsHumanReview']=true;
            unset($offer['reviewedAt'],$offer['verification'],$offer['acceptedAt'],$offer['acceptedBy'],$offer['sentAt'],$offer['sentTo'],$offer['commercialSnapshot']);
        }
        unset($offer['outdatedReason'],$offer['outdatedAt']);return;
    }
    $offer['status']='OUTDATED';
    $old=(int)($offer['analysisVersion']??0);$new=(int)($session['internalAnalysis']['version']??0);
    $offer['outdatedReason']=$old!==$new?'Oferta v'.(int)($offer['version']??0).' pochodzi z analizy v'.$old.', a aktualna analiza ma wersję v'.$new.'.':'Od utworzenia tej oferty zmienił się brief lub ręczne ustalenia analizy.';
}
