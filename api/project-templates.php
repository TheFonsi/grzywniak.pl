<?php
declare(strict_types=1);
require_once __DIR__.'/project-settings-model.php';

function projectTemplatesDb(): PDO {
    $db=sessionDb();
    $db->exec('CREATE TABLE IF NOT EXISTS project_templates (id TEXT PRIMARY KEY, name TEXT NOT NULL, stack TEXT NOT NULL, description TEXT NOT NULL, repo_owner TEXT NOT NULL, repo_name TEXT NOT NULL, status TEXT NOT NULL, source_session TEXT, reason TEXT NOT NULL DEFAULT \'\', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
    $owner=projectSetting('GITHUB_TEMPLATE_OWNER'); $repo=projectSetting('GITHUB_TEMPLATE_REPO');
    $stmt=$db->prepare("INSERT OR IGNORE INTO project_templates(id,name,stack,description,repo_owner,repo_name,status,source_session,reason,created_at,updated_at) VALUES('web-vite','Strona lub aplikacja React + Vite','React, Vite, TypeScript','Klientowa aplikacja webowa z Dockerfile i GitHub Actions.',?,?,'active',NULL,'Szablon startowy systemu.',?,?)");
    $stmt->execute([$owner,$repo,time(),time()]);
    return $db;
}
function projectTemplates(string $status='active'): array {
    $stmt=projectTemplatesDb()->prepare('SELECT * FROM project_templates WHERE status=? ORDER BY created_at DESC');
    $stmt->execute([$status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function projectTemplate(string $id): ?array {
    $stmt=projectTemplatesDb()->prepare('SELECT * FROM project_templates WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
}
function projectTemplateProposal(string $sessionId,array $decision,array $plan): array {
    if(!preg_match('/^[a-f0-9]{32}$/',$sessionId)) throw new InvalidArgumentException('Niepoprawna sprawa.');
    $name=trim((string)($decision['proposedName']??''));
    $reason=trim((string)($decision['reason']??''));
    if(mb_strlen($name)<5||mb_strlen($name)>100||mb_strlen($reason)<15||mb_strlen($reason)>1000) throw new InvalidArgumentException('Propozycja szablonu wymaga nazwy i uzasadnienia.');
    $id='proposal-'.substr($sessionId,0,12);
    $repo='web-template-'.substr($sessionId,0,8);
    $stmt=projectTemplatesDb()->prepare("INSERT OR IGNORE INTO project_templates(id,name,stack,description,repo_owner,repo_name,status,source_session,reason,created_at,updated_at) VALUES(?,?,?,?,?,?,'proposed',?,?,?,?)");
    $stmt->execute([$id,$name,mb_substr((string)($plan['stack']??''),0,200),mb_substr((string)($plan['architecture']??''),0,500),projectSetting('GITHUB_ORG'),$repo,$sessionId,$reason,time(),time()]);
    return projectTemplate($id)??throw new RuntimeException('Nie udało się zapisać propozycji szablonu.');
}
