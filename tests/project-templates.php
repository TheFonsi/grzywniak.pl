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
    echo "Project template catalog and proposal OK\n";
} finally { $db->prepare('DELETE FROM project_templates WHERE id=?')->execute([$id]); }
