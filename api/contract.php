<?php
 declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/contract-model.php';
require_once __DIR__.'/contract-pdf.php';
require_once __DIR__.'/contract-ai.php';
require_once __DIR__.'/contract-review.php';
header('Content-Type: application/json; charset=utf-8');
$user=getenv('ADMIN_USERNAME')?:''; $password=getenv('ADMIN_PASSWORD')?:'';
if($user==='' || $password==='' || !hash_equals($user,(string)($_SERVER['PHP_AUTH_USER']??'')) || !hash_equals($password,(string)($_SERVER['PHP_AUTH_PW']??''))) { header('WWW-Authenticate: Basic realm="Grzywniak Discovery"'); http_response_code(401); exit; }
function contractError(int $status,string $message): never { http_response_code($status); echo json_encode(['message'=>$message],JSON_UNESCAPED_UNICODE); exit; }
function contractReply(array $data,string $location): never {
    sessionDb()->exec('COMMIT');
    if (!str_contains((string)($_SERVER['CONTENT_TYPE']??''),'application/json')) { header('Location: '.$location,true,303); exit; }
    echo json_encode($data,JSON_UNESCAPED_UNICODE); exit;
}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)) contractError(405,'Niedozwolona metoda.');
$body=[];
if($method==='POST') {
    $body=json_decode(file_get_contents('php://input')?:'{}',true);
    if(!is_array($body)) $body=[];
    $body=array_replace($body,$_POST);
    if(!hash_equals(contractToken(),(string)($body['csrf']??''))) contractError(403,'Sesja formularza wygasła. Odśwież panel.');
    // AI only fills the browser form; do not hold a SQLite write lock during HTTP.
    if(($body['action']??$body['contract_action']??'')!=='ai-fill') sessionDb()->exec('BEGIN IMMEDIATE');
}
$action=(string)($body['action']??$body['contract_action']??'');
if($action==='save-profile') {
    $profile=contractProfile();
    if((int)($body['expectedVersion']??-1)!==(int)$profile['version']) contractError(409,'Dane wykonawcy zostały zmienione. Odśwież ustawienia.');
    foreach(contractProfileFields() as $key=>$label) {
        if(!is_string($body[$key]??null)||mb_strlen($body[$key])>2000) contractError(422,'Niepoprawne pole: '.$label);
        $profile[$key]=trim($body[$key]);
    }
    $missing=contractProfileMissing($profile);
    if($missing) contractError(422,'Uzupełnij dane wykonawcy: '.implode(', ',$missing));
    if(!filter_var($profile['email'],FILTER_VALIDATE_EMAIL)) contractError(422,'Podaj poprawny e-mail wykonawcy.');
    $profile['version']++; $profile['updatedAt']=time();
    $stmt=sessionDb()->prepare('INSERT INTO contract_profile(id,data) VALUES(1,:data) ON CONFLICT(id) DO UPDATE SET data=excluded.data');
    $stmt->execute([':data'=>json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    contractReply(['profileVersion'=>$profile['version']],apiPath('admin.php').'?view=contract-settings&profileSaved=1');
}
if($action==='save-template') {
    $template=contractTemplate();
    if((int)($body['expectedVersion']??-1)!==(int)$template['version']) contractError(409,'Wzór został zmieniony. Odśwież ustawienia.');
    foreach(contractFields() as $key=>$label) if($key!=='provider' && array_key_exists($key,contractTemplateDefaults())) { if(!is_string($body[$key]??null)||mb_strlen($body[$key])>20000) contractError(422,'Niepoprawna treść pola: '.$label); $template[$key]=trim($body[$key]); }
    foreach(['defaultTransferTerms','defaultIpPayment'] as $key) {
        if(!is_string($body[$key]??null)||trim($body[$key])===''||mb_strlen($body[$key])>2000) contractError(422,'Uzupełnij domyślne warunki praw IP (maksymalnie 2000 znaków).');
        $template[$key]=trim($body[$key]);
    }
    $template['version']++; $template['updatedAt']=time();
    $stmt=sessionDb()->prepare('INSERT INTO contract_templates(id,data) VALUES(1,:data) ON CONFLICT(id) DO UPDATE SET data=excluded.data');
    $stmt->execute([':data'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    contractReply(['template'=>$template],apiPath('admin.php').'?view=contract-settings&saved=1');
}
$id=(string)($_GET['session']??$body['contract_session']??'');
if(!preg_match('/^[a-f0-9]{32}$/',$id)) contractError(400,'Niepoprawny identyfikator rozmowy.');
$s=readSession($id);
if(!$s) contractError(404,'Nie znaleziono rozmowy.');
if($method==='GET' && ($_GET['format']??'')==='editor') { header('Content-Type: text/html; charset=utf-8'); echo contractEditor($s); exit; }
if($method==='GET') {
    if(($_GET['format']??'')==='pdf') {
        $contract=$s['contract']??null;
        if(isset($_GET['version']) && (int)$_GET['version']!==(int)($contract['version']??0)) { $contract=null; foreach($s['contractVersions']??[] as $old) if((int)$old['version']===(int)$_GET['version']) $contract=$old; }
        $pdf=base64_decode((string)($contract['pdfBase64']??''),true);
        if(!$pdf || !str_starts_with($pdf,'%PDF-')) contractError(404,'Ta wersja nie ma przygotowanego PDF.');
        header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="umowa-'.preg_replace('/[^A-Za-z0-9-]/','',$contract['number']).'-v'.(int)$contract['version'].'.pdf"'); header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
    }
    echo json_encode(['contract'=>$s['contract']??null,'contractVersions'=>$s['contractVersions']??[]],JSON_UNESCAPED_UNICODE); exit;
}
if(($s['offer']['status']??'')!=='ACCEPTED') contractError(409,'Najpierw zaakceptuj ofertę.');
if((int)($body['expectedVersion']??-1)!==(int)($s['contract']['version']??0)) contractError(409,'Umowa została zmieniona. Odśwież panel przed zapisem.');
if($action==='ai-fill') {
    $profile=contractProfile();
    if(!isset($s['contract']) && (int)($body['profileVersion']??-1)!==(int)$profile['version']) contractError(409,'Dane wykonawcy zmieniły się. Odśwież formularz, aby pobrać aktualne dane.');
    try {
        $facts=contractReadFacts($body,$s['contract']['facts']??[],contractTemplate()); $draft=[];
        foreach(contractFields() as $key=>$label) { if(!is_string($body[$key]??null)||mb_strlen($body[$key])>20000) throw new InvalidArgumentException('Niepoprawne pole: '.$label); $draft[$key]=$body[$key]; }
        $template=contractTemplate();
        $review=contractReviewState($body['reviewState']??null,$draft,$facts,$s['contract']['review']??[]);
        $accepted=[]; foreach($review as $key=>$entry) if($entry['accepted']) $accepted[$key]=$entry['value'];
        session_write_close();
        $result=contractAiDraft($s['offer'],$draft,$facts,$template,$accepted);
        // Factual identifiers never originate from model guesses. Existing commercial
        // terms are copied exactly; only absent negotiable terms can be proposed.
        foreach(['provider','party'] as $key) $result['fields'][$key]=trim($draft[$key])!==''?$draft[$key]:'[DO UZUPEŁNIENIA: '.contractFields()[$key].']';
        $result['fields']['paymentDetails']=trim($draft['paymentDetails'])!==''?$draft['paymentDetails']:'Płatność przelewem na rachunek wskazany na fakturze.';
        foreach(['price','deposit','deadline'] as $key) if(trim($draft[$key])!=='') $result['fields'][$key]=$draft[$key];
        if(trim($draft['price'])==='') $result['fields']['price']='[DO UZUPEŁNIENIA: wynagrodzenie zgodne z ofertą]';
        foreach(['clientAddress','clientTaxId','clientRepresentative','clientType','dataRole'] as $key) $result['facts'][$key]=$facts[$key];
        foreach($facts as $key=>$value) if($value!=='' && !($key==='rightsTerms'&&$facts['ipMode']==='')) $result['facts'][$key]=$value;
        $result['facts']=contractReadFacts($result['facts'],$s['contract']['facts']??[],$template);
        foreach($accepted as $key=>$value) { if(array_key_exists($key,$result['fields'])) $result['fields'][$key]=$value; elseif(array_key_exists($key,$result['facts'])) $result['facts'][$key]=$value; }
        $latest=readSession($id);
        if(!$latest || ($latest['offer']['status']??'')!=='ACCEPTED' || ($latest['offer']??[])!==$s['offer'] || (int)($latest['contract']['version']??0)!==(int)($s['contract']['version']??0)) contractError(409,'Oferta lub umowa zmieniła się podczas generowania. Odśwież panel.');
        $result['missing']=array_values(array_unique(array_merge(contractFactsMissing($result['facts']),$result['missing'])));
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
    } catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    catch(Throwable $error) { contractError(502,$error instanceof RuntimeException?$error->getMessage():'Nie udało się przygotować projektu AI.'); }
}
if(in_array($action,['save','generate'],true)) {
    if(!is_array($s['contract']??null)) {
        $profile=contractProfile();
        if((int)($body['profileVersion']??0)!==(int)$profile['version']) contractError(409,'Dane wykonawcy zmieniły się. Odśwież formularz przed zapisem.');
        if($action==='generate' && contractProfileMissing($profile)) contractError(422,'Najpierw uzupełnij „Moje dane do umów” w ustawieniach.');
    }
    try { $facts=contractReadFacts($body,$s['contract']['facts']??[],contractTemplate()); } catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    $c=[];
    foreach(contractFields() as $key=>$label) { if(!is_string($body[$key]??null)||mb_strlen($body[$key])>20000) contractError(422,'Niepoprawna treść pola: '.$label); $c[$key]=trim($body[$key]); }
    try { $review=contractReviewState($body['reviewState']??null,$c,$facts,$s['contract']['review']??[]); } catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    if($action==='generate') foreach(['provider','party','scope','price','deadline','ip'] as $key) if($c[$key]==='') contractError(422,'Uzupełnij: '.contractFields()[$key]);
    if($action==='generate') {
        $pending=contractPendingReview($review);
        if($pending) contractError(422,'Zaakceptuj lub zmień propozycje przed PDF: '.implode(', ',$pending));
        $missing=contractFactsMissing($facts);
        if($missing) contractError(422,'Uzupełnij ustalenia umowy: '.implode(', ',$missing));
        foreach(array_merge($c,$facts) as $value) if(preg_match('/\[DO (UZUPEŁNIENIA|UZGODNIENIA):/iu',$value)) contractError(422,'Uzupełnij oznaczone braki danych przed wygenerowaniem PDF. Projekt możesz zapisać w obecnej postaci.');
    }
    $c+=['number'=>$s['contract']['number']??'UM-'.date('Y').'-'.strtoupper(substr(hash('sha256',$id),0,8)), 'version'=>(int)($s['contract']['version']??0)+1, 'createdAt'=>time(), 'templateVersion'=>(int)($s['contract']['templateVersion']??$body['templateVersion']??0), 'offerVersion'=>(int)($s['offer']['version']??1), 'status'=>'DRAFT'];
    $c['facts']=$facts;
    $c['review']=$review;
    $c['profileVersion']=(int)($s['contract']['profileVersion']??$body['profileVersion']??0);
    if($action==='generate') $c['pdfBase64']=base64_encode(contractPdf($c));
    if(is_array($s['contract']??null)) $s['contractVersions'][]=$s['contract'];
    $s['contract']=$c; writeSession($s);
    contractReply(['contract'=>$c],apiPath('admin.php').'?view=all&session='.$id.'#contract-panel');
}
if($action==='send') {
    $c=$s['contract']??null;
    if(empty($c['pdfBase64'])) contractError(409,'Najpierw przygotuj PDF aktualnej wersji.');
    $to=(string)(($s['offer']['contact']['email']??'')?:($s['projectState']['contactEmail']??''));
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)) contractError(422,'Brak poprawnego adresu e-mail klienta.');
    $boundary='contract_'.bin2hex(random_bytes(16));
    $headers='MIME-Version: 1.0'."\r\n".'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
    $message='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode('W załączeniu projekt umowy do zapoznania się i podpisania.')).'--'.$boundary."\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"umowa-v".(int)$c['version'].".pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split($c['pdfBase64']).'--'.$boundary."--\r\n";
    $mock=getenv('DISCOVERY_MAIL_MOCK')==='true';
    if(!$mock && !@mail($to,'=?UTF-8?B?'.base64_encode('Projekt umowy — Grzywniak.pl').'?=',$message,$headers)) contractError(502,'Nie udało się przekazać wiadomości do serwera poczty.');
    $s['contract']['status']=$mock?'MOCK_SENT':'SENT'; $s['contract']['sentAt']=time(); $s['contract']['sentTo']=$to;
    $s['contract']['deliveryLog'][]=['to'=>$to,'at'=>time(),'mock'=>$mock]; writeSession($s);
    contractReply(['contract'=>$s['contract']],apiPath('admin.php').'?view=all&session='.$id.'#contract-panel');
}
contractError(400,'Nieznana operacja.');
