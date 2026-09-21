<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/internal-analysis.php';
require_once __DIR__ . '/offer-changes.php';

$user = getenv('ADMIN_USERNAME') ?: '';
$pass = getenv('ADMIN_PASSWORD') ?: '';
if ($user === '' || $pass === '' || !isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) || !hash_equals($user, $_SERVER['PHP_AUTH_USER']) || !hash_equals($pass, $_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Grzywniak Discovery"');
    http_response_code(401);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$id = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['session'] ?? ''));
$file = __DIR__ . '/storage/' . $id . '.json';
if (!preg_match('/^[a-f0-9]{32}$/', $id) || (!is_file($file) && readSession($id) === null)) { http_response_code(404); echo json_encode(['message' => 'Nie znaleziono briefu.']); exit; }
$sessionLock=fopen(__DIR__.'/storage/'.$id.'.lock','c');
if($sessionLock===false || !flock($sessionLock,LOCK_EX|LOCK_NB)) { if(is_resource($sessionLock)) fclose($sessionLock); http_response_code(409); echo json_encode(['message'=>'Sprawa jest teraz przetwarzana.']); exit; }
$session = readSession($id) ?? json_decode((string) file_get_contents($file), true);
if (!is_array($session)) { http_response_code(500); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') register_shutdown_function(static function () use (&$session): void { if (is_array($session) && isset($session['id'])) writeSession($session); });
register_shutdown_function(static function() use ($sessionLock): void { flock($sessionLock,LOCK_UN); fclose($sessionLock); });
if (normalizeSessionData($session) && $_SERVER['REQUEST_METHOD'] === 'POST') file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
$analysisReady = (($session['internalAnalysis']['status'] ?? '') === 'COMPLETED');
$requestedVersion = max(0, (int) ($_GET['version'] ?? 0));
function offerSourceHash(array $session): string { return hash('sha256', json_encode([$session['projectState'] ?? [], $session['internalAnalysis'] ?? [], $session['adminDecisions'] ?? []], JSON_UNESCAPED_UNICODE)); }
if (is_array($session['offer'] ?? null) && ($session['offer']['sourceHash'] ?? '') !== offerSourceHash($session)) $session['offer']['status'] = 'OUTDATED';
if ($requestedVersion > 0 && $_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(409); echo json_encode(['message' => 'Archiwalna oferta jest tylko do odczytu.']); exit; }
$selectedOffer = $session['offer'] ?? null;
if ($requestedVersion > 0 && (int) ($session['offer']['version'] ?? 0) !== $requestedVersion) {
    $foundVersion = false;
    foreach (($session['offerVersions'] ?? []) as $archivedOffer) if ((int) ($archivedOffer['version'] ?? 0) === $requestedVersion) { $selectedOffer = $archivedOffer; $foundVersion = true; break; }
    if (!$foundVersion) { http_response_code(404); echo json_encode(['message' => 'Nie znaleziono wskazanej wersji oferty.']); exit; }
}
// Starsze wersje mogły zawierać surowe hipotezy z poprzednich analiz. Nie
// pokazuj ich jako aktualnej oferty — oznacz ją do jednorazowego odświeżenia.
if ($requestedVersion === 0 && is_array($session['offer'] ?? null)) {
    foreach (($session['offer']['sections'] ?? []) as $section) foreach (($section['items'] ?? []) as $item) {
        if (preg_match('/(?:^|\s)(?:hipoteza|ustalenie)\s*:/iu', (string) $item)) { $session['offer']['status'] = 'OUTDATED'; break 2; }
    }
}

function listOf(mixed $items): array { return is_array($items) ? array_values(array_filter(array_map('strval', $items))) : []; }
function applyOfferUpdate(array $offer, array $update): array {
    if (is_string($update['summary'] ?? null) && trim($update['summary']) !== '') $offer['summary'] = $update['summary'];
    $seen = [];
    foreach (($update['sections'] ?? []) as $section) {
        $index = $section['index'] ?? -1;
        if (!is_int($index) || !isset($offer['sections'][$index]) || isset($seen[$index]) || !is_array($section['items'] ?? null)) throw new RuntimeException('Nieprawidłowy format aktualizacji sekcji.');
        $seen[$index] = true;
        $offer['sections'][$index] = ['title' => (string) $section['title'], 'items' => listOf($section['items'])];
    }
    return $offer;
}
function cleanDecisionText(string $text): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    $text = preg_replace('/poniewa[sś]\s+bud[zż]et\s+do\s+([^,.;]+),\s*w\s+ramach\s+bud[zż]etu\s+do\s+\1/iu', 'Przy budżecie do $1', $text) ?? $text;
    $text = preg_replace('/w\s+ramach\s+bud[zż]etu\s+do\s+([^,.!?]+),?\s*w\s+ramach\s+bud[zż]etu\s+do\s+\1/iu', 'W ramach budżetu do $1', $text) ?? $text;
    return $text;
}
function offerDocument(array $session, float $rate, float $vat, int $version = 1): array {
    $state = is_array($session['projectState'] ?? null) ? $session['projectState'] : [];
    $analysis = $session['offerAnalysis'] ?? $session['internalAnalysis'] ?? [];
    $scope = listOf($analysis['recommendedScope'] ?? []);
    $scope = array_values(array_unique($scope));
    if (!$scope) $scope = array_values(array_filter([(string) ($state['mustHaveFeatures'] ?? ''), (string) ($state['coreProcesses'] ?? ''), 'Testy oraz publikacja uzgodnionego rozwiązania']));
    $optional = listOf($analysis['optionalScope'] ?? []);
    $features = mb_strtolower(implode(' ', $scope));
    preg_match('/\b(\d{1,2})\s*(?:podstron|stron)\b/ui', $features, $pageMatch);
    $pages = isset($pageMatch[1]) ? max(1, (int) $pageMatch[1]) : (preg_match('/one.page|landing|wizytówk|strona internetowa/ui', $features) ? 1 : 3);
    $system = preg_match('/aplikac|system|panel|logowan|użytkownik|rezerwac|konto/ui', $features) === 1;
    $complexity = 0;
    foreach (['płatno', 'integrac', 'crm', 'api', 'role', 'uprawnien', 'import', 'migrac', 'wielojęzy', 'sklep'] as $keyword) if (str_contains($features, $keyword)) $complexity += 14;
    if (!$system) foreach (['formularz', 'realizac', 'cms', 'seo', 'galeri'] as $keyword) if (str_contains($features, $keyword)) $complexity += 3;
    $hours = ($system ? 48 : 12) + ($pages * ($system ? 7 : 5)) + $complexity;
    $hours = max($system ? 48 : 18, min(260, $hours));
    $modules = [];
    if ($system) { $modules[] = ['name' => 'Analiza i konfiguracja rozwiązania', 'hours' => 12]; $modules[] = ['name' => 'Logowanie i role użytkowników', 'hours' => 14]; $modules[] = ['name' => 'Główne moduły i formularze', 'hours' => max(16, $pages * 7)]; $modules[] = ['name' => 'Panel zarządzania i raporty', 'hours' => 14]; $modules[] = ['name' => 'Testy, wdrożenie i poprawki', 'hours' => 12]; }
    else { $modules[] = ['name' => 'Analiza i przygotowanie struktury', 'hours' => 6]; $modules[] = ['name' => 'Projekt i skład pierwszej wersji', 'hours' => max(6, $pages * 5)]; $modules[] = ['name' => 'Formularz/kontakt i konfiguracja', 'hours' => 4]; $modules[] = ['name' => 'Testy, publikacja i poprawki', 'hours' => 6]; }
    $moduleHours = array_sum(array_map(static fn($module) => (int) ($module['hours'] ?? 0), $modules));
    $hours = max($hours, $moduleHours);
    $rationale = 'Kalkulacja wynika z aktualnego zakresu analizy, liczby modułów i ograniczeń budżetowych. Stawka: ' . number_format($rate, 2, ',', ' ') . ' zł netto/h.';
    $net = round($hours * $rate, 2);
    $depositRate = $net <= 5000 ? 10 : ($net <= 10000 ? 20 : ($net <= 30000 ? 30 : 50));
    $project = trim((string) ($state['businessProblem'] ?? 'Projekt cyfrowy'));
    return [
        'title' => 'Wstępny zakres realizacji i wycena',
        'status' => 'DRAFT',
        'analysisVersion' => (int) ($analysis['version'] ?? 1),
        'sourceHash' => offerSourceHash($session),
        'version' => max(1, $version),
        'offerId' => 'OF-' . strtoupper(substr(hash('sha256', (string) ($session['id'] ?? '') . '-' . $version), 0, 10)),
        'createdAt' => time(),
        'updatedAt' => time(),
        'project' => $project,
        'summary' => (string) ($analysis['summary'] ?? 'Zakres opracowany na podstawie przekazanego briefu.'),
        'contact' => ['name' => (string) ($state['contactName'] ?? ''), 'phone' => (string) ($state['contactPhone'] ?? ''), 'email' => (string) ($state['contactEmail'] ?? '')],
        'sections' => [
            ['title' => 'Cel i kontekst', 'items' => array_values(array_filter([(string) ($state['businessGoals'] ?? ''), (string) ($state['targetUsers'] ?? '')]))],
            ['title' => 'Zakres realizacji', 'items' => $scope],
            ['title' => 'Poza obecnym zakresem / możliwe później', 'items' => $optional ?: ['Elementy niewymienione w zakresie będą wymagały osobnego ustalenia.']],
            ['title' => 'Planowany termin', 'items' => [(string) ($state['deadline'] ?? 'Termin do potwierdzenia po akceptacji zakresu.')]],
        ],
        'pricing' => ['hours' => $hours, 'rate' => $rate, 'modules' => $modules, 'rationale' => $rationale, 'net' => $net, 'vatRate' => $vat, 'vat' => round($net * $vat / 100, 2), 'gross' => round($net * (1 + $vat / 100), 2)],
        'payment' => ['depositRate' => $depositRate, 'deposit' => round($net * $depositRate / 100, 2), 'note' => 'Pozostała część rozliczana zgodnie z harmonogramem ustalonym przed podpisaniem umowy.'],
        'note' => 'Dokument ma charakter wstępny i stanowi podstawę do przygotowania umowy oraz finalnego harmonogramu.',
    ];
}
function offerChangeLog(array $previous, array $current): array {
    $changes = [];
    $compare = static function (string $key, string $label, mixed $old, mixed $new) use (&$changes): void {
        $format = static function (mixed $value): string { if (is_array($value)) { $items = array_values(array_filter(array_map('strval', $value))); return count($items) . ' elementów: ' . implode(' • ', array_slice($items, 0, 2)) . (count($items) > 2 ? ' …' : ''); } return trim((string) $value); };
        $oldText = $format($old);
        $newText = $format($new);
        if ($old !== $new) $changes[] = ['key' => $key, 'label' => $label, 'previous' => $oldText, 'current' => $newText, 'confirmed' => false];
    };
    $compare('project', 'Nazwa projektu', $previous['project'] ?? '', $current['project'] ?? '');
    $compare('summary', 'Podsumowanie', $previous['summary'] ?? '', $current['summary'] ?? '');
    foreach (['sections' => 'Zakres i warunki realizacji', 'contact' => 'Dane klienta', 'payment' => 'Warunki płatności'] as $field => $label) $compare($field, $label, json_encode($previous[$field] ?? [], JSON_UNESCAPED_UNICODE), json_encode($current[$field] ?? [], JSON_UNESCAPED_UNICODE));
    $compare('pricing.net', 'Cena netto', $previous['pricing']['net'] ?? '', $current['pricing']['net'] ?? '');
    $compare('pricing.gross', 'Cena brutto', $previous['pricing']['gross'] ?? '', $current['pricing']['gross'] ?? '');
    return $changes;
}
function pdfText(string $value): string {
    // Keep the API/session data in UTF-8, but emit the single-byte encoding
    // expected by the built-in PDF font. Repair legacy Windows-1250 input
    // before conversion so Polish capitals are not silently dropped.
    if (!mb_check_encoding($value, 'UTF-8')) $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1250');
    $encoded = iconv('UTF-8', 'CP1250//IGNORE', $value);
    $value = $encoded === false ? '' : $encoded;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}
function pdfFontObject(): string {
    // pdfText() emits Windows-1250 bytes. Map every Polish letter explicitly
    // instead of relying on WinAnsi (which renders several capitals incorrectly).
    return '<< /Type /Font /Subtype /Type1 /BaseFont /Arial /Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [140 /Sacute 143 /Zacute 156 /sacute 159 /zacute 163 /Lslash 165 /Aogonek 175 /Zdotaccent 179 /lslash 185 /aogonek 191 /zdotaccent 198 /Cacute 202 /Eogonek 209 /Nacute 211 /Oacute 230 /cacute 234 /eogonek 241 /nacute 243 /oacute] >> >>';
}
function offerSlug(string $value): string { $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value)) ?: trim($value); $value = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value)); return trim($value, '-') ?: 'klient'; }
function sendOfferPdf(array $offer): never { sendOfferPdfFixed($offer, '');
    $contact = $offer['contact'] ?? [];
    $lines=['WSTEPNA OFERTA - ZAKRES REALIZACJI I WYCENA','', 'Projekt: '.(string)($offer['project']??'Projekt cyfrowy'), 'Wygenerowano: '.date('Y-m-d H:i',(int)($offer['updatedAt']??$offer['createdAt']??time())), 'Kontakt: '.implode(' | ', array_filter([(string)($contact['name']??''),(string)($contact['phone']??''),(string)($contact['email']??'')])), ''];
    if (!empty($offer['summary'])) { $lines[]='Podsumowanie'; $lines=array_merge($lines,str_split((string)$offer['summary'],95)); $lines[]=''; }
    foreach (($offer['sections']??[]) as $section) { $lines[]=(string)($section['title']??'Sekcja'); foreach (($section['items']??[]) as $item) { foreach (str_split(cleanDecisionText((string)$item),88) as $part) $lines[]='- '.$part; } $lines[]=''; }
    $pricing=$offer['pricing']??[]; $payment=$offer['payment']??[]; $lines[]='WYCENA'; $lines[]='Netto: '.number_format((float)($pricing['net']??0),2,',',' ').' PLN'; $lines[]='VAT '.(float)($pricing['vatRate']??23).'%: '.number_format((float)($pricing['vat']??0),2,',',' ').' PLN'; $lines[]='Brutto: '.number_format((float)($pricing['gross']??0),2,',',' ').' PLN'; $lines[]='Szacowany naklad: '.(int)($pricing['hours']??0).' h'; $lines[]='Zaliczka: '.(int)($payment['depositRate']??0).'% ('.number_format((float)($payment['deposit']??0),2,',',' ').' PLN)'; $lines[]=''; $lines[]=pdfText((string)($offer['note']??''));
    $pages=array_chunk($lines,45); $objects=[]; $objects[]='<< /Type /Catalog /Pages 2 0 R >>'; $objects[]='<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($i)=>($i+5).' 0 R',array_keys($pages))).'] /Count '.count($pages).' >>'; $objects[]=pdfFontObject();
    foreach ($pages as $index=>$pageLines) { $content="BT /F1 11 Tf 48 780 Td 15 TL "; foreach ($pageLines as $line) $content.='('.pdfText($line).') Tj T* '; $content.='ET'; $objects[]='<< /Length '.strlen($content).' >>\nstream\n'.$content.'\nendstream'; $objects[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.(4+$index*2).' 0 R >>'; }
    $pdf="%PDF-1.4\n"; $offsets=[]; foreach($objects as $i=>$object){$offsets[$i+1]=strlen($pdf);$pdf.=($i+1).' 0 obj\n'.$object."\nendobj\n";} $xref=strlen($pdf); $pdf.='xref\n0 '.(count($objects)+1).'\n0000000000 65535 f \n'; for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n \n',$offsets[$i]); $pdf.='trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>\nstartxref\n'.$xref.'\n%%EOF'; header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="wstepna-oferta.pdf"'); header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
}

function sendOfferPdfFixed(array $offer, string $sessionId): never {
    $offer['sections'] = array_values(array_filter($offer['sections'] ?? [], static fn($section) => !preg_match('/zatwierdzone ustalenia|zmiany do potwierdzenia/iu', (string) ($section['title'] ?? ''))));
    $contact = is_array($offer['contact'] ?? null) ? $offer['contact'] : [];
    $contactLabel = (string) ($contact['name'] ?? '') ?: (string) ($offer['project'] ?? 'Klient');
    $offerCode = (string) ($offer['offerId'] ?? '') ?: 'OF-' . strtoupper(substr(hash('sha256', $sessionId . '-offer'), 0, 10));
    $lines = ['PROPONOWANA OFERTA DLA: ' . $contactLabel, 'Projekt: ' . (string) ($offer['project'] ?? 'Projekt cyfrowy'), 'Numer: ' . $offerCode . '  |  Wersja: ' . (int) ($offer['version'] ?? 1), '', 'Utworzono: ' . date('Y-m-d H:i', (int) ($offer['createdAt'] ?? $offer['updatedAt'] ?? time())), 'Kontakt: ' . implode(' | ', array_filter([(string) ($contact['name'] ?? ''), (string) ($contact['phone'] ?? ''), (string) ($contact['email'] ?? '')])), ''];
    if (!empty($offer['summary'])) { $lines[] = 'PODSUMOWANIE'; foreach (explode("\n", wordwrap(cleanDecisionText((string) $offer['summary']), 72, "\n", true)) as $line) $lines[] = $line; $lines[] = ''; }
    foreach (($offer['sections'] ?? []) as $section) { if (str_contains(mb_strtolower((string) ($section['title'] ?? '')), 'zatwierdzone ustalenia')) continue; $lines[] = mb_strtoupper((string) ($section['title'] ?? 'Sekcja'), 'UTF-8'); foreach (($section['items'] ?? []) as $item) { $wrapped = explode("\n", wordwrap(cleanDecisionText((string) $item), 68, "\n", true)); foreach ($wrapped as $lineIndex => $line) $lines[] = ($lineIndex === 0 ? '- ' : '  ') . $line; } $lines[] = ''; }
    $pricing = $offer['pricing'] ?? []; $payment = $offer['payment'] ?? []; $lines[] = 'WYCENA'; $lines[] = 'Netto: ' . number_format((float) ($pricing['net'] ?? 0), 2, ',', ' ') . ' PLN'; $lines[] = 'VAT ' . (float) ($pricing['vatRate'] ?? 23) . '%: ' . number_format((float) ($pricing['vat'] ?? 0), 2, ',', ' ') . ' PLN'; $lines[] = 'Brutto: ' . number_format((float) ($pricing['gross'] ?? 0), 2, ',', ' ') . ' PLN'; $lines[] = 'Zaliczka: ' . (int) ($payment['depositRate'] ?? 0) . '% (' . number_format((float) ($payment['deposit'] ?? 0), 2, ',', ' ') . ' PLN)'; $lines[] = ''; $lines[] = (string) ($offer['note'] ?? '');
    $pages = array_chunk($lines, 45); $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($i) => ($i * 2 + 5) . ' 0 R', array_keys($pages))) . '] /Count ' . count($pages) . ' >>', pdfFontObject()];
    foreach ($pages as $index => $pageLines) { $content = "BT /F1 10 Tf 48 780 Td 14 TL\n"; foreach ($pageLines as $line) $content .= '(' . pdfText($line) . ") Tj T*\n"; $content .= 'ET'; $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream"; $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . (4 + $index * 2) . ' 0 R >>'; }
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets = []; foreach ($objects as $index => $object) { $number = $index + 1; $offsets[$number] = strlen($pdf); $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n"; } $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n"; for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]); $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    $version = max(1, (int) ($offer['version'] ?? 1)); $contactLabel = (string) ($contact['name'] ?? '') ?: (string) ($offer['project'] ?? 'klient'); $dateLabel = date('Y-m-d', (int) ($offer['updatedAt'] ?? $offer['createdAt'] ?? time())); $offerCode = strtolower(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($offer['offerId'] ?? $sessionId))); $filename = 'oferta-' . offerSlug($contactLabel) . '-' . $dateLabel . '-' . $offerCode . '-v' . $version . '.pdf'; header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $filename . '"'); header('Content-Length: ' . strlen($pdf)); echo $pdf; exit;
}
$body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $body['action'] ?? '';
    if (in_array($action, ['generate', 'review', 'send', 'confirmChange'], true)) {
        if (!$analysisReady) { http_response_code(409); echo json_encode(['message' => 'Analiza nie jest ukończona. Kliknij „Przeanalizuj brief od nowa” i poczekaj na zakończenie.']); exit; }
        if (($session['internalAnalysis']['readiness'] ?? '') === 'NOT_A_FIT') { http_response_code(409); echo json_encode(['message' => 'Analiza oznacza projekt jako poza zakresem usług. Po potwierdzeniu ustaleń ponów analizę przed przygotowaniem oferty.']); exit; }
        foreach (($session['internalAnalysis']['missingInformation'] ?? []) as $question) {
            $decision = $session['adminDecisions'][trim($question)] ?? $session['adminDecisions'][$question] ?? [];
            if (($decision['source'] ?? '') !== 'HUMAN' || trim((string) ($decision['answer'] ?? '')) === '') { http_response_code(409); echo json_encode(['message' => 'Brakuje zapisanej akceptacji odpowiedzi: ' . trim($question) . '. Zatwierdź ją w analizie i spróbuj ponownie.']); exit; }
        }
    }
    if ($action !== 'generate' && ($session['offer']['status'] ?? '') === 'OUTDATED') { http_response_code(409); echo json_encode(['message' => 'Oferta jest nieaktualna. Przygotuj nową wersję z aktualnej analizy.']); exit; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'restoreChange') {
    $offer = $session['offer'] ?? [];
    if ((int) ($body['version'] ?? 0) !== (int) ($offer['version'] ?? 0)) { http_response_code(409); echo json_encode(['message'=>'Oferta zmieniła się. Odśwież panel.']); exit; }
    try { $restored = restoreOfferChange($offer, (string) ($body['key'] ?? '')); }
    catch (RuntimeException $error) { http_response_code(409); echo json_encode(['message'=>$error->getMessage()], JSON_UNESCAPED_UNICODE); exit; }
    $session['offerVersions'][] = $offer;
    $restored['version'] = (int) $offer['version'] + 1;
    $restored['offerId'] = 'OF-' . strtoupper(substr(hash('sha256', $id . '-' . $restored['version']), 0, 10));
    $restored['status'] = 'DRAFT'; $restored['needsHumanReview'] = true; $restored['updatedAt'] = time();
    unset($restored['reviewedAt'], $restored['verification'], $restored['sentAt'], $restored['sentTo']);
    $session['offer'] = $restored;
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['offer'=>$restored], JSON_UNESCAPED_UNICODE); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'confirmChange') {
    $offer = is_array($session['offer'] ?? null) ? $session['offer'] : null;
    if (isset($body['version']) && (int) $body['version'] !== (int) ($offer['version'] ?? 0)) { http_response_code(409); echo json_encode(['message'=>'Oferta zmieniła się. Odśwież panel.']); exit; }
    $key = trim((string) ($body['key'] ?? ''));
    if ($offer === null || $key === '') { http_response_code(422); echo json_encode(['message' => 'Nieprawidłowa zmiana.']); exit; }
    $found = false;
    $changes = is_array($offer['changeLog'] ?? null) ? $offer['changeLog'] : [];
    foreach ($changes as &$change) if (($change['key'] ?? '') === $key) { $change['confirmed'] = true; $found = true; break; }
    unset($change);
    if (!$found) { http_response_code(404); echo json_encode(['message' => 'Nie znaleziono zmiany.']); exit; }
    $offer['changeLog'] = $changes;
    $relevantChanges = $changes;
    $allConfirmed = count($relevantChanges) > 0 && count(array_filter($relevantChanges, static fn($change) => ($change['confirmed'] ?? false) !== true)) === 0;
    // Potwierdzenie różnic nie zastępuje weryfikacji całego dokumentu.
    $offer['updatedAt'] = time(); $session['offer'] = $offer;
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['offer' => $offer, 'allConfirmed' => $allConfirmed], JSON_UNESCAPED_UNICODE); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'review') {
    $offer = is_array($session['offer'] ?? null) ? $session['offer'] : null;
    if ($offer === null) { http_response_code(404); echo json_encode(['message' => 'Najpierw przygotuj ofertę.']); exit; }
    $missing = is_array($session['internalAnalysis']['missingInformation'] ?? null) ? $session['internalAnalysis']['missingInformation'] : [];
    foreach ($missing as $question) if (!is_array($session['adminDecisions'][$question] ?? null) || ($session['adminDecisions'][$question]['source'] ?? '') !== 'HUMAN') { http_response_code(409); echo json_encode(['message' => 'Najpierw ręcznie potwierdź wszystkie brakujące informacje.'], JSON_UNESCAPED_UNICODE); exit; }
    $offer['status'] = 'REVIEWED';
    $offer['reviewedAt'] = time();
    $offer['verification'] = 'HUMAN_REVIEWED';
    $offer['needsHumanReview'] = false;
    $offer['updatedAt'] = time();
    $session['offer'] = $offer;
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['offer' => $offer], JSON_UNESCAPED_UNICODE); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'send') {
    $offer = is_array($session['offer'] ?? null) ? $session['offer'] : null;
    $recipient = (string) (($offer['contact']['email'] ?? '') ?: ($session['projectState']['contactEmail'] ?? ''));
    if ($offer === null) { http_response_code(404); echo json_encode(['message' => 'Najpierw przygotuj ofertę.']); exit; }
    if (($offer['status'] ?? 'DRAFT') !== 'REVIEWED') { http_response_code(409); echo json_encode(['message' => 'Najpierw oznacz ofertę jako zweryfikowaną.']); exit; }
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['message' => 'Brak poprawnego adresu e-mail klienta.']); exit; }
    $sender = getenv('CONTACT_FROM') ?: (getenv('DISCOVERY_TO') ?: 'dawid@grzywniak.pl');
    $p = $offer['pricing'] ?? []; $payment = $offer['payment'] ?? [];
    $bodyText = "Dzień dobry,\n\nprzesyłamy wstępny dokument zakresu realizacji i wyceny projektu.\n\n" . ($offer['project'] ?? 'Projekt cyfrowy') . "\n\n" . ($offer['summary'] ?? '') . "\n\nWycena: " . number_format((float)($p['net'] ?? 0), 2, ',', ' ') . " zł netto / " . number_format((float)($p['gross'] ?? 0), 2, ',', ' ') . " zł brutto.\nSzacowany nakład: " . (int)($p['hours'] ?? 0) . " h.\nZaliczka: " . (int)($payment['depositRate'] ?? 0) . "%.\n\nDokument ma charakter wstępny i wymaga wspólnego potwierdzenia zakresu przed umową.\n\nPozdrawiamy,\nGrzywniak.pl";
    $bodyText = "Klient: " . ((string) ($offer['contact']['name'] ?? '') ?: 'Klient') . "\nData dokumentu: " . date('Y-m-d H:i', (int) ($offer['updatedAt'] ?? time())) . "\nNumer oferty: " . ($offer['offerId'] ?? '') . "\n\n" . $bodyText;
    $headers = 'From: Grzywniak.pl <'.$sender.'>\r\nContent-Type: text/plain; charset=UTF-8';
    if (getenv('DISCOVERY_MAIL_MOCK') !== 'true' && !@mail($recipient, 'Wstępny zakres realizacji i wycena — Grzywniak.pl', $bodyText, $headers)) { http_response_code(502); echo json_encode(['message' => 'Nie udało się wysłać wiadomości. Sprawdź konfigurację poczty.']); exit; }
    $sentAt = time();
    $offer['sentAt'] = $sentAt; $offer['sentTo'] = $recipient; $offer['status'] = 'SENT'; $offer['updatedAt'] = $sentAt;
    $offer['deliveryLog'] = is_array($offer['deliveryLog'] ?? null) ? $offer['deliveryLog'] : [];
    $offer['deliveryLog'][] = ['status' => 'SENT', 'to' => $recipient, 'version' => (int) ($offer['version'] ?? 1), 'at' => $sentAt];
    $session['offer'] = $offer;
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['offer' => $offer], JSON_UNESCAPED_UNICODE); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'saveClientMessage') {
    if (!is_array($session['offer'] ?? null)) { http_response_code(404); echo json_encode(['message' => 'Najpierw przygotuj ofertę.'], JSON_UNESCAPED_UNICODE); exit; }
    $session['offer']['clientMessage'] = trim((string) ($body['clientMessage'] ?? '')); $session['offer']['clientMessageAt'] = time();
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX); echo json_encode(['offer' => $session['offer']], JSON_UNESCAPED_UNICODE); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'accept') {
    if (!is_array($session['offer'] ?? null)) { http_response_code(404); echo json_encode(['message' => 'Najpierw przygotuj ofertę.'], JSON_UNESCAPED_UNICODE); exit; }
    $offer = $session['offer'];
    if (!in_array(($offer['status'] ?? ''), ['REVIEWED', 'SENT'], true)) { http_response_code(409); echo json_encode(['message' => 'Najpierw zweryfikuj ofertę.'], JSON_UNESCAPED_UNICODE); exit; }
    $offer['status'] = 'ACCEPTED'; $offer['acceptedAt'] = time(); $offer['acceptedBy'] = 'Zespół';
    $note = trim((string) ($body['clientMessage'] ?? '')); if ($note !== '') { $offer['clientMessage'] = $note; $offer['clientMessageAt'] = time(); }
    $session['offer'] = $offer; file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX); echo json_encode(['offer' => $offer], JSON_UNESCAPED_UNICODE); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'generate') {
    if (!$analysisReady) { http_response_code(409); echo json_encode(['message' => 'Najpierw zakończ analizę wewnętrzną AI.']); exit; }
    if (is_array($session['offer'] ?? null) && ($session['offer']['sourceHash'] ?? '') === offerSourceHash($session) && ($session['offer']['status'] ?? '') !== 'OUTDATED') { echo json_encode(['offer' => $session['offer']], JSON_UNESCAPED_UNICODE); exit; }
    $updatedOffer = null;
    try {
        if (is_array($session['offer'] ?? null)) $updatedOffer = applyOfferUpdate($session['offer'], internalAnalysis($session, true));
        else {
            $session['offerAnalysis'] = internalAnalysis($session);
            if (empty($session['offerAnalysis']['recommendedScope'])) throw new RuntimeException('Brak wygenerowanego zakresu.');
        }
    } catch (Throwable $error) {
        error_log('Offer analysis failed: ' . $error->getMessage());
        $detail = $error->getMessage();
        $reason = match (true) {
            str_contains($detail, '401'), str_contains($detail, '403'), str_contains($detail, 'Brak konfiguracji') => 'Sprawdź konfigurację klucza i uprawnień usługi AI na serwerze.',
            str_contains($detail, '429') => 'Usługa AI zgłosiła przekroczenie limitu. Sprawdź limity i środki konta AI.',
            str_contains($detail, '400'), str_contains($detail, '404') => 'Usługa AI odrzuciła model lub parametry żądania. Sprawdź konfigurację modelu.',
            str_contains($detail, 'AI_INCOMPLETE'), str_contains($detail, 'format') => 'AI zwróciło niepełną lub niepoprawną analizę mimo ponowienia. Spróbuj ponownie.',
            str_contains($detail, 'timed out'), str_contains($detail, 'HTTP 0') => 'Przekroczono czas oczekiwania lub nie udało się połączyć z AI. Spróbuj ponownie.',
            default => 'Nie udało się opracować zakresu. Szczegóły zapisano w logu serwera.',
        };
        http_response_code(502); echo json_encode(['message' => $reason . ' Poprzednia oferta pozostaje zachowana.'], JSON_UNESCAPED_UNICODE); exit;
    }
    // Nie nadpisuj ustaleń zapisanych w trakcie oczekiwania na AI.
    $latest = json_decode((string) file_get_contents($file), true);
    if (!is_array($latest) || offerSourceHash($latest) !== offerSourceHash($session) || ($latest['offer']['updatedAt'] ?? null) !== ($session['offer']['updatedAt'] ?? null)) {
        http_response_code(409); echo json_encode(['message' => 'Dane zmieniły się podczas generowania. Odśwież panel i ponów przygotowanie oferty.']); exit;
    }
    $previous = is_array($session['offer'] ?? null) ? $session['offer'] : [];
    $previousOutdated = (($previous['status'] ?? '') === 'OUTDATED');
    if ($previous) { $session['offerVersions'] = is_array($session['offerVersions'] ?? null) ? $session['offerVersions'] : []; $session['offerVersions'][] = $previous; }
    $nextVersion = max(1, (int) ($previous['version'] ?? 0) + 1);
    $offer = $updatedOffer ?? offerDocument($session, (float) (getenv('OFFER_HOURLY_RATE_NET') ?: 150), (float) (getenv('OFFER_VAT_RATE') ?: 23), $nextVersion);
    $offer['version'] = $nextVersion;
    $offer['offerId'] = 'OF-' . strtoupper(substr(hash('sha256', $id . '-' . $nextVersion), 0, 10));
    $offer['updatedAt'] = time();
    $offer['sourceHash'] = offerSourceHash($session);
    $offer['sourceSnapshot'] = ['projectState' => $session['projectState'] ?? [], 'adminDecisions' => $session['adminDecisions'] ?? []];
    unset($offer['reviewedAt'], $offer['verification'], $offer['sentAt'], $offer['sentTo']);
    if ($previousOutdated) {
        $offer['changeLog'] = offerChangeLog($previous, $offer);
        $offer['changedFields'] = is_array($previous['changedFields'] ?? null) ? $previous['changedFields'] : [];
        $currentMissing = array_values(array_filter(array_map('trim', is_array($session['internalAnalysis']['missingInformation'] ?? null) ? $session['internalAnalysis']['missingInformation'] : [])));
        foreach ($offer['changedFields'] as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label !== '' && $currentMissing && in_array($label, $currentMissing, true)) $offer['changeLog'][] = ['key' => 'manual.' . substr(hash('sha256', $label), 0, 12), 'label' => 'Uzupełnienie: ' . $label, 'previous' => 'Brak w poprzedniej wersji', 'current' => (string) ($field['answer'] ?? 'Uwzględniono w nowej analizie'), 'confirmed' => false];
        }
    }
    if ($updatedOffer === null && ($previous['pricing']['manualPrice'] ?? false) === true) {
        $net = (float) ($previous['pricing']['net'] ?? 0);
        $vatRate = (float) ($offer['pricing']['vatRate'] ?? 23);
        $depositRate = $net <= 5000 ? 10 : ($net <= 10000 ? 20 : ($net <= 30000 ? 30 : 50));
        $offer['pricing']['net'] = $net;
        $offer['pricing']['vat'] = round($net * $vatRate / 100, 2);
        $offer['pricing']['gross'] = round($net * (1 + $vatRate / 100), 2);
        $offer['pricing']['manualPrice'] = true;
        $offer['payment']['depositRate'] = $depositRate;
        $offer['payment']['deposit'] = round($net * $depositRate / 100, 2);
    }
    $offer['status'] = 'DRAFT';
    $offer['needsHumanReview'] = true;
    $offer['changeLog'] = $previous ? offerTextChanges($previous, $offer) : [];
    $session['offer'] = $offer;
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'updatePrice') {
    $net = (float) str_replace(',', '.', (string) ($body['net'] ?? '0'));
    $offer = is_array($session['offer'] ?? null) ? $session['offer'] : null;
    if ($offer === null || $net <= 0 || $net > 1000000) { http_response_code(422); echo json_encode(['message' => 'Podaj poprawną cenę netto.']); exit; }
    $session['offerVersions'] = is_array($session['offerVersions'] ?? null) ? $session['offerVersions'] : [];
    $session['offerVersions'][] = $offer;
    $offer['version'] = max(1, (int) ($offer['version'] ?? 1) + 1);
    $offer['offerId'] = 'OF-' . strtoupper(substr(hash('sha256', $id . '-' . $offer['version']), 0, 10));
    $vatRate = (float) ($offer['pricing']['vatRate'] ?? getenv('OFFER_VAT_RATE') ?: 23);
    $depositRate = $net <= 5000 ? 10 : ($net <= 10000 ? 20 : ($net <= 30000 ? 30 : 50));
    $offer['pricing']['net'] = round($net, 2);
    $offer['pricing']['vat'] = round($net * $vatRate / 100, 2);
    $offer['pricing']['gross'] = round($net * (1 + $vatRate / 100), 2);
    $offer['pricing']['manualPrice'] = true;
    $offer['payment']['depositRate'] = $depositRate;
    $offer['payment']['deposit'] = round($net * $depositRate / 100, 2);
    $offer['status'] = 'DRAFT';
    $offer['needsHumanReview'] = true;
    unset($offer['reviewedAt'], $offer['verification'], $offer['sentAt'], $offer['sentTo']);
    $offer['updatedAt'] = time();
    $session['offer'] = $offer;
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($body['action'] ?? '') === 'updateText') {
    $offer = is_array($session['offer'] ?? null) ? $session['offer'] : null;
    if ($offer === null) { http_response_code(404); echo json_encode(['message' => 'Najpierw przygotuj ofertę.']); exit; }
    $session['offerVersions'] = is_array($session['offerVersions'] ?? null) ? $session['offerVersions'] : [];
    $session['offerVersions'][] = $offer;
    $offer['version'] = max(1, (int) ($offer['version'] ?? 1) + 1);
    $offer['offerId'] = 'OF-' . strtoupper(substr(hash('sha256', $id . '-' . $offer['version']), 0, 10));
    if (array_key_exists('project', $body)) $offer['project'] = trim((string) $body['project']);
    if (array_key_exists('summary', $body)) $offer['summary'] = trim((string) $body['summary']);
    if (is_array($body['sections'] ?? null)) {
        $offer['sections'] = array_values(array_map(static function ($section): array {
            return ['title' => trim((string) ($section['title'] ?? 'Sekcja')), 'items' => array_values(array_filter(array_map(static fn($item) => trim((string) $item), is_array($section['items'] ?? null) ? $section['items'] : []), static fn($item) => $item !== ''))];
        }, $body['sections']));
    }
    $offer['status'] = 'DRAFT';
    unset($offer['reviewedAt'], $offer['verification'], $offer['sentAt'], $offer['sentTo']);
    $offer['changeLog'] = [];
    $offer['needsHumanReview'] = true;
    $offer['updatedAt'] = time();
    $session['offer'] = $offer;
    file_put_contents($file, json_encode($session, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['offer' => $offer], JSON_UNESCAPED_UNICODE); exit;
}
if (($_GET['format'] ?? '') === 'pdf') { $offerForPdf = $requestedVersion > 0 ? $selectedOffer : ($session['offer'] ?? null); if (!is_array($offerForPdf)) { http_response_code(404); echo json_encode(['message' => 'Najpierw przygotuj ofertę.']); exit; } sendOfferPdfFixed($offerForPdf, $id); }
$visibleOffer = $analysisReady ? ($requestedVersion > 0 ? $selectedOffer : ($session['offer'] ?? null)) : null;
if (is_array($visibleOffer) && in_array(($visibleOffer['status'] ?? ''), ['REVIEWED', 'SENT'], true) && is_array($visibleOffer['sections'] ?? null)) $visibleOffer['sections'] = array_values(array_filter($visibleOffer['sections'], static fn($section) => !str_contains(mb_strtolower((string) ($section['title'] ?? '')), 'zatwierdzone ustalenia')));

echo json_encode(['offer' => $visibleOffer, 'offerVersions' => is_array($session['offerVersions'] ?? null) ? array_values($session['offerVersions']) : []], JSON_UNESCAPED_UNICODE);
