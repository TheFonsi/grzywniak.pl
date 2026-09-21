<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/discovery-prompt.php';
const DISCOVERY_SCOPE_GUARD = 'You handle discovery only for digital products and IT services: websites, web or mobile apps, business systems, software, process automation, integrations, data solutions, and AI features. The client may be from any industry, but the requested solution itself must be digital or IT-related. Requests whose primary goal is a physical, household, culinary, construction, repair, or other non-IT task (for example cooking a meal, laying kitchen tiles, renovation work, or a joke) are out of scope. For an out-of-scope request, reply in the active conversation language with one short, polite sentence saying that you collect requirements only for digital/IT projects and that other matters can be sent through the email form. Set offTopic=true, do not update projectState, do not set readyForSummary, and do not continue discovery. If a request could reasonably describe an IT solution supporting such work, ask one concise question about the digital solution and keep offTopic=false.';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, ['https://grzywniak.pl', 'https://www.grzywniak.pl', 'http://localhost:8443'], true)) {
  http_response_code(403); echo json_encode(['message' => 'Origin not allowed.']); exit;
}

const MAX_MESSAGE = 4000;
const SESSION_TTL = 2592000;
const ALLOWED_STATUS = ['STARTED', 'DISCOVERY', 'NEEDS_INFORMATION', 'READY_FOR_SUMMARY', 'COMPLETED', 'CLOSED'];
const ALLOWED_FLAGS = ['MEDICAL','FINANCIAL','LEGAL','PAYMENTS','PERSONAL_DATA','SENSITIVE_DATA','HIGH_SECURITY','EXTERNAL_INTEGRATION','DATA_MIGRATION','LARGE_SCALE','UNCLEAR_SCOPE','UNREALISTIC_BUDGET','UNREALISTIC_DEADLINE'];
function reply(array $data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function input(): array { $data = json_decode(file_get_contents('php://input') ?: '{}', true); return is_array($data) ? $data : []; }
function sessionPath(string $id): string { return storage() . '/' . preg_replace('/[^a-f0-9]/', '', $id) . '.json'; }
function loadSession(string $id): array { if (!preg_match('/^[a-f0-9]{32}$/', $id) || !is_file(sessionPath($id))) reply(['message' => 'Nie znaleziono rozmowy.'], 404); $data = json_decode((string) file_get_contents(sessionPath($id)), true); if (!is_array($data) || ($data['createdAt'] ?? 0) < time() - SESSION_TTL) reply(['message' => 'Rozmowa wygasła. Rozpocznij nową.'], 410); return $data; }
function contactMissingFields(array $session): array { $state=$session['projectState']??[]; $labels=[]; if (!is_string($state['contactName']??null) || trim((string)($state['contactName']??''))==='') $labels[]='imienia lub nazwy firmy'; if (!is_string($state['contactPhone']??null) || trim((string)($state['contactPhone']??''))==='') $labels[]='preferowanego numeru telefonu'; if (!is_string($state['contactEmail']??null) || trim((string)($state['contactEmail']??''))==='') $labels[]='adresu e-mail'; return $labels; }
function hasRequiredContact(array $session): bool { return contactMissingFields($session) === []; }
function hasDiscoveryMinimum(array $session): bool { $state = is_array($session['projectState'] ?? null) ? $session['projectState'] : []; foreach (['businessProblem','targetUsers','mustHaveFeatures','budget','deadline'] as $field) if (!is_string($state[$field] ?? null) || trim((string) $state[$field]) === '') return false; return true; }
function contactFromMessage(string $message): array { $result=[]; if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',$message,$match)) $result['contactEmail']=$match[0]; if (preg_match('/(?:nazwa firmy|firma|imię)\s*:\s*(.+?)(?=\s+(?:oraz|i)\s+|[,.;]|$)/iu',$message,$match)) $result['contactName']=trim($match[1]); if (preg_match('/(?:numer telefonu|telefon|tel\.?)(?:\s*:\s*|\s+)([+()\d][\d\s()\-]{6,})/iu',$message,$match)) $result['contactPhone']=trim($match[1]); return $result; }
function asksAboutBudget(string $content): bool { return preg_match('/(?:^|\\n)\\s*[^?\\n]*budżet[^?\\n]*\\?\\s*$/iu', $content) === 1; }
function asksAboutTimeline(string $content): bool { return preg_match('/(?:^|\\n)\\s*[^?\\n]*(?:kiedy|termin|uruchomienie|wdrożenie|realizacj)[^?\\n]*\\?\\s*$/iu', $content) === 1; }
function asksAboutExplicitTimeline(string $content): bool {
  return preg_match('/(?:^|\\n)\\s*[^?\\n]*(?:na kiedy|do kiedy|jaki(?: jest)? termin|orientacyjny termin|kiedy planujesz|kiedy ma by[cć]|kiedy powin(?:no|na|ien)|w jakim terminie|deadline|data uruchomienia|termin uruchomienia)[^?\\n]*\\?\\s*$/iu', $content) === 1;
}
function loadSessionDb(string $id): array { if (!preg_match('/^[a-f0-9]{32}$/', $id)) reply(['message'=>'Nie znaleziono rozmowy.'],404); $data=readSession($id); if (!is_array($data)) reply(['message'=>'Nie znaleziono rozmowy.'],404); if (($data['createdAt']??0)<time()-SESSION_TTL) reply(['message'=>'Rozmowa wygasła. Rozpocznij nową.'],410); return $data; }

function saveSession(array &$session): void {
  if (is_array($session['internalAnalysis'] ?? null) && ($session['internalAnalysis']['status'] ?? '') === 'COMPLETED') { $old = is_file(sessionPath((string) ($session['id'] ?? ''))) ? json_decode((string) file_get_contents(sessionPath((string) $session['id'])), true) : []; $oldAnalysis = is_array($old['internalAnalysis'] ?? null) ? $old['internalAnalysis'] : []; if (($oldAnalysis['createdAt'] ?? null) !== ($session['internalAnalysis']['createdAt'] ?? null)) { $session['internalAnalysis']['version'] = max(1, (int) ($oldAnalysis['version'] ?? 0) + 1); $session['internalAnalysis']['runId'] = substr(hash('sha256', (string) ($session['id'] ?? '') . '-analysis-' . $session['internalAnalysis']['version']), 0, 16); } }
  $last=$session['messages'][array_key_last($session['messages'])]??null;
  if(($session['language']??'pl')==='pl'&&is_array($last)&&asksAboutBudget((string)($last['content']??''))) $session['suggestedAnswers']=['do 1 tys. zł','1–2 tys. zł','2–5 tys. zł','5–10 tys. zł','10–20 tys. zł','20–30 tys. zł','powyżej 30 tys. zł'];
  if(($session['language']??'pl')==='pl'&&is_array($last)&&asksAboutTimeline((string)($last['content']??''))) $session['suggestedAnswers']=['Jak najszybciej','W ciągu miesiąca','1–3 miesiące','3–6 miesięcy','Termin elastyczny'];
  if((($session['readyForSummary']??false)||($session['status']??'')==='COMPLETED')&&(!hasRequiredContact($session) || !hasDiscoveryMinimum($session))) {
    $session['readyForSummary']=false; $session['status']='NEEDS_INFORMATION';
    if(is_array($last)&&!preg_match('/(?:imię|nazwa firmy|telefon|e-mail|kontakt)/iu',(string)($last['content']??''))) {
      $missing=contactMissingFields($session);
      if ($missing) { $fields=count($missing)===1?$missing[0]:implode(', ',array_slice($missing,0,-1)).' oraz '.end($missing); $followUp='Zanim przekażemy brief zespołowi, potrzebuję jeszcze '.$fields.' do kontaktu.'; }
      else { $followUp=nextDiscoveryQuestion($session['projectState'] ?? [], (string) ($session['language'] ?? 'pl')); }
      $session['messages'][]=['id'=>bin2hex(random_bytes(8)),'role'=>'assistant','content'=>$followUp];
    }
  }
  writeSession($session);
}
function discoveryProgress(array $session): int {
  if (($session['status'] ?? '') === 'COMPLETED') return 100;
  $userTurns=count(array_filter($session['messages'] ?? [], static fn(array $entry): bool => ($entry['role'] ?? '') === 'user'));
  if ($userTurns === 0) return 0;
  $state=$session['projectState'] ?? [];
  $fields=['businessProblem','targetUsers','coreProcesses','mustHaveFeatures','budget','deadline'];
  $filled=count(array_filter($fields, static fn(string $field): bool => is_string($state[$field] ?? null) && trim($state[$field]) !== ''));
  if ($filled === 0) return 0;
  $progress=(int)round(($filled / count($fields)) * 85);
  if (($session['readyForSummary'] ?? false)) return 100;
  return max(8, $progress);
}
function publicSession(array $s): array { $ready=(bool)($s['readyForSummary']??false)&&hasRequiredContact($s)&&hasDiscoveryMinimum($s); $status=(!$ready&&($s['status']??'')==='READY_FOR_SUMMARY')?'NEEDS_INFORMATION':$s['status']; $suggestions=array_values($s['suggestedAnswers'] ?? []); $last=$s['messages'][array_key_last($s['messages'])]??null; if(($s['language']??'pl')==='pl'&&is_array($last)&&($last['role']??'')==='assistant'){if(asksAboutTimeline((string)($last['content']??'')))$suggestions=['Jak najszybciej','W ciągu miesiąca','1–3 miesiące','3–6 miesięcy','Termin elastyczny'];elseif(asksAboutBudget((string)($last['content']??'')))$suggestions=['do 1 tys. zł','1–2 tys. zł','2–5 tys. zł','5–10 tys. zł','10–20 tys. zł','20–30 tys. zł','powyżej 30 tys. zł'];} return ['id'=>$s['id'],'status'=>$status,'messages'=>$s['messages'],'readyForSummary'=>$ready,'summary'=>$s['summary'] ?? null,'progress'=>discoveryProgress($s),'showContactForm'=>(bool)($s['showContactForm'] ?? false),'suggestedAnswers'=>$suggestions,'draftAnalysis'=>isset($s['draftAnalysis']) ? array_intersect_key($s['draftAnalysis'], array_flip(['status','missingInformation'])) : null,'followUpQuestionIndex'=>(int)($s['followUpQuestionIndex']??0)]; }
function safeStateUpdate(mixed $update): array {
  $allowed = ['projectSummary','businessProblem','businessGoals','targetUsers','userRoles','coreProcesses','mustHaveFeatures','niceToHaveFeatures','integrations','platforms','authenticationRequirements','permissions','notifications','dataRequirements','fileRequirements','sensitiveData','existingSystem','migrationRequirements','estimatedUsers','budget','deadline','mvpScope','outOfScope','openQuestions','assumptions','contactName','contactPhone','contactEmail'];
  if (!is_array($update)) return [];
  $safe = [];
  foreach ($allowed as $key) {
    $value = $update[$key] ?? null;
    if (is_string($value) && trim($value) !== '') $safe[$key] = mb_substr(trim($value), 0, 2000);
    if (is_array($value) && count($value) <= 30) {
      $valid = true;
      foreach ($value as $item) if (!is_string($item) || mb_strlen($item) > 500) $valid = false;
      if ($valid && $value) $safe[$key] = implode('; ', array_filter(array_map('trim', $value)));
    }
  }
  return $safe;
}
function nextDiscoveryQuestion(array $state, string $language): string {
  $pl = $language !== 'en';
  foreach (['businessProblem','targetUsers','coreProcesses','mustHaveFeatures','budget','deadline','contactName','contactPhone','contactEmail'] as $field) {
    if (is_string($state[$field] ?? null) && trim($state[$field]) !== '') continue;
    if ($field === 'mustHaveFeatures' && preg_match('/(?:zdoby(?:wa|ć)|zlece(?:nia|ń)|klient(?:ów|a)?|zapytan(?:ia|ie)|kontakt(?:ów|owy)?)/iu', json_encode($state, JSON_UNESCAPED_UNICODE) ?: '')) return $pl ? 'W jaki sposób klient ma się skontaktować lub wysłać zapytanie — formularzem, telefonem czy innym kanałem?' : 'How should a potential customer contact you or send an enquiry — form, phone or another channel?';
    return match($field) {
      'businessProblem' => $pl ? 'Jaki problem ma rozwiązać projekt lub co chcesz dzięki niemu osiągnąć?' : 'What problem should this project solve or what would you like to achieve?',
      'targetUsers' => $pl ? 'Kto będzie korzystać z tego rozwiązania na co dzień?' : 'Who will use this solution day to day?',
      'coreProcesses' => $pl ? 'Jak dziś krok po kroku radzisz sobie z tym zadaniem i co najbardziej utrudnia lub spowalnia pracę?' : 'How do you handle this task step by step today, and what causes the biggest difficulty or delay?',
      'mustHaveFeatures' => $pl ? 'Co rozwiązanie musi umożliwiać od pierwszego dnia?' : 'What must the solution enable from day one?',
      'budget' => $pl ? 'Jaki orientacyjny przedział budżetu chcesz przeznaczyć na ten projekt?' : 'What approximate budget range do you have for this project?',
      'deadline' => $pl ? 'Na kiedy planujesz uruchomienie rozwiązania?' : 'When would you like to launch the solution?',
      'contactName' => $pl ? 'Jakie imię lub nazwę firmy mamy zapisać do kontaktu?' : 'What name or company name should we save for contact?',
      'contactPhone' => $pl ? 'Jaki numer telefonu mamy zapisać do kontaktu?' : 'What phone number should we save for contact?',
      default => $pl ? 'Jaki adres e-mail mamy zapisać do kontaktu?' : 'What email address should we save for contact?',
    };
  }
  return $pl ? 'Czy jest jeszcze jedna rzecz, którą to rozwiązanie musi dobrze robić od pierwszego dnia?' : 'Is there one more thing this solution must do well from day one?';
}
function buildSummary(array $state): array {
  $sections=[];
  foreach(['contactName'=>'Kontakt / firma','contactPhone'=>'Telefon kontaktowy','contactEmail'=>'E-mail kontaktowy','businessGoals'=>'Cel projektu','businessProblem'=>'Problem do rozwiązania','targetUsers'=>'Użytkownicy','coreProcesses'=>'Główne działania','mustHaveFeatures'=>'Najważniejszy zakres pierwszej wersji','niceToHaveFeatures'=>'Pomysły na kolejny etap','integrations'=>'Integracje','budget'=>'Podany budżet','deadline'=>'Oczekiwany termin'] as $key=>$title){
    $value=$state[$key]??null;
    if ($key === 'integrations' && !$value) continue;
    $content = $value ? (is_array($value)?array_map('strval',$value):[(string)$value]) : ['Do ustalenia'];
    if ($key === 'niceToHaveFeatures' && !$value) $content = ['Możliwe rozszerzenia ustalimy po uruchomieniu pierwszej wersji.', 'Do ustalenia wspólnie z zespołem.'];
    $sections[]=['title'=>$title,'content'=>$content];
  }
  $sections[]=['title'=>'Kolejne kroki','content'=>['1. Zespół zweryfikuje zebrane informacje, zakres i najważniejsze założenia projektu.','2. Przygotujemy rozwiązanie dopasowane do potrzeb, harmonogram oraz szczegółową wycenę.','3. Wyślemy wstępną ofertę, gdy wszystko zostanie sprawdzone i opracowane.','4. Skontaktujemy się z Tobą, aby omówić szczegóły.']];
  return ['title'=>'Brief projektu','sections'=>$sections];
}
function sendSummaryEmail(array $session): bool {
  $recipient=getenv('DISCOVERY_TO') ?: (getenv('CONTACT_TO') ?: 'dawid@grzywniak.pl');
  $sender=getenv('CONTACT_FROM') ?: $recipient;
  if (!filter_var($recipient,FILTER_VALIDATE_EMAIL) || !filter_var($sender,FILTER_VALIDATE_EMAIL)) return false;
  $body="Nowy brief z AI Analityka Projektowego\n\n";
  foreach (($session['summary']['sections'] ?? []) as $section) $body .= ($section['title'] ?? '') . ":\n- " . implode("\n- ", $section['content'] ?? []) . "\n\n";
  $body .= "Ryzyka: " . implode(', ', $session['riskFlags'] ?? []) . "\nSesja: " . $session['id'];
  return @mail($recipient, 'Nowy brief projektu — grzywniak.pl', $body, "From: Grzywniak.pl <{$sender}>\r\nContent-Type: text/plain; charset=UTF-8");
}
require_once __DIR__ . '/internal-analysis.php';
function limited(string $key, int $max, int $window): bool { $file = sys_get_temp_dir() . '/grzywniak-discovery-' . hash('sha256', $key); $h = fopen($file, 'c+'); if (!$h || !flock($h, LOCK_EX)) return false; $now=time(); $items=json_decode(stream_get_contents($h) ?: '[]', true); $items=is_array($items)?array_values(array_filter($items, fn($v)=>is_int($v)&&$v>$now-$window)):[]; $ok=count($items)<$max; if($ok)$items[]=$now; rewind($h); ftruncate($h,0); fwrite($h,json_encode($items)); flock($h,LOCK_UN); fclose($h); return !$ok; }
function keepCurrentAnalysisDecisions(array &$session): void {
  $questions = array_fill_keys(array_map('trim', is_array($session['internalAnalysis']['missingInformation'] ?? null) ? $session['internalAnalysis']['missingInformation'] : []), true);
  if (!$questions) { $session['adminDecisions'] = []; $session['adminProposals'] = []; return; }
  foreach (['adminDecisions','adminProposals'] as $key) if (is_array($session[$key] ?? null)) $session[$key] = array_intersect_key($session[$key], $questions);
}
function fallbackDiscoveryResult(string $message, string $language, array $state = []): array {
  $contact = contactFromMessage($message);
  $question = nextDiscoveryQuestion($state, $language);
  return ['message' => ($language === 'en' ? 'Thank you — I saved this information. ' : 'Dziękuję — zapisuję tę informację. ') . $question, 'projectStateUpdate' => array_merge(array_fill_keys(['businessProblem','businessGoals','targetUsers','coreProcesses','mustHaveFeatures','niceToHaveFeatures','integrations','sensitiveData','existingSystem','migrationRequirements','budget','deadline','contactName','contactPhone','contactEmail'], null), $contact), 'discoveryStatus' => 'DISCOVERY', 'missingInformation' => [], 'riskFlags' => [], 'readyForSummary' => false, 'offTopic' => false, 'suggestedAnswers' => []];
}
function ai(array $session, string $message): array {
  if (getenv('DISCOVERY_MOCK') === 'true') return ['message'=>'Dziękuję — rozumiem kierunek. Jak dziś wygląda ten proces i kto będzie korzystać z rozwiązania na co dzień?','projectStateUpdate'=>['businessProblem'=>$message],'discoveryStatus'=>'DISCOVERY','missingInformation'=>['obecny proces','użytkownicy'],'riskFlags'=>[],'readyForSummary'=>false,'offTopic'=>false,'suggestedAnswers'=>['Właściciel firmy','Pracownicy','Klienci']];
  $key=getenv('OPENAI_API_KEY'); if (!$key) throw new RuntimeException('Usługa analityczna nie jest jeszcze skonfigurowana.');
  $stateKeys = ['businessProblem','businessGoals','targetUsers','coreProcesses','mustHaveFeatures','niceToHaveFeatures','integrations','sensitiveData','existingSystem','migrationRequirements','budget','deadline','contactName','contactPhone','contactEmail'];
  $stateProperties = [];
  foreach ($stateKeys as $stateKey) $stateProperties[$stateKey] = ['type'=>['string','null']];
  $schema=['type'=>'object','additionalProperties'=>false,'required'=>['message','projectStateUpdate','discoveryStatus','missingInformation','riskFlags','readyForSummary','offTopic','suggestedAnswers'],'properties'=>['message'=>['type'=>'string'],'projectStateUpdate'=>['type'=>'object','properties'=>$stateProperties,'required'=>$stateKeys,'additionalProperties'=>false],'discoveryStatus'=>['type'=>'string','enum'=>ALLOWED_STATUS],'missingInformation'=>['type'=>'array','items'=>['type'=>'string']],'riskFlags'=>['type'=>'array','items'=>['type'=>'string','enum'=>ALLOWED_FLAGS]],'readyForSummary'=>['type'=>'boolean'],'offTopic'=>['type'=>'boolean'],'suggestedAnswers'=>['type'=>'array','items'=>['type'=>'string']]]];
  // Rebuild contact fields from the complete user history so older sessions do not
  // ask for a name/e-mail again after the contact was already provided.
  $historyContacts=[];
  foreach (($session['messages'] ?? []) as $entry) if (($entry['role'] ?? '') === 'user') $historyContacts=array_replace($historyContacts, contactFromMessage((string) ($entry['content'] ?? '')));
  if ($historyContacts) $session['projectState']=array_replace($historyContacts, is_array($session['projectState'] ?? null) ? $session['projectState'] : []);
  $context=array_map(static fn(array $entry): array => ['role'=>(string)$entry['role'],'content'=>(string)$entry['content']], array_slice($session['messages'], -4));
  $context[]=['role'=>'user','content'=>$message];
  $languageInstruction=(($session['language'] ?? 'pl') === 'en') ? 'Conduct the entire conversation in English.' : 'Prowadź całą rozmowę po polsku.';
  $missingContact=contactMissingFields($session);
  $contactInstruction=$missingContact ? 'Dane kontaktowe już zapisane w aktualnym stanie są prawdziwe i nie wolno o nie pytać ponownie. Jeśli pytasz o kontakt, pytaj wyłącznie o: '.implode(', ',$missingContact).'.' : 'Wszystkie wymagane dane kontaktowe są już zapisane. Nie pytaj ponownie o imię, firmę, telefon ani e-mail.';
  $payload=['model'=>getenv('OPENAI_MODEL') ?: 'gpt-5.6-luna','store'=>false,'reasoning'=>['effort'=>'low'],'max_output_tokens'=>2200,'prompt_cache_key'=>'grzywniak-discovery-v3','input'=>[['role'=>'system','content'=>DISCOVERY_SYSTEM_PROMPT],['role'=>'system','content'=>DISCOVERY_SCOPE_GUARD],['role'=>'system','content'=>$languageInstruction],['role'=>'system','content'=>$contactInstruction],['role'=>'system','content'=>'Aktualny stan projektu: '.json_encode($session['projectState'], JSON_UNESCAPED_UNICODE)], ...$context],'text'=>['format'=>['type'=>'json_schema','name'=>'discovery_turn','strict'=>true,'schema'=>$schema]]];
  $ch=curl_init('https://api.openai.com/v1/responses'); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>35]); $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_error($ch);
  if ($raw===false || $code<200 || $code>=300) {
    error_log('Discovery OpenAI failure: HTTP '.$code.' '.$error);
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $details = json_decode(is_string($raw) ? $raw : '', true);
    $reason = is_array($details) ? (string)($details['error']['message'] ?? '') : '';
    if ($code === 0) throw new RuntimeException('Nie można połączyć się z usługą AI. Sprawdź internet, zaporę sieciową lub konfigurację SSL serwera.');
    throw new RuntimeException($local ? 'OpenAI odrzuciło żądanie (HTTP '.$code.'): '.$reason : 'Analityk chwilowo nie może odpowiedzieć. Spróbuj ponownie.');
  }
  $response=json_decode($raw,true);
  $text=is_string($response['output_text'] ?? null) ? $response['output_text'] : '';
  if ($text === '') foreach (($response['output'] ?? []) as $item) foreach (($item['content'] ?? []) as $content) if (($content['type'] ?? '') === 'output_text' && is_string($content['text'] ?? null)) { $text=$content['text']; break 2; }
  $result=json_decode($text,true); if (($response['status'] ?? '') === 'incomplete' || !is_array($result) || !is_string($result['message'] ?? null) || trim($result['message']) === '' || !is_array($result['projectStateUpdate'] ?? null) || !in_array($result['discoveryStatus'] ?? '', ALLOWED_STATUS, true)) { error_log('Discovery invalid or incomplete structured output'); throw new RuntimeException(($session['language'] ?? 'pl') === 'en' ? 'The response was interrupted. Please send your message again.' : 'Odpowiedź została przerwana. Wyślij wiadomość ponownie — jej treść pozostaje w polu tekstowym.'); }
  if (preg_match('/budżet[^?]*\?/iu', (string) ($result['message'] ?? '')) && preg_match('/(?:kiedy|termin|uruchom|wdrożeni)[^?]*\?/iu', (string) ($result['message'] ?? ''))) {
    $result['message'] = trim((string) preg_replace('/(?:^|\n)[^\n?]*(?:kiedy|termin|uruchom|wdrożeni)[^\n?]*\?\s*/iu', '', (string) $result['message'], 1));
  }
  if(!str_contains((string)$result['message'],'?') && !preg_match('/\?\s*$/u', $message) && !($result['readyForSummary']??false) && !($result['offTopic']??false)){ $nextState=array_replace($session['projectState']??[],safeStateUpdate($result['projectStateUpdate']??[])); $result['message']=rtrim((string)$result['message'])."\n\n".nextDiscoveryQuestion($nextState,(string)($session['language']??'pl')); }
  if(($session['language'] ?? 'pl')==='pl' && asksAboutBudget((string)($result['message'] ?? ''))) $result['suggestedAnswers']=['do 1 tys. zł','1–2 tys. zł','2–5 tys. zł','5–10 tys. zł','10–20 tys. zł','20–30 tys. zł','powyżej 30 tys. zł'];
  if(($session['language'] ?? 'pl')==='pl' && asksAboutTimeline((string)($result['message'] ?? ''))) $result['suggestedAnswers']=['Jak najszybciej','W ciągu miesiąca','1–3 miesiące','3–6 miesięcy','Termin elastyczny'];
  $result['projectStateUpdate']=array_replace(is_array($result['projectStateUpdate']??null)?$result['projectStateUpdate']:[],contactFromMessage($message));
  $nextState = array_replace($session['projectState'] ?? [], safeStateUpdate($result['projectStateUpdate'] ?? []));
  $previousAssistant = '';
  foreach (array_reverse($session['messages'] ?? []) as $entry) { if (($entry['role'] ?? '') === 'assistant') { $previousAssistant = (string) ($entry['content'] ?? ''); break; } }
  preg_match_all('/[^?\n]{5,}\?/u', $previousAssistant, $oldQuestions);
  preg_match_all('/[^?\n]{5,}\?/u', (string) ($result['message'] ?? ''), $newQuestions);
  if (!empty($oldQuestions[0]) && !empty($newQuestions[0])) {
    $oldSignature = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim(end($oldQuestions[0]))));
    $newSignature = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim(end($newQuestions[0]))));
    if ($oldSignature === $newSignature && $nextState !== ($session['projectState'] ?? [])) $result['message'] = (($session['language'] ?? 'pl') === 'en' ? "Thank you.\n\n" : "Dziękuję.\n\n") . nextDiscoveryQuestion($nextState, (string) ($session['language'] ?? 'pl'));
  }
  $result['_usage']=$response['usage'] ?? [];
  return $result;
}
function normaliseDiscoveryTurn(array $session, array $result): array {
  if (!empty($result['offTopic'])) {
    $result['projectStateUpdate']=[]; $result['readyForSummary']=false; $result['discoveryStatus']='DISCOVERY'; $result['suggestedAnswers']=[];
    return $result;
  }
  $candidate=$session;
  $candidate['projectState']=array_replace($session['projectState']??[],safeStateUpdate($result['projectStateUpdate']??[]));
  // Odpowiedź modelu może zawierać kilka pytań lub powtórzyć już uzupełniony etap.
  // Przed zapisaniem zostawiamy najwyżej jedno pytanie właściwe dla bieżącego stanu.
  $message = (string) ($result['message'] ?? '');
  preg_match_all('/[^?\n]{5,}\?/u', $message, $questionMatches);
  $questions = $questionMatches[0] ?? [];
  if (count($questions) > 1) {
    $first = trim((string) $questions[0]);
    $message = preg_replace('/\n?[^?\n]{5,}\?/u', '', $message, -1, $removed) ?? $message;
    $result['message'] = trim($message) . "\n\n" . $first;
  }
  $stateText = mb_strtolower(json_encode($candidate['projectState'], JSON_UNESCAPED_UNICODE) ?: '');
  $asked = mb_strtolower((string) (end($questions) ?: ''), 'UTF-8');
  $answered = (($candidate['projectState']['budget'] ?? '') !== '' && str_contains($asked, 'budżet'))
    || (($candidate['projectState']['deadline'] ?? '') !== '' && (str_contains($asked, 'termin') || str_contains($asked, 'kiedy') || str_contains($asked, 'uruchom')))
    || (($candidate['projectState']['targetUsers'] ?? '') !== '' && str_contains($asked, 'kto'))
    || (($candidate['projectState']['mustHaveFeatures'] ?? '') !== '' && (str_contains($asked, 'musi umożliwiać') || str_contains($asked, 'funkcj')));
  if ($answered) { $result['message'] = trim((string) preg_replace('/[^?\n]{5,}\?/u', '', (string) ($result['message'] ?? ''))); $result['message'] .= "\n\n" . nextDiscoveryQuestion($candidate['projectState'], (string) ($session['language'] ?? 'pl')); $result['suggestedAnswers'] = []; }
  unset($stateText);
  $ready=!empty($result['readyForSummary']) && hasDiscoveryMinimum($candidate) && hasRequiredContact($candidate) && !str_contains($result['message'],'?');
  if (!empty($result['readyForSummary']) && !$ready) {
    $result['message']=nextDiscoveryQuestion($candidate['projectState'],(string)($session['language']??'pl'));
    $result['suggestedAnswers']=[];
  }
  $result['readyForSummary']=$ready;
  $result['discoveryStatus']=$ready?'READY_FOR_SUMMARY':'DISCOVERY';
  return $result;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(['message'=>'Method not allowed'],405);
$action=$_GET['action'] ?? ''; $ip=$_SERVER['REMOTE_ADDR'] ?? 'unknown';
if ($action === 'summary') $action = 'complete';
if ($action === 'retryAnalysis') {
  $user=getenv('ADMIN_USERNAME') ?: ''; $pass=getenv('ADMIN_PASSWORD') ?: '';
  if ($user==='' || $pass==='' || !hash_equals($user,(string)($_SERVER['PHP_AUTH_USER']??'')) || !hash_equals($pass,(string)($_SERVER['PHP_AUTH_PW']??''))) reply(['message'=>'Wymagane logowanie administratora.'],401);
}
if ($action==='message' && limited($ip.'-messages', 30, 120)) reply(['message'=>'Zbyt wiele wiadomości. Spróbuj za chwilę.'],429);
$data=input();
if($action==='session'){ $id=bin2hex(random_bytes(16)); $language=(($data['language'] ?? 'pl') === 'en') ? 'en' : 'pl'; $opening=$language==='en' ? 'Briefly tell me what system or solution you would like to create. You do not need to know the technology or prepare a specification — start with the problem you want to solve, and I will help organise the requirements.' : 'Opowiedz mi krótko, jaki system lub rozwiązanie chcesz stworzyć. Nie musisz znać technologii ani przygotowywać specyfikacji — zacznij od problemu, który chcesz rozwiązać, a ja pomogę uporządkować wymagania.'; $s=['id'=>$id,'status'=>'STARTED','createdAt'=>time(),'updatedAt'=>time(),'projectState'=>[],'riskFlags'=>[],'language'=>$language,'messages'=>[['id'=>bin2hex(random_bytes(8)),'role'=>'assistant','content'=>$opening]],'readyForSummary'=>false,'showContactForm'=>false,'suggestedAnswers'=>[]]; saveSession($s); reply(['session'=>publicSession($s)]); }
$id=(string)($_GET['sessionId']??'');
if (!preg_match('/^[a-f0-9]{32}$/', $id)) reply(['message'=>'Nie znaleziono rozmowy.'],404);
$sessionLock=fopen(storage().'/'.$id.'.lock','c');
if (!$sessionLock || !flock($sessionLock,LOCK_EX|LOCK_NB)) reply(['message'=>'Poprzednia operacja nadal trwa. Poczekaj chwilę.'],409);
register_shutdown_function(static function () use ($sessionLock): void { flock($sessionLock,LOCK_UN); fclose($sessionLock); });
$s=loadSessionDb($id);
if ($action === 'complete' && ($s['status']??'') === 'COMPLETED') reply(['session'=>publicSession($s)]);
if (($s['status']??'') === 'CLOSED' && $action !== 'language') reply(['message'=>'Rozmowa została zakończona.'],409);
if($action==='language'){ $s['language']=(($data['language'] ?? 'pl') === 'en') ? 'en' : 'pl'; $hasUserMessage=count(array_filter($s['messages'] ?? [], static fn(array $entry): bool => ($entry['role'] ?? '') === 'user'))>0; if(!$hasUserMessage){$s['messages']=[['id'=>bin2hex(random_bytes(8)),'role'=>'assistant','content'=>$s['language']==='en' ? 'Briefly tell me what system or solution you would like to create. You do not need to know the technology or prepare a specification — start with the problem you want to solve, and I will help organise the requirements.' : 'Opowiedz mi krótko, jaki system lub rozwiązanie chcesz stworzyć. Nie musisz znać technologii ani przygotowywać specyfikacji — zacznij od problemu, który chcesz rozwiązać, a ja pomogę uporządkować wymagania.']];} saveSession($s); reply(['session'=>publicSession($s)]); }
if($action==='finish'){ if(($s['status']??'')==='COMPLETED' || ($s['status']??'')==='CLOSED') reply(['message'=>'Rozmowa została już zakończona.'],409); $s['readyForSummary']=true; $s['status']='READY_FOR_SUMMARY'; $s['showContactForm']=false; $s['messages'][]=['id'=>bin2hex(random_bytes(8)),'role'=>'assistant','content'=>'Mamy informacje potrzebne na start. Możesz teraz przekazać brief zespołowi albo wrócić do rozmowy i dodać więcej szczegółów.']; saveSession($s); reply(['session'=>publicSession($s)]); }
if($action==='discard'){ if(($s['status']??'')==='COMPLETED') reply(['message'=>'Brief został już przekazany zespołowi.'],409); $s['readyForSummary']=false; $s['status']='CLOSED'; $s['showContactForm']=false; saveSession($s); reply(['session'=>publicSession($s)]); }
if($action==='message'){ $message=trim((string)($data['message']??'')); if($message===''||mb_strlen($message)>MAX_MESSAGE) reply(['message'=>'Wiadomość musi mieć od 1 do 4000 znaków.'],422); if(($s['readyForSummary']??false) || in_array(($s['status']??''),['COMPLETED','CLOSED'],true)) reply(['message'=>'Rozmowa została już zakończona.'],409); $started=microtime(true); try{$result=ai($s,$message);}catch(Throwable $e){reply(['message'=>$e->getMessage()],503);} $result=normaliseDiscoveryTurn($s, $result); $s['draftAnalysis']=null; $usage=is_array($result['_usage']??null)?$result['_usage']:[]; unset($result['_usage']); $s['messages'][]=['id'=>bin2hex(random_bytes(8)),'role'=>'user','content'=>$message]; $s['messages'][]=['id'=>bin2hex(random_bytes(8)),'role'=>'assistant','content'=>mb_substr((string)$result['message'],0,1200)]; $s['projectState']=array_replace($s['projectState'],safeStateUpdate($result['projectStateUpdate']??[])); $s['status']=$result['discoveryStatus']; $s['riskFlags']=array_values(array_unique(array_merge($s['riskFlags']??[],array_intersect($result['riskFlags']??[],ALLOWED_FLAGS)))); $s['readyForSummary']=(bool)$result['readyForSummary']; $s['showContactForm']=(bool)($result['offTopic']??false); $suggestions=$result['suggestedAnswers']??[]; $s['suggestedAnswers']=is_array($suggestions)?array_values(array_slice(array_filter($suggestions,static fn($item)=>is_string($item)&&mb_strlen(trim($item))>0&&mb_strlen($item)<=120),0,5)):[]; $s['metrics']['inputTokens']=($s['metrics']['inputTokens']??0)+(int)($usage['input_tokens']??0); $s['metrics']['outputTokens']=($s['metrics']['outputTokens']??0)+(int)($usage['output_tokens']??0); $s['metrics']['cachedTokens']=($s['metrics']['cachedTokens']??0)+(int)($usage['input_tokens_details']['cached_tokens']??0); $s['metrics']['turns']=($s['metrics']['turns']??0)+1; $s['metrics']['lastLatencyMs']=(int)round((microtime(true)-$started)*1000); saveSession($s); reply(['session'=>publicSession($s)]); }
if($action==='prepare'){ if(!($s['readyForSummary']??false)||!hasRequiredContact($s)||!hasDiscoveryMinimum($s)) reply(['message'=>'Przed przygotowaniem briefu potrzebujemy danych kontaktowych.'],409); try{$s['draftAnalysis']=internalAnalysis($s);$s['followUpQuestionIndex']=0;}catch(Throwable $e){error_log('Draft brief analysis failed for session '.$s['id'].': '.$e->getMessage());$s['draftAnalysis']=['status'=>'FAILED','missingInformation'=>[]];} saveSession($s); reply(['session'=>publicSession($s)]); }
if($action==='retryAnalysis'){ if(($s['status']??'')!=='COMPLETED') reply(['message'=>'Analizę można ponowić po przekazaniu briefu.'],409); try{$s['internalAnalysis']=internalAnalysis($s);}catch(Throwable $e){error_log('Internal brief retry failed for session '.$s['id'].': '.$e->getMessage());$s['internalAnalysis']=['status'=>'FAILED','createdAt'=>time(),'message'=>'Analiza nie została ukończona. Spróbuj ponownie później.'];} if(is_array($s['offer']??null)){ $s['offerVersions']=is_array($s['offerVersions']??null)?$s['offerVersions']:[]; $archived=$s['offer']; $archived['archivedAt']=time(); $s['offerVersions'][]=$archived; $s['offer']['status']='OUTDATED'; $s['offer']['outdatedAt']=time(); $s['offer']['updatedAt']=time(); $s['offer']['changedFields']=[]; } saveSession($s); reply(['session'=>publicSession($s),'analysisStatus'=>$s['internalAnalysis']['status']??'FAILED']); }
if($action==='complete'){ if(!($s['readyForSummary']??false)||!hasRequiredContact($s)||!hasDiscoveryMinimum($s)) reply(['message'=>'Przed przekazaniem briefu potrzebujemy nazwy osoby lub firmy oraz numeru telefonu.'],409); $s['summary']=buildSummary($s['projectState']); $s['status']='COMPLETED'; $s['deliveredAt']=time(); try{$s['internalAnalysis']=internalAnalysis($s);}catch(Throwable $e){error_log('Internal brief analysis failed for session '.$s['id'].': '.$e->getMessage());$s['internalAnalysis']=['status'=>'FAILED','createdAt'=>time(),'message'=>'Analiza nie została ukończona. Spróbuj ponownie później.'];} if(!sendSummaryEmail($s)) error_log('Discovery summary email could not be sent for session '.$s['id']); saveSession($s); reply(['session'=>publicSession($s)]); }
// Legacy summary is routed through the same validated delivery action.
if($action==='reopen'){ if(in_array($s['status']??'',['COMPLETED','CLOSED'],true)) reply(['message'=>'Ta rozmowa jest zakończona. Rozpocznij nową rozmowę.'],409); $s['summary']=null; $s['status']='DISCOVERY'; $s['readyForSummary']=false; $s['suggestedAnswers']=[]; if(($data['followUp']??false)===true){$questions=is_array($s['draftAnalysis']['missingInformation']??null)?$s['draftAnalysis']['missingInformation']:[];$index=(int)($s['followUpQuestionIndex']??0);if(isset($questions[$index])){$topic=(string)$questions[$index];$s['messages'][]=['id'=>bin2hex(random_bytes(8)),'role'=>'assistant','content'=>($s['language']??'pl')==='en' ? 'Could you clarify: '.$topic.'?' : 'Czy możesz doprecyzować: '.$topic.'?'];$s['followUpQuestionIndex']=$index+1;}} saveSession($s); reply(['session'=>publicSession($s)]); }
reply(['message'=>'Nieznana akcja.'],404);
