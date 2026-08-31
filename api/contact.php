<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid request data.']);
    exit;
}

function contactValue(array $payload, string $key, int $maxLength): string
{
    $value = trim((string)($payload[$key] ?? ''));
    $value = str_replace(["\r", "\n"], ' ', $value);
    return mb_substr($value, 0, $maxLength);
}

$name = contactValue($payload, 'name', 120);
$email = contactValue($payload, 'email', 254);
$message = trim((string)($payload['message'] ?? ''));
$message = mb_substr($message, 0, 5000);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Uzupełnij poprawnie wszystkie pola formularza.']);
    exit;
}

$recipient = getenv('CONTACT_TO') ?: 'dawid@grzywniak.pl';
$sender = getenv('CONTACT_FROM') ?: 'noreply@grzywniak.pl';
if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Brakuje konfiguracji nadawcy wiadomości.']);
    exit;
}

$subject = 'Nowe zapytanie ze strony — ' . $name;
$body = "Imię / firma: {$name}\n";
$body .= "E-mail do odpowiedzi: {$email}\n\n";
$body .= "Wiadomość:\n{$message}\n";
$headers = [
    "From: Formularz strony <{$sender}>",
    "Reply-To: {$email}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];

if (!mail($recipient, $subject, $body, implode("\r\n", $headers))) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Nie udało się wysłać wiadomości. Spróbuj ponownie później.']);
    exit;
}

echo json_encode(['ok' => true]);
