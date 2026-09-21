<?php
declare(strict_types=1);
require_once __DIR__.'/project-model.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
function feedbackPage(string $message,int $status=200,string $form=''): never {
    http_response_code($status);
    echo '<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Uwagi do projektu</title><style>body{font:16px system-ui;background:#101b2b;color:#f3f6fb;margin:0;padding:40px 16px}main{max-width:650px;margin:auto;background:#1b2a3f;border:1px solid #405471;border-radius:18px;padding:30px}h1{font-size:28px}label{display:block;margin:22px 0}textarea,input{display:block;width:100%;box-sizing:border-box;margin-top:8px;padding:12px;background:#101b2b;color:white;border:1px solid #8297b4;border-radius:8px;font:inherit}button{background:#8eb5ff;color:#101b2b;border:0;border-radius:8px;padding:12px 20px;font:inherit;font-weight:700;cursor:pointer}p{line-height:1.6}</style><main><h1>Uwagi do podglądu</h1><p>'.htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>'.$form.'</main></html>'; exit;
}
$id=(string)($_SERVER['REQUEST_METHOD']==='POST'?($_POST['project']??''):($_GET['project']??''));
$token=(string)($_SERVER['REQUEST_METHOD']==='POST'?($_POST['token']??''):($_GET['token']??''));
if(!preg_match('/^[a-f0-9]{32}$/',$id) || !preg_match('/^[a-f0-9]{48}$/',$token)) feedbackPage('Link jest niepoprawny.',404);
$case=projectCase($id);
if(empty($case['feedbackTokenHash']) || !hash_equals((string)$case['feedbackTokenHash'],hash('sha256',$token)) || empty($case['previewSentDigest'])) feedbackPage('Link wygasł lub nie jest już aktualny.',403);
if($_SERVER['REQUEST_METHOD']==='POST') {
    $message=trim((string)($_POST['message']??'')); $page=trim((string)($_POST['page_url']??''));
    if(mb_strlen($message)<10 || mb_strlen($message)>4000 || mb_strlen($page)>1000) feedbackPage('Wpisz uwagę od 10 do 4000 znaków i spróbuj ponownie.',400);
    if($page!=='' && (!filter_var($page,FILTER_VALIDATE_URL) || !str_starts_with($page,'https://'))) feedbackPage('Adres strony musi być adresem HTTPS.',400);
    $db=projectDb(); $db->exec('BEGIN IMMEDIATE');
    try {
        $count=$db->prepare('SELECT COUNT(*) FROM project_feedback WHERE session_id=? AND created_at>=?');
        $count->execute([$id,time()-3600]);
        if((int)$count->fetchColumn()>=5) { $db->rollBack(); feedbackPage('Osiągnięto limit pięciu uwag na godzinę. Spróbuj później.',429); }
        $db->prepare('INSERT INTO project_feedback(session_id,image_digest,message,page_url,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$id,$case['previewSentDigest'],$message,$page,time(),time()]);
        $feedbackId=(int)$db->lastInsertId();
        projectEnqueue($id,'classify_feedback_'.$feedbackId);
        projectEvent($id,'feedback','received','Klient','Nowa uwaga do wersji '.$case['previewSentDigest'].'.');
        $db->commit();
    } catch(Throwable $error) { if($db->inTransaction())$db->rollBack(); error_log('Feedback: '.$error->getMessage()); feedbackPage('Nie udało się zapisać uwagi.',500); }
    feedbackPage('Dziękujemy. Uwaga została zapisana przy właściwej wersji podglądu.');
}
if($_SERVER['REQUEST_METHOD']!=='GET') feedbackPage('Niedozwolona metoda.',405);
$form='<form method="post"><input type="hidden" name="project" value="'.htmlspecialchars($id,ENT_QUOTES,'UTF-8').'"><input type="hidden" name="token" value="'.htmlspecialchars($token,ENT_QUOTES,'UTF-8').'"><label>Adres widoku, którego dotyczy uwaga (opcjonalnie)<input name="page_url" type="url" maxlength="1000" placeholder="https://..."></label><label>Twoja uwaga<textarea name="message" rows="7" minlength="10" maxlength="4000" required></textarea></label><button>Wyślij uwagę</button></form>';
feedbackPage('Opisz zmianę lub błąd. Uwaga zostanie przypięta do wersji, którą otrzymałeś w wiadomości.',200,$form);
