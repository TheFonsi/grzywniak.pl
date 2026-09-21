<?php
declare(strict_types=1);
require_once __DIR__.'/project-execution.php';

function projectAiRates(): array {
    $input=(float)projectSetting('OPENAI_INPUT_PLN_PER_MILLION');
    $output=(float)projectSetting('OPENAI_OUTPUT_PLN_PER_MILLION');
    if($input<=0 || $output<=0) throw new RuntimeException('Ustaw dodatnie stawki tokenów modelu w limitach panelu.');
    return [$input,$output];
}

function projectAiMaximumCost(string $payloadJson,array $rates): float {
    $payload=json_decode($payloadJson,true,512,JSON_THROW_ON_ERROR);
    $maxOutput=(int)($payload['max_output_tokens']??0);
    if(strlen($payloadJson)>100000 || $maxOutput<1 || $maxOutput>8000) throw new RuntimeException('Żądanie modelu przekracza dopuszczalny rozmiar.');
    return max(0.01,ceil((strlen($payloadJson)*$rates[0]+$maxOutput*$rates[1])/10000)/100);
}

function projectAiReservation(string $id,string $callKey,string $kind,array $case,string $payloadJson,array $rates): float {
    if(!preg_match('/^job:[1-9][0-9]*:[1-9][0-9]*$/',$callKey)) throw new InvalidArgumentException('Niepoprawny identyfikator wywołania AI.');
    $reserve=projectAiMaximumCost($payloadJson,$rates);
    $db=projectDb(); $db->exec('BEGIN IMMEDIATE');
    try {
        $concurrency=(int)$db->query("SELECT COUNT(*) FROM project_agent_tasks WHERE state='running'")->fetchColumn()+(int)$db->query("SELECT COUNT(*) FROM project_ai_calls WHERE state='reserved'")->fetchColumn();
        if($concurrency>=max(1,(int)projectSetting('AGENT_MAX_CONCURRENCY'))) throw new RuntimeException('Limit równoległych agentów jest wykorzystany.');
        [$projectTotal,$monthlyTotal]=projectTaskTotals($db,$id);
        if($reserve>(float)projectSetting('AGENT_TASK_COST_LIMIT_PLN')+0.0001 || $projectTotal+$reserve>(float)($case['budgetPln']??0)+0.0001 || $monthlyTotal+$reserve>(float)projectSetting('AGENT_MONTHLY_LIMIT_PLN')+0.0001) throw new RuntimeException('Limit kosztu zadania, projektu lub miesiąca blokuje wywołanie modelu.');
        $db->prepare("INSERT INTO project_ai_calls(call_key,session_id,task_kind,state,reserved_pln,created_at,updated_at) VALUES(?,?,?,'reserved',?,?,?)")->execute([$callKey,$id,$kind,$reserve,time(),time()]);
        $db->exec('COMMIT');
        return $reserve;
    } catch(Throwable $error) { if($db->inTransaction()) $db->exec('ROLLBACK'); throw $error; }
}

function projectAiSettle(string $callKey,array $response,array $rates,float $reserve): void {
    $usage=$response['usage']??null;
    $input=$usage['input_tokens']??null; $output=$usage['output_tokens']??null;
    if(!is_int($input)||!is_int($output)||$input<0||$output<0) throw new RuntimeException('Dostawca nie zwrócił liczby tokenów. Rezerwacja wymaga rozliczenia.');
    $spent=round(($input*$rates[0]+$output*$rates[1])/1000000,4);
    if(!is_finite($spent)||$spent<0) throw new RuntimeException('Niepoprawny koszt wywołania modelu.');
    $db=projectDb();
    $db->prepare("UPDATE project_ai_calls SET state='settled',reserved_pln=0,spent_pln=?,input_tokens=?,output_tokens=?,updated_at=? WHERE call_key=? AND state='reserved'")->execute([$spent,$input,$output,time(),$callKey]);
    if($spent>$reserve+0.0001) throw new RuntimeException('Dostawca przekroczył zarezerwowany koszt; dalsza praca wymaga decyzji.');
}

function projectBudgetedAiResponse(string $id,string $callKey,string $kind,array $case,array $payload): array {
    $rates=projectAiRates();
    $key=projectSetting('OPENAI_API_KEY');
    if($key==='') throw new RuntimeException('Brak klucza OpenAI dla agentów projektu.');
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $reserve=projectAiReservation($id,$callKey,$kind,$case,$json,$rates);
    $curl=curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>$json,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>90,CURLOPT_CONNECTTIMEOUT=>8]);
    $raw=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
    $response=is_string($raw)?json_decode($raw,true):null;
    if(is_array($response) && is_array($response['usage']??null)) projectAiSettle($callKey,$response,$rates,$reserve);
    if($raw===false || $status<200 || $status>=300) throw new RuntimeException('Wywołanie modelu nie powiodło się (HTTP '.$status.'); rezerwacja bez rozliczenia wymaga wyjaśnienia.');
    if(!is_array($response)) throw new RuntimeException('Model zwrócił niepoprawną odpowiedź; rezerwacja wymaga rozliczenia.');
    $state=projectDb()->prepare('SELECT state FROM project_ai_calls WHERE call_key=?'); $state->execute([$callKey]);
    if($state->fetchColumn()!=='settled') throw new RuntimeException('Brak rozliczenia tokenów; rezerwacja wymaga wyjaśnienia.');
    return $response;
}
