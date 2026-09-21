<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-ai-budget.php';
$payload=json_encode(['input'=>str_repeat('a',1000),'max_output_tokens'=>100],JSON_THROW_ON_ERROR);
$expected=ceil((strlen($payload)*1000+100*2000)/10000)/100;
if(projectAiMaximumCost($payload,[1000,2000])!==$expected) throw new RuntimeException('Niepoprawna rezerwacja kosztu tokenów.');
try { projectAiMaximumCost(json_encode(['max_output_tokens'=>8001],JSON_THROW_ON_ERROR),[1000,2000]); throw new RuntimeException('Przekroczony limit odpowiedzi został zaakceptowany.'); }
catch(RuntimeException $error) { if($error->getMessage()==='Przekroczony limit odpowiedzi został zaakceptowany.') throw $error; }
echo "Project AI budget estimate OK\n";
