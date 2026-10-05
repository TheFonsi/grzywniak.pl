<?php
declare(strict_types=1);
require_once __DIR__.'/decision-ledger.php';

/** Immutable, session-local snapshots. Sources always identify captured content, never live data. */
function documentArchivePut(array &$session, string $kind, array $data, array $sources = [], ?int $version = null): string {
    unset($data['documentRef'], $data['pdfBase64'], $data['_versions']);
    $record=['kind'=>$kind,'version'=>$version??(int)($data['version']??1),'data'=>$data,'sources'=>$sources];
    $ref=hash('sha256',json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    if(!isset($session['documentArchive'][$ref])) $session['documentArchive'][$ref]=$record+['capturedAt'=>time()];
    return $ref;
}
function documentBriefSource(array &$session): string {
    $data=['summary'=>$session['summary']??null,'projectState'=>$session['projectState']??[],'riskFlags'=>$session['riskFlags']??[]];
    $version=0;
    foreach($session['documentArchive']??[] as $ref=>$record) if(($record['kind']??'')==='brief') {
        $version=max($version,(int)$record['version']);
        if(($record['data']??null)===$data) return $ref;
    }
    return documentArchivePut($session,'brief',$data,[],$version+1);
}
function documentAnalysisSource(array &$session): string {
    $analysis=$session['internalAnalysis']??[];
    return documentArchivePut($session,'analysis',$analysis,$analysis['sourceRefs']??[]);
}
function documentOfferSources(array &$session): array {
    // Capture both actual inputs of this generation, including manual decisions in the analysis snapshot.
    $analysis=$session['internalAnalysis']??[];
    $analysis['confirmedDecisions']=$session['adminDecisions']??[];
    $analysis['decisionLedger']=decisionLedgerActive($session);
    return ['brief'=>documentBriefSource($session),'analysis'=>documentArchivePut($session,'analysis',$analysis,$analysis['sourceRefs']??[])];
}
function documentContractSources(array &$session): array {
    $offer=$session['offer']??[];
    return ['offer'=>documentArchivePut($session,'offer',$offer,$offer['sourceRefs']??[])]+($offer['sourceRefs']??[]);
}
function documentArchiveCurrent(array &$session): void {
    foreach(['analysis'=>'internalAnalysis','offer'=>'offer','contract'=>'contract'] as $kind=>$key) {
        if(is_array($session[$key]??null) && isset($session[$key]['sourceRefs'])) {
            $session[$key]['documentRef']=documentArchivePut($session,$kind,$session[$key],$session[$key]['sourceRefs']);
        }
    }
}
function documentHistoryUrl(string $sessionId, ?string $ref=null): string {
    return apiPath('document-history.php').'?session='.rawurlencode($sessionId).($ref!==null?'&ref='.rawurlencode($ref):'');
}
function documentSourceLinks(array $document, string $sessionId): string {
    $e=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $labels=['brief'=>'Brief','analysis'=>'Analiza','offer'=>'Oferta','preparation'=>'Opracowanie oferty'];
    $html='<div class="document-sources" style="padding:12px;border:1px solid #354563;border-radius:10px;margin:12px 0"><strong>Na podstawie:</strong> ';
    $links=[];
    foreach($document['sourceRefs']??[] as $kind=>$ref) if(isset($labels[$kind])) $links[]='<a target="_blank" rel="noopener" href="'.$e(documentHistoryUrl($sessionId,$ref)).'">'.$labels[$kind].' — zapisana wersja ↗</a>';
    $html.= $links?implode(' · ',$links):'Brak zapisanych powiązań dla tej historycznej wersji.';
    return $html.'<br><a target="_blank" rel="noopener" href="'.$e(documentHistoryUrl($sessionId)).'">Archiwum dokumentów i wersji ↗</a></div>';
}
