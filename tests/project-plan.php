<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-agent.php';
require_once __DIR__.'/../api/project-model.php';
putenv('PROJECT_AI_MOCK=true');
$plan=generateProjectPlan(['summary'=>['sections'=>[]]],['scope'=>'Przykładowa aplikacja webowa','budgetPln'=>0]);
if(count($plan['tasks'])!==3 || $plan['tasks'][2]['dependencies']!==['frontend']) throw new RuntimeException('Plan testowy ma błędne zależności.');
$leadSession=['offer'=>['sections'=>[['items'=>['Formularz kontaktowy do pozyskiwania zapytań']]]]];
$leadCase=['scope'=>'Strona portfolio z formularzem kontaktowym','budgetPln'=>10];
if(!projectPlanRequiresContactDelivery($leadSession,$leadCase)) throw new RuntimeException('Formularz kontaktowy musi uruchamiać wymóg bezpiecznego dostarczania leadów.');
if(projectPlanHasContactDelivery(['tasks'=>[['role'=>'frontend','title'=>'Formularz kontaktowy','acceptance'=>['Formularz waliduje e-mail.']]]])) throw new RuntimeException('Frontend-only form must not pass as a functioning lead-delivery flow.');
if(!projectPlanHasContactDelivery(['tasks'=>[['role'=>'backend','title'=>'Endpoint formularza kontaktowego','acceptance'=>['Backend waliduje zgłoszenie i wysyła je na zatwierdzony adres e-mail.']]]])) throw new RuntimeException('Backend delivery task should satisfy the lead-flow plan gate.');
$newStack=projectTasksWithScaffold($plan['tasks']);
if(count($newStack)!==4 || $newStack[0]['id']!=='scaffold' || !in_array('scaffold',$newStack[2]['dependencies'],true)) throw new RuntimeException('Nowy stos nie wymaga przygotowania repozytorium przed budową.');
echo "Project plan mock OK\n";
