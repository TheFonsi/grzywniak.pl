<?php
declare(strict_types=1);
require_once __DIR__.'/project-model.php';
require_once __DIR__.'/project-settings-model.php';
require_once __DIR__.'/project-provision.php';

function projectRunnerConfig(): array {
    $url=rtrim(trim(projectSetting('CODEX_RUNNER_URL')),'/');
    $token=projectSetting('CODEX_RUNNER_TOKEN');
    if($url==='' || $token==='' || !str_starts_with($url,'https://') || !filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('Skonfiguruj adres HTTPS i token osobnego runnera Codex.');
    return [$url,$token];
}

function projectTaskStage(string $role): string {
    return match($role) { 'ux','ui'=>'design','qa','security','performance','documentation'=>'qa',default=>'build' };
}

function projectTaskTotals(PDO $db,string $id): array {
    $stmt=$db->prepare('SELECT COALESCE(SUM(spent_pln+reserved_pln),0) FROM project_agent_tasks WHERE session_id=?');
    $stmt->execute([$id]);
    $project=(float)$stmt->fetchColumn();
    $stmt=$db->prepare('SELECT COALESCE(SUM(spent_pln+reserved_pln),0) FROM project_ai_calls WHERE session_id=?');
    $stmt->execute([$id]); $project+=(float)$stmt->fetchColumn();
    $month=strtotime(date('Y-m-01 00:00:00'));
    $stmt=$db->prepare('SELECT COALESCE(SUM(amount_pln),0) FROM project_agent_costs WHERE incurred_at>=?');
    $stmt->execute([$month]); $monthly=(float)$stmt->fetchColumn();
    $stmt=$db->query('SELECT COALESCE(SUM(reserved_pln),0) FROM project_agent_tasks WHERE reserved_pln>0');
    $monthly+=(float)$stmt->fetchColumn();
    $stmt=$db->prepare('SELECT COALESCE(SUM(spent_pln),0) FROM project_ai_calls WHERE created_at>=?');
    $stmt->execute([$month]); $monthly+=(float)$stmt->fetchColumn();
    $monthly+=(float)$db->query('SELECT COALESCE(SUM(reserved_pln),0) FROM project_ai_calls')->fetchColumn();
    return [$project,$monthly];
}

function projectDispatchAgentTask(): bool {
    $db=projectDb();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $max=max(1,(int)projectSetting('AGENT_MAX_CONCURRENCY'));
        $running=(int)$db->query("SELECT COUNT(*) FROM project_agent_tasks WHERE state='running'")->fetchColumn()+(int)$db->query("SELECT COUNT(*) FROM project_ai_calls WHERE state='reserved'")->fetchColumn();
        if($running>=$max) { $db->exec('COMMIT'); return false; }
        $tasks=$db->query("SELECT * FROM project_agent_tasks WHERE state='pending' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $selected=null; $case=[]; $repo=[];
        foreach($tasks as $task) {
            $id=(string)$task['session_id'];
            $session=readSession($id); $case=projectCase($id);
            if(!$session || (int)($case['sourceContractVersion']??0)!==(int)($session['contract']['version']??0)) continue;
            $repoStmt=$db->prepare("SELECT result FROM project_jobs WHERE session_id=? AND kind='create_repository' AND state='done'");
            $repoStmt->execute([$id]); $repo=json_decode((string)$repoStmt->fetchColumn(),true);
            if(!is_array($repo) || empty($repo['url'])) continue;
            $deps=json_decode((string)$task['dependencies'],true)?:[];
            if($deps) {
                $stateStmt=$db->prepare('SELECT task_key,state FROM project_agent_tasks WHERE session_id=?');
                $stateStmt->execute([$id]);
                $states=$stateStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                if(array_filter($deps,static fn($dep)=>($states[$dep]??'')!=='done')) continue;
            }
            $selected=$task; break;
        }
        if(!$selected) { $db->exec('COMMIT'); return false; }
        [$url,$token]=projectRunnerConfig();
        $id=(string)$selected['session_id'];
        [$projectTotal,$monthlyTotal]=projectTaskTotals($db,$id);
        $reserve=(float)projectSetting('AGENT_TASK_COST_LIMIT_PLN');
        $projectCap=(float)($case['budgetPln']??0); $monthlyCap=(float)projectSetting('AGENT_MONTHLY_LIMIT_PLN');
        if($reserve<=0 || $projectTotal+$reserve>$projectCap+0.0001 || $monthlyTotal+$reserve>$monthlyCap+0.0001) {
            $db->prepare("UPDATE project_agent_tasks SET state='failed',error=?,updated_at=? WHERE id=?")->execute(['Limit kosztu projektu, miesiąca lub zadania nie pozwala uruchomić pracy.',time(),$selected['id']]);
            projectEvent($id,projectTaskStage((string)$selected['role']),'limit','System','Zadanie '.$selected['task_key'].' wstrzymane przez limit kosztów.');
            $db->exec('COMMIT'); return true;
        }
        $key=$id.':'.$selected['task_key'].':'.((int)$selected['attempts']+1);
        $db->prepare("UPDATE project_agent_tasks SET state='running',runner_id=?,attempts=attempts+1,reserved_pln=?,started_at=?,updated_at=? WHERE id=? AND state='pending'")->execute([$key,$reserve,time(),time(),$selected['id']]);
        projectEvent($id,projectTaskStage((string)$selected['role']),'started','Agent '.$selected['role'],'Zlecono zadanie: '.$selected['title'].'.');
        $db->exec('COMMIT');
        $body=['id'=>$key,'projectId'=>$id,'taskId'=>$selected['task_key'],'role'=>$selected['role'],'title'=>$selected['title'],'dependencies'=>json_decode((string)$selected['dependencies'],true)?:[],'acceptance'=>json_decode((string)$selected['acceptance'],true)?:[],'repository'=>$repo['url'],'approvedScope'=>$case['scope']??'','maxCostPln'=>$reserve,'timeoutMinutes'=>(int)projectSetting('AGENT_TASK_TIMEOUT_MIN')];
        $response=workerRequest('POST',$url.'/v1/tasks',$body,['Authorization: Bearer '.$token,'Idempotency-Key: '.$key,'Content-Type: application/json','Accept: application/json']);
        if(!in_array($response['status'],[200,201,202],true) || ($response['body']['id']??'')!==$key) throw new RuntimeException('Runner nie potwierdził identyfikatora zadania.');
        return true;
    } catch(Throwable $error) {
        try { $db->exec('ROLLBACK'); } catch(Throwable) {}
        if(isset($selected) && $selected) projectEvent((string)$selected['session_id'],projectTaskStage((string)$selected['role']),'runner_error','System','Nie udało się zlecić zadania. Runner musi obsługiwać ponowienie z tym samym Idempotency-Key.');
        throw $error;
    }
}

function projectPollAgentTask(): bool {
    $db=projectDb();
    $task=$db->query("SELECT * FROM project_agent_tasks WHERE state='running' ORDER BY updated_at,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$task) return false;
    [$url,$token]=projectRunnerConfig();
    $key=(string)$task['runner_id'];
    try { $response=workerRequest('GET',$url.'/v1/tasks/'.rawurlencode($key),null,['Authorization: Bearer '.$token,'Accept: application/json']); }
    catch(Throwable $error) {
        if(time()-(int)$task['started_at']>max(1,(int)projectSetting('AGENT_TASK_TIMEOUT_MIN'))*60) {
            $db->prepare("UPDATE project_agent_tasks SET state='failed',error='Runner jest niedostępny po limicie czasu. Sprawdź zadanie i rozlicz rezerwację.',updated_at=? WHERE id=? AND state='running'")->execute([time(),$task['id']]);
            projectEvent((string)$task['session_id'],projectTaskStage((string)$task['role']),'reconcile_needed','System','Runner jest niedostępny po limicie czasu zadania '.$task['task_key'].'.');
            return true;
        }
        throw $error;
    }
    if($response['status']===404) {
        $db->prepare("UPDATE project_agent_tasks SET state='failed',error='Runner nie znajduje zadania. Sprawdź stan i rozlicz rezerwację przed ponowieniem.',updated_at=? WHERE id=? AND state='running'")->execute([time(),$task['id']]);
        projectEvent((string)$task['session_id'],projectTaskStage((string)$task['role']),'reconcile_needed','System','Runner nie znajduje zadania '.$task['task_key'].'; rezerwacja kosztu pozostaje zablokowana.');
        return true;
    }
    if($response['status']!==200 || ($response['body']['id']??'')!==$key) throw new RuntimeException('Runner nie zwrócił stanu zadania.');
    $body=$response['body']; $state=(string)($body['state']??'');
    $timeout=max(1,(int)projectSetting('AGENT_TASK_TIMEOUT_MIN'))*60;
    if(in_array($state,['queued','running'],true) && time()-(int)$task['started_at']>$timeout) {
        try { $cancel=workerRequest('POST',$url.'/v1/tasks/'.rawurlencode($key).'/cancel',[],['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json']); }
        catch(Throwable $error) { $cancel=['status'=>0,'body'=>[]]; }
        if($cancel['status']!==200 || ($cancel['body']['state']??'')!=='failed') {
            $db->prepare("UPDATE project_agent_tasks SET state='failed',error='Runner nie potwierdził anulowania. Sprawdź stan i rozlicz rezerwację.',updated_at=? WHERE id=? AND state='running'")->execute([time(),$task['id']]);
            projectEvent((string)$task['session_id'],projectTaskStage((string)$task['role']),'reconcile_needed','System','Przekroczono limit czasu zadania '.$task['task_key'].'; runner nie potwierdził anulowania.');
            return true;
        }
        $state='failed'; $body=$cancel['body'];
    }
    if(in_array($state,['queued','running'],true)) {
        $phase=in_array($body['phase']??'', ['queued','clone','codex','git','reviewing','waiting_merge','waiting_merge_auto','waiting_image'],true)?$body['phase']:null;
        $previous=json_decode((string)($task['result']??''),true)?:[];
        $incoming=is_array($body['result']??null)?$body['result']:[];
        $summary=mb_substr((string)($incoming['summary']??$previous['summary']??''),0,2000);
        $pr=(string)($incoming['pullRequestUrl']??$previous['pullRequestUrl']??'');
        if($pr!=='' && !preg_match('~^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/pull/[0-9]+$~',$pr)) $pr='';
        $result=$summary!==''||$pr!==''?['summary'=>$summary,'pullRequestUrl'=>$pr]:null;
        if($result && is_string($incoming['reviewSummary']??null)) $result['reviewSummary']=mb_substr($incoming['reviewSummary'],0,2000);
        if($result && is_bool($incoming['reviewApproved']??null)) $result['reviewApproved']=$incoming['reviewApproved'];
        $db->prepare('UPDATE project_agent_tasks SET runner_phase=?,result=?,updated_at=? WHERE id=? AND state=\'running\'')->execute([$phase,$result?json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,time(),$task['id']]);
        if($pr!=='' && $pr!==($previous['pullRequestUrl']??'')) projectEvent((string)$task['session_id'],projectTaskStage((string)$task['role']),'pull_request','Agent '.$task['role'],'Pull request gotowy do niezależnego przeglądu: '.$pr);
        return true;
    }
    if(!in_array($state,['done','failed'],true)) throw new RuntimeException('Runner zwrócił nieznany stan zadania.');
    $spent=$body['costPln']??null;
    if(!is_numeric($spent) || (float)$spent<0 || !is_finite((float)$spent) || (float)$spent>1000000) {
        $db->prepare("UPDATE project_agent_tasks SET state='failed',error='Runner nie podał rozliczenia. Sprawdź koszt i zwolnij rezerwację ręcznie.',updated_at=? WHERE id=? AND state='running'")->execute([time(),$task['id']]);
        projectEvent((string)$task['session_id'],projectTaskStage((string)$task['role']),'reconcile_needed','System','Brak poprawnego kosztu zadania '.$task['task_key'].'; rezerwacja pozostaje zablokowana.');
        return true;
    }
    $overspent=(float)$spent>(float)$task['reserved_pln']+0.0001;
    if($overspent) { $state='failed'; $body['error']='Runner przekroczył limit kosztu zadania. Rozliczono rzeczywisty koszt; dalsza praca wymaga decyzji.'; }
    $result=$state==='done'?($body['result']??null):null;
    if($state==='done' && (!is_array($result) || empty($result['summary']))) throw new RuntimeException('Runner nie podał wyniku zadania.');
    $db->exec('BEGIN IMMEDIATE');
    try {
        $update=$db->prepare("UPDATE project_agent_tasks SET state=?,runner_phase='finished',result=?,error=?,spent_pln=spent_pln+?,reserved_pln=0,updated_at=? WHERE id=? AND state='running' AND runner_id=?");
        $update->execute([$state,$result?json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,$state==='failed'?mb_substr((string)($body['error']??'Zadanie nie powiodło się.'),0,500):null,(float)$spent,time(),$task['id'],$key]);
        if($update->rowCount()===1) {
            $db->prepare('INSERT OR IGNORE INTO project_agent_costs(runner_id,session_id,task_key,amount_pln,incurred_at) VALUES(?,?,?,?,?)')->execute([$key,$task['session_id'],$task['task_key'],(float)$spent,time()]);
            projectEvent((string)$task['session_id'],projectTaskStage((string)$task['role']),$state==='done'?'completed':'failed','Agent '.$task['role'],$state==='done'?'Ukończono zadanie '.$task['task_key'].'.':'Nie udało się ukończyć zadania '.$task['task_key'].'.');
            if($overspent) projectEvent((string)$task['session_id'],projectTaskStage((string)$task['role']),'limit_exceeded','System','Runner zgłosił koszt '.$spent.' PLN ponad rezerwację '.$task['reserved_pln'].' PLN.');
        }
        $db->exec('COMMIT');
    } catch(Throwable $error) { try { $db->exec('ROLLBACK'); } catch(Throwable) {} throw $error; }
    return true;
}

function projectQueueReadyPreviews(): void {
    $db=projectDb();
    $ids=$db->query("SELECT DISTINCT session_id FROM project_agent_tasks WHERE role='qa'")->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id) {
        $tasks=projectAgentTasks((string)$id);
        if(!$tasks || count(array_filter($tasks,static fn($task)=>$task['state']==='done'))!==count($tasks)) continue;
        $jobs=projectJobs((string)$id); $states=[];
        foreach($jobs as $job) $states[$job['kind']]=$job['state'];
        if(($states['provision_preview']??'')!=='done') continue;
        try { $evidence=deploymentEvidence($tasks); } catch(Throwable $error) { continue; }
        $latest=projectLatestPreviewJob((string)$id);
        $latestResult=$latest && $latest['state']==='done'?json_decode((string)$latest['result'],true):null;
        if(($latestResult['imageDigest']??'')===$evidence['imageDigest']) continue;
        $kind=$latest?'publish_preview_'.substr($evidence['imageDigest'],7,12):'publish_preview';
        if(isset($states[$kind])) continue;
        projectEnqueue((string)$id,$kind,$evidence);
        projectEvent((string)$id,'preview','queued','System','QA ukończone. Zlecono wdrożenie i sprawdzenie podglądu.');
    }
}

function projectQueueScaffoldConfiguration(): void {
    $db=projectDb();
    $rows=$db->query("SELECT r.session_id,r.result FROM project_jobs r JOIN project_agent_tasks t ON t.session_id=r.session_id AND t.task_key='scaffold' AND t.state='done' WHERE r.kind='create_repository' AND r.state='done'")->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row) {
        $repo=json_decode((string)$row['result'],true);
        if(($repo['ci']??'')!=='awaiting_project_scaffold') continue;
        $exists=$db->prepare("SELECT 1 FROM project_jobs WHERE session_id=? AND kind='configure_scaffold'");
        $exists->execute([$row['session_id']]);
        if($exists->fetchColumn()) continue;
        projectEnqueue((string)$row['session_id'],'configure_scaffold');
        projectEvent((string)$row['session_id'],'environment','queued','System','Scaffold połączono z main. Zlecono kontrolę workflow i ochrony gałęzi.');
    }
}

function projectBackfillAgentTasks(): void {
    $db=projectDb();
    $rows=$db->query("SELECT c.session_id,c.data,j.result FROM project_cases c JOIN project_jobs j ON j.session_id=c.session_id AND j.kind='create_repository' AND j.state='done'")->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row) {
        $case=json_decode((string)$row['data'],true);
        if(!is_array($case) || !is_array($case['plan']['tasks']??null)) continue;
        $repo=json_decode((string)$row['result'],true)?:[];
        $newStack=($repo['ci']??'')==='awaiting_project_scaffold';
        $tasks=$newStack?projectTasksWithScaffold($case['plan']['tasks']):$case['plan']['tasks'];
        projectSeedAgentTasks((string)$row['session_id'],$tasks);
        if($newStack) foreach($tasks as $task) if($task['id']!=='scaffold') {
            $db->prepare("UPDATE project_agent_tasks SET dependencies=? WHERE session_id=? AND task_key=? AND state='pending'")->execute([json_encode($task['dependencies'],JSON_THROW_ON_ERROR),$row['session_id'],$task['id']]);
        }
    }
}
