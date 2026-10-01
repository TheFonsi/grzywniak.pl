<?php
declare(strict_types=1);
require_once __DIR__.'/project-model.php';
require_once __DIR__.'/project-settings-model.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
function feedbackPage(string $message,int $status=200,string $form=''): never {
    http_response_code($status);
    echo '<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Uwagi do projektu</title><style>body{font:16px system-ui;background:#101b2b;color:#f3f6fb;margin:0;padding:40px 16px}main{max-width:650px;margin:auto;background:#1b2a3f;border:1px solid #405471;border-radius:18px;padding:30px}h1{font-size:28px}label{display:block;margin:22px 0}textarea,input{display:block;width:100%;box-sizing:border-box;margin-top:8px;padding:12px;background:#101b2b;color:white;border:1px solid #8297b4;border-radius:8px;font:inherit}button{background:#8eb5ff;color:#101b2b;border:0;border-radius:8px;padding:12px 20px;font:inherit;font-weight:700;cursor:pointer}p{line-height:1.6}</style><main><h1>Uwagi do podglądu</h1><p>'.htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>'.$form.'</main></html>'; exit;
}
$jsonRequest=str_contains(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json') || $_SERVER['REQUEST_METHOD']==='OPTIONS';
$payload=[];
if($jsonRequest && $_SERVER['REQUEST_METHOD']==='POST') { $decoded=json_decode(file_get_contents('php://input')?:'{}',true); if(is_array($decoded)) $payload=$decoded; }
$id=(string)($_SERVER['REQUEST_METHOD']==='POST'?($_POST['project']??$payload['project']??''):($_GET['project']??''));
$token=(string)($_SERVER['REQUEST_METHOD']==='POST'?($_POST['token']??$payload['token']??''):($_GET['token']??''));
if(!preg_match('/^[a-f0-9]{32}$/',$id) || ($_SERVER['REQUEST_METHOD']!=='OPTIONS' && !preg_match('/^[a-f0-9]{48}$/',$token))) feedbackPage('Link jest niepoprawny.',404);
$case=projectCase($id);
$feedbackDigest=projectFeedbackDigest($case);
if($_SERVER['REQUEST_METHOD']!=='OPTIONS' && (empty($case['feedbackTokenHash']) || !hash_equals((string)$case['feedbackTokenHash'],hash('sha256',$token)) || $feedbackDigest===null)) feedbackPage('Link wygasł lub nie jest już aktualny.',403);
if($jsonRequest) {
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    $base=strtolower(trim(projectSetting('PREVIEW_BASE_DOMAIN')));
    $expected='https://p-'.substr($id,0,12).'.'.$base;
    if($origin!==$expected) { http_response_code(403); exit; }
    header('Access-Control-Allow-Origin: '.$expected); header('Vary: Origin'); header('Access-Control-Allow-Methods: POST, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Max-Age: 600');
    if($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
    header('Content-Type: application/json; charset=utf-8');
    if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['message'=>'Niedozwolona metoda.']); exit; }
    $requestId=bin2hex(random_bytes(6));
    $message=trim((string)($payload['message']??'')); $page=trim((string)($payload['page_url']??'')); $annotation=$payload['annotation']??null;
    $fail=function(string $message,int $status) use ($requestId): never { http_response_code($status); $body=['message'=>$message]; if($status>=500) $body['reference']=$requestId; echo json_encode($body,JSON_UNESCAPED_UNICODE); exit; };
    $messageLength=function_exists('mb_strlen')?mb_strlen($message,'UTF-8'):strlen($message);
    $pageLength=function_exists('mb_strlen')?mb_strlen($page,'UTF-8'):strlen($page);
    if($messageLength<10 || $messageLength>4000 || $pageLength>1000) $fail('Wpisz uwagę od 10 do 4000 znaków.',400);
    if($page!=='' && (!filter_var($page,FILTER_VALIDATE_URL)||!str_starts_with($page,'https://'))) $fail('Adres strony musi być adresem HTTPS.',400);
    try { $safeAnnotation=projectNormalizeFeedbackAnnotation($annotation); } catch(InvalidArgumentException $error) { $fail($error->getMessage(),400); }
    $db=null; $transactionOpen=false;
    try {
        $db=projectDb();
        $db->exec('BEGIN IMMEDIATE');
        $transactionOpen=true;
        $count=$db->prepare('SELECT COUNT(*) FROM project_feedback WHERE session_id=? AND created_at>=?'); $count->execute([$id,time()-3600]);
        if((int)$count->fetchColumn()>=30) { $db->rollBack(); $fail('Osiągnięto limit 30 zgłoszeń na godzinę.',429); }
        $db->prepare('INSERT INTO project_feedback(session_id,image_digest,message,page_url,annotation_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([$id,$feedbackDigest,$message,$page,json_encode($safeAnnotation,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time(),time()]);
        $feedbackId=(int)$db->lastInsertId(); projectEnqueue($id,'classify_feedback_'.$feedbackId); projectEvent($id,'feedback','received','Klient','Nowa uwaga do wersji '.$feedbackDigest.'.'); $db->commit(); $transactionOpen=false;
    } catch(Throwable $error) {
        if($transactionOpen && $db instanceof PDO) { try { $db->rollBack(); } catch(Throwable) {} }
        error_log('Feedback ['.$requestId.']: '.$error->getMessage());
        if($error instanceof PDOException && str_contains(strtolower($error->getMessage()),'locked')) $fail('Serwer jest chwilowo zajęty. Twoja uwaga nie została wysłana; spróbuj ponownie.',503);
        $fail('Nie udało się zapisać uwagi. Spróbuj ponownie za chwilę.',500);
    }
    http_response_code(201); echo json_encode(['message'=>'Uwaga została zapisana przy właściwej wersji podglądu.'],JSON_UNESCAPED_UNICODE); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    $message=trim((string)($_POST['message']??'')); $page=trim((string)($_POST['page_url']??''));
    if(mb_strlen($message)<10 || mb_strlen($message)>4000 || mb_strlen($page)>1000) feedbackPage('Wpisz uwagę od 10 do 4000 znaków i spróbuj ponownie.',400);
    if($page!=='' && (!filter_var($page,FILTER_VALIDATE_URL) || !str_starts_with($page,'https://'))) feedbackPage('Adres strony musi być adresem HTTPS.',400);
    $db=projectDb(); $db->exec('BEGIN IMMEDIATE');
    try { $count=$db->prepare('SELECT COUNT(*) FROM project_feedback WHERE session_id=? AND created_at>=?'); $count->execute([$id,time()-3600]); if((int)$count->fetchColumn()>=30) { $db->rollBack(); feedbackPage('Osiągnięto limit 30 zgłoszeń na godzinę. Spróbuj później.',429); } $db->prepare('INSERT INTO project_feedback(session_id,image_digest,message,page_url,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$id,$feedbackDigest,$message,$page,time(),time()]); $feedbackId=(int)$db->lastInsertId(); projectEnqueue($id,'classify_feedback_'.$feedbackId); projectEvent($id,'feedback','received','Klient','Nowa uwaga do wersji '.$feedbackDigest.'.'); $db->commit(); }
    catch(Throwable $error) { if($db->inTransaction())$db->rollBack(); error_log('Feedback: '.$error->getMessage()); feedbackPage('Nie udało się zapisać uwagi.',500); }
    feedbackPage('Dziękujemy. Uwaga została zapisana przy właściwej wersji podglądu.');
}
if($_SERVER['REQUEST_METHOD']!=='GET') feedbackPage('Niedozwolona metoda.',405);
$form='<form method="post"><input type="hidden" name="project" value="'.htmlspecialchars($id,ENT_QUOTES,'UTF-8').'"><input type="hidden" name="token" value="'.htmlspecialchars($token,ENT_QUOTES,'UTF-8').'"><label>Adres widoku (opcjonalnie)<input name="page_url" type="url" maxlength="1000" placeholder="https://..."></label><label>Twoja uwaga<textarea name="message" rows="7" minlength="10" maxlength="4000" required></textarea></label><button>Wyślij uwagę</button></form>';
feedbackPage('Opisz zmianę lub błąd. Uwaga zostanie przypięta do wersji, którą otrzymałeś w wiadomości.',200,$form);
