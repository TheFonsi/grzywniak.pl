<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-execution.php';
require_once __DIR__.'/../api/project-provision.php';

if(projectPreviewUsername(['projectState'=>['contactName'=>'Test','contactEmail'=>'dawid@example.com']])!=='dawid@example.com') throw new RuntimeException('Login do podglądu powinien użyć e-maila klienta.');
if(projectPreviewUsername(['projectState'=>['contactName'=>'Test','contactPhone'=>'+48 600 700 800']])!=='+48 600 700 800') throw new RuntimeException('Login do podglądu powinien przyjąć numer telefonu, gdy brak e-maila.');
if(projectContactLogin(['contactEmail'=>'invalid','contactPhone'=>'123'])!==null) throw new RuntimeException('Niepoprawny e-mail i zbyt krótki telefon nie mogą zamknąć briefu.');
$feedbackDigest='sha256:'.str_repeat('a',64);
if(projectFeedbackDigest(['feedbackEnabledDigest'=>$feedbackDigest])!==$feedbackDigest) throw new RuntimeException('Aktywny formularz uwag musi działać niezależnie od statusu wysłania e-maila.');
if(projectFeedbackDigest(['previewSentDigest'=>$feedbackDigest])!==$feedbackDigest) throw new RuntimeException('Starsze sprawy z wysłanym podglądem nadal muszą przyjmować uwagi.');

$executionSource=(string)file_get_contents(__DIR__.'/../api/project-execution.php');
if(str_contains($executionSource,'->commit()') || str_contains($executionSource,'->rollBack()')) throw new RuntimeException('BEGIN IMMEDIATE musi być kończony przez SQL COMMIT lub ROLLBACK.');

$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE project_agent_tasks(session_id TEXT,spent_pln REAL,reserved_pln REAL,started_at INTEGER,state TEXT)');
$db->exec('CREATE TABLE project_agent_costs(runner_id TEXT,session_id TEXT,task_key TEXT,amount_pln REAL,incurred_at INTEGER)');
$db->exec('CREATE TABLE project_ai_calls(call_key TEXT,session_id TEXT,task_kind TEXT,state TEXT,reserved_pln REAL,spent_pln REAL,input_tokens INTEGER,output_tokens INTEGER,created_at INTEGER,updated_at INTEGER)');
$stmt=$db->prepare('INSERT INTO project_agent_tasks VALUES(?,?,?,?,?)');
$stmt->execute(['a',10,5,time(),'running']);
$stmt->execute(['b',7,3,time(),'running']);
$db->exec("INSERT INTO project_agent_costs VALUES('a:one','a','one',10,strftime('%s','now')),('b:one','b','one',7,strftime('%s','now'))");
$db->exec("INSERT INTO project_agent_costs VALUES('old','a','old',100,strftime('%s','now','start of month','-1 day'))");
$db->exec("INSERT INTO project_ai_calls VALUES('job:1:1','a','generate_plan','settled',0,2,100,50,strftime('%s','now'),strftime('%s','now')),('job:2:1','b','classify_feedback','reserved',1,0,NULL,NULL,strftime('%s','now'),strftime('%s','now'))");
[$project,$monthly]=projectTaskTotals($db,'a');
if($project!==17.0 || $monthly!==28.0) throw new RuntimeException('Niepoprawne rozliczenie projektu lub miesiąca.');
$sha=str_repeat('a',40); $digest='sha256:'.str_repeat('b',64);
$verifiedQa=['summary'=>'QA PASS','qaPassed'=>true,'appPort'=>8080,'healthPath'=>'/health','commitSha'=>$sha,'imageCommitSha'=>$sha,'imageDigest'=>$digest];
if(!projectRunnerQaResultIsValid($verifiedQa)) throw new RuntimeException('Poprawny wynik QA runnera nie przeszedł synchronizacji.');
if(projectRunnerQaResultIsValid(array_merge($verifiedQa,['imageDigest'=>null]))) throw new RuntimeException('Wynik QA bez digestu obrazu nie powinien zostać zsynchronizowany.');
$repairDb=new PDO('sqlite::memory:');
$repairDb->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$repairDb->exec('CREATE TABLE project_agent_tasks(id INTEGER PRIMARY KEY,session_id TEXT,task_key TEXT,state TEXT,role TEXT,runner_id TEXT,spent_pln REAL,result TEXT,updated_at INTEGER)');
$repairDb->exec('CREATE TABLE project_agent_costs(runner_id TEXT PRIMARY KEY,session_id TEXT,task_key TEXT,amount_pln REAL,incurred_at INTEGER)');
$repairDb->prepare('INSERT INTO project_agent_tasks VALUES(1,?,?,?,?,?,?,?,?)')->execute(['case-a','qa-task','done','qa','case-a:qa-task:1',1.73,json_encode(['summary'=>'Old incomplete result']),time()]);
$repairDb->prepare('INSERT INTO project_agent_costs VALUES(?,?,?,?,?)')->execute(['case-a:qa-task:1','case-a','qa-task',1.73,time()]);
$repairTask=$repairDb->query('SELECT * FROM project_agent_tasks WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if(!projectReconcileCompletedQaResult($repairDb,$repairTask,$verifiedQa,1.77)) throw new RuntimeException('Poprawny, uzupełniony wynik QA nie został odzyskany.');
$repairTask=$repairDb->query('SELECT * FROM project_agent_tasks WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if(abs((float)$repairTask['spent_pln']-1.77)>0.0001 || abs((float)$repairDb->query('SELECT amount_pln FROM project_agent_costs')->fetchColumn()-1.77)>0.0001) throw new RuntimeException('Odzyskanie QA nie zaksięgowało wyłącznie różnicy kosztu.');
if(!projectReconcileCompletedQaResult($repairDb,$repairTask,$verifiedQa,1.77) || abs((float)$repairDb->query('SELECT spent_pln FROM project_agent_tasks')->fetchColumn()-1.77)>0.0001) throw new RuntimeException('Ponowne odzyskanie QA podwoiło koszt.');
$accountingTask=['runner_id'=>'d036a6fbdef7324f0ebbf9fd8da6d1b9:runtime-hardening:4-code-unreported','reserved_pln'=>0];
if(projectTaskAffectsStageStatus($accountingTask)) throw new RuntimeException('Rozliczona próba księgowa nadal blokuje status etapu.');
$accountingTask['reserved_pln']=9.96;
if(!projectTaskAffectsStageStatus($accountingTask)) throw new RuntimeException('Nierozliczona rezerwacja księgowa nie blokuje etapu.');
$blocker=projectIncompleteAgentTasksMessage([
    ['task_key'=>'api','title'=>'Backend API','state'=>'done'],
    ['task_key'=>'runtime-qa','title'=>'Niezależne QA runtime','state'=>'running','runner_phase'=>'waiting_image','error'=>''],
]);
if(!str_contains($blocker,'Niezależne QA runtime') || !str_contains($blocker,'running') || !str_contains($blocker,'czeka na obraz z CI')) throw new RuntimeException('Blokada podglądu nie wskazuje konkretnego zadania i przyczyny.');
if(projectIncompleteAgentTasksMessage([])==='') throw new RuntimeException('Brak zadań agentów powinien wyjaśniać blokadę.');
$accountedAttempt=['task_key'=>'runtime-hardening-vps-parity','title'=>'Rozliczenie niezaksięgowanej pracy z PR #12 (próba 4)','role'=>'qa','state'=>'failed','runner_id'=>'d036:runtime-hardening-vps-parity:4-code-unreported','reserved_pln'=>0,'error'=>'Koszt został już ujęty.'];
$completedQa=['task_key'=>'qa-container-runtime','title'=>'Niezależne QA','role'=>'qa','state'=>'done','runner_id'=>'d036:qa-container-runtime:1','reserved_pln'=>0,'result'=>['qaPassed'=>true]];
if(!projectAgentTasksCompleteForPreview([$completedQa,$accountedAttempt]) || projectIncompleteAgentTasksMessage([$completedQa,$accountedAttempt])!=='') throw new RuntimeException('Rozliczona próba kodująca nadal blokuje podgląd.');
if(projectAgentTasksCompleteForPreview([$completedQa,array_merge($accountedAttempt,['runner_id'=>'d036:runtime-hardening-vps-parity:4','reserved_pln'=>0])])) throw new RuntimeException('Nierozliczone zadanie robocze nie blokuje podglądu.');
$evidence=deploymentEvidence([['role'=>'qa','state'=>'done','result'=>['summary'=>'OK','qaPassed'=>true,'commitSha'=>$sha,'imageCommitSha'=>$sha,'imageDigest'=>$digest,'appPort'=>8080,'healthPath'=>'/healthz']]]);
if($evidence['commitSha']!==$sha || $evidence['imageDigest']!==$digest) throw new RuntimeException('Niepoprawna wersja wdrożenia.');
try { deploymentEvidence([['role'=>'qa','state'=>'done','result'=>['summary'=>'OK','qaPassed'=>false,'commitSha'=>$sha,'imageDigest'=>$digest,'appPort'=>8080,'healthPath'=>'/healthz']]]); throw new RuntimeException('QA bez akceptacji przeszło bramkę.'); }
catch(RuntimeException $error) { if($error->getMessage()==='QA bez akceptacji przeszło bramkę.') throw $error; }
try { deploymentEvidence([['role'=>'qa','state'=>'done','result'=>['summary'=>'OK','qaPassed'=>true,'commitSha'=>$sha,'imageCommitSha'=>str_repeat('c',40),'imageDigest'=>$digest,'appPort'=>8080,'healthPath'=>'/healthz']]]); throw new RuntimeException('Image from a different commit passed the gate.'); }
catch(RuntimeException $error) { if($error->getMessage()==='Image from a different commit passed the gate.') throw $error; if(!str_contains($error->getMessage(),'imageCommitSha')) throw new RuntimeException('Błąd QA nie wskazuje brakującego powiązania obrazu z commitem.'); }
echo "Project execution gates OK\n";
