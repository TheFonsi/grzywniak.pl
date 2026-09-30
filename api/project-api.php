<?php
declare(strict_types=1);
require_once __DIR__.'/project-model.php';
require_once __DIR__.'/contract-model.php';
require_once __DIR__.'/project-settings-model.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$adminUser=getenv('ADMIN_USERNAME')?:'';
$adminPassword=getenv('ADMIN_PASSWORD')?:'';
if($adminUser===''||$adminPassword===''||!hash_equals($adminUser,(string)($_SERVER['PHP_AUTH_USER']??''))||!hash_equals($adminPassword,(string)($_SERVER['PHP_AUTH_PW']??''))) { header('WWW-Authenticate: Basic realm="Grzywniak Discovery"'); http_response_code(401); exit; }
$projectCsrf=contractToken();
function projectReply(array $body,int $code=200): never { http_response_code($code); echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
function projectVpsRequest(string $method,string $url,array $body,array $headers): array {
    if(!filter_var($url,FILTER_VALIDATE_URL)||!str_starts_with($url,'https://')) throw new RuntimeException('Niepoprawny adres prywatnego API VPS.');
    $curl=curl_init($url); curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    $raw=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $error=curl_error($curl); curl_close($curl);
    $decoded=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($decoded)) throw new RuntimeException('Nie udało się odczytać odpowiedzi prywatnego API VPS.'.($error!==''?' '.$error:''));
    return ['status'=>$status,'body'=>$decoded];
}
$id=(string)($_GET['session']??'');
if(!preg_match('/^[a-f0-9]{32}$/',$id)) projectReply(['message'=>'Niepoprawny identyfikator rozmowy.'],400);
importLegacySessions();
$session=readSession($id);
if(!$session || ($session['status']??'')!=='COMPLETED') projectReply(['message'=>'Sprawa powstaje po przekazaniu briefu.'],404);
if($_SERVER['REQUEST_METHOD']==='GET') {
    $snapshot=projectSnapshot($session);
    foreach(projectJobs($id) as $candidate) if($candidate['kind']==='provision_preview' && $candidate['state']==='done') {
        $environment=json_decode((string)$candidate['result'],true)?:[];
        $stored=(string)($environment['previewPasswordEncrypted']??$environment['previewPassword']??'');
        if($stored!=='') $snapshot['previewAccess']=['password'=>str_starts_with($stored,'enc:')?projectDecryptSecret($stored):$stored,'username'=>'client'];
        break;
    }
    if(empty($snapshot['previewAccess']['password']) && !empty($case['previewPasswordEncrypted'])) $snapshot['previewAccess']=['password'=>projectDecryptSecret((string)$case['previewPasswordEncrypted']),'username'=>'client'];
    projectReply(['project'=>$snapshot,'csrf'=>$projectCsrf,'defaults'=>['costLimitPln'=>projectSetting('PROJECT_DEFAULT_COST_LIMIT_PLN')]]);
}
if($_SERVER['REQUEST_METHOD']!=='POST') projectReply(['message'=>'Niedozwolona metoda.'],405);
$body=json_decode(file_get_contents('php://input')?:'{}',true);
if(!is_array($body)) projectReply(['message'=>'Niepoprawne dane.'],400);
if(!hash_equals($projectCsrf,(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) projectReply(['message'=>'Odśwież panel i ponów działanie.'],403);
$action=(string)($body['action']??'');
$db=projectDb();
try {
    $db->exec('BEGIN IMMEDIATE');
    $session=readSession($id);
    $case=projectCase($id);
    if($action==='confirm_contract') {
        $contract=$session['contract']??[];
        $evidence=trim((string)($body['evidence']??''));
        if(empty($contract['pdfBase64']) || (int)($contract['version']??0)<1) throw new DomainException('Najpierw przygotuj aktualną wersję umowy PDF.');
        if(mb_strlen($evidence)<8 || mb_strlen($evidence)>500) throw new DomainException('Wpisz sposób i datę potwierdzenia zawarcia umowy (8–500 znaków).');
        $case['contractSignedVersion']=(int)$contract['version'];
        $case['contractEvidence']=$evidence;
        $case['contractConfirmedAt']=time();
        projectSave($id,$case);
        projectEvent($id,'contract','confirmed',$adminUser,'Potwierdzono zawarcie umowy v'.$case['contractSignedVersion'].': '.$evidence);
    } elseif($action==='start') {
        $contract=$session['contract']??[];
        if(empty($case['contractSignedVersion']) || (int)$case['contractSignedVersion']!==(int)($contract['version']??0)) throw new DomainException('Potwierdź zawarcie aktualnej wersji umowy.');
        if(!empty($case['startedAt'])) throw new DomainException('Realizacja została już uruchomiona.');
        $scope=trim((string)($body['scope']??'')); $owner=trim((string)($body['owner']??'')); $budget=$body['budget']??null;
        if(mb_strlen($scope)<10 || mb_strlen($scope)>3000) throw new DomainException('Opisz zatwierdzony zakres (10–3000 znaków).');
        if(mb_strlen($owner)<2 || mb_strlen($owner)>120) throw new DomainException('Wpisz osobę odpowiedzialną.');
        if(!is_numeric($budget) || (float)$budget<0 || (float)$budget>1000000) throw new DomainException('Wpisz limit kosztów usług od 0 do 1 000 000 PLN.');
        $case['scope']=$scope; $case['owner']=$owner; $case['budgetPln']=(float)$budget; $case['startedAt']=time();
        $case['sourceContractVersion']=(int)$contract['version'];
        projectSave($id,$case);
        projectEnqueue($id,'generate_plan',['contractVersion'=>$case['sourceContractVersion']]);
        projectEvent($id,'kickoff','approved',$adminUser,'Rozpoczęto realizację. Opiekun: '.$owner.'; limit usług: '.$case['budgetPln'].' PLN.');
        projectEvent($id,'plan','queued','System','Zlecono planowanie projektu.');
        projectEvent($id,'repository','waiting','System','Repozytorium powstanie po wyborze technologii i szablonu w planie.');
    } elseif($action==='retry_job') {
        $kind=(string)($body['kind']??'');
        if(!in_array($kind,['generate_plan','create_repository','configure_scaffold','provision_preview','publish_preview','publish_production'],true) && !preg_match('/^(?:publish_preview_[a-f0-9]{12}|classify_feedback_[1-9][0-9]*)$/',$kind)) throw new DomainException('Nieznane zadanie.');
        $stmt=$db->prepare("UPDATE project_jobs SET state='queued',error=NULL,updated_at=? WHERE session_id=? AND kind=? AND state='failed'");
        $stmt->execute([time(),$id,$kind]);
        if($stmt->rowCount()!==1) throw new DomainException('Można ponowić tylko zadanie zakończone błędem.');
        projectEvent($id,str_starts_with($kind,'classify_feedback_')?'feedback':(str_starts_with($kind,'publish_preview')?'preview':match($kind){'generate_plan'=>'plan','create_repository'=>'repository','publish_production'=>'release',default=>'environment'}),'retried',$adminUser,'Ponowiono zadanie '.$kind.'.');
    } elseif($action==='retry_agent_task') {
        $key=(string)($body['task']??'');
        if(!preg_match('/^[a-z][a-z0-9_-]{1,39}$/',$key)) throw new DomainException('Niepoprawne zadanie agenta.');
        $stmt=$db->prepare("UPDATE project_agent_tasks SET state='pending',runner_id=NULL,runner_phase=NULL,result=NULL,error=NULL,started_at=NULL,updated_at=? WHERE session_id=? AND task_key=? AND state='failed' AND reserved_pln=0");
        $stmt->execute([time(),$id,$key]);
        if($stmt->rowCount()!==1) throw new DomainException('Można ponowić tylko zadanie zakończone błędem.');
        projectEvent($id,'build','retried',$adminUser,'Ponowiono zadanie agenta '.$key.'.');
    } elseif($action==='reconcile_agent_task') {
        $key=(string)($body['task']??''); $cost=$body['cost']??null; $evidence=trim((string)($body['evidence']??''));
        if(!preg_match('/^[a-z][a-z0-9_-]{1,39}$/',$key) || !is_numeric($cost) || (float)$cost<0 || mb_strlen($evidence)<8 || mb_strlen($evidence)>1000) throw new DomainException('Podaj zadanie, rzeczywisty koszt i podstawę rozliczenia.');
        $find=$db->prepare("SELECT runner_id,reserved_pln FROM project_agent_tasks WHERE session_id=? AND task_key=? AND state='failed' AND reserved_pln>0");
        $find->execute([$id,$key]); $task=$find->fetch(PDO::FETCH_ASSOC);
        if(!$task || (float)$cost>(float)$task['reserved_pln']) throw new DomainException('Koszt przekracza rezerwację albo zadanie nie wymaga rozliczenia.');
        $db->prepare('INSERT OR IGNORE INTO project_agent_costs(runner_id,session_id,task_key,amount_pln,incurred_at) VALUES(?,?,?,?,?)')->execute([$task['runner_id'],$id,$key,(float)$cost,time()]);
        $db->prepare('UPDATE project_agent_tasks SET spent_pln=spent_pln+?,reserved_pln=0,updated_at=? WHERE session_id=? AND task_key=?')->execute([(float)$cost,time(),$id,$key]);
        projectEvent($id,'build','reconciled',$adminUser,'Rozliczono zadanie '.$key.' za '.$cost.' PLN. '.$evidence);
    } elseif($action==='reconcile_ai_call') {
        $key=(string)($body['callKey']??''); $cost=$body['cost']??null; $evidence=trim((string)($body['evidence']??''));
        if(!preg_match('/^job:([1-9][0-9]*):([1-9][0-9]*)$/',$key,$match) || !is_numeric($cost) || (float)$cost<0 || mb_strlen($evidence)<8 || mb_strlen($evidence)>1000) throw new DomainException('Podaj wywołanie, koszt i podstawę rozliczenia.');
        $find=$db->prepare("SELECT a.reserved_pln,j.state,j.attempts FROM project_ai_calls a JOIN project_jobs j ON j.id=? AND j.session_id=a.session_id WHERE a.call_key=? AND a.session_id=? AND a.state='reserved'");
        $find->execute([(int)$match[1],$key,$id]); $call=$find->fetch(PDO::FETCH_ASSOC);
        if(!$call || $call['state']!=='failed' || (int)$call['attempts']<(int)$match[2] || (float)$cost>(float)$call['reserved_pln']) throw new DomainException('Wywołanie nadal trwa albo koszt przekracza rezerwację.');
        $db->prepare("UPDATE project_ai_calls SET state='reconciled',reserved_pln=0,spent_pln=?,updated_at=? WHERE call_key=? AND state='reserved'")->execute([(float)$cost,time(),$key]);
        projectEvent($id,'plan','reconciled',$adminUser,'Rozliczono wywołanie modelu '.$key.' za '.$cost.' PLN. '.$evidence);
    } elseif($action==='secure_preview') {
        $preview=projectLatestPreviewJob($id); $result=$preview && $preview['state']==='done'?json_decode((string)$preview['result'],true):null;
        if(!is_array($result) || !preg_match('/^sha256:[a-f0-9]{64}$/',(string)($result['imageDigest']??''))) throw new DomainException('Najpierw przygotuj działający podgląd.');
        $repository=[]; foreach(projectJobs($id) as $candidate) if($candidate['kind']==='create_repository' && $candidate['state']==='done') { $repository=json_decode((string)$candidate['result'],true)?:[]; break; }
        $hostname=(string)($result['hostname']??''); if($hostname==='' && !empty($result['url'])) $hostname=(string)(parse_url((string)$result['url'],PHP_URL_HOST)?:'');
        $base=strtolower(trim(projectSetting('PREVIEW_BASE_DOMAIN')));
        if($hostname!=='p-'.substr($id,0,12).'.'.$base || empty($repository['url'])) throw new DomainException('Nie można zweryfikować hosta i repozytorium podglądu.');
        $control=rtrim(trim(projectSetting('VPS_CONTROL_URL')),'/'); $controlToken=trim(projectSetting('VPS_CONTROL_TOKEN')); $password=bin2hex(random_bytes(16));
        $provision=projectVpsRequest('POST',$control.'/v1/projects',['projectId'=>$id,'hostname'=>$hostname,'repository'=>(string)$repository['url'],'kind'=>'preview','previewPassword'=>$password,'limitPln'=>(float)($case['budgetPln']??0)],['Authorization: Bearer '.$controlToken,'Idempotency-Key: '.$id.':preview','Content-Type: application/json','Accept: application/json']);
        if(!in_array($provision['status'],[200,201],true) || ($provision['body']['ready']??false)!==true || ($provision['body']['hostname']??'')!==$hostname) throw new DomainException('VPS nie potwierdził zabezpieczenia podglądu hasłem.');
        $case['previewPasswordEncrypted']=projectEncryptSecret($password); projectSave($id,$case);
        projectEvent($id,'preview','password_set',$adminUser,'Ustawiono unikalne hasło dostępu do podglądu.');
    } elseif($action==='send_preview') {
        $preview=projectLatestPreviewJob($id);
        $result=$preview && $preview['state']==='done'?json_decode((string)$preview['result'],true):null;
        if(!is_array($result) || !preg_match('/^sha256:[a-f0-9]{64}$/',(string)($result['imageDigest']??''))) throw new DomainException('Nie ma sprawdzonej wersji podglądu.');
        if(!empty($case['previewSentAt']) && ($case['previewSentDigest']??'')===$result['imageDigest']) throw new DomainException('Tę wersję podglądu już wysłano.');
        $email=(string)($session['projectState']['contactEmail']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Brak poprawnego adresu e-mail klienta.');
        $jobs=projectJobs($id); $environment=[]; $repository=[];
        foreach($jobs as $candidate) {
            if($candidate['kind']==='provision_preview' && $candidate['state']==='done') $environment=json_decode((string)$candidate['result'],true)?:[];
            if($candidate['kind']==='create_repository' && $candidate['state']==='done') $repository=json_decode((string)$candidate['result'],true)?:[];
        }
        $hostname=(string)($result['hostname']??'');
        if($hostname==='' && !empty($result['url'])) $hostname=(string)(parse_url((string)$result['url'],PHP_URL_HOST)?:'');
        $base=strtolower(trim(projectSetting('PREVIEW_BASE_DOMAIN')));
        if(!preg_match('/^[a-f0-9]{32}$/',$id) || $hostname!=='p-'.substr($id,0,12).'.'.$base || empty($repository['url'])) throw new DomainException('Nie można zweryfikować środowiska podglądu i repozytorium.');
        $control=rtrim(trim(projectSetting('VPS_CONTROL_URL')),'/'); $controlToken=trim(projectSetting('VPS_CONTROL_TOKEN'));
        if(!str_starts_with($control,'https://') || strlen($controlToken)<32) throw new DomainException('Brak bezpiecznej konfiguracji API VPS.');
        $previewPassword=(string)($environment['previewPasswordEncrypted']??$environment['previewPassword']??$case['previewPasswordEncrypted']??'');
        if(str_starts_with($previewPassword,'enc:')) $previewPassword=projectDecryptSecret($previewPassword);
        if(strlen($previewPassword)<16) $previewPassword=bin2hex(random_bytes(16));
        $case['previewPasswordEncrypted']=projectEncryptSecret($previewPassword);
        $provision=projectVpsRequest('POST',$control.'/v1/projects',['projectId'=>$id,'hostname'=>$hostname,'repository'=>(string)$repository['url'],'kind'=>'preview','previewPassword'=>$previewPassword,'limitPln'=>(float)($case['budgetPln']??0)],['Authorization: Bearer '.$controlToken,'Idempotency-Key: '.$id.':preview','Content-Type: application/json','Accept: application/json']);
        if(!in_array($provision['status'],[200,201],true) || ($provision['body']['ready']??false)!==true || ($provision['body']['hostname']??'')!==$hostname) throw new DomainException('VPS nie potwierdził zabezpieczenia podglądu hasłem.');
        $token=bin2hex(random_bytes(24));
        $feedbackUrl=rtrim(projectSetting('PUBLIC_API_URL'),'/').'/project-feedback.php?project='.rawurlencode($id).'&token='.rawurlencode($token);
        $tokenSet=projectVpsRequest('POST',$control.'/v1/projects/feedback-token',['projectId'=>$id,'feedbackToken'=>$token,'feedbackUrl'=>$feedbackUrl],['Authorization: Bearer '.$controlToken,'Content-Type: application/json','Accept: application/json']);
        if($tokenSet['status']!==200 || ($tokenSet['body']['ready']??false)!==true) throw new DomainException('VPS nie zapisał bezpiecznej konfiguracji formularza uwag.');
        $message="Dzień dobry,\n\nPodgląd projektu: ".$result['url']."\nLogin do podglądu: client\nHasło do podglądu: ".$previewPassword."\n\nNa stronie można kliknąć „Zgłoś uwagę”, zaznaczyć obszar i opisać zmianę. Formularz zapasowy: ".$feedbackUrl."\n\nPozdrawiamy,\nGrzywniak.pl";
        $sender=getenv('MAIL_FROM')?:'kontakt@grzywniak.pl';
        if(getenv('DISCOVERY_MAIL_MOCK')!=='true' && !@mail($email,'Podgląd projektu — Grzywniak.pl',$message,"From: Grzywniak.pl <{$sender}>\r\nContent-Type: text/plain; charset=UTF-8")) throw new DomainException('Serwer pocztowy nie przyjął wiadomości.');
        $case['feedbackTokenHash']=hash('sha256',$token); $case['previewSentDigest']=$result['imageDigest']; $case['previewSentAt']=time();
        projectSave($id,$case);
        projectEvent($id,'preview','sent',$adminUser,'Wysłano klientowi podgląd obrazu '.$result['imageDigest'].'.');
    } elseif($action==='resolve_feedback') {
        $feedbackId=filter_var($body['feedbackId']??null,FILTER_VALIDATE_INT);
        $resolution=trim((string)($body['resolution']??''));
        if(!$feedbackId || mb_strlen($resolution)<8 || mb_strlen($resolution)>1000) throw new DomainException('Podaj uzasadnienie rozstrzygnięcia uwagi (8–1000 znaków).');
        $stmt=$db->prepare("UPDATE project_feedback SET state='resolved',updated_at=? WHERE id=? AND session_id=? AND state IN ('triaged','fixed_pending_client')");
        $stmt->execute([time(),$feedbackId,$id]);
        if($stmt->rowCount()!==1) throw new DomainException('Uwaga nie jest już otwarta.');
        projectEvent($id,'feedback','resolved',$adminUser,'Uwaga #'.$feedbackId.': '.$resolution);
    } elseif($action==='request_fix') {
        $feedbackId=filter_var($body['feedbackId']??null,FILTER_VALIDATE_INT);
        if(!$feedbackId) throw new DomainException('Niepoprawna uwaga.');
        $find=$db->prepare("SELECT message,image_digest FROM project_feedback WHERE id=? AND session_id=? AND state='triaged' AND category='bug'");
        $find->execute([$feedbackId,$id]); $feedback=$find->fetch(PDO::FETCH_ASSOC);
        if(!$feedback) throw new DomainException('Uwaga nie jest już otwarta.');
        $fix='fix_'.$feedbackId; $qa='qa_fix_'.$feedbackId;
        $insert=$db->prepare('INSERT OR IGNORE INTO project_agent_tasks(session_id,task_key,title,role,dependencies,acceptance,updated_at) VALUES(?,?,?,?,?,?,?)');
        $insert->execute([$id,$fix,'Popraw uwagę klienta #'.$feedbackId,'frontend','[]',json_encode(['Zrealizuj uwagę do wersji '.$feedback['image_digest'].': '.$feedback['message'],'Otwórz pull request i przejdź CI.'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time()]);
        $insert->execute([$id,$qa,'Sprawdź poprawkę #'.$feedbackId,'qa',json_encode([$fix],JSON_THROW_ON_ERROR),json_encode(['Sprawdź poprawkę, bezpieczeństwo i regresję.','Zwróć commitSha, imageDigest i qaPassed dla nowej wersji.'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time()]);
        $db->prepare("UPDATE project_feedback SET state='in_fix',updated_at=? WHERE id=? AND session_id=?")->execute([time(),$feedbackId,$id]);
        projectEvent($id,'feedback','fix_requested',$adminUser,'Zlecono poprawkę uwagi #'.$feedbackId.' oraz ponowną kontrolę QA.');
    } elseif($action==='approve_production') {
        $evidence=trim((string)($body['evidence']??''));
        if(mb_strlen($evidence)<8 || mb_strlen($evidence)>1000) throw new DomainException('Zapisz podstawę akceptacji klienta i decyzji produkcyjnej (8–1000 znaków).');
        $preview=projectLatestPreviewJob($id);
        $result=$preview && $preview['state']==='done'?json_decode((string)$preview['result'],true):null;
        if(!is_array($result) || ($case['previewSentDigest']??'')!==($result['imageDigest']??null)) throw new DomainException('Najpierw wyślij klientowi aktualną wersję podglądu.');
        $open=$db->prepare("SELECT COUNT(*) FROM project_feedback WHERE session_id=? AND state!='resolved'"); $open->execute([$id]);
        if((int)$open->fetchColumn()>0) throw new DomainException('Najpierw rozstrzygnij wszystkie otwarte uwagi klienta.');
        $existing=$db->prepare("SELECT 1 FROM project_jobs WHERE session_id=? AND kind='publish_production'"); $existing->execute([$id]);
        if($existing->fetchColumn()) throw new DomainException('Publikację już zatwierdzono lub zlecono.');
        $case['productionApprovedDigest']=$result['imageDigest']; $case['productionApprovedCommit']=$result['commitSha']; $case['productionApprovedAt']=time(); $case['productionApprovalEvidence']=$evidence;
        projectSave($id,$case);
        projectEnqueue($id,'publish_production',['imageDigest'=>$result['imageDigest'],'commitSha'=>$result['commitSha'],'appPort'=>$result['appPort'],'healthPath'=>$result['healthPath']]);
        projectEvent($id,'release','approved',$adminUser,'Zatwierdzono produkcję dokładnej wersji '.$result['imageDigest'].'. '.$evidence);
    } elseif($action==='complete_handover') {
        $release=$db->prepare("SELECT result FROM project_jobs WHERE session_id=? AND kind='publish_production' AND state='done'");
        $release->execute([$id]); $result=json_decode((string)$release->fetchColumn(),true);
        if(!is_array($result) || empty($result['imageDigest'])) throw new DomainException('Produkcja musi działać przed przekazaniem.');
        if(!empty($case['handoverAt'])) throw new DomainException('Projekt został już przekazany.');
        $note=trim((string)($body['note']??''));
        if(mb_strlen($note)<15 || mb_strlen($note)>2000) throw new DomainException('Opisz sposób przekazania i wsparcia (15–2000 znaków).');
        $case['handoverAt']=time(); $case['handoverNote']=$note; $case['handoverDigest']=$result['imageDigest'];
        projectSave($id,$case);
        projectEvent($id,'handover','completed',$adminUser,'Przekazano wersję '.$result['imageDigest'].'. '.$note);
    } else throw new DomainException('Nieznana operacja.');
    $db->exec('COMMIT');
    projectReply(['project'=>projectSnapshot(readSession($id))]);
} catch(DomainException $error) { if($db->inTransaction())$db->exec('ROLLBACK'); projectReply(['message'=>$error->getMessage()],409); }
catch(Throwable $error) { if($db->inTransaction())$db->exec('ROLLBACK'); error_log('Project API: '.$error->getMessage()); projectReply(['message'=>'Nie udało się zapisać działania.'],500); }
