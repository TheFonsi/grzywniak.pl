<?php
declare(strict_types=1);
require_once __DIR__.'/../api/offer-agreement.php';
function defaultCheck(bool $ok,string $message):void {if(!$ok) throw new RuntimeException($message);}
$s=['projectState'=>[],'internalAnalysis'=>['recommendedScope'=>['One page z telefonem']]];
$a=offerAgreementDraft($s);
defaultCheck(offerAgreementMissing(['agreement'=>$a,'pricing'=>['net'=>2000,'vatRate'=>23]])===[],'Default simple project is a complete reviewable offer proposal');
defaultCheck($a['publicationDestination']==='agency'&&$a['dataRole']==='none'&&$a['acceptanceDays']==='7','No unanswered select for the simple low cost variant');
defaultCheck(str_contains($a['externalCosts'],'najniższym koszcie')&&str_contains($a['cooperationTerms'],'bez dodatkowo płatnych materiałów'),'Avoid default paid additions');
defaultCheck(!str_contains(json_encode($a,JSON_UNESCAPED_UNICODE),'[DO UZUPEŁNIENIA:'),'No placeholder clauses');
$legacy=['acceptanceCriteria'=>'Widoki: [DO UZUPEŁNIENIA: scenariusze]','publicationDestination'=>'','ipMode'=>'','acceptanceDays'=>'14','cooperationTerms'=>'Moje ustalenie z klientem'];
$offer=['sections'=>[['title'=>'Zakres realizacji','items'=>['Kontakt telefoniczny']]],'agreement'=>$legacy];
$prepared=offerAgreementPrepared($s,$legacy,$offer);
defaultCheck($prepared['acceptanceDays']==='14'&&$prepared['cooperationTerms']===$legacy['cooperationTerms'],'Keep concrete saved conditions');
defaultCheck(str_contains($prepared['acceptanceCriteria'],'Kontakt telefoniczny'),'Use actual scope for legacy placeholder criteria');
defaultCheck($offer['agreement']===$legacy,'Preparing suggestions does not mutate the saved offer');
$app=offerAgreementPrepared($s,[],['sections'=>[['title'=>'Zakres realizacji','items'=>['Aplikacja z kontami użytkowników i formularzem']]]]);
defaultCheck($app['dataRole']==='processor','Explicit accounts must not be silently reduced to no data processing');
$handoff=offerAgreementPrepared($s,['publicationDestination'=>'client_handoff','productionDomain'=>'klient.example','ipMode'=>'exclusive']);
defaultCheck($handoff['publicationDestination']==='client_handoff'&&$handoff['productionDomain']==='klient.example','Known deployment instructions take precedence');
defaultCheck(offerAgreementMissing(['agreement'=>$handoff,'pricing'=>['net'=>2000,'vatRate'=>23]])===[],'Known deployment and license receive complete standard proposal clauses');
$unknownDomain=offerAgreementPrepared($s,['publicationDestination'=>'agency_purchase']);
defaultCheck(in_array(offerAgreementFields()['productionDomain']['label'],offerAgreementMissing(['agreement'=>$unknownDomain,'pricing'=>['net'=>2000,'vatRate'=>23]]),true),'Never fabricate a domain name to bypass approval');
echo "Offer defaults passed: complete low cost proposals, legacy placeholders, priority of known terms and no invented domains.\n";
