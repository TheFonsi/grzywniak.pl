<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$username = getenv('ADMIN_USERNAME') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';
if ($username === '' || $password === '' || !isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) || !hash_equals($username, $_SERVER['PHP_AUTH_USER']) || !hash_equals($password, $_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Grzywniak Discovery"');
    http_response_code(401);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$id = preg_replace('/[^a-f0-9]/', '', (string) ($data['session'] ?? ''));
$question = trim((string) ($data['question'] ?? ''));
$file = __DIR__ . '/storage/' . $id . '.json';
if (!preg_match('/^[a-f0-9]{32}$/', $id) || $question === '' || (!is_file($file) && readSession($id) === null)) {
    http_response_code(404);
    echo json_encode(['message' => 'Nie znaleziono briefu.']);
    exit;
}
$sessionLock=fopen(__DIR__.'/storage/'.$id.'.lock','c');
if($sessionLock===false || !flock($sessionLock,LOCK_EX|LOCK_NB)) { if(is_resource($sessionLock)) fclose($sessionLock); http_response_code(409); echo json_encode(['message'=>'Sprawa jest teraz przetwarzana.']); exit; }
$session = readSession($id) ?? (json_decode((string) file_get_contents($file), true) ?: []);
register_shutdown_function(static function () use (&$session): void { if (is_array($session) && isset($session['id'])) writeSession($session); });
register_shutdown_function(static function() use ($sessionLock): void { flock($sessionLock,LOCK_UN); fclose($sessionLock); });
if (normalizeSessionData($session)) file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
$action = (string) ($data['action'] ?? '');
$autoConfirm = false; // Propozycja AI zawsze wymaga świadomej decyzji administratora.
$session['adminProposals'] = is_array($session['adminProposals'] ?? null) ? $session['adminProposals'] : [];
$session['adminDecisions'] = is_array($session['adminDecisions'] ?? null) ? $session['adminDecisions'] : [];
function archiveOfferAfterBriefChange(array &$session, string $question = '', string $answer = ''): void {
    if (!is_array($session['offer'] ?? null)) return;
    if (($session['offer']['status'] ?? '') !== 'OUTDATED') {
        $session['offerVersions'] = is_array($session['offerVersions'] ?? null) ? $session['offerVersions'] : [];
        $archived = $session['offer']; $archived['archivedAt'] = time(); $session['offerVersions'][] = $archived;
        $session['offer']['status'] = 'OUTDATED'; $session['offer']['outdatedAt'] = time(); $session['offer']['updatedAt'] = time(); $session['offer']['changedFields'] = [];
    }
    if ($question !== '') { $session['offer']['changedFields'] = is_array($session['offer']['changedFields'] ?? null) ? $session['offer']['changedFields'] : []; $session['offer']['changedFields'][] = ['label' => $question, 'answer' => $answer, 'at' => time()]; }
}

if ($action === 'confirm') {
    $answer = trim((string) ($data['answer'] ?? ''));
    if ($answer === '') { http_response_code(422); echo json_encode(['message' => 'Brak odpowiedzi.']); exit; }
    $session['adminDecisions'][$question] = ['answer' => $answer, 'at' => time(), 'source' => 'HUMAN'];
    archiveOfferAfterBriefChange($session, $question, $answer);
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['accepted' => true, 'answer' => $answer]);
    exit;
}
if (isset($session['adminDecisions'][$question]) && ($session['adminDecisions'][$question]['source'] ?? '') === 'HUMAN' && $action !== 'regenerate') {
    $decision = $session['adminDecisions'][$question];
    echo json_encode([
        'accepted' => true,
        'answer' => (string) ($decision['answer'] ?? ''),
        'auto' => ($decision['source'] ?? '') === 'AI_AUTO',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action !== 'regenerate' && isset($session['adminProposals'][$question]['answer'])) {
    if ($autoConfirm) { $answer = (string) $session['adminProposals'][$question]['answer']; $session['adminDecisions'][$question] = ['answer' => $answer, 'at' => time(), 'source' => 'AI_AUTO']; archiveOfferAfterBriefChange($session, $question, $answer); file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX); echo json_encode(['accepted' => true, 'answer' => $answer, 'auto' => true, 'cached' => true], JSON_UNESCAPED_UNICODE); exit; }
    echo json_encode(['aiGenerated' => true, 'proposal' => (string) $session['adminProposals'][$question]['answer'], 'cached' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$key = getenv('OPENAI_API_KEY');
if (getenv('DISCOVERY_MOCK') === 'true') {
    $proposal = 'Najprostsze rozwiązanie zgodne z budżetem i zakresem pierwszej wersji.';
    $session['adminProposals'][$question] = ['answer' => $proposal, 'at' => time()];
    if ($autoConfirm) $session['adminDecisions'][$question] = ['answer' => $proposal, 'at' => time(), 'source' => 'AI_AUTO'];
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode($autoConfirm ? ['accepted' => true, 'answer' => $proposal, 'auto' => true, 'cached' => false] : ['aiGenerated' => true, 'proposal' => $proposal, 'cached' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($key === '') { http_response_code(503); echo json_encode(['message' => 'Brak konfiguracji AI.']); exit; }
$previous = (string) ($session['adminProposals'][$question]['answer'] ?? '');
$instruction = 'Jesteś analitykiem projektu. Na podstawie briefu zaproponuj jedną krótką, konkretną odpowiedź na brakujące pytanie. To hipoteza do zatwierdzenia przez zespół, nie fakt. Dopasuj odpowiedź do celu, budżetu i faktów z briefu. Dla niskiego budżetu wybierz najprostsze rozwiązanie dające wartość. Nie używaj ogólników typu „to istotne”, „tak” ani „nie”. Zwróć wyłącznie odpowiedź po polsku.';
if ($action === 'regenerate' && $previous !== '') $instruction .= ' Poprzednia propozycja brzmiała: „' . $previous . '”. Zaproponuj inne, rozsądne rozwiązanie.';
$payload = ['model' => getenv('OPENAI_MODEL') ?: 'gpt-5.6-luna', 'store' => false, 'reasoning' => ['effort' => 'low'], 'max_output_tokens' => 180, 'input' => [['role' => 'system', 'content' => $instruction], ['role' => 'user', 'content' => json_encode(['brief' => $session['projectState'] ?? [], 'question' => $question], JSON_UNESCAPED_UNICODE)]]];
$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
$raw = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$response = json_decode((string) $raw, true) ?: [];
$proposal = trim((string) ($response['output_text'] ?? ''));
if ($proposal === '') foreach (($response['output'] ?? []) as $item) foreach (($item['content'] ?? []) as $content) if (($content['type'] ?? '') === 'output_text' && is_string($content['text'] ?? null)) { $proposal = trim($content['text']); break 2; }
if ($proposal === '') { http_response_code($code >= 400 ? $code : 503); echo json_encode(['message' => 'Nie udało się wygenerować propozycji.']); exit; }
$session['adminProposals'][$question] = ['answer' => $proposal, 'at' => time()];
if ($autoConfirm) { $session['adminDecisions'][$question] = ['answer' => $proposal, 'at' => time(), 'source' => 'AI_AUTO']; archiveOfferAfterBriefChange($session, $question, $proposal); }
file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
echo json_encode($autoConfirm ? ['accepted' => true, 'answer' => $proposal, 'auto' => true, 'cached' => false] : ['aiGenerated' => true, 'proposal' => $proposal, 'cached' => false], JSON_UNESCAPED_UNICODE);
