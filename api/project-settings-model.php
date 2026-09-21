<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function projectSettingsSchema(): array {
    return [
        'github'=>['title'=>'GitHub i CI/CD','description'=>'GitHub App wykonuje operacje infrastruktury. Agenci kodujący korzystają z osobnego konta Codex.', 'fields'=>[
            'GITHUB_ORG'=>['label'=>'Organizacja GitHub','default'=>'Grzywniak'],
            'GITHUB_TEMPLATE_OWNER'=>['label'=>'Właściciel szablonu','default'=>'Grzywniak'],
            'GITHUB_TEMPLATE_REPO'=>['label'=>'Repozytorium szablonu','default'=>'web-vite-template'],
            'GITHUB_APP_ID'=>['label'=>'ID GitHub App'],
            'GITHUB_INSTALLATION_ID'=>['label'=>'ID instalacji GitHub App'],
            'GITHUB_APP_PRIVATE_KEY'=>['label'=>'Klucz prywatny GitHub App (PEM)','secret'=>true,'multiline'=>true],
        ]],
        'cloudflare'=>['title'=>'Cloudflare i domeny','description'=>'Token ogranicz do zarządzania DNS właściwej strefy.', 'fields'=>[
            'CLOUDFLARE_ZONE_ID'=>['label'=>'ID strefy Cloudflare'],
            'CLOUDFLARE_DNS_TOKEN'=>['label'=>'Token DNS Cloudflare','secret'=>true],
            'PREVIEW_BASE_DOMAIN'=>['label'=>'Domena podglądów','default'=>'preview.grzywniak.pl'],
            'PREVIEW_ORIGIN_HOST'=>['label'=>'Host origin dla CNAME'],
            'PRODUCTION_BASE_DOMAIN'=>['label'=>'Domena produkcyjna projektów','default'=>'app.grzywniak.pl'],
            'PRODUCTION_ORIGIN_HOST'=>['label'=>'Host origin produkcji'],
        ]],
        'vps'=>['title'=>'VPS i wdrożenia','description'=>'Prywatne API VPS musi działać przez HTTPS i potwierdzać gotowość środowiska.', 'fields'=>[
            'PUBLIC_SITE_URL'=>['label'=>'Publiczny adres strony','default'=>'https://grzywniak.pl'],
            'PUBLIC_API_URL'=>['label'=>'Publiczny adres API i formularza uwag','default'=>'https://api.grzywniak.pl'],
            'VPS_CONTROL_URL'=>['label'=>'Adres API VPS'],
            'VPS_CONTROL_TOKEN'=>['label'=>'Token API VPS','secret'=>true],
        ]],
        'agents'=>['title'=>'Agenci i modele','description'=>'Konto Codex agentów działa poza tym panelem. Poniższe dane runnera są przygotowane na jego podłączenie.', 'fields'=>[
            'OPENAI_API_KEY'=>['label'=>'Klucz OpenAI dla planisty projektu','secret'=>true],
            'OPENAI_MODEL'=>['label'=>'Model planisty','default'=>'gpt-5.6-luna'],
            'CODEX_RUNNER_URL'=>['label'=>'Adres oddzielnego runnera Codex'],
            'CODEX_RUNNER_TOKEN'=>['label'=>'Token runnera Codex','secret'=>true],
            'AGENT_MAX_CONCURRENCY'=>['label'=>'Równoległe zadania agentów','default'=>'2','number'=>true,'min'=>1,'max'=>20],
        ]],
        'limits'=>['title'=>'Limity i kontrola kosztów','description'=>'Domyślne granice dla przyszłych zadań. Limit zaakceptowany przy starcie sprawy pozostaje obowiązujący.', 'fields'=>[
            'PROJECT_DEFAULT_COST_LIMIT_PLN'=>['label'=>'Domyślny limit projektu (PLN)','default'=>'500','number'=>true,'min'=>0,'max'=>1000000],
            'AGENT_MONTHLY_LIMIT_PLN'=>['label'=>'Miesięczny limit agentów (PLN)','default'=>'2000','number'=>true,'min'=>0,'max'=>1000000],
            'AGENT_TASK_TIMEOUT_MIN'=>['label'=>'Limit czasu jednego zadania (min)','default'=>'60','number'=>true,'min'=>1,'max'=>1440],
            'AGENT_TASK_COST_LIMIT_PLN'=>['label'=>'Maksymalny koszt jednego zadania (PLN)','default'=>'50','number'=>true,'min'=>0,'max'=>1000000],
            'OPENAI_INPUT_PLN_PER_MILLION'=>['label'=>'Koszt 1 mln tokenów wejściowych modelu (PLN)','number'=>true,'min'=>0,'max'=>1000000],
            'OPENAI_OUTPUT_PLN_PER_MILLION'=>['label'=>'Koszt 1 mln tokenów wyjściowych modelu (PLN)','number'=>true,'min'=>0,'max'=>1000000],
        ]],
    ];
}
function projectSettingsFields(): array {
    $fields=[];
    foreach(projectSettingsSchema() as $section) foreach($section['fields'] as $key=>$field) $fields[$key]=$field;
    return $fields;
}
function projectSettingsDb(): PDO {
    $db=sessionDb();
    $db->exec('CREATE TABLE IF NOT EXISTS project_settings (name TEXT PRIMARY KEY, value TEXT NOT NULL, is_secret INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS project_settings_events (id INTEGER PRIMARY KEY AUTOINCREMENT, names TEXT NOT NULL, created_at INTEGER NOT NULL)');
    return $db;
}
function projectSettingsKey(): string {
    if(!function_exists('openssl_encrypt')) throw new RuntimeException('Serwer wymaga rozszerzenia PHP OpenSSL.');
    $file=__DIR__.'/storage/project-settings.key';
    if(!is_file($file)) {
        $key=random_bytes(32);
        $handle=@fopen($file,'x');
        if($handle!==false) { @chmod($file,0600); fwrite($handle,$key); fclose($handle); }
    }
    $key=@file_get_contents($file);
    if($key===false||strlen($key)!==32) throw new RuntimeException('Nie można odczytać klucza ustawień.');
    return $key;
}
function projectSetting(string $name): string {
    if(!array_key_exists($name,projectSettingsFields())) throw new InvalidArgumentException('Nieznane ustawienie.');
    $stmt=projectSettingsDb()->prepare('SELECT value,is_secret FROM project_settings WHERE name=?');
    $stmt->execute([$name]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if($row) {
        if(!(int)$row['is_secret']) return (string)$row['value'];
        $blob=base64_decode((string)$row['value'],true);
        if($blob===false||strlen($blob)<28) throw new RuntimeException('Nie można odczytać sekretu '.$name.'.');
        $plain=openssl_decrypt(substr($blob,28),'aes-256-gcm',projectSettingsKey(),OPENSSL_RAW_DATA,substr($blob,0,12),substr($blob,12,16));
        if($plain===false) throw new RuntimeException('Nie można odszyfrować sekretu '.$name.'.');
        return $plain;
    }
    $environment=getenv($name);
    if($environment!==false && $environment!=='') return (string)$environment;
    return (string)(projectSettingsFields()[$name]['default']??'');
}
function projectSettingConfigured(string $name): bool {
    $stmt=projectSettingsDb()->prepare('SELECT 1 FROM project_settings WHERE name=?');
    $stmt->execute([$name]);
    return (bool)$stmt->fetchColumn() || (getenv($name)!==false && getenv($name)!=='');
}
function projectSettingsSave(array $input): array {
    $fields=projectSettingsFields(); $changes=[];
    foreach($fields as $name=>$field) {
        if(!array_key_exists($name,$input)) continue;
        $value=trim((string)$input[$name]);
        if(!empty($field['secret']) && $value==='') continue;
        if(strlen($value)>($field['multiline']??false?16000:2000)) throw new InvalidArgumentException('Wartość pola '.$field['label'].' jest za długa.');
        if(!empty($field['number']) && ($value===''||!is_numeric($value)||!is_finite((float)$value)||(float)$value<$field['min']||(float)$value>$field['max'])) throw new InvalidArgumentException('Niepoprawny zakres pola '.$field['label'].'.');
        if(in_array($name,['GITHUB_ORG','GITHUB_TEMPLATE_OWNER','GITHUB_TEMPLATE_REPO'],true) && !preg_match('/^[A-Za-z0-9_.-]+$/',$value)) throw new InvalidArgumentException('Niepoprawna nazwa GitHub.');
        if(in_array($name,['PREVIEW_BASE_DOMAIN','PREVIEW_ORIGIN_HOST','PRODUCTION_BASE_DOMAIN','PRODUCTION_ORIGIN_HOST'],true) && $value!=='' && !preg_match('/^[a-z0-9.-]+$/i',$value)) throw new InvalidArgumentException('Niepoprawna domena.');
        if(in_array($name,['PUBLIC_SITE_URL','PUBLIC_API_URL','VPS_CONTROL_URL','CODEX_RUNNER_URL'],true) && $value!=='' && (!filter_var($value,FILTER_VALIDATE_URL)||!str_starts_with(strtolower($value),'https://'))) throw new InvalidArgumentException('Adres usługi musi być poprawnym URL HTTPS.');
        if($name==='GITHUB_APP_PRIVATE_KEY' && $value!=='' && openssl_pkey_get_private($value)===false) throw new InvalidArgumentException('Klucz GitHub App musi być poprawnym kluczem prywatnym PEM.');
        $changes[$name]=$value;
    }
    $db=projectSettingsDb(); $db->beginTransaction();
    try {
        $save=$db->prepare('INSERT INTO project_settings(name,value,is_secret,updated_at) VALUES(?,?,?,?) ON CONFLICT(name) DO UPDATE SET value=excluded.value,is_secret=excluded.is_secret,updated_at=excluded.updated_at');
        foreach($changes as $name=>$value) {
            $secret=!empty($fields[$name]['secret']);
            if($secret) { $nonce=random_bytes(12); $tag=''; $cipher=openssl_encrypt($value,'aes-256-gcm',projectSettingsKey(),OPENSSL_RAW_DATA,$nonce,$tag); if($cipher===false) throw new RuntimeException('Nie udało się zaszyfrować ustawienia.'); $value=base64_encode($nonce.$tag.$cipher); }
            $save->execute([$name,$value,$secret?1:0,time()]);
        }
        if($changes) $db->prepare('INSERT INTO project_settings_events(names,created_at) VALUES(?,?)')->execute([implode(', ',array_keys($changes)),time()]);
        $db->commit();
    } catch(Throwable $error) { $db->rollBack(); throw $error; }
    return array_keys($changes);
}
