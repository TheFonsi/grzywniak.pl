<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-templates.php';
$sessionId='11111111111111111111111111111111';
$id='proposal-'.substr($sessionId,0,12);
$db=projectTemplatesDb();
if(projectTemplate($id)) throw new RuntimeException('Test proposal already exists.');
try {
    $decision=['mode'=>'new','templateId'=>'','proposedName'=>'Szablon aplikacji testowej','reason'=>'Projekt wymaga innego układu i technologii niż dostępny szablon React i Vite.'];
    $plan=['stack'=>'Inny stos','architecture'=>'Ogólna architektura bez danych klienta.'];
    $first=projectTemplateProposal($sessionId,$decision,$plan);
    $second=projectTemplateProposal($sessionId,$decision,$plan);
    if($first['id']!==$id||$second['id']!==$id||$second['status']!=='proposed') throw new RuntimeException('Template proposal is not idempotent.');
    if(!projectTemplate('web-vite')) throw new RuntimeException('Starter template missing.');
    $templateRoot=__DIR__.'/../templates/web-vite';
    foreach(['package-lock.json','.gitignore','.dockerignore','.github/workflows/ci.yml'] as $requiredFile) if(!is_file($templateRoot.'/'.$requiredFile)) throw new RuntimeException('Starter template is missing '.$requiredFile.'.');
    $workflow=(string)file_get_contents($templateRoot.'/.github/workflows/ci.yml');
    if(!str_contains($workflow,'npm ci --no-audit --no-fund')||!str_contains($workflow,'docker build --tag')||!str_contains($workflow,'packages: write')||!str_contains($workflow,'github.sha')) throw new RuntimeException('Starter CI does not validate, build, and publish an immutable commit image.');
    if(!str_contains((string)file_get_contents($templateRoot.'/Dockerfile'),'npm ci --no-audit --no-fund')) throw new RuntimeException('Container build does not use the committed lockfile.');
    echo "Project template catalog and proposal OK\n";
} finally { $db->prepare('DELETE FROM project_templates WHERE id=?')->execute([$id]); }
