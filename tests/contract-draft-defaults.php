<?php
declare(strict_types=1);
require __DIR__.'/../api/contract-model.php';
function check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
$session=['offer'=>['status'=>'ACCEPTED','sections'=>[['title'=>'Zakres realizacji','items'=>['One page: oferta, realizacje i kontakt telefoniczny']]],'agreement'=>['acceptanceDays'=>'14','cooperationTerms'=>'Klient przekazuje logo do 10 dni.','publicationDestination'=>'client_handoff']]];
$profile=['privacyRecipients'=>'Poczta i księgowość: rzeczywisty wykaz z profilu.'];
$facts=contractCompleteDraftFacts($session,$profile,['acceptanceCriteria'=>'[DO UZUPEŁNIENIA: scenariusze]','publicationDestination'=>'client_handoff']);
check($facts['acceptanceDays']==='14','Accepted offer must win over default days');
check($facts['cooperationTerms']===$session['offer']['agreement']['cooperationTerms'],'Accepted material terms must remain exact');
check(str_contains($facts['acceptanceCriteria'],'kontakt telefoniczny')&&str_contains($facts['acceptanceCriteria'],'360, 768 i 1440'),'Complete criteria must use project scope and actual proposed checks');
check($facts['obligationKind']==='result','Handoff proposes a specified result');
check($facts['privacyRecipients']===$profile['privacyRecipients'],'Reusable real profile data must populate drafts');
check(empty($facts['rightsInventory'])&&empty($facts['legalStatusBasis']),'Never fabricate ownership or legal status');
$custom=contractCompleteDraftFacts($session,$profile,['acceptanceCriteria'=>'Własne kryteria: widoki 320 i 1280 px.','obligationKind'=>'service','acceptanceDays'=>'21']);
check($custom['acceptanceDays']==='21'&&$custom['obligationKind']==='service'&&str_contains($custom['acceptanceCriteria'],'320'),'Preserve meaningful manual edits');
foreach(contractPackageFields() as $key=>$field) foreach(contractContextSuggestions($key,$session,$facts) as $value) check(!str_contains($value,'DO UZUPEŁNIENIA'),'No incomplete examples in suggestions: '.$key);
check(contractContextSuggestions('acceptanceCriteria',$session,$facts)!==[],'Ready project criteria must be available');
echo "Contract draft defaults passed: offer priority, complete criteria, manual preservation, factual limits and complete suggestions.\n";
