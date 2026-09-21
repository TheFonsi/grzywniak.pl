<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/../api/project-delete.php';

$user=(string)(getenv('ADMIN_USERNAME')?:''); $password=(string)(getenv('ADMIN_PASSWORD')?:'');
if($user===''||$password==='') throw new RuntimeException('Configure local admin credentials before the HTTP regression test.');
$base=rtrim((string)(getenv('TEST_BASE_URL')?:'http://127.0.0.1:8081'),'/');
$id=bin2hex(random_bytes(16)); $file=__DIR__.'/../api/storage/'.$id.'.json';
$cookies=tempnam(sys_get_temp_dir(),'grzywniak-test-');
if($cookies===false) throw new RuntimeException('Cannot create temporary cookie jar.');
function regressionRequest(string $url,string $user,string $password,string $cookies,?array $form=null): array {
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$cookies,CURLOPT_COOKIEJAR=>$cookies,CURLOPT_TIMEOUT=>10]);
    if($user!=='') curl_setopt_array($curl,[CURLOPT_USERPWD=>$user.':'.$password,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC]);
    if($form!==null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($form)]);
    $raw=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $headerBytes=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE); $error=curl_error($curl); unset($curl);
    if($raw===false) throw new RuntimeException('HTTP test failed: '.$error);
    return [$status,substr($raw,$headerBytes)];
}
function regressionCheck(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
try {
    $state=['businessProblem'=>'Projekt testowy']; $analysis=['status'=>'COMPLETED']; $decisions=[];
    $hash=hash('sha256',json_encode([$state,$analysis,$decisions],JSON_UNESCAPED_UNICODE));
    $offer1=['version'=>1,'status'=>'REVIEWED','sourceHash'=>$hash,'sections'=>[]];
    $offer2=['version'=>2,'status'=>'REVIEWED','sourceHash'=>$hash,'sections'=>[]];
    $session=['id'=>$id,'status'=>'COMPLETED','createdAt'=>time(),'projectState'=>$state,'internalAnalysis'=>$analysis,'adminDecisions'=>$decisions,'messages'=>[['role'=>'user','content'=>'Fikcyjny projekt testowy']],'offer'=>$offer2,'offerVersions'=>[$offer1]];
    writeSession($session);
    file_put_contents($file,json_encode($session,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    projectSave($id,['startedAt'=>time()]);
    projectEvent($id,'plan','test','System','Test usuwania.');
    $db=projectDb();
    $db->prepare("INSERT INTO project_jobs(session_id,kind,state,input,created_at,updated_at) VALUES(?,'test_job','done','{}',?,?)")->execute([$id,time(),time()]);
    projectTemplateProposal($id,['proposedName'=>'Testowy szablon projektu','reason'=>'Fikcyjna propozycja tylko do weryfikacji usuwania projektu.'],['stack'=>'test','architecture'=>'test']);

    [$status,$body]=regressionRequest($base.'/api/offer.php?session='.$id.'&version=1',$user,$password,$cookies);
    $data=json_decode($body,true);
    regressionCheck($status===200 && ($data['offer']['version']??null)===1,'Archived offer did not load.');
    regressionCheck((readSession($id)['offer']['version']??null)===2,'Archived GET changed the current offer.');
    [$status,$pdf]=regressionRequest($base.'/api/offer.php?session='.$id.'&version=1&format=pdf',$user,$password,$cookies);
    regressionCheck($status===200 && str_starts_with($pdf,'%PDF'),'Archived PDF did not load.');
    regressionCheck((readSession($id)['offer']['version']??null)===2,'Archived PDF changed the current offer.');
    echo "Archived offer read: OK\n";

    [$status]=regressionRequest($base.'/api/admin.php',$user,$password,$cookies,['id'=>$id,'action'=>'delete','view'=>'all']);
    regressionCheck($status===403 && readSession($id)!==null,'Admin delete without CSRF was not rejected.');
    [$status,$html]=regressionRequest($base.'/api/admin.php?view=all&session='.$id,$user,$password,$cookies);
    regressionCheck($status===200 && preg_match('/<meta name="admin-csrf" content="([a-f0-9]{64})">/',$html,$match)===1,'Admin CSRF token missing.');
    regressionCheck(substr_count($html,'name="csrf" value="'.$match[1].'"')>=2,'Individual admin forms lack CSRF tokens.');
    [$status]=regressionRequest($base.'/api/admin.php',$user,$password,$cookies,['id'=>$id,'action'=>'archive','view'=>'all','csrf'=>$match[1]]);
    regressionCheck($status===303 && !empty(readSession($id)['archivedAt']),'Archive failed after CSRF protection.');
    [$status]=regressionRequest($base.'/api/admin.php',$user,$password,$cookies,['id'=>$id,'action'=>'restore','view'=>'all','csrf'=>$match[1]]);
    regressionCheck($status===303 && empty(readSession($id)['archivedAt']),'Restore failed after CSRF protection.');
    $db->prepare("INSERT INTO project_jobs(session_id,kind,state,attempts,input,created_at,updated_at) VALUES(?,'create_repository','failed',1,'{}',?,?)")->execute([$id,time(),time()]);
    [$status]=regressionRequest($base.'/api/admin.php',$user,$password,$cookies,['id'=>$id,'action'=>'delete','view'=>'all','csrf'=>$match[1]]);
    regressionCheck($status===409 && readSession($id)!==null,'Deletion discarded external resource references.');
    $db->prepare("DELETE FROM project_jobs WHERE session_id=? AND kind='create_repository'")->execute([$id]);
    $db->prepare("UPDATE project_jobs SET state='queued' WHERE session_id=?")->execute([$id]);
    [$status]=regressionRequest($base.'/api/admin.php',$user,$password,$cookies,['id'=>$id,'action'=>'delete','view'=>'all','csrf'=>$match[1]]);
    regressionCheck($status===409 && readSession($id)!==null,'Deletion did not wait for a queued project job.');
    $db->prepare("UPDATE project_jobs SET state='done' WHERE session_id=?")->execute([$id]);
    [$status]=regressionRequest($base.'/api/admin.php',$user,$password,$cookies,['id'=>$id,'action'=>'delete','view'=>'all','csrf'=>$match[1]]);
    regressionCheck($status===303,'Admin delete did not redirect after success.');
    regressionCheck(readSession($id)===null && !is_file($file) && projectCase($id)===[] && projectJobs($id)===[] && projectEvents($id)===[] && projectTemplate('proposal-'.substr($id,0,12))===null,'Admin delete left local case data.');
    regressionCheck(count(array_filter(allSessions(),static fn($item)=>($item['id']??'')===$id))===0,'Deleted session was imported again.');
    echo "CSRF and complete local delete: OK\n";

    [$status]=regressionRequest($base.'/tests/project-plan.php','','',$cookies);
    regressionCheck(in_array($status,[403,404],true),'Tests are executable over HTTP.');
    echo "HTTP tests directory blocked: OK\n";
} finally {
    $db=projectDb();
    foreach(['project_jobs','project_events','project_cases'] as $table) $db->prepare('DELETE FROM '.$table.' WHERE session_id=?')->execute([$id]);
    $db->prepare('DELETE FROM project_templates WHERE source_session=?')->execute([$id]);
    deleteSession($id);
    if(is_file($file)) unlink($file);
    if(is_file($cookies)) unlink($cookies);
}
