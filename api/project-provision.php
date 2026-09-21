<?php
declare(strict_types=1);

function provisionPreview(array $session,array $case,array $repository): array {
    return provisionProjectEnvironment($session,$case,$repository,'preview');
}

function provisionProjectEnvironment(array $session,array $case,array $repository,string $kind): array {
    if(!in_array($kind,['preview','production'],true)) throw new InvalidArgumentException('Niepoprawny rodzaj środowiska.');
    $base=strtolower(trim(projectSetting($kind==='preview'?'PREVIEW_BASE_DOMAIN':'PRODUCTION_BASE_DOMAIN')));
    $zone=trim(projectSetting('CLOUDFLARE_ZONE_ID'));
    $token=trim(projectSetting('CLOUDFLARE_DNS_TOKEN'));
    $origin=strtolower(trim(projectSetting($kind==='preview'?'PREVIEW_ORIGIN_HOST':'PRODUCTION_ORIGIN_HOST')));
    $control=rtrim(trim(projectSetting('VPS_CONTROL_URL')),'/');
    $controlToken=trim(projectSetting('VPS_CONTROL_TOKEN'));
    if(!$base||!$zone||!$token||!$origin||!$control||!$controlToken) throw new RuntimeException('Skonfiguruj Cloudflare, domenę podglądu oraz prywatne API VPS.');
    if(!preg_match('/^[a-z0-9.-]+$/',$base)||!preg_match('/^[a-z0-9.-]+$/',$origin)||!preg_match('/^[a-f0-9]{32}$/',(string)$session['id'])) throw new RuntimeException('Niepoprawna konfiguracja domeny lub projektu.');
    if(!str_starts_with($control,'https://')) throw new RuntimeException('VPS_CONTROL_URL musi używać HTTPS.');
    if(!filter_var($control,FILTER_VALIDATE_URL)) throw new RuntimeException('Niepoprawny adres API VPS.');
    $id=(string)$session['id'];
    $hostname=($kind==='preview'?'p-':'a-').substr($id,0,12).'.'.$base;
    $vps=workerRequest('POST',$control.'/v1/projects',['projectId'=>$id,'hostname'=>$hostname,'repository'=>(string)($repository['url']??''),'kind'=>$kind,'limitPln'=>(float)($case['budgetPln']??0)],['Authorization: Bearer '.$controlToken,'Idempotency-Key: '.$id.':'.$kind,'Content-Type: application/json','Accept: application/json']);
    if(!in_array($vps['status'],[200,201],true)||($vps['body']['ready']??false)!==true||($vps['body']['routingReady']??false)!==true||($vps['body']['tlsReady']??false)!==true||($vps['body']['hostname']??'')!==$hostname) throw new RuntimeException('VPS nie potwierdził routingu i TLS środowiska (HTTP '.$vps['status'].').');
    $dnsUrl='https://api.cloudflare.com/client/v4/zones/'.rawurlencode($zone).'/dns_records';
    $auth=['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json'];
    $lookup=workerRequest('GET',$dnsUrl.'?name='.rawurlencode($hostname),null,$auth);
    if($lookup['status']!==200||($lookup['body']['success']??false)!==true) throw new RuntimeException('Nie udało się sprawdzić rekordu DNS w Cloudflare.');
    $records=$lookup['body']['result']??[];
    if(count($records)>1) throw new RuntimeException('Subdomena jest zajęta przez wiele rekordów DNS.');
    if($records) {
        $record=$records[0];
        if(($record['type']??'')!=='CNAME'||strtolower((string)($record['content']??''))!==$origin||($record['proxied']??false)!==true||!str_contains((string)($record['comment']??''),$id)) throw new RuntimeException('Subdomena jest zajęta przez inny rekord DNS.');
    } else {
        $created=workerRequest('POST',$dnsUrl,['type'=>'CNAME','name'=>$hostname,'content'=>$origin,'proxied'=>true,'ttl'=>1,'comment'=>'Grzywniak project '.$id],$auth);
        if($created['status']!==200 && $created['status']!==201) throw new RuntimeException('Nie udało się utworzyć rekordu DNS (HTTP '.$created['status'].').');
        if(($created['body']['success']??false)!==true) throw new RuntimeException('Cloudflare nie potwierdził utworzenia rekordu DNS.');
        $record=$created['body']['result']??[];
    }
    return ['hostname'=>$hostname,'url'=>'https://'.$hostname,'dnsRecordId'=>(string)($record['id']??''),'origin'=>$origin,'vpsProjectId'=>(string)($vps['body']['projectId']??$id),'state'=>'infrastructure_ready'];
}

function deploymentEvidence(array $tasks): array {
    $qa=array_values(array_filter($tasks,static fn($task)=>$task['role']==='qa' && $task['state']==='done'));
    if(!$qa) throw new RuntimeException('Podgląd wymaga zakończonego zadania QA.');
    $result=$qa[count($qa)-1]['result']??[];
    $sha=(string)($result['commitSha']??'');
    $digest=(string)($result['imageDigest']??'');
    $port=filter_var($result['appPort']??null,FILTER_VALIDATE_INT);
    $healthPath=(string)($result['healthPath']??'');
    if(!preg_match('/^[a-f0-9]{40}$/',$sha) || !preg_match('/^sha256:[a-f0-9]{64}$/',$digest) || ($result['qaPassed']??false)!==true || !$port || $port>65535 || !preg_match('~^/[a-zA-Z0-9/_-]{1,100}$~',$healthPath)) throw new RuntimeException('QA musi potwierdzić commit, obraz, port, ścieżkę zdrowia i wynik kontroli.');
    return ['commitSha'=>$sha,'imageDigest'=>$digest,'appPort'=>$port,'healthPath'=>$healthPath];
}

function verifyGithubCi(array $repository,string $sha): void {
    $org=trim(projectSetting('GITHUB_ORG'));
    $name=(string)($repository['name']??'');
    if(!preg_match('/^[A-Za-z0-9_.-]+$/',$org) || !preg_match('/^[A-Za-z0-9_.-]+$/',$name)) throw new RuntimeException('Niepoprawne repozytorium do weryfikacji CI.');
    $checks=githubApi('GET','/repos/'.rawurlencode($org).'/'.rawurlencode($name).'/commits/'.$sha.'/check-runs',null,githubToken());
    if($checks['status']!==200) throw new RuntimeException('Nie udało się sprawdzić CI dla wskazanego commita.');
    $passed=[];
    foreach(($checks['body']['check_runs']??[]) as $check) if(in_array($check['name']??'',['validate','publish'],true) && ($check['conclusion']??'')==='success') $passed[$check['name']]=true;
    if(isset($passed['validate'],$passed['publish'])) return;
    throw new RuntimeException('Wskazany commit nie ma pozytywnych kontroli validate i publish.');
}

function verifyPublicDeployment(string $hostname,string $projectId,string $digest,string $healthPath): void {
    $curl=curl_init('https://'.$hostname.'/.well-known/grzywniak/deployment');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $raw=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
    $body=is_string($raw)?json_decode($raw,true):null;
    if($status!==200 || !is_array($body) || ($body['projectId']??'')!==$projectId || ($body['imageDigest']??'')!==$digest) throw new RuntimeException('Publiczny adres HTTPS nie potwierdza uruchomienia dokładnie zatwierdzonego obrazu.');
    $curl=curl_init('https://'.$hostname.$healthPath);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $healthy=curl_exec($curl); $healthStatus=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
    if($healthy===false || $healthStatus!==200) throw new RuntimeException('Publiczna ścieżka zdrowia aplikacji nie odpowiada poprawnie.');
}

function deployProjectVersion(array $session,array $case,array $repository,array $environment,array $evidence,string $kind): array {
    $id=(string)$session['id'];
    $hostname=(string)($environment['hostname']??'');
    if($hostname==='' || ($environment['state']??'')!=='infrastructure_ready') throw new RuntimeException('Środowisko VPS i DNS nie jest przygotowane.');
    $control=rtrim(trim(projectSetting('VPS_CONTROL_URL')),'/'); $token=projectSetting('VPS_CONTROL_TOKEN');
    if(!str_starts_with($control,'https://') || $token==='') throw new RuntimeException('Brak bezpiecznej konfiguracji VPS.');
    verifyGithubCi($repository,$evidence['commitSha']);
    $response=workerRequest('POST',$control.'/v1/deployments',['projectId'=>$id,'kind'=>$kind,'hostname'=>$hostname,'repository'=>$repository['url'],'commitSha'=>$evidence['commitSha'],'imageDigest'=>$evidence['imageDigest'],'appPort'=>$evidence['appPort'],'healthPath'=>$evidence['healthPath']],['Authorization: Bearer '.$token,'Idempotency-Key: '.$id.':'.$kind.':'.$evidence['imageDigest'],'Content-Type: application/json','Accept: application/json'],180);
    $deploymentId=(string)($response['body']['deploymentId']??'');
    if(!in_array($response['status'],[200,201],true) || ($response['body']['ready']??false)!==true || ($response['body']['imageDigest']??'')!==$evidence['imageDigest'] || ($response['body']['hostname']??'')!==$hostname || !preg_match('/^[A-Za-z0-9_-]{4,100}$/',$deploymentId)) throw new RuntimeException('VPS nie potwierdził gotowego wdrożenia wskazanego obrazu.');
    try { verifyPublicDeployment($hostname,$id,$evidence['imageDigest'],$evidence['healthPath']); }
    catch(Throwable $error) {
        $rollback=workerRequest('POST',$control.'/v1/deployments/'.rawurlencode($deploymentId).'/rollback',[],['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json']);
        if($rollback['status']!==200 || ($rollback['body']['rolledBack']??false)!==true) throw new RuntimeException('Kontrola publiczna nie powiodła się, a VPS nie potwierdził powrotu do poprzedniej wersji.');
        throw $error;
    }
    return ['url'=>'https://'.$hostname,'hostname'=>$hostname,'commitSha'=>$evidence['commitSha'],'imageDigest'=>$evidence['imageDigest'],'appPort'=>$evidence['appPort'],'healthPath'=>$evidence['healthPath'],'deployedAt'=>time(),'vpsDeploymentId'=>$deploymentId];
}
