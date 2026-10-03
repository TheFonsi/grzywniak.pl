<?php
declare(strict_types=1);
require_once __DIR__.'/project-model.php';
require_once __DIR__.'/project-settings-model.php';

function projectAssetRoot(string $sessionId): string {
    if(!preg_match('/^[a-f0-9]{32}$/',$sessionId)) throw new InvalidArgumentException('Niepoprawna sprawa.');
    return __DIR__.'/storage/project-assets/'.$sessionId;
}
function projectAssetSignature(string $sessionId,string $assetKey,int $expires): string {
    return hash_hmac('sha256',$sessionId.'|'.$assetKey.'|'.$expires,projectSettingsKey());
}
function projectAssetSignedUrl(string $sessionId,array $asset,int $ttl=14400): string {
    $key=(string)($asset['asset_key']??'');
    if(!preg_match('/^[a-f0-9]{48}$/',$key)) throw new RuntimeException('Niepoprawny identyfikator materiału.');
    $expires=time()+max(60,min(86400,$ttl));
    $base=rtrim(projectSetting('PUBLIC_API_URL')?:'https://api.grzywniak.pl','/');
    return $base.'/project-assets.php?session='.rawurlencode($sessionId).'&asset='.rawurlencode($key).'&expires='.$expires.'&signature='.projectAssetSignature($sessionId,$key,$expires);
}
