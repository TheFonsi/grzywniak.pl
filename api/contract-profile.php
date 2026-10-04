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
    $labels=['legalName'=>'Nazwa','address'=>'Adres','taxId'=>'NIP','representative'=>'Reprezentacja','email'=>'E-mail','phone'=>'Telefon','complaintsAddress'=>'Adres reklamacji'];
    foreach($labels as $key=>$label) if(!empty($profile[$key])) $lines[]=$label.': '.$profile[$key];
    return implode("\n",$lines);
}
function contractFacts(): array {
    return array_merge([
        'publicationDestination'=>['label'=>'Sposób publikacji produkcyjnej','options'=>[''=>'Wybierz','agency'=>'Publikacja na infrastrukturze i domenie Grzywniak','client_handoff'=>'Przekazanie klientowi do publikacji na jego domenie i serwerze']],
        'productionDomain'=>['label'=>'Domena produkcyjna klienta (wymagana przy publikacji klienta)'],
        'domainRegistrar'=>['label'=>'Rejestrator lub operator DNS (opcjonalnie)'],
        'domainOwnershipTerms'=>['label'=>'Ustalenie o prawie klienta do domeny i jej weryfikacji'],
        'productionHosting'=>['label'=>"Dostawca hostingu klienta (je\u{015B}li ustalony)"],
        'serverTarget'=>['label'=>"Serwer i spos\u{00F3}b uruchomienia (je\u{015B}li ustalone)"],
        'backupResponsibility'=>['label'=>"Odpowiedzialno\u{015B}\u{0107} za kopie zapasowe"],
        'dnsTlsResponsibility'=>['label'=>"Odpowiedzialno\u{015B}\u{0107} za DNS i HTTPS/TLS"],
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
        'consumerDocuments'=>['label'=>'Dodatkowe uzgodnienia ochrony klienta (opcjonalnie; właściwe załączniki są tworzone poniżej)'],
        'dataProcessingTerms'=>['label'=>'Dodatkowe uzgodnienia powierzenia (treść załącznika jest tworzona z danych poniżej)'],
    ], contractPackageFields());
}
function contractReadFacts(array $body, array $saved = [], ?array $template = null): array {
    $facts=[];
    foreach(contractFacts() as $key=>$field) {
        $value=$body[$key]??'';
        if(!is_string($value)||mb_strlen($value)>(isset($field['module'])?20000:2000) || (isset($field['options'])&&!array_key_exists($value,$field['options']))) throw new InvalidArgumentException('Niepoprawne pole: '.$field['label']);
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
    foreach(contractFacts() as $key=>$field) {
        if(isset($field['module'])) continue;
        if(in_array($key,['consumerDocuments','dataProcessingTerms'],true)) continue;
        if($key==='consumerDocuments' && !in_array($facts['clientType']??'',['consumer','protected'],true)) continue;
        if($key==='dataProcessingTerms' && ($facts['dataRole']??'')!=='processor') continue;
        if(in_array($key,['clientTaxId','domainRegistrar','serverTarget'],true)) continue;
        if(in_array($key,['domainOwnershipTerms','productionHosting','backupResponsibility','dnsTlsResponsibility'],true) && ($facts['publicationDestination']??'')!=='client_handoff') continue;
        if(in_array($key,['productionDomain'],true) && ($facts['publicationDestination']??'')!=='client_handoff') continue;
        if(trim((string)($facts[$key]??''))==='') $missing[]=$field['label'];
    }
    if(($facts['publicationDestination']??'')==='client_handoff') {
        $domain=strtolower(trim((string)($facts['productionDomain']??'')));
        if($domain!=='' && (strlen($domain)>253 || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/D',$domain)!==1)) $missing[]='Poprawna domena produkcyjna klienta';
    }
    return array_merge($missing,contractPackageMissing($facts));
}
function contractProfileForm(): string {
    $profile=contractProfile(); $e='contractEscape';
    $html='<section class="panel"><h2>Moje dane do umów</h2><p class="muted">Wpisz dane zgodne z rejestrem i sposób reprezentacji. Nowe umowy pobiorą je automatycznie. Zapisane umowy zachowują własną kopię danych. Pola rejestrowe uzupełnij odpowiednio do formy działalności; PESEL i numer dowodu nie są potrzebne do tego formularza.</p><form class="contract-form" method="post" action="'.apiPath('contract.php').'"><input type="hidden" name="action" value="save-profile"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="expectedVersion" value="'.(int)$profile['version'].'">';
    foreach(contractProfileFields() as $key=>$label) $html.='<label>'.$e($label).'<input name="'.$key.'" type="'.($key==='email'?'email':'text').'" maxlength="2000" value="'.$e($profile[$key]).'"'.(in_array($key,['legalName','address','representative','email','phone'],true)?' required':'').'></label>';
    return $html.'<button class="button">Zapisz moje dane</button><p role="status">'.(isset($_GET['profileSaved'])?'Dane zostały zapisane.':'').'</p></form></section>';
}
