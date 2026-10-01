<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-model.php';
$id=bin2hex(random_bytes(16));
$gateId=bin2hex(random_bytes(16));
$db=projectDb();
try {
    projectSave($id,['startedAt'=>time(),'sourceContractVersion'=>1,'plan'=>['tasks'=>[]]]);
    $db->prepare("INSERT INTO project_jobs(session_id,kind,state,input,result,created_at,updated_at) VALUES(?,'create_repository','done','{}','{}',?,?)")->execute([$id,time(),time()]);
    $db->prepare("INSERT INTO project_agent_tasks(session_id,task_key,title,role,dependencies,acceptance,state,runner_phase,result,updated_at) VALUES(?,'frontend','Zbuduj interfejs','frontend','[]','[]','running','waiting_merge',?,?)")->execute([$id,json_encode(['summary'=>'Kod gotowy','pullRequestUrl'=>'https://github.com/Grzywniak/project/pull/1']),time()]);
    $session=['id'=>$id,'status'=>'COMPLETED','offer'=>['status'=>'ACCEPTED','version'=>1],'contract'=>['version'=>1,'offerVersion'=>1],'projectState'=>['businessProblem'=>'Przykład','contactName'=>'Klient']];
    $snapshot=projectSnapshot($session);
    if($snapshot['status']['build']!=='needs_you' || $snapshot['agentTasks'][0]['runner_phase']!=='waiting_merge') throw new RuntimeException('PR oczekujący na przegląd nie jest widoczny na mapie.');
    $offerSession=array_replace($session,['internalAnalysis'=>['status'=>'COMPLETED','missingInformation'=>['Budżet testowy']]]);
    $offerSession['offer']=['status'=>'DRAFT','version'=>1];
    $blockedOffer=projectSnapshot($offerSession);
    if($blockedOffer['status']['offer']!=='needs_you') throw new RuntimeException('Oferta nie wskazuje decyzji administratora, gdy analiza ma nierozstrzygnięte braki.');
    $offerSession['adminDecisions']=['Budżet testowy'=>['source'=>'HUMAN','answer'=>'Nieustalony; nie wpisywać do oferty.']];
    if(projectSnapshot($offerSession)['status']['offer']!=='ready') throw new RuntimeException('Rozstrzygnięcie brakującej informacji nie odblokowało etapu oferty.');
    $offerSession['adminDecisions']=[];
    $offerSession['internalAnalysis']['missingInformation']=[];
    foreach(['DRAFT'=>'ready','REVIEWED'=>'needs_you','SENT'=>'needs_you','ACCEPTED'=>'done'] as $offerStatus=>$expected) {
        $offerSession['offer']=['status'=>$offerStatus];
        $snapshot=projectSnapshot($offerSession);
        if($snapshot['status']['offer']!==$expected) throw new RuntimeException('Niepoprawny stan etapu oferty dla '.$offerStatus.': '.$snapshot['status']['offer']);
        $expectedContract=$offerStatus==='ACCEPTED'?'needs_you':'locked';
        if($snapshot['status']['contract']!==$expectedContract) throw new RuntimeException('Umowa odblokowała się przed akceptacją oferty: '.$offerStatus);
    }
    $offerSession['offer']=['status'=>'ACCEPTED','version'=>2];
    $offerSession['contract']=['version'=>3,'offerVersion'=>1];
    $gateSession=$offerSession; $gateSession['id']=$gateId; projectSave($gateId,['contractSignedVersion'=>3]);
    $staleContractSnapshot=projectSnapshot($gateSession);
    if($staleContractSnapshot['status']['contract']!=='needs_you' || $staleContractSnapshot['status']['kickoff']!=='locked') throw new RuntimeException('Umowa do starszej wersji oferty nie może odblokować startu projektu.');
    $offerSession['contract']['offerVersion']=2;
    $gateSession['contract']=$offerSession['contract'];
    $matchingContractSnapshot=projectSnapshot($gateSession);
    if($matchingContractSnapshot['status']['contract']!=='done' || $matchingContractSnapshot['status']['kickoff']!=='needs_you') throw new RuntimeException('Umowa do aktualnej zaakceptowanej oferty nie odblokowała prawidłowo kolejnego etapu.');
    $projectApiSource=(string)file_get_contents(__DIR__.'/../api/project-api.php');
    if(substr_count($projectApiSource,'projectContractMatchesAcceptedOffer($session)')!==2) throw new RuntimeException('Potwierdzenie umowy i start projektu muszą wymagać umowy zgodnej z aktualną zaakceptowaną ofertą.');
    $db->prepare("UPDATE project_jobs SET result=? WHERE session_id=? AND kind='create_repository'")->execute([json_encode(['ci'=>'awaiting_project_scaffold']),$id]);
    $db->prepare("INSERT INTO project_jobs(session_id,kind,state,input,error,created_at,updated_at) VALUES(?,'configure_scaffold','failed','{}','Brak workflow',?,?)")->execute([$id,time(),time()]);
    $snapshot=projectSnapshot($session);
    if($snapshot['status']['environment']!=='error') throw new RuntimeException('Błąd przygotowania CI nie jest widoczny na mapie.');
    echo "Project review map status OK\n";
} finally {
    $db->prepare('DELETE FROM project_agent_tasks WHERE session_id=?')->execute([$id]);
    $db->prepare('DELETE FROM project_jobs WHERE session_id=?')->execute([$id]);
    $db->prepare('DELETE FROM project_cases WHERE session_id=?')->execute([$id]);
    $db->prepare('DELETE FROM project_cases WHERE session_id=?')->execute([$gateId]);
}
