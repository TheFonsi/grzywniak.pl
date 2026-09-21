<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/project-settings-model.php';
$db=projectSettingsDb();
$name='CODEX_RUNNER_TOKEN';
$stmt=$db->prepare('SELECT value,is_secret,updated_at FROM project_settings WHERE name=?');
$stmt->execute([$name]);
$before=$stmt->fetch(PDO::FETCH_ASSOC);
$lastEvent=(int)$db->query('SELECT COALESCE(MAX(id),0) FROM project_settings_events')->fetchColumn();
try {
    $testValue='test-'.bin2hex(random_bytes(16));
    projectSettingsSave([$name=>$testValue]);
    $stmt->execute([$name]);
    $stored=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$stored||(int)$stored['is_secret']!==1||str_contains((string)$stored['value'],$testValue)||projectSetting($name)!==$testValue) throw new RuntimeException('Secret encryption roundtrip failed.');
    try { projectSettingsSave(['VPS_CONTROL_URL'=>'http://insecure.example']); throw new RuntimeException('Invalid URL was accepted.'); }
    catch(InvalidArgumentException) {}
    echo "Project settings encryption and validation OK\n";
} finally {
    if($before) $db->prepare('INSERT INTO project_settings(name,value,is_secret,updated_at) VALUES(?,?,?,?) ON CONFLICT(name) DO UPDATE SET value=excluded.value,is_secret=excluded.is_secret,updated_at=excluded.updated_at')->execute([$name,$before['value'],$before['is_secret'],$before['updated_at']]);
    else $db->prepare('DELETE FROM project_settings WHERE name=?')->execute([$name]);
    $db->prepare('DELETE FROM project_settings_events WHERE id>?')->execute([$lastEvent]);
}
