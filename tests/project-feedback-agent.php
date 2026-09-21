<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-feedback-agent.php';
require_once __DIR__.'/../api/project-model.php';
putenv('PROJECT_AI_MOCK=true');
$id=bin2hex(random_bytes(16));
$db=projectDb();
try {
    $feedback=['message'=>'Po kliknięciu przycisku zapis nie działa w uzgodnionym formularzu.','image_digest'=>'sha256:'.str_repeat('a',64),'page_url'=>'https://example.test/form'];
    $result=classifyProjectFeedback($feedback,['scope'=>'Formularz z działającym zapisem danych.']);
    if($result['category']!=='bug' || trim($result['rationale'])==='') throw new RuntimeException('Klasyfikacja testowa nie zwróciła oceny.');
    $db->prepare("INSERT INTO project_feedback(session_id,image_digest,message,page_url,state,category,analysis,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)")
       ->execute([$id,$feedback['image_digest'],$feedback['message'],$feedback['page_url'],'triaged',$result['category'],json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time(),time()]);
    $stored=projectFeedback($id)[0]??null;
    if(!$stored || $stored['category']!=='bug' || ($stored['analysis']['suggestedAction']??'')!==$result['suggestedAction']) throw new RuntimeException('Ocena uwagi nie została odczytana z bazy.');
    echo "Project feedback triage OK\n";
} finally {
    $db->prepare('DELETE FROM project_feedback WHERE session_id=?')->execute([$id]);
}
