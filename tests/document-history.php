<?php
declare(strict_types=1);
require_once __DIR__.'/../api/document-history-model.php';
function verifyHistory(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$s=['id'=>str_repeat('b',32),'projectState'=>['businessProblem'=>'Original brief'],'summary'=>['sections'=>[['title'=>'Scope','content'=>['Original scope']]]]];
$brief=documentBriefSource($s);
verifyHistory(documentBriefSource($s)===$brief,'Same brief must reuse immutable snapshot');
$s['internalAnalysis']=['status'=>'COMPLETED','version'=>1,'summary'=>'Original analysis','sourceRefs'=>['brief'=>$brief]];
$sources=documentOfferSources($s);
$s['offer']=['version'=>1,'status'=>'ACCEPTED','summary'=>'Original offer','sourceRefs'=>$sources];
$contractSources=documentContractSources($s);
$s['contract']=['version'=>1,'status'=>'DRAFT','scope'=>'Original contract','sourceRefs'=>$contractSources,'pdfBase64'=>'must-not-be-copied'];
documentArchiveCurrent($s);
$before=$s['documentArchive'];
$s['projectState']['businessProblem']='Changed brief';
$s['internalAnalysis']['version']=2;
$s['internalAnalysis']['summary']='Changed analysis';
$s['internalAnalysis']['sourceRefs']=['brief'=>documentBriefSource($s)];
$s['offer']['version']=2; $s['offer']['summary']='Changed offer'; $s['offer']['sourceRefs']=documentOfferSources($s);
documentArchiveCurrent($s);
foreach($before as $ref=>$doc) verifyHistory($s['documentArchive'][$ref]===$doc,'Old snapshot changed');
verifyHistory($s['documentArchive'][$contractSources['brief']]['data']['projectState']['businessProblem']==='Original brief','Contract brief link followed live state');
verifyHistory($s['documentArchive'][$contractSources['analysis']]['data']['summary']==='Original analysis','Contract analysis link followed live state');
verifyHistory($s['documentArchive'][$contractSources['offer']]['data']['summary']==='Original offer','Contract offer link followed live state');
foreach($s['documentArchive'] as $record) verifyHistory(!isset($record['data']['pdfBase64']),'Archive duplicated PDF payload');
$legacy=['offer'=>['version'=>4,'summary'=>'Unknown old inputs'],'internalAnalysis'=>['summary'=>'Current analysis']];
$legacySources=documentContractSources($legacy);
verifyHistory(array_keys($legacySources)===['offer'],'Legacy contract sources must not invent old brief or analysis');
echo "Document history checks passed: immutable lineage, regeneration, legacy sources and compact snapshots.\n";
