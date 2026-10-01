<?php
declare(strict_types=1);
require_once __DIR__ . '/project-model.php';

function discoveryMessageHasTestContact(string $message): bool {
    return preg_match('/(?:\b(?:fikcyjn\p{L}*|testow\p{L}*|przykładow\p{L}*|placeholder|fictional|fake|dummy)\b|tylko\s+(?:do|na)\s+testu|wyłącznie\s+(?:do|na)\s+testu|test[- ]only|for\s+testing\s+only|test\s+data)/iu', $message) === 1;
}

function contactFromMessage(string $message): array {
    if(discoveryMessageHasTestContact($message)) return [];
    $result=[];
    if(preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',$message,$match) && projectContactEmailIsValid($match[0])) $result['contactEmail']=$match[0];
    if(preg_match('/(?:nazwa firmy|firma|imię)\s*:\s*(.+?)(?=\s+(?:oraz|i)\s+|[,.;]|$)/iu',$message,$match)) $result['contactName']=trim($match[1]);
    if(preg_match('/(?:numer telefonu|telefon|tel\.?)\s*:?\s*([+()\d][\d\s()\-]{6,})/iu',$message,$match) && projectContactPhoneIsValid($match[1])) $result['contactPhone']=trim($match[1]);
    return $result;
}
