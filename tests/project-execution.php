<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-execution.php';
require_once __DIR__.'/../api/project-provision.php';

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
$evidence=deploymentEvidence([['role'=>'qa','state'=>'done','result'=>['summary'=>'OK','qaPassed'=>true,'commitSha'=>$sha,'imageDigest'=>$digest,'appPort'=>8080,'healthPath'=>'/healthz']]]);
if($evidence['commitSha']!==$sha || $evidence['imageDigest']!==$digest) throw new RuntimeException('Niepoprawna wersja wdrożenia.');
try { deploymentEvidence([['role'=>'qa','state'=>'done','result'=>['summary'=>'OK','qaPassed'=>false,'commitSha'=>$sha,'imageDigest'=>$digest,'appPort'=>8080,'healthPath'=>'/healthz']]]); throw new RuntimeException('QA bez akceptacji przeszło bramkę.'); }
catch(RuntimeException $error) { if($error->getMessage()==='QA bez akceptacji przeszło bramkę.') throw $error; }
echo "Project execution gates OK\n";
