<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-model.php';
$id=bin2hex(random_bytes(16));
$db=projectDb();
try {
    projectSave($id,['startedAt'=>time(),'sourceContractVersion'=>1,'plan'=>['tasks'=>[]]]);
    $db->prepare("INSERT INTO project_jobs(session_id,kind,state,input,result,created_at,updated_at) VALUES(?,'create_repository','done','{}','{}',?,?)")->execute([$id,time(),time()]);
    $db->prepare("INSERT INTO project_agent_tasks(session_id,task_key,title,role,dependencies,acceptance,state,runner_phase,result,updated_at) VALUES(?,'frontend','Zbuduj interfejs','frontend','[]','[]','running','waiting_merge',?,?)")->execute([$id,json_encode(['summary'=>'Kod gotowy','pullRequestUrl'=>'https://github.com/Grzywniak/project/pull/1']),time()]);
    $session=['id'=>$id,'status'=>'COMPLETED','contract'=>['version'=>1],'projectState'=>['businessProblem'=>'Przykład','contactName'=>'Klient']];
    $snapshot=projectSnapshot($session);
    if($snapshot['status']['build']!=='needs_you' || $snapshot['agentTasks'][0]['runner_phase']!=='waiting_merge') throw new RuntimeException('PR oczekujący na przegląd nie jest widoczny na mapie.');
    $db->prepare("UPDATE project_jobs SET result=? WHERE session_id=? AND kind='create_repository'")->execute([json_encode(['ci'=>'awaiting_project_scaffold']),$id]);
    $db->prepare("INSERT INTO project_jobs(session_id,kind,state,input,error,created_at,updated_at) VALUES(?,'configure_scaffold','failed','{}','Brak workflow',?,?)")->execute([$id,time(),time()]);
    $snapshot=projectSnapshot($session);
    if($snapshot['status']['environment']!=='error') throw new RuntimeException('Błąd przygotowania CI nie jest widoczny na mapie.');
    echo "Project review map status OK\n";
} finally {
    $db->prepare('DELETE FROM project_agent_tasks WHERE session_id=?')->execute([$id]);
    $db->prepare('DELETE FROM project_jobs WHERE session_id=?')->execute([$id]);
    $db->prepare('DELETE FROM project_cases WHERE session_id=?')->execute([$id]);
}
