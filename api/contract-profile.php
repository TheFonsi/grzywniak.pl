<?php
declare(strict_types=1);

function contractProfileFields(): array {
    return ['legalName'=>'Pełna nazwa (JDG: z imieniem i nazwiskiem; spółka: z formą prawną, np. sp. z o.o.)', 'address'=>'Pełny adres siedziby / działalności', 'taxId'=>'NIP (jeśli dotyczy)', 'representative'=>'Osoba podpisująca i podstawa reprezentacji', 'email'=>'E-mail do kontaktu i reklamacji', 'phone'=>'Telefon', 'bankAccount'=>'Rachunek bankowy / IBAN (opcjonalnie)', 'paymentTerms'=>'Domyślne zasady płatności (opcjonalnie)', 'complaintsAddress'=>'Adres reklamacji, jeśli inny (opcjonalnie)'];
}
function contractProfile(): array {
    $db=sessionDb(); $db->exec('CREATE TABLE IF NOT EXISTS contract_profile (id INTEGER PRIMARY KEY CHECK(id=1), data TEXT NOT NULL)');
    $json=$db->query('SELECT data FROM contract_profile WHERE id=1')->fetchColumn();
    return array_replace(array_fill_keys(array_keys(contractProfileFields()),''),['version'=>0],$json?json_decode($json,true,512,JSON_THROW_ON_ERROR):[]);
}
function contractProfileMissing(array $profile): array {
    $missing=[];
    foreach(['legalName','address','representative','email','phone'] as $key) if(trim((string)($profile[$key]??''))==='') $missing[]=contractProfileFields()[$key];
    return $missing;
}
function contractProviderText(array $profile): string {
    $lines=[];
    foreach(['legalName','address','taxId','representative','email','phone','complaintsAddress'] as $key) if(!empty($profile[$key])) $lines[]=contractProfileFields()[$key].': '.$profile[$key];
    return implode("\n",$lines);
}
function contractFacts(): array {
    return [
        'clientType'=>['label'=>'Status klienta','options'=>[''=>'Wybierz','business'=>'Firma — umowa zawodowa B2B','consumer'=>'Konsument','protected'=>'Przedsiębiorca z ochroną konsumencką']],
        'ipMode'=>['label'=>'Prawa do projektu','options'=>[''=>'Wybierz','transfer'=>'Przeniesienie praw majątkowych','exclusive'=>'Licencja wyłączna','nonexclusive'=>'Licencja niewyłączna']],
        'rightsTerms'=>['label'=>'Moment przejścia praw lub czas, terytorium i zakres licencji'],
        'ipPayment'=>['label'=>'Wynagrodzenie za prawa IP (kwota lub wyraźne włączenie w cenę)'],
        'signing'=>['label'=>'Sposób podpisania','options'=>[''=>'Wybierz','paper'=>'Podpisy własnoręczne','qualified'=>'Kwalifikowane podpisy elektroniczne']],
        'dataRole'=>['label'=>'Dostęp wykonawcy do danych osobowych klienta','options'=>[''=>'Do ustalenia','none'=>'Brak przetwarzania w imieniu klienta','processor'=>'Przetwarzanie w imieniu klienta — powierzenie']],
        'clientAddress'=>['label'=>'Pełny adres klienta'],
        'clientTaxId'=>['label'=>'NIP / identyfikator i rejestr klienta (jeśli dotyczy)'],
        'clientRepresentative'=>['label'=>'Osoba podpisująca po stronie klienta / podstawa umocowania'],
        'contractDate'=>['label'=>'Planowana data zawarcia umowy (RRRR-MM-DD)'],
    ];
}
function contractReadFacts(array $body, array $saved = [], ?array $template = null): array {
    $facts=[];
    foreach(contractFacts() as $key=>$field) {
        $value=$body[$key]??'';
        if(!is_string($value)||mb_strlen($value)>2000 || (isset($field['options'])&&!array_key_exists($value,$field['options']))) throw new InvalidArgumentException('Niepoprawne pole: '.$field['label']);
        $facts[$key]=trim($value);
    }
    if($facts['contractDate']!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$facts['contractDate']) || date('Y-m-d',strtotime($facts['contractDate'])?:0)!==$facts['contractDate'])) throw new InvalidArgumentException('Podaj poprawną datę zawarcia umowy.');
    $template ??= contractTemplateDefaults();
    $facts['ipPayment']=trim((string)($saved['ipPayment']??''))!=='' ? $saved['ipPayment'] : $template['defaultIpPayment'];
    if($facts['ipMode']==='transfer') $facts['rightsTerms']=($saved['ipMode']??'')==='transfer' && trim((string)($saved['rightsTerms']??''))!=='' ? $saved['rightsTerms'] : $template['defaultTransferTerms'];
    return $facts;
}
function contractFactsMissing(array $facts): array {
    $missing=[];
    foreach(contractFacts() as $key=>$field) if($key!=='clientTaxId' && trim((string)($facts[$key]??''))==='') $missing[]=$field['label'];
    return $missing;
}
function contractProfileForm(): string {
    $profile=contractProfile(); $e='contractEscape';
    $html='<section class="panel"><h2>Moje dane do umów</h2><p class="muted">Wpisz dane zgodne z rejestrem i sposób reprezentacji. Nowe umowy pobiorą je automatycznie. Zapisane umowy zachowują własną kopię danych. Pola rejestrowe uzupełnij odpowiednio do formy działalności; PESEL i numer dowodu nie są potrzebne do tego formularza.</p><form class="contract-form" method="post" action="/api/contract.php"><input type="hidden" name="action" value="save-profile"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="expectedVersion" value="'.(int)$profile['version'].'">';
    foreach(contractProfileFields() as $key=>$label) $html.='<label>'.$e($label).'<input name="'.$key.'" type="'.($key==='email'?'email':'text').'" maxlength="2000" value="'.$e($profile[$key]).'"'.(in_array($key,['legalName','address','representative','email','phone'],true)?' required':'').'></label>';
    return $html.'<button class="button">Zapisz moje dane</button><p role="status">'.(isset($_GET['profileSaved'])?'Dane zostały zapisane.':'').'</p></form></section>';
}
