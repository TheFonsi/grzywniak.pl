<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function projectDb(): PDO {
    $db = sessionDb();
    $db->exec('CREATE TABLE IF NOT EXISTS project_cases (session_id TEXT PRIMARY KEY, data TEXT NOT NULL, updated_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS project_events (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT NOT NULL, stage TEXT NOT NULL, kind TEXT NOT NULL, actor TEXT NOT NULL, details TEXT NOT NULL, created_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS project_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT NOT NULL, kind TEXT NOT NULL, state TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, input TEXT NOT NULL, result TEXT, error TEXT, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, UNIQUE(session_id, kind))');
    $db->exec('CREATE TABLE IF NOT EXISTS project_agent_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT NOT NULL, task_key TEXT NOT NULL, title TEXT NOT NULL, role TEXT NOT NULL, dependencies TEXT NOT NULL, acceptance TEXT NOT NULL, state TEXT NOT NULL DEFAULT \'pending\', runner_id TEXT, runner_phase TEXT, attempts INTEGER NOT NULL DEFAULT 0, result TEXT, error TEXT, reserved_pln REAL NOT NULL DEFAULT 0, spent_pln REAL NOT NULL DEFAULT 0, started_at INTEGER, updated_at INTEGER NOT NULL, UNIQUE(session_id, task_key))');
    $columns=$db->query('PRAGMA table_info(project_agent_tasks)')->fetchAll(PDO::FETCH_ASSOC);
    if(!in_array('attempts',array_column($columns,'name'),true)) $db->exec('ALTER TABLE project_agent_tasks ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0');
    if(!in_array('runner_phase',array_column($columns,'name'),true)) $db->exec('ALTER TABLE project_agent_tasks ADD COLUMN runner_phase TEXT');
    $db->exec('CREATE INDEX IF NOT EXISTS project_agent_tasks_state ON project_agent_tasks(state,updated_at)');
    $db->exec('CREATE TABLE IF NOT EXISTS project_agent_costs (runner_id TEXT PRIMARY KEY, session_id TEXT NOT NULL, task_key TEXT NOT NULL, amount_pln REAL NOT NULL, incurred_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS project_ai_calls (call_key TEXT PRIMARY KEY, session_id TEXT NOT NULL, task_kind TEXT NOT NULL, state TEXT NOT NULL, reserved_pln REAL NOT NULL DEFAULT 0, spent_pln REAL NOT NULL DEFAULT 0, input_tokens INTEGER, output_tokens INTEGER, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
    $db->exec("INSERT OR IGNORE INTO project_agent_costs(runner_id,session_id,task_key,amount_pln,incurred_at) SELECT 'legacy:'||id,session_id,task_key,spent_pln,updated_at FROM project_agent_tasks t WHERE spent_pln>0 AND NOT EXISTS (SELECT 1 FROM project_agent_costs c WHERE c.session_id=t.session_id AND c.task_key=t.task_key)");
    $db->exec('CREATE TABLE IF NOT EXISTS project_feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT NOT NULL, image_digest TEXT NOT NULL, message TEXT NOT NULL, page_url TEXT NOT NULL, state TEXT NOT NULL DEFAULT \'new\', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
    $feedbackColumns=$db->query('PRAGMA table_info(project_feedback)')->fetchAll(PDO::FETCH_ASSOC);
    if(!in_array('category',array_column($feedbackColumns,'name'),true)) $db->exec('ALTER TABLE project_feedback ADD COLUMN category TEXT');
    if(!in_array('analysis',array_column($feedbackColumns,'name'),true)) $db->exec('ALTER TABLE project_feedback ADD COLUMN analysis TEXT');
    return $db;
}

function projectStages(): array {
    return [
        ['id'=>'brief','name'=>'Brief','agent'=>'Agent rozmowy','x'=>30,'y'=>190,'next'=>['analysis']],
        ['id'=>'analysis','name'=>'Analiza','agent'=>'Agent analizy','x'=>270,'y'=>190,'next'=>['offer']],
        ['id'=>'offer','name'=>'Oferta','agent'=>'Agent oferty','x'=>510,'y'=>190,'next'=>['contract']],
        ['id'=>'contract','name'=>'Umowa','agent'=>'Agent umowy','x'=>750,'y'=>190,'next'=>['kickoff']],
        ['id'=>'kickoff','name'=>'Start projektu','agent'=>'Ty + koordynator','x'=>990,'y'=>190,'next'=>['plan']],
        ['id'=>'plan','name'=>'Plan projektu','agent'=>'Koordynator','x'=>1230,'y'=>190,'next'=>['repository']],
        ['id'=>'repository','name'=>'Repozytorium','agent'=>'Agent infrastruktury','x'=>1470,'y'=>190,'next'=>['environment']],
        ['id'=>'environment','name'=>'CI/CD i środowisko','agent'=>'Agent infrastruktury','x'=>1710,'y'=>190,'next'=>['design','build']],
        ['id'=>'design','name'=>'UI / UX','agent'=>'Agenci projektowania','x'=>1950,'y'=>65,'next'=>['qa']],
        ['id'=>'build','name'=>'Frontend + backend','agent'=>'Agenci budowy','x'=>1950,'y'=>315,'next'=>['qa']],
        ['id'=>'qa','name'=>'Integracja i QA','agent'=>'Agenci przeglądu','x'=>2190,'y'=>190,'next'=>['preview']],
        ['id'=>'preview','name'=>'Podgląd klienta','agent'=>'Agent infrastruktury','x'=>2430,'y'=>190,'next'=>['feedback']],
        ['id'=>'feedback','name'=>'Uwagi klienta','agent'=>'Agent uwag','x'=>2670,'y'=>190,'next'=>['release']],
        ['id'=>'release','name'=>'Publikacja','agent'=>'Ty + agent infrastruktury','x'=>2910,'y'=>190,'next'=>['handover']],
        ['id'=>'handover','name'=>'Przekazanie','agent'=>'Koordynator','x'=>3150,'y'=>190,'next'=>[]],
    ];
}

function projectCase(string $id): array {
    $stmt = projectDb()->prepare('SELECT data FROM project_cases WHERE session_id = ?');
    $stmt->execute([$id]);
    $data = json_decode((string)($stmt->fetchColumn() ?: '{}'), true);
    return is_array($data) ? $data : [];
}

function projectSave(string $id, array $data): void {
    $stmt = projectDb()->prepare('INSERT INTO project_cases(session_id,data,updated_at) VALUES(?,?,?) ON CONFLICT(session_id) DO UPDATE SET data=excluded.data,updated_at=excluded.updated_at');
    $stmt->execute([$id,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time()]);
}

function projectEvent(string $id,string $stage,string $kind,string $actor,string $details): void {
    $stmt = projectDb()->prepare('INSERT INTO project_events(session_id,stage,kind,actor,details,created_at) VALUES(?,?,?,?,?,?)');
    $stmt->execute([$id,$stage,$kind,$actor,$details,time()]);
}

function projectEvents(string $id): array {
    $stmt = projectDb()->prepare('SELECT stage,kind,actor,details,created_at FROM project_events WHERE session_id=? ORDER BY id DESC LIMIT 200');
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function projectJobs(string $id): array {
    $stmt = projectDb()->prepare('SELECT id,kind,state,attempts,result,error,created_at,updated_at FROM project_jobs WHERE session_id=? ORDER BY id DESC');
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function projectLatestPreviewJob(string $id): ?array {
    foreach(projectJobs($id) as $job) if(preg_match('/^publish_preview(?:_[a-f0-9]{12})?$/',(string)$job['kind'])) return $job;
    return null;
}

function projectAgentTasks(string $id): array {
    $stmt=projectDb()->prepare('SELECT task_key,title,role,dependencies,acceptance,state,runner_id,runner_phase,attempts,result,error,reserved_pln,spent_pln,started_at,updated_at FROM project_agent_tasks WHERE session_id=? ORDER BY id');
    $stmt->execute([$id]);
    $tasks=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($tasks as &$task) {
        $task['dependencies']=json_decode((string)$task['dependencies'],true)?:[];
        $task['acceptance']=json_decode((string)$task['acceptance'],true)?:[];
        $task['result']=json_decode((string)($task['result']??''),true);
    }
    return $tasks;
}

function projectAiCalls(string $id): array {
    $stmt=projectDb()->prepare('SELECT call_key,task_kind,state,reserved_pln,spent_pln,input_tokens,output_tokens,created_at,updated_at FROM project_ai_calls WHERE session_id=? ORDER BY created_at DESC');
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function projectFeedback(string $id): array {
    $stmt=projectDb()->prepare('SELECT id,image_digest,message,page_url,state,category,analysis,created_at,updated_at FROM project_feedback WHERE session_id=? ORDER BY id DESC LIMIT 100');
    $stmt->execute([$id]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row) $row['analysis']=json_decode((string)($row['analysis']??''),true);
    return $rows;
}

function projectSeedAgentTasks(string $id,array $tasks): void {
    $stmt=projectDb()->prepare('INSERT OR IGNORE INTO project_agent_tasks(session_id,task_key,title,role,dependencies,acceptance,updated_at) VALUES(?,?,?,?,?,?,?)');
    foreach($tasks as $task) $stmt->execute([$id,(string)$task['id'],(string)$task['title'],(string)$task['role'],json_encode($task['dependencies'],JSON_THROW_ON_ERROR),json_encode($task['acceptance'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time()]);
}

function projectTasksWithScaffold(array $tasks): array {
    foreach($tasks as $task) if(($task['id']??'')==='scaffold') throw new RuntimeException('Identyfikator scaffold jest zarezerwowany dla przygotowania repozytorium.');
    $scaffold=['id'=>'scaffold','title'=>'Przygotuj szkielet aplikacji i CI/CD','role'=>'architect','dependencies'=>[],'acceptance'=>[
        'Dodaj kod startowy w technologii wybranej w planie, bez danych klienta i sekretów.',
        'Dodaj .github/workflows/ci.yml z kontrolą validate dla pull requestów i publikacją obrazu GHCR po połączeniu do main.',
        'Dodaj Dockerfile, działającą ścieżkę zdrowia oraz dokumentację portu aplikacji.',
    ]];
    foreach($tasks as &$task) $task['dependencies']=array_values(array_unique(array_merge(['scaffold'],$task['dependencies']??[])));
    unset($task);
    array_unshift($tasks,$scaffold);
    return $tasks;
}

function projectEnqueue(string $id,string $kind,array $input=[]): void {
    $now=time();
    $stmt=projectDb()->prepare("INSERT OR IGNORE INTO project_jobs(session_id,kind,state,input,created_at,updated_at) VALUES(?,?,'queued',?,?,?)");
    $stmt->execute([$id,$kind,json_encode($input,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$now,$now]);
}

function projectSnapshot(array $session): array {
    $id=(string)$session['id'];
    $case=projectCase($id);
    $jobs=projectJobs($id);
    $jobByKind=[];
    foreach($jobs as $job) $jobByKind[$job['kind']]=$job;
    $briefDone=($session['status']??'')==='COMPLETED';
    $analysisDone=($session['internalAnalysis']['status']??'')==='COMPLETED';
    $offer=$session['offer']??[];
    $contract=$session['contract']??[];
    $signed=(int)($case['contractSignedVersion']??0)>0 && (int)($case['contractSignedVersion']??0)===(int)($contract['version']??0);
    $started=!empty($case['startedAt']);
    $sourceCurrent=$started && (int)($case['sourceContractVersion']??0)===(int)($contract['version']??0);
    $status=[];
    $status['brief']=$briefDone?'done':'waiting_client';
    $status['analysis']=$analysisDone?'done':($briefDone?'ready':'locked');
    $status['offer']=in_array($offer['status']??'',['REVIEWED','SENT','ACCEPTED'],true)?'done':($analysisDone?'needs_you':'locked');
    $status['contract']=$signed?'done':(($contract['status']??'')==='SENT'?'needs_you':(($offer['status']??'')==='ACCEPTED'?'needs_you':'locked'));
    $status['kickoff']=$started?($sourceCurrent?'done':'review'):($signed?'needs_you':'locked');
    $status['plan']=projectJobStatus($jobByKind['generate_plan']??null,$started && $sourceCurrent);
    if($started && !$sourceCurrent && !empty($case['plan'])) $status['plan']='review';
    $repoJob=$jobByKind['create_repository']??null;
    $status['repository']=projectJobStatus($repoJob,$started && !empty($case['plan']));
    if(!$repoJob && !empty($case['templateProposalId'])) $status['repository']='review';
    if(!$sourceCurrent && $status['repository']==='done') $status['repository']='review';
    $environmentJob=$jobByKind['provision_preview']??$jobByKind['configure_scaffold']??null;
    $status['environment']=projectJobStatus($environmentJob,(bool)($repoJob && $repoJob['state']==='done' && $sourceCurrent));
    $repoResult=$repoJob && $repoJob['state']==='done'?json_decode((string)($repoJob['result']??''),true):null;
    if(is_array($repoResult) && ($repoResult['ci']??'')==='awaiting_project_scaffold' && !isset($jobByKind['configure_scaffold'])) $status['environment']='review';
    foreach(['design','build','qa','preview','feedback','release','handover'] as $stage) $status[$stage]='locked';
    $agentTasks=projectAgentTasks($id);
    if($status['repository']==='done') {
        foreach(['design'=>['ux','ui'],'build'=>['architect','frontend','backend','integration'],'qa'=>['qa','security','performance','documentation']] as $stage=>$roles) {
            $relevant=array_values(array_filter($agentTasks,static fn($task)=>in_array($task['role'],$roles,true)));
            if(!$relevant) { $status[$stage]=$stage==='qa'?'review':'ready'; continue; }
            $states=array_column($relevant,'state');
            $status[$stage]=in_array('failed',$states,true)?'error':(count(array_filter($relevant,static fn($task)=>$task['state']==='running' && $task['runner_phase']==='waiting_merge'))?'needs_you':(in_array('running',$states,true)?'working':(in_array('queued',$states,true)?'queued':(count(array_filter($states,static fn($state)=>$state==='done'))===count($states)?'done':'ready'))));
        }
    }
    $latestPreview=null;
    foreach($jobs as $candidate) if(preg_match('/^publish_preview(?:_[a-f0-9]{12})?$/',(string)$candidate['kind'])) { $latestPreview=$candidate; break; }
    $status['preview']=projectJobStatus($latestPreview,$status['qa']==='done' && $status['environment']==='done');
    if($status['preview']==='done') $status['feedback']=!empty($case['previewSentAt'])?'waiting_client':'needs_you';
    $feedback=projectFeedback($id);
    if($feedback) $status['feedback']=count(array_filter($feedback,static fn($entry)=>$entry['state']!=='resolved'))?'review':'done';
    $status['release']=projectJobStatus($jobByKind['publish_production']??null,!empty($case['previewSentAt']));
    if($status['release']==='ready') $status['release']='needs_you';
    $status['handover']=!empty($case['handoverAt'])?'done':($status['release']==='done'?'needs_you':'locked');
    $name=trim((string)($session['projectState']['businessProblem']??''));
    return ['id'=>$id,'name'=>$name?:'Projekt bez nazwy','client'=>(string)($session['projectState']['contactName']??'Klient'),'email'=>(string)($session['projectState']['contactEmail']??''),'status'=>$status,'stages'=>projectStages(),'case'=>$case,'jobs'=>$jobs,'agentTasks'=>$agentTasks,'aiCalls'=>projectAiCalls($id),'feedback'=>$feedback,'events'=>projectEvents($id),'brief'=>$session['summary']??null,'analysis'=>$session['internalAnalysis']??null,'offer'=>['status'=>$offer['status']??null,'version'=>$offer['version']??null,'project'=>$offer['project']??null],'contract'=>['status'=>$contract['status']??null,'version'=>$contract['version']??null,'number'=>$contract['number']??null,'hasPdf'=>!empty($contract['pdfBase64'])],'messages'=>$session['messages']??[],'updatedAt'=>$session['updatedAt']??null];
}

function projectJobStatus(?array $job,bool $unlocked): string {
    if(!$unlocked)return 'locked';
    if(!$job)return 'ready';
    return match($job['state']) {'queued'=>'queued','running'=>'working','done'=>'done','failed'=>'error',default=>'ready'};
}
