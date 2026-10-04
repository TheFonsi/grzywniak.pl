<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/contract-model.php';
function briefAssert(bool $ok,string $message):void { if(!$ok) throw new RuntimeException($message); }
$s=['projectState'=>['materialsTerms'=>'Klient dostarcza logo; teksty przygotowuje zespół.','publicationPreference'=>'agency_purchase','domainName'=>'example.test'], 'offer'=>['status'=>'ACCEPTED','sections'=>[['title'=>'Strona','items'=>['Kontakt telefoniczny','Prezentacja oferty']]]]];
$p=contractBriefProposal($s);
briefAssert($p['facts']['cooperationTerms']===$s['projectState']['materialsTerms'],'Copy real materials statement');
briefAssert($p['facts']['publicationDestination']==='agency_purchase','Copy explicit preference');
briefAssert(str_contains($p['facts']['acceptanceCriteria'],'Kontakt telefoniczny'),'Proposal derives from accepted scope');
briefAssert(!isset($p['facts']['hostingFee'],$p['facts']['rightsInventory'],$p['facts']['processingSecurity']),'Never fabricate legal or infrastructure facts');
$p=contractBriefProposal($s,['cooperationTerms'=>'Admin edit','publicationDestination'=>'client_handoff']);
briefAssert(!isset($p['facts']['cooperationTerms'],$p['facts']['publicationDestination']),'Never replace current values');
$s['offer']['status']='DRAFT';$s['projectState']['materialsTerms']='Do ustalenia';$s['projectState']['publicationPreference']='Own domain with agency hosting';
$p=contractBriefProposal($s);
briefAssert(!isset($p['facts']['cooperationTerms'],$p['facts']['acceptanceCriteria'],$p['facts']['publicationDestination']),'Unknown terms and unaccepted scope stay empty');
$s['adminDecisions']=['Czy dostarczy logo i zdjęcia?'=>['source'=>'AI','answer'=>'Hypothesis']];
briefAssert(!isset(contractBriefProposal($s)['facts']['cooperationTerms']),'Do not use unconfirmed AI hypothesis');
$s['adminDecisions']['Czy dostarczy logo i zdjęcia?']['source']='HUMAN';
briefAssert(contractBriefProposal($s)['facts']['cooperationTerms']==='Hypothesis','Use manually confirmed legacy answer');
echo "Brief-to-contract proposal checks passed\n";
$s['offer']['status']='ACCEPTED';$s['offer']['agreement']=['acceptanceCriteria'=>'Actual offered tests','cooperationTerms'=>'Accepted material conditions','acceptanceDays'=>'14'];
$s['offer']['sections']=[['title'=>'Zakres realizacji','items'=>['Included A']],['title'=>'Poza obecnym zakresem / możliwe później','items'=>['Excluded B']]];
$s['projectState']['materialsTerms']='Later brief change';
$p=contractBriefProposal($s);
briefAssert($p['facts']['acceptanceCriteria']==='Actual offered tests'&&$p['facts']['cooperationTerms']==='Accepted material conditions','Accepted offer overrides brief and generated criteria');
briefAssert(count(contractOfferScopeSections($s['offer']))===1,'Optional work is excluded from contractual scope');
briefAssert(contractOfferFactDifferences($s,$p['facts'])===[],'Matching offered terms pass');
$p['facts']['acceptanceDays']='7';briefAssert(count(contractOfferFactDifferences($s,$p['facts']))===1,'Changing negotiated days requires a new offer');
