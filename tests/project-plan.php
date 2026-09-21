<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-agent.php';
require_once __DIR__.'/../api/project-model.php';
putenv('PROJECT_AI_MOCK=true');
$plan=generateProjectPlan(['summary'=>['sections'=>[]]],['scope'=>'Przykładowa aplikacja webowa','budgetPln'=>0]);
if(count($plan['tasks'])!==3 || $plan['tasks'][2]['dependencies']!==['frontend']) throw new RuntimeException('Plan testowy ma błędne zależności.');
$newStack=projectTasksWithScaffold($plan['tasks']);
if(count($newStack)!==4 || $newStack[0]['id']!=='scaffold' || !in_array('scaffold',$newStack[2]['dependencies'],true)) throw new RuntimeException('Nowy stos nie wymaga przygotowania repozytorium przed budową.');
echo "Project plan mock OK\n";
