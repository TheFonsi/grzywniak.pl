<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$allowedOrigins = [
    'https://grzywniak.pl',
    'https://www.grzywniak.pl',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    if (!in_array($origin, $allowedOrigins, true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Origin not allowed.']);
        exit;
    }

    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

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

function contactRateLimit(string $ip): bool
{
    $file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'grzywniak-contact-' . hash('sha256', $ip);
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        return true;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return true;
        }

        $now = time();
        $timestamps = json_decode(stream_get_contents($handle) ?: '[]', true);
        $timestamps = is_array($timestamps)
            ? array_values(array_filter($timestamps, static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 600))
            : [];

        if (count($timestamps) >= 5) {
            return false;
        }

        $timestamps[] = $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($timestamps));
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

$name = contactValue($payload, 'name', 120);
$email = contactValue($payload, 'email', 254);
$phone = contactValue($payload, 'phone', 40);
$budget = contactValue($payload, 'budget', 40);
$message = trim((string)($payload['message'] ?? ''));
$message = mb_substr($message, 0, 5000);
$honeypot = contactValue($payload, 'website', 254);
$language = contactValue($payload, 'language', 2);

if ($honeypot !== '') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Uzupełnij poprawnie wszystkie pola formularza.']);
    exit;
}

if ($phone !== '' && !preg_match('/^[0-9+(). -]+$/', $phone)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Podaj poprawny numer telefonu lub pozostaw to pole puste.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!contactRateLimit($ip)) {
    http_response_code(429);
    header('Retry-After: 600');
    echo json_encode(['ok' => false, 'message' => 'Zbyt wiele wiadomości. Spróbuj ponownie za kilka minut.']);
    exit;
}

$recipient = getenv('CONTACT_TO') ?: 'dawid@grzywniak.pl';
$sender = getenv('CONTACT_FROM') ?: 'dawid@grzywniak.pl';
if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Brakuje konfiguracji nadawcy wiadomości.']);
    exit;
}

$subject = 'Nowe zapytanie ze strony — ' . $name;
$body = "Imię / firma: {$name}\n";
$body .= "E-mail do odpowiedzi: {$email}\n";
$body .= 'Telefon: ' . ($phone !== '' ? $phone : 'Nie podano') . "\n\n";
$body .= 'Orientacyjny budżet: ' . ($budget !== '' ? $budget : 'Nie podano') . "\n\n";
$body .= "Wiadomość:\n{$message}\n";
$headers = [
    "From: Formularz strony <{$sender}>",
    "Reply-To: {$email}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];

// MyDevil validates the envelope sender against the domain's SPF policy.
// Keep it on our verified domain; the visitor's address belongs only in Reply-To.
if (!mail($recipient, $subject, $body, implode("\r\n", $headers), '-f' . $sender)) {
    error_log('Contact form: mail() rejected the message for ' . $recipient);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Nie udało się wysłać wiadomości. Spróbuj ponownie później.']);
    exit;
}

$confirmationIsEnglish = $language === 'en';
$confirmationSubject = $confirmationIsEnglish
    ? 'We received your message — Dawid Grzywniak'
    : 'Potwierdzenie kontaktu — Dawid Grzywniak';
$confirmationBody = $confirmationIsEnglish
    ? "Hello {$name},\n\nThank you for your message. I have received it and will get back to you as soon as possible, usually within 1–2 business days.\n\nBest regards,\nDawid Grzywniak\nhttps://grzywniak.pl\n"
    : "Cześć {$name},\n\nDziękuję za wiadomość. Dotarła do mnie i wrócę z odpowiedzią tak szybko, jak to możliwe — zwykle w ciągu 1–2 dni roboczych.\n\nPozdrawiam,\nDawid Grzywniak\nhttps://grzywniak.pl\n";
$confirmationHeaders = [
    "From: Dawid Grzywniak <{$sender}>",
    "Reply-To: {$sender}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Auto-Submitted: auto-replied',
    'X-Auto-Response-Suppress: All',
];

// Confirmation is a convenience for the visitor. Do not report a failed
// confirmation as a failed enquiry: the main message is already delivered.
if (!mail($email, $confirmationSubject, $confirmationBody, implode("\r\n", $confirmationHeaders), '-f' . $sender)) {
    error_log('Contact form: confirmation mail() rejected for ' . $email);
}

echo json_encode(['ok' => true]);
