<?php
declare(strict_types=1);

/**
 * Loads production configuration from the PHP document root and local
 * development configuration from the repository root without overwriting
 * server variables. The API .htaccess denies all web access to .env files.
 */
$envFile = basename(__DIR__) === 'public_html'
    ? __DIR__ . '/.env'
    : dirname(__DIR__) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || getenv($name) !== false) continue;
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1);
        putenv($name . '=' . $value);
    }
}

function sessionDb(): PDO { static $db; if ($db instanceof PDO) return $db; $dir = __DIR__ . '/storage'; if (!is_dir($dir)) mkdir($dir, 0700, true); $db = new PDO('sqlite:' . $dir . '/sessions.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $db->exec('PRAGMA busy_timeout=5000'); $db->exec('CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, data TEXT NOT NULL, updated_at INTEGER NOT NULL)'); return $db; }
function readSession(string $id): ?array { $stmt = sessionDb()->prepare('SELECT data FROM sessions WHERE id=:id'); $stmt->execute([':id'=>$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if (!$row) return null; $data = json_decode((string)$row['data'], true); return is_array($data) ? $data : null; }
function writeSession(array $session): void { $id = (string) ($session['id'] ?? ''); if (!preg_match('/^[a-f0-9]{32}$/', $id)) throw new RuntimeException('Invalid session id'); $session['updatedAt'] = time(); $json = json_encode($session, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); $stmt = sessionDb()->prepare('INSERT INTO sessions (id,data,updated_at) VALUES (:id,:data,:updated) ON CONFLICT(id) DO UPDATE SET data=excluded.data,updated_at=excluded.updated_at'); $stmt->execute([':id'=>$id, ':data'=>$json, ':updated'=>$session['updatedAt']]); }
function importLegacySessions(): void { $db=sessionDb(); $stmt=$db->prepare('INSERT OR IGNORE INTO sessions (id,data,updated_at) VALUES (:id,:data,:updated)'); foreach(glob(__DIR__.'/storage/*.json')?:[] as $file){$data=json_decode((string)@file_get_contents($file),true);if(is_array($data)&&preg_match('/^[a-f0-9]{32}$/',(string)($data['id']??'')))$stmt->execute([':id'=>$data['id'],':data'=>json_encode($data,JSON_UNESCAPED_UNICODE),':updated'=>(int)($data['updatedAt']??time())]);} }
function allSessions(): array { importLegacySessions(); $rows = sessionDb()->query('SELECT data FROM sessions ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_COLUMN); $items=[]; foreach($rows as $json){$data=json_decode((string)$json,true);if(is_array($data))$items[]=$data;} return $items; }
function deleteSession(string $id): void { $stmt=sessionDb()->prepare('DELETE FROM sessions WHERE id=:id'); $stmt->execute([':id'=>$id]); }
function normalizeSessionData(array &$session): bool {
    $changed = false;
    $analysis = is_array($session['internalAnalysis'] ?? null) ? $session['internalAnalysis'] : [];
    if (($analysis['status'] ?? '') === 'COMPLETED' && (int) ($analysis['version'] ?? 0) < 1) { $analysis['version'] = 1; $analysis['runId'] = substr(hash('sha256', (string) ($session['id'] ?? '') . '-analysis-1'), 0, 16); $session['internalAnalysis'] = $analysis; $changed = true; }
    $version = (int) ($analysis['version'] ?? 0);
    $questions = array_fill_keys(array_map('trim', is_array($analysis['missingInformation'] ?? null) ? $analysis['missingInformation'] : []), true);
    foreach (['adminDecisions','adminProposals'] as $key) if (is_array($session[$key] ?? null)) { $filtered = []; foreach ($session[$key] as $question => $value) if (isset($questions[trim((string) $question)])) { if (is_array($value)) $value['analysisVersion'] = $version; $filtered[(string) $question] = $value; } if ($filtered !== $session[$key]) { $session[$key] = $filtered; $changed = true; } }
    if (is_array($session['offer'] ?? null) && $version > 0 && (int) ($session['offer']['analysisVersion'] ?? 0) !== $version) { $session['offer']['status'] = 'OUTDATED'; $session['offer']['outdatedAt'] = time(); $changed = true; }
    return $changed;
}
