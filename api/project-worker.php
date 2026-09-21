<?php
declare(strict_types=1);
require_once __DIR__.'/project-model.php';
require_once __DIR__.'/project-agent.php';
require_once __DIR__.'/project-provision.php';
require_once __DIR__.'/project-settings-model.php';
require_once __DIR__.'/project-templates.php';
require_once __DIR__.'/project-execution.php';
require_once __DIR__.'/project-feedback-agent.php';
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }

function workerRequest(string $method,string $url,?array $body,array $headers,int $timeout=25): array {
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false]);
    if($body!==null) curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $raw=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);
    if($raw===false) throw new RuntimeException('Usługa zewnętrzna jest niedostępna: '.$error);
    $data=json_decode($raw,true);
    return ['status'=>$status,'body'=>is_array($data)?$data:[]];
}

function githubToken(): string {
    $appId=trim(projectSetting('GITHUB_APP_ID'));
    $installation=trim(projectSetting('GITHUB_INSTALLATION_ID'));
    $key=projectSetting('GITHUB_APP_PRIVATE_KEY');
    $keyFile=trim((string)getenv('GITHUB_APP_PRIVATE_KEY_FILE'));
    if($key==='' && $keyFile!=='' && is_readable($keyFile)) $key=(string)file_get_contents($keyFile);
    if($appId===''||$installation===''||$key==='') throw new RuntimeException('Skonfiguruj GitHub App ID, instalację i klucz prywatny w ustawieniach.');
    $now=time();
    $base64=static fn(string $value):string=>rtrim(strtr(base64_encode($value),'+/','-_'),'=');
    $header=$base64(json_encode(['alg'=>'RS256','typ'=>'JWT'],JSON_THROW_ON_ERROR));
    $payload=$base64(json_encode(['iat'=>$now-60,'exp'=>$now+540,'iss'=>$appId],JSON_THROW_ON_ERROR));
    $signed=$header.'.'.$payload;
    if(!openssl_sign($signed,$signature,$key,OPENSSL_ALGO_SHA256)) throw new RuntimeException('Nie udało się podpisać żądania GitHub App.');
    $jwt=$signed.'.'.$base64($signature);
    $response=workerRequest('POST','https://api.github.com/app/installations/'.rawurlencode($installation).'/access_tokens',null,['Accept: application/vnd.github+json','Authorization: Bearer '.$jwt,'User-Agent: grzywniak-project-worker']);
    if($response['status']!==201 || empty($response['body']['token'])) throw new RuntimeException('GitHub App nie wydała tokenu instalacji (HTTP '.$response['status'].').');
    return (string)$response['body']['token'];
}

function githubApi(string $method,string $path,?array $body,string $token): array {
    return workerRequest($method,'https://api.github.com'.$path,$body,['Accept: application/vnd.github+json','Authorization: Bearer '.$token,'User-Agent: grzywniak-project-worker','Content-Type: application/json']);
}

function repoSlug(string $name,string $id): string {
    $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name)?:$name;
    $slug=strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/','-',$ascii),'-'));
    return 'project-'.substr($slug?:'web',0,35).'-'.substr($id,0,8);
}

function createRepository(array $session,array $case): array {
    $org=trim(projectSetting('GITHUB_ORG'));
    $decision=$case['plan']['templateDecision']??[];
    if(!in_array($decision['mode']??'',['existing','new'],true)) throw new RuntimeException('Plan nie zawiera decyzji o szablonie.');
    $template=$decision['mode']==='existing'?projectTemplate((string)($decision['templateId']??'')):null;
    if($decision['mode']==='existing' && (!$template||$template['status']!=='active')) throw new RuntimeException('Wybrany szablon nie jest aktywny.');
    $templateOwner=$template?trim((string)$template['repo_owner']):'';
    $templateRepo=$template?trim((string)$template['repo_name']):'';
    if($org===''||!preg_match('/^[a-zA-Z0-9_.-]+$/',$org)) throw new RuntimeException('Skonfiguruj poprawną organizację GitHub.');
    foreach([$templateOwner,$templateRepo] as $part) if($part!==''&&!preg_match('/^[a-zA-Z0-9_.-]+$/',$part)) throw new RuntimeException('Niepoprawna nazwa szablonu GitHub.');
    $id=(string)$session['id'];
    $name=repoSlug((string)($session['projectState']['businessProblem']??'web'),$id);
    $token=githubToken();
    $path='/repos/'.rawurlencode($org).'/'.rawurlencode($name);
    $existing=githubApi('GET',$path,null,$token);
    if($existing['status']===404) {
        $description='Projekt '.substr($id,0,8).' — '.mb_substr((string)($session['projectState']['businessProblem']??''),0,100);
        $created=$template
            ?githubApi('POST','/repos/'.rawurlencode($templateOwner).'/'.rawurlencode($templateRepo).'/generate',['owner'=>$org,'name'=>$name,'description'=>$description,'include_all_branches'=>false,'private'=>true],$token)
            :githubApi('POST','/orgs/'.rawurlencode($org).'/repos',['name'=>$name,'description'=>$description,'private'=>true,'auto_init'=>true,'has_issues'=>true],$token);
        if($created['status']!==201) throw new RuntimeException('Nie udało się utworzyć prywatnego repozytorium (HTTP '.$created['status'].').');
        $existing=$created;
    }
    if($existing['status']!==200 && $existing['status']!==201) throw new RuntimeException('Nie udało się sprawdzić repozytorium (HTTP '.$existing['status'].').');
    if(($existing['body']['private']??false)!==true || strcasecmp((string)($existing['body']['owner']['login']??''),$org)!==0) throw new RuntimeException('Repozytorium o tej nazwie nie jest prywatne lub należy do innego właściciela.');
    if(!str_contains((string)($existing['body']['description']??''),substr($id,0,8))) throw new RuntimeException('Nazwa repozytorium jest zajęta przez inny projekt.');
    if($template) { $workflow=githubApi('GET',$path.'/contents/.github/workflows/ci.yml',null,$token); if($workflow['status']!==200) throw new RuntimeException('Szablon nie zawiera .github/workflows/ci.yml.'); }
    $protection=githubApi('PUT',$path.'/branches/main/protection',['required_status_checks'=>$template?['strict'=>true,'contexts'=>['validate']]:null,'enforce_admins'=>true,'required_pull_request_reviews'=>['required_approving_review_count'=>1,'dismiss_stale_reviews'=>true],'restrictions'=>null,'required_linear_history'=>true,'allow_force_pushes'=>false,'allow_deletions'=>false,'required_conversation_resolution'=>true],$token);
    if($protection['status']!==200) throw new RuntimeException('Repozytorium istnieje, lecz ochrona main wymaga poprawy lub plan GitHub nie udostępnia tej funkcji (HTTP '.$protection['status'].').');
    return ['name'=>$name,'url'=>(string)$existing['body']['html_url'],'repositoryId'=>(int)$existing['body']['id'],'private'=>true,'ci'=>$template?'configured':'awaiting_project_scaffold','branchProtection'=>'configured','templateId'=>$template['id']??null,'templateProposalId'=>$case['templateProposalId']??null];
}

function configureScaffold(string $id,array $repository): array {
    $org=trim(projectSetting('GITHUB_ORG'));
    $name=(string)($repository['name']??'');
    if(!preg_match('/^[A-Za-z0-9_.-]+$/',$org)||!preg_match('/^[A-Za-z0-9_.-]+$/',$name)) throw new RuntimeException('Repozytorium nie jest gotowe do konfiguracji CI.');
    $token=githubToken();
    $path='/repos/'.rawurlencode($org).'/'.rawurlencode($name);
    foreach(['.github/workflows/ci.yml','Dockerfile'] as $file) {
        $check=githubApi('GET',$path.'/contents/'.implode('/',array_map('rawurlencode',explode('/',$file))),null,$token);
        if($check['status']!==200 || ($check['body']['type']??'')!=='file') throw new RuntimeException('Scaffold nie zawiera pliku '.$file.' na gałęzi main.');
    }
    $protection=githubApi('PUT',$path.'/branches/main/protection',['required_status_checks'=>['strict'=>true,'contexts'=>['validate']],'enforce_admins'=>true,'required_pull_request_reviews'=>['required_approving_review_count'=>1,'dismiss_stale_reviews'=>true],'restrictions'=>null,'required_linear_history'=>true,'allow_force_pushes'=>false,'allow_deletions'=>false,'required_conversation_resolution'=>true],$token);
    if($protection['status']!==200) throw new RuntimeException('Nie udało się włączyć wymaganej kontroli validate dla main (HTTP '.$protection['status'].').');
    return ['ci'=>'configured','workflow'=>'.github/workflows/ci.yml','dockerfile'=>'Dockerfile'];
}

function publishTemplate(): void {
    $org=trim(projectSetting('GITHUB_ORG'));
    $name=trim(projectSetting('GITHUB_TEMPLATE_REPO'));
    if($org===''||$name===''||!preg_match('/^[a-zA-Z0-9_.-]+$/',$org)||!preg_match('/^[a-zA-Z0-9_.-]+$/',$name)) throw new RuntimeException('Ustaw poprawne GITHUB_ORG i GITHUB_TEMPLATE_REPO.');
    $token=githubToken();
    $path='/repos/'.rawurlencode($org).'/'.rawurlencode($name);
    $repo=githubApi('GET',$path,null,$token);
    if($repo['status']===404) {
        $repo=githubApi('POST','/orgs/'.rawurlencode($org).'/repos',['name'=>$name,'description'=>'Zatwierdzony szablon projektów webowych Grzywniak','private'=>true,'auto_init'=>true,'has_issues'=>true],$token);
        if($repo['status']!==201) throw new RuntimeException('Nie udało się utworzyć repozytorium szablonu (HTTP '.$repo['status'].').');
    }
    if(!in_array($repo['status'],[200,201],true) || ($repo['body']['private']??false)!==true) throw new RuntimeException('Repozytorium szablonu musi być prywatne.');
    if(($repo['body']['default_branch']??'main')!=='main') throw new RuntimeException('Repozytorium szablonu musi używać gałęzi main.');
    $root=realpath(__DIR__.'/../templates/web-vite');
    if($root===false) throw new RuntimeException('Nie znaleziono lokalnego szablonu.');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) {
        if(!$file->isFile()) continue;
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
        if(preg_match('~(^|/)(dist|node_modules)/~',$relative)) continue;
        $remote=$path.'/contents/'.implode('/',array_map('rawurlencode',explode('/',$relative)));
        $existing=githubApi('GET',$remote,null,$token);
        if(!in_array($existing['status'],[200,404],true)) throw new RuntimeException('Nie udało się sprawdzić pliku '.$relative.' (HTTP '.$existing['status'].').');
        $content=file_get_contents($file->getPathname());
        if($content===false) throw new RuntimeException('Nie udało się odczytać '.$relative.'.');
        if($existing['status']===200) {
            $remoteContent=base64_decode(preg_replace('/\s+/','',(string)($existing['body']['content']??'')),true);
            if($remoteContent===$content) { echo "Unchanged: {$relative}\n"; continue; }
            $sha=(string)($existing['body']['sha']??'');
            if(!preg_match('/^[a-f0-9]{40}$/',$sha)) throw new RuntimeException('Brak identyfikatora istniejącego pliku '.$relative.'.');
        }
        $payload=['message'=>($existing['status']===200?'Update':'Add').' project template: '.$relative,'content'=>base64_encode($content),'branch'=>'main'];
        if(isset($sha)) $payload['sha']=$sha;
        $result=githubApi('PUT',$remote,$payload,$token);
        if(!in_array($result['status'],[200,201],true)) throw new RuntimeException('Nie udało się opublikować '.$relative.' (HTTP '.$result['status'].').');
        echo ($existing['status']===200?'Updated: ':'Created: ').$relative."\n";
        unset($sha);
    }
    $updated=githubApi('PATCH',$path,['is_template'=>true],$token);
    if($updated['status']!==200) throw new RuntimeException('Pliki opublikowano, ale nie udało się oznaczyć repozytorium jako szablonu.');
    echo "Template ready: https://github.com/{$org}/{$name}\n";
}

function activateTemplate(string $id): void {
    if(!preg_match('/^[a-z0-9-]{3,60}$/',$id)) throw new RuntimeException('Niepoprawny identyfikator szablonu.');
    $template=projectTemplate($id);
    if(!$template || $template['status']!=='proposed') throw new RuntimeException('Nie znaleziono propozycji szablonu.');
    $token=githubToken();
    $path='/repos/'.rawurlencode($template['repo_owner']).'/'.rawurlencode($template['repo_name']);
    $repo=githubApi('GET',$path,null,$token);
    if($repo['status']!==200||($repo['body']['private']??false)!==true||($repo['body']['is_template']??false)!==true) throw new RuntimeException('Repozytorium szablonu musi istnieć, być prywatne i oznaczone jako template.');
    if(strcasecmp((string)($repo['body']['owner']['login']??''),(string)$template['repo_owner'])!==0) throw new RuntimeException('Szablon należy do innej organizacji.');
    $workflow=githubApi('GET',$path.'/contents/.github/workflows/ci.yml',null,$token);
    if($workflow['status']!==200) throw new RuntimeException('Szablon wymaga workflow .github/workflows/ci.yml.');
    $db=projectDb(); $db->beginTransaction();
    try {
        $db->prepare("UPDATE project_templates SET status='active',updated_at=? WHERE id=? AND status='proposed'")->execute([time(),$id]);
        $source=(string)($template['source_session']??'');
        if($source!=='') {
            $case=projectCase($source);
            if(($case['templateProposalId']??'')===$id && !empty($case['plan'])) {
                $case['templateActivatedAt']=time();
                projectSave($source,$case);
                projectEvent($source,'repository','template_active','System','Nowy szablon został sprawdzony i aktywowany: '.$template['name'].'.');
            }
        }
        $db->commit();
    } catch(Throwable $error) { $db->rollBack(); throw $error; }
    echo "Template active: {$id}\n";
}

function runOneJob(): bool {
    $db=projectDb();
    $db->exec('BEGIN IMMEDIATE');
    $stale=$db->prepare("UPDATE project_jobs SET state='failed',error='Praca została przerwana. Sprawdź wynik u dostawcy i ponów zadanie.',updated_at=? WHERE state='running' AND updated_at<?");
    $stale->execute([time(),time()-900]);
    $job=$db->query("SELECT * FROM project_jobs WHERE state='queued' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$job) { $db->exec('COMMIT'); return false; }
    $stmt=$db->prepare("UPDATE project_jobs SET state='running',attempts=attempts+1,updated_at=? WHERE id=?");
    $stmt->execute([time(),$job['id']]);
    $db->exec('COMMIT');
    $id=(string)$job['session_id'];
    $isPreview=(bool)preg_match('/^publish_preview(?:_[a-f0-9]{12})?$/',(string)$job['kind']);
    $isFeedback=(bool)preg_match('/^classify_feedback_([1-9][0-9]*)$/',(string)$job['kind'],$feedbackMatch);
    $stage=$isFeedback?'feedback':($isPreview?'preview':match($job['kind']) {'generate_plan'=>'plan','create_repository'=>'repository','publish_production'=>'release',default=>'environment'});
    $actor=$isFeedback?'Agent uwag':'Agent infrastruktury';
    projectEvent($id,$stage,'started',$actor,'Rozpoczęto zadanie '.$job['kind'].'.');
    try {
        $session=readSession($id); $case=projectCase($id);
        if(!$session || empty($case['startedAt']) || (int)($case['sourceContractVersion']??0)!==(int)($session['contract']['version']??0)) throw new RuntimeException('Projekt lub zatwierdzona umowa uległy zmianie.');
        $callKey='job:'.$job['id'].':'.((int)$job['attempts']+1);
        $result=$isFeedback?(static function() use ($db,$id,$case,$feedbackMatch,$callKey): array {
                $feedbackId=(int)$feedbackMatch[1];
                $find=$db->prepare("SELECT id,session_id,message,page_url,image_digest,state FROM project_feedback WHERE id=? AND session_id=?");
                $find->execute([$feedbackId,$id]); $feedback=$find->fetch(PDO::FETCH_ASSOC);
                if(!$feedback) throw new RuntimeException('Nie znaleziono uwagi klienta.');
                if($feedback['state']!=='new') throw new RuntimeException('Uwaga nie czeka już na klasyfikację.');
                $analysis=classifyProjectFeedback($feedback,$case,$callKey);
                return ['feedbackId'=>$feedbackId]+$analysis;
            })():($isPreview?(static function() use ($id,$session,$case,$job): array {
                $results=[]; foreach(projectJobs($id) as $related) if($related['state']==='done') $results[$related['kind']]=json_decode((string)$related['result'],true)?:[];
                $repo=$results['create_repository']??[]; $environment=$results['provision_preview']??[];
                $tasks=projectAgentTasks($id);
                if(!$tasks || count(array_filter($tasks,static fn($task)=>$task['state']==='done'))!==count($tasks)) throw new RuntimeException('Zadania agentów nie są zakończone.');
                $evidence=deploymentEvidence($tasks); $requested=json_decode((string)$job['input'],true)?:[];
                if($requested && ($requested['imageDigest']??'')!==$evidence['imageDigest']) throw new RuntimeException('Wersja QA zmieniła się po zleceniu wdrożenia.');
                return deployProjectVersion($session,$case,$repo,$environment,$evidence,'preview');
            })():match($job['kind']) {
            'generate_plan'=>generateProjectPlan($session,$case,$callKey),
            'create_repository'=>createRepository($session,$case),
            'configure_scaffold'=>(static function() use ($id): array {
                foreach(projectJobs($id) as $related) if($related['kind']==='create_repository'&&$related['state']==='done') return configureScaffold($id,json_decode((string)$related['result'],true)?:[]);
                throw new RuntimeException('Repozytorium nie jest gotowe.');
            })(),
            'provision_preview'=>provisionPreview($session,$case,(static function() use ($id): array { foreach(projectJobs($id) as $job) if($job['kind']==='create_repository'&&$job['state']==='done') return json_decode((string)$job['result'],true)?:[]; throw new RuntimeException('Repozytorium nie jest gotowe.'); })()),
            'publish_production'=>(static function() use ($id,$session,$case,$job): array {
                $approved=json_decode((string)$job['input'],true)?:[];
                if(($approved['imageDigest']??'')!==($case['productionApprovedDigest']??'') || ($approved['commitSha']??'')!==($case['productionApprovedCommit']??'')) throw new RuntimeException('Zgoda na produkcję nie dotyczy tej wersji.');
                $results=[]; foreach(projectJobs($id) as $related) if($related['state']==='done') $results[$related['kind']]=json_decode((string)$related['result'],true)?:[];
                $latest=projectLatestPreviewJob($id);
                $preview=$latest && $latest['state']==='done'?json_decode((string)$latest['result'],true)?:[]:[];
                $repo=$results['create_repository']??[];
                if(($preview['imageDigest']??'')!==$approved['imageDigest'] || ($preview['commitSha']??'')!==$approved['commitSha']) throw new RuntimeException('Podgląd zmienił się po zgodzie na produkcję.');
                $environment=provisionProjectEnvironment($session,$case,$repo,'production');
                return deployProjectVersion($session,$case,$repo,$environment,$approved,'production');
            })(),
            default=>throw new RuntimeException('Wykonawca tego rodzaju zadania nie jest jeszcze skonfigurowany.'),
        });
        $db->exec('BEGIN IMMEDIATE');
        $stmt=$db->prepare("UPDATE project_jobs SET state='done',result=?,error=NULL,updated_at=? WHERE id=? AND state='running'");
        $stmt->execute([json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time(),$job['id']]);
        if($stmt->rowCount()!==1) throw new RuntimeException('Stan zadania zmienił się podczas wykonania.');
        if($isFeedback) {
            $feedbackId=(int)$result['feedbackId'];
            $update=$db->prepare("UPDATE project_feedback SET state='triaged',category=?,analysis=?,updated_at=? WHERE id=? AND session_id=? AND state='new'");
            $update->execute([$result['category'],json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time(),$feedbackId,$id]);
            if($update->rowCount()!==1) throw new RuntimeException('Stan uwagi zmienił się podczas klasyfikacji.');
            projectEvent($id,'feedback','triaged','Agent uwag','Uwaga #'.$feedbackId.': '.$result['category'].'. '.$result['rationale']);
        }
        if($job['kind']==='generate_plan') {
            $case['plan']=$result; $case['planCreatedAt']=time();
            if(($result['templateDecision']['mode']??'')==='new') {
                $proposal=projectTemplateProposal($id,$result['templateDecision'],$result);
                $case['templateProposalId']=$proposal['id'];
                projectEvent($id,'repository','template_proposed','Koordynator','Zaproponowano nowy szablon: '.$proposal['name'].'.');
            }
            projectSave($id,$case);
            projectEnqueue($id,'create_repository',['templateId'=>$result['templateDecision']['templateId']??null]);
            projectEvent($id,'repository','queued','System','Zlecono utworzenie repozytorium po wyborze technologii.');
        }
        if($job['kind']==='create_repository') {
            projectSeedAgentTasks($id,($result['ci']??'')==='awaiting_project_scaffold'?projectTasksWithScaffold($case['plan']['tasks']??[]):($case['plan']['tasks']??[]));
            if(($result['ci']??'')==='configured') { projectEnqueue($id,'provision_preview'); projectEvent($id,'environment','queued','System','Zlecono przygotowanie VPS i subdomeny Cloudflare.'); }
            else projectEvent($id,'environment','waiting','System','CI i środowisko czekają na przygotowanie nowego stosu w repozytorium.');
        }
        if($job['kind']==='configure_scaffold') {
            $find=$db->prepare("SELECT result FROM project_jobs WHERE session_id=? AND kind='create_repository' AND state='done'");
            $find->execute([$id]); $repository=json_decode((string)$find->fetchColumn(),true)?:[];
            $repository['ci']='configured';
            $db->prepare("UPDATE project_jobs SET result=?,updated_at=? WHERE session_id=? AND kind='create_repository' AND state='done'")->execute([json_encode($repository,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time(),$id]);
            projectEnqueue($id,'provision_preview');
            projectEvent($id,'environment','queued','System','Scaffold i CI zatwierdzone. Zlecono przygotowanie VPS i Cloudflare.');
        }
        if($isPreview) {
            $db->prepare("UPDATE project_feedback SET state='fixed_pending_client',updated_at=? WHERE session_id=? AND state='in_fix'")->execute([time(),$id]);
            projectEvent($id,'feedback','new_preview','System','Nowa wersja podglądu jest gotowa; poprawki czekają na ponowne pokazanie klientowi.');
        }
        projectEvent($id,$stage,'completed',$actor,'Zadanie ukończone: '.$job['kind'].'.');
        $db->exec('COMMIT');
        echo "Job {$job['id']} done\n";
    } catch(Throwable $error) {
        if($db->inTransaction()) $db->exec('ROLLBACK');
        $message=mb_substr($error->getMessage(),0,500);
        $stmt=$db->prepare("UPDATE project_jobs SET state='failed',error=?,updated_at=? WHERE id=?");
        $stmt->execute([$message,time(),$job['id']]);
        projectEvent($id,$stage,'failed',$actor,$message);
        fwrite(STDERR,"Job {$job['id']} failed: {$message}\n");
    }
    return true;
}

if(in_array('--publish-template',$argv,true)) { try { publishTemplate(); exit(0); } catch(Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); } }
foreach($argv as $argument) if(str_starts_with($argument,'--activate-template=')) { try { activateTemplate(substr($argument,20)); exit(0); } catch(Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); } }
function runOneUnit(): bool {
    if(runOneJob()) return true;
    try { if(projectPollAgentTask()) return true; } catch(Throwable $error) { error_log('Agent polling: '.$error->getMessage()); }
    projectQueueScaffoldConfiguration();
    projectQueueReadyPreviews();
    try { return projectDispatchAgentTask(); } catch(Throwable $error) { error_log('Agent dispatch: '.$error->getMessage()); return false; }
}
projectBackfillAgentTasks();
foreach(projectDb()->query("SELECT id,session_id FROM project_feedback WHERE state='new'")->fetchAll(PDO::FETCH_ASSOC) as $feedback) projectEnqueue((string)$feedback['session_id'],'classify_feedback_'.$feedback['id']);
if(in_array('--once',$argv,true)) { runOneUnit(); exit; }
while(true) { runOneUnit(); sleep(5); }
