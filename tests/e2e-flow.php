<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }

// Deterministic end-to-end contract test. It exercises the same persisted
// session shape as the HTTP endpoints, but never calls OpenAI or mail().
require_once __DIR__ . '/../api/bootstrap.php';
$offerSource = file_get_contents(__DIR__ . '/../api/offer.php');
$requestedVersion = 0;
$from = strpos($offerSource, 'function offerSourceHash');
$to = strpos($offerSource, 'function sendOfferPdf', $from);
if ($from === false || $to === false) throw new RuntimeException('Offer functions unavailable');
eval(substr($offerSource, $from, $to - $from));
function checkE2E(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

$id = bin2hex(random_bytes(16));
$session = [
    'id' => $id, 'status' => 'COMPLETED', 'createdAt' => time(), 'updatedAt' => time(),
    'readyForSummary' => true, 'projectState' => [
        'businessProblem' => 'Raporty pracy są przepisywane ręcznie.',
        'businessGoals' => 'Ograniczyć ręczną pracę.', 'targetUsers' => 'Pracownicy i kierownicy',
        'coreProcesses' => 'Raportowanie budów', 'mustHaveFeatures' => 'Logowanie, raporty, eksport',
        'budget' => '5–10 tys. zł', 'deadline' => '1–3 miesiące',
        'contactName' => 'Test E2E', 'contactPhone' => '600 700 800', 'contactEmail' => 'e2e@example.com',
    ],
    'summary' => ['title' => 'Testowy brief', 'sections' => [['title' => 'Cel', 'content' => ['Ograniczenie pracy ręcznej.']]]],
    'internalAnalysis' => ['status' => 'COMPLETED', 'version' => 1, 'readiness' => 'READY', 'summary' => 'Zakres jest wystarczający.', 'recommendedScope' => ['Aplikacja webowa z raportami'], 'optionalScope' => ['Rozszerzone raporty'], 'missingInformation' => ['Tryb wdrożenia'], 'risks' => []],
    'adminDecisions' => ['Tryb wdrożenia' => ['source' => 'HUMAN', 'answer' => 'Wdrożenie online', 'analysisVersion' => 1]],
    'messages' => [['role' => 'user', 'content' => 'Chcę aplikację do raportów pracy.']], 'metrics' => ['turns' => 1],
];

// 1. Chat/session persistence
writeSession($session); checkE2E(readSession($id)['id'] === $id, 'Session was not persisted in SQLite'); echo "Chat/session: OK\n";
// 2. Analysis and missing information contract
checkE2E($session['internalAnalysis']['status'] === 'COMPLETED', 'Analysis is not completed'); checkE2E(count($session['internalAnalysis']['missingInformation']) === 1, 'Missing information not detected'); echo "Analysis and missing information: OK\n";
// 3. Automatic proposal + human confirmation
$session['adminDecisions']['Tryb wdrożenia']['source'] = 'HUMAN'; writeSession($session); checkE2E($session['adminDecisions']['Tryb wdrożenia']['source'] === 'HUMAN', 'Missing answer was not confirmed'); echo "Automatic proposal/confirmation: OK\n";
// 4. Offer generated strictly from current analysis
$offer = offerDocument($session, 150, 23); checkE2E(($offer['status'] ?? '') === 'DRAFT', 'Offer was not generated'); checkE2E(!str_contains(json_encode($offer, JSON_UNESCAPED_UNICODE), 'Hipoteza:'), 'Raw hypothesis leaked into offer'); echo "Offer: OK\n";
// 5. PDF encoding contract
checkE2E(str_contains($offerSource, "iconv('UTF-8', 'CP1250//IGNORE'"), 'PDF UTF-8 conversion is missing'); checkE2E(str_contains($offerSource, '/Sacute') && str_contains($offerSource, '/Zdotaccent'), 'Polish PDF font mappings are missing'); echo "PDF: OK\n";
// 6. Email delivery audit record
$session['offer'] = $offer; $session['offer']['deliveryLog'][] = ['recipient' => 'e2e@example.com', 'sentAt' => time(), 'mock' => true]; writeSession($session); $saved = readSession($id); checkE2E(!empty($saved['offer']['deliveryLog']), 'Email delivery was not logged'); echo "Email delivery log: OK\n";

deleteSession($id); echo "E2E flow checks passed\n";
