<?php
declare(strict_types=1);
require_once __DIR__.'/../api/bootstrap.php';
require_once __DIR__.'/../api/offer-agreement.php';
require_once __DIR__.'/../api/contract-ai.php';
function flowCheck(bool $ok,string $message):void {if(!$ok) throw new RuntimeException($message);}

$s=['internalAnalysis'=>['status'=>'COMPLETED','version'=>1,'missingInformation'=>['Kto dostarczy zdjęcia?']],
    'adminDecisions'=>['Kto dostarczy zdjęcia?'=>['source'=>'HUMAN','answer'=>'Klient przekazuje zdjęcia w ciągu 5 dni.','at'=>100,'author'=>'owner']]];
normalizeSessionData($s);$ledger=$s['decisionLedger'];$id=array_key_first($ledger);
$s['internalAnalysis']['version']=2;$s['internalAnalysis']['missingInformation']=[];normalizeSessionData($s);
flowCheck($s['adminDecisions']!==[]&&$s['decisionLedger']===$ledger,'Resolved human decision must survive later analysis');
$s['adminDecisions']['Kto dostarczy zdjęcia?']['answer']='Klient przekazuje zdjęcia w ciągu 7 dni.';decisionLedgerSync($s);
flowCheck($s['decisionLedger'][$id]['version']===2&&$s['decisionLedgerHistory'][0]['answer']==='Klient przekazuje zdjęcia w ciągu 5 dni.','Decision edits require immutable revisions');
$coverage=offerDecisionCoverage($s);
flowCheck($coverage[$id]['target']===''&&$coverage[$id]['suggestedTarget']==='cooperationTerms','Suggested mapping is prepared without silently approving it');
flowCheck(offerAgreementMissing(['decisionCoverage'=>$coverage])!==[],'Unmapped human decision blocks offer review');
$coverage=offerDecisionCoverage($s,$coverage,[$id=>'cooperationTerms']);
flowCheck($coverage[$id]['targetLabel']===offerAgreementFields()['cooperationTerms']['label'],'Decision maps to specific offer and contract term');

$offer=['status'=>'ACCEPTED','version'=>2,'offerId'=>'OF-TEST-2','sections'=>[['title'=>'Zakres realizacji','items'=>['One page z kontaktem']]],
    'pricing'=>['net'=>9000,'vatRate'=>23,'gross'=>11070],'payment'=>['depositRate'=>50],'decisionCoverage'=>$coverage,
    'agreement'=>['deliverySchedule'=>'40 dni po otrzymaniu materiałów.','paymentSchedule'=>'Pozostała część po odbiorze, termin 7 dni.',
      'supportPlan'=>'Wsparcie 12 miesięcy.','rightsSummary'=>'Przeniesienie praw majątkowych po zapłacie.','externalCosts'=>'Koszty po akceptacji do 100 zł.','dataPlan'=>'Bez przetwarzania w imieniu klienta.',
      'ipMode'=>'transfer','dataRole'=>'none','acceptanceDays'=>'7','cooperationTerms'=>'Klient dostarcza zdjęcia.']];
$s['offer']=$offer;$snapshot=contractCommercialSnapshot($s);
flowCheck($snapshot['pricing']['grossCents']===1107000&&$snapshot['payment']['depositGrossCents']===553500,'Gross payment must use correct cents and deposit rate');
$facts=$offer['agreement'];flowCheck(contractCommercialDifferences($s,$snapshot['fields'],$facts,$snapshot['hash'])===[],'Canonical offer terms pass consistency gate');
foreach(['scope','price','deposit','deadline'] as $key) {
    $wrong=$snapshot['fields'];$wrong[$key]='OLD VALUE';flowCheck(contractCommercialDifferences($s,$wrong,$facts,$snapshot['hash'])!==[],'Modified '.$key.' must block PDF');
}
flowCheck(contractCommercialDifferences($s,$snapshot['fields'],$facts,'old-hash')!==[],'Stale commercial binding blocks PDF');
$wrong=$facts;$wrong['ipMode']='nonexclusive';flowCheck(contractCommercialDifferences($s,$snapshot['fields'],$wrong,$snapshot['hash'])!==[],'Rights mode contradiction blocks PDF');
$wrong=$facts;$wrong['dataRole']='processor';flowCheck(contractCommercialDifferences($s,$snapshot['fields'],$wrong,$snapshot['hash'])!==[],'Data role contradiction blocks PDF');
$dataConflict=$s;$dataConflict['offer']['agreement']['dataPlan']='Powierzenie jest wymagane.';$conflictSnapshot=contractCommercialSnapshot($dataConflict);
flowCheck(contractCommercialDifferences($dataConflict,$conflictSnapshot['fields'],$facts,$conflictSnapshot['hash'])!==[],'Explicit data-plan contradiction requires an admin decision');
$sections=contractCommercialSections($snapshot);
foreach(['deliverySchedule','paymentSchedule','supportPlan','rightsSummary','externalCosts','dataPlan'] as $key) flowCheck(in_array($offer['agreement'][$key],$sections,true),'Commercial annex must retain '.$key);
flowCheck(str_contains(implode("\n",$sections),$coverage[$id]['answer']),'Mapped human decision must reach contract annex');
$changed=$offer;$changed['sections'][0]['items']=['NOWY ZAKRES'];offerMarkDependentReview($offer,$changed);
flowCheck($changed['agreement']['acceptanceDays']==='7'&&count($changed['dependencyReview'])>5,'Scope edits preserve conditions but require dependency review');
flowCheck(offerAgreementMissing($changed)!==[],'Unreviewed dependencies must block acceptance');
$optional=$offer;$optional['sections'][]=['title'=>'Poza obecnym zakresem / możliwe później','items'=>['Sklep i system zamówień']];
flowCheck(contractSelectTemplate($optional)['id']==='website','Excluded shop must not select ecommerce template');
$input=contractAiContext($offer,['scope'=>'OLD','price'=>'OLD','deadline'=>'OLD','deposit'=>'OLD','provider'=>'PRIVATE PROVIDER','paymentDetails'=>'PRIVATE BANK'],$facts,[],[]);
flowCheck($input['offer']['agreement']===$offer['agreement']&&$input['commercialSnapshot']['hash']===$snapshot['hash'],'AI receives complete actual commercial terms');
// Legacy appended commercial fragments cannot remain hidden inside a legal clause.
$legacy=$snapshot['fields'];$legacy['support']="Klauzula\nUstalenia zaakceptowanej oferty: OLD SUPPORT";
flowCheck(contractCommercialDifferences($s,$legacy,$facts,$snapshot['hash'])!==[],'Old appended offer clauses require explicit cleanup');
$sample=contractSampleContract('website');$sample=array_replace($sample,$snapshot['fields']);$sample['commercialSnapshot']=$snapshot;
$sample['package']=contractPackageBuild($sample);$sample['pdfBase64']=base64_encode('test bytes');
$sample['package']['pdfSha256']=hash('sha256','test bytes');
$sample['package']['legalReview']=['packageHash'=>$sample['package']['hash'],'pdfSha256'=>$sample['package']['pdfSha256']];
$instructions=contractPackageExecutionInstructions(['contract'=>$sample],['sourceContractVersion'=>1,'contractSignedPackageHash'=>$sample['package']['hash']]);
foreach(['Wsparcie 12 miesięcy.','Koszty po akceptacji do 100 zł.',$coverage[$id]['answer']] as $term) flowCheck(str_contains(implode("\n",$instructions),$term),'Signed conditions must reach execution agents: '.$term);
flowCheck(count($input['conflicts'])>=4&&!isset($input['draft']['provider'],$input['draft']['paymentDetails']),'AI receives difference report without private provider or bank details');
echo "Document flow checks passed: all seven audited scenarios, decision revisions, commercial annex, money and AI context.\n";
