<?php
declare(strict_types=1);
require_once __DIR__.'/../api/offer-source.php';
function sourceCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$s=['projectState'=>['mustHaveFeatures'=>'Strona'], 'internalAnalysis'=>['status'=>'COMPLETED','version'=>2,'summary'=>'Analiza','sourceRefs'=>['brief'=>'b']], 'adminDecisions'=>[]];
$raw=hash('sha256',json_encode([$s['projectState'],$s['internalAnalysis'],$s['adminDecisions']],JSON_UNESCAPED_UNICODE));
$s['offer']=['version'=>7,'analysisVersion'=>2,'status'=>'DRAFT','sourceHash'=>$raw,'sourceRefs'=>['brief'=>'b','analysis'=>'a']];
$s['documentArchive']=['b'=>['data'=>['projectState'=>$s['projectState']]],'a'=>['data'=>$s['internalAnalysis']+['confirmedDecisions'=>[],'decisionLedger'=>[]]]];
$original=$s;$s['internalAnalysis']['documentRef']='archive-reference-added-during-write';
offerReconcileSource($s);sourceCheck($s['offer']['status']==='DRAFT','An archive reference must not invalidate offer v7');
sourceCheck($s['offer']['sourceHash']===offerStableSourceHash($s),'Old raw hashes migrate after proving source identity');
$s['offer']['status']='OUTDATED';offerReconcileSource($s);sourceCheck($s['offer']['status']==='DRAFT','A technical false alarm is repaired without automatic acceptance');
$s['projectState']['mustHaveFeatures']='Sklep';offerReconcileSource($s);sourceCheck($s['offer']['status']==='OUTDATED','Real brief changes remain stale');
sourceCheck(str_contains($s['offer']['outdatedReason'],'brief'),'Explain the actual source change');
$s=$original;$s['internalAnalysis']['version']=3;offerReconcileSource($s);sourceCheck($s['offer']['status']==='OUTDATED'&&str_contains($s['offer']['outdatedReason'],'v2'),'New analysis version remains stale, regardless of offer version number');
echo "Offer source checks passed: archive-only migration, v7 compatibility and real source change detection.\n";
