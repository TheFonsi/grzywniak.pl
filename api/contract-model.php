<?php
declare(strict_types=1);
require_once __DIR__.'/contract-profile.php';

function contractFields(): array {
    return ['provider'=>'Wykonawca — dane i reprezentacja', 'party'=>'Klient — dane i reprezentacja', 'paymentDetails'=>'Rachunek i zasady płatności', 'scope'=>'Przedmiot i zakres (aplikacja, strona, materiały)', 'price'=>'Wynagrodzenie, VAT i część za prawa IP', 'deposit'=>'Zaliczka i harmonogram płatności', 'deadline'=>'Termin i etapy', 'acceptance'=>'Odbiór i przekazanie kodu / dostępów', 'ip'=>'Prawa autorskie — zakres, pola eksploatacji i moment przejścia', 'exclusions'=>'Komponenty zewnętrzne, licencje i wyłączenia', 'support'=>'Usuwanie wad i wsparcie — okres, zakres, czasy reakcji', 'extras'=>'Dodatkowe płatne prace i koszty usług zewnętrznych', 'terms'=>'Pozostałe warunki, odpowiedzialność, rozwiązanie, dane osobowe'];
}
function contractTemplateDefaults(): array {
    return ['version'=>0, 'provider'=>'', 'defaultTransferTerms'=>'Autorskie prawa majątkowe do utworów wskazanych w umowie przechodzą na klienta po zapłacie pełnego wynagrodzenia określonego w ofercie.', 'defaultIpPayment'=>'Wynagrodzenie za przeniesienie autorskich praw majątkowych lub udzielenie wybranej w umowie licencji, na wskazanych w umowie polach eksploatacji, jest zawarte w cenie określonej w ofercie.', 'acceptance'=>'Strony określą kryteria odbioru, termin zgłaszania wad i sposób ich usuwania. Przekazanie obejmuje uzgodniony kod źródłowy, instrukcję uruchomienia, dokumentację oraz dostęp do repozytorium i kont. Lista przekazywanych elementów i data odbioru zostaną utrwalone w protokole.',
        'ip'=>'Do uzupełnienia dla konkretnego projektu: oznaczenie utworów i uprawnienia wykonawcy; wybór przeniesienia autorskich praw majątkowych albo licencji; wynagrodzenie za prawa; moment ich przejścia. Dla kodu określić zwielokrotnianie, tłumaczenie, przystosowywanie i inne zmiany oraz rozpowszechnianie, w tym najem. Dla grafiki, tekstów i dokumentacji wskazać właściwe pola eksploatacji, w tym publiczne udostępnianie online, oraz zasady opracowań. Przeniesienie praw wymaga formy pisemnej pod rygorem nieważności lub kwalifikowanych podpisów elektronicznych. Autorskie prawa osobiste nie podlegają sprzedaży.',
        'exclusions'=>'Wykaz bibliotek open source, fontów, zdjęć, SDK, usług SaaS i własnych komponentów wykonawcy wraz z licencjami oraz zakresem uprawnień klienta stanowi załącznik. Przeniesienie praw nie obejmuje praw osób trzecich. Należy wskazać opłaty i ograniczenia dotyczące kont, domen, hostingu i sklepów z aplikacjami.',
        'support'=>'Do uzgodnienia: okres usuwania wad, definicja wady względem odebranego zakresu, kanał zgłoszeń, godziny obsługi, priorytety oraz czasy reakcji i naprawy. Odrębnie wskazać płatne utrzymanie, aktualizacje i rozwój. Postanowienia nie ograniczają bezwzględnie obowiązujących praw klienta.',
        'extras'=>'Prace poza zakresem wymagają wcześniejszego uzgodnienia zakresu, ceny lub stawki i limitu godzin oraz wpływu na harmonogram. Klient zatwierdza zamówienie przed rozpoczęciem prac. Koszty hostingu, domen, API i sklepów z aplikacjami należy wymienić oddzielnie wraz z płatnikiem.',
        'terms'=>'Do uzgodnienia: odpowiedzialność, poufność, rozwiązanie umowy i rozliczenie wykonanych etapów, prawo właściwe oraz rozstrzyganie sporów. Jeżeli wykonawca przetwarza dane osobowe w imieniu klienta, należy ustalić role i zawrzeć umowę powierzenia zgodną z art. 28 RODO. Dla konsumentów i przedsiębiorców objętych ochroną konsumencką wymagany jest odpowiednio dostosowany wzór.'];
}
function contractTemplate(): array {
    $db = sessionDb();
    $db->exec('CREATE TABLE IF NOT EXISTS contract_templates (id INTEGER PRIMARY KEY CHECK(id=1), data TEXT NOT NULL)');
    $json = $db->query('SELECT data FROM contract_templates WHERE id=1')->fetchColumn();
    return array_replace(contractTemplateDefaults(), $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : []);
}
function contractToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $path=__DIR__.'/storage/admin-sessions';
        if(!is_dir($path)) mkdir($path,0700,true);
        session_save_path($path);
        session_start(['cookie_httponly'=>true, 'cookie_samesite'=>'Strict', 'cookie_secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'), 'use_strict_mode'=>true]);
    }
    return $_SESSION['contract_csrf'] ??= bin2hex(random_bytes(32));
}
function contractEscape(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function contractEditor(array $session): string {
    $offer = $session['offer'] ?? [];
    if (($offer['status'] ?? '') !== 'ACCEPTED') return '<section id="contract-panel" class="conversation-contract"><h3>Umowa</h3><p class="muted">Przygotowanie umowy będzie dostępne po zaakceptowaniu oferty.</p></section>';
    $template = contractTemplate(); $profile=contractProfile(); $saved = $session['contract'] ?? null;
    $defaults = array_replace($template, ['party'=>$offer['contact']['name'] ?? '', 'scope'=>implode("\n", array_map(static fn($s)=>($s['title']??'')."\n".implode("\n",$s['items']??[]), $offer['sections']??[])), 'price'=>number_format((float)($offer['pricing']['net']??0),2,',',' ').' zł netto; VAT '.($offer['pricing']['vatRate']??23).'%', 'deposit'=>($offer['payment']['depositRate']??0).'%', 'deadline'=>$session['projectState']['deadline']??'']);
    if (!empty($offer['contractTerms'])) $defaults['terms'] .= "\n".$offer['contractTerms'];
    if($profile['version']>0) $defaults['provider']=contractProviderText($profile);
    $defaults['paymentDetails']=implode("\n",array_filter([$profile['bankAccount']!==''?'Rachunek: '.$profile['bankAccount']:'',$profile['paymentTerms']]));
    $values = is_array($saved) ? $saved : $defaults;
    $e = 'contractEscape'; $id=$e($session['id']);
    $html='<section id="contract-panel" class="conversation-contract"><h3>Umowa</h3><p class="muted">'.($saved ? $e($saved['number']).' · wersja '.(int)$saved['version'].' · '.$e($saved['status']??'DRAFT') : 'Przygotuj projekt na podstawie zaakceptowanej oferty.').'</p><p class="muted">Przeniesienie praw wymaga podpisania w odpowiedniej formie. Akceptacja oferty i wysłanie PDF nie oznaczają podpisania umowy.</p><a class="button" href="?view=contract-settings">Ustawienia wzorów umów</a> <details'.(!$saved?' open':'').'><summary class="button">'.($saved?'Edytuj umowę':'Przygotuj umowę').'</summary><form method="post" action="/api/contract.php" class="contract-form"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="contract_session" value="'.$id.'"><input type="hidden" name="expectedVersion" value="'.(int)($saved['version']??0).'"><input type="hidden" name="templateVersion" value="'.(int)($saved['templateVersion']??$template['version']).'">';
    $html.='<input type="hidden" name="profileVersion" value="'.(int)($saved['profileVersion']??$profile['version']).'"><h4>Dane i ustalenia do umowy</h4><p class="muted">AI przygotuje propozycje wszystkich warunków, a dane pobierze z oferty i ustawień. Przy każdym polu możesz wybrać Akceptuj albo Zmień. Nieznane dane identyfikacyjne uzupełnij ręcznie. Zapis projektu zachowuje również stan akceptacji.</p>';
    $html.='<input type="hidden" name="reviewState" value="'.$e(json_encode($saved['review']??new stdClass(),JSON_UNESCAPED_UNICODE)).'">';
    foreach(contractFacts() as $key=>$field) {
        if($key==='ipPayment') continue;
        $value=$saved['facts'][$key]??'';
        if($key==='rightsTerms' && !in_array($saved['facts']['ipMode']??'',['exclusive','nonexclusive'],true)) $value='';
        $html.='<label'.($key==='rightsTerms'?' data-license-terms'.(!in_array($saved['facts']['ipMode']??'',['exclusive','nonexclusive'],true)?' hidden':''):'').'>'.$e($key==='rightsTerms'?'Czas, terytorium i zakres licencji':$field['label']);
        if(isset($field['options'])) { $html.='<select name="'.$key.'">'; foreach($field['options'] as $option=>$label) $html.='<option value="'.$e($option).'"'.($value===$option?' selected':'').'>'.$e($label).'</option>'; $html.='</select>'; }
        else $html.='<input name="'.$key.'" maxlength="2000" value="'.$e($value).'"'.($key==='contractDate'?' type="date"':'').'>';
        $html.='</label>';
    }
    $html.='<p class="muted">Moment przeniesienia praw i wynagrodzenie za IP są pobierane z ustawień wzoru. Zapisana umowa zachowuje własne warunki.</p>';
    $html.='<div><button class="button" name="contract_action" value="ai-fill" formnovalidate>Wypełnij umowę AI</button></div><div data-ai-feedback role="status"></div>';
    foreach(contractFields() as $key=>$label) $html.='<label>'.$e($label).'<textarea name="'.$key.'" rows="'.(in_array($key,['ip','terms','scope'],true)?5:3).'" maxlength="20000">'.$e($values[$key]??'').'</textarea></label>';
    $html.='<div><button class="button" name="contract_action" value="save">Zapisz projekt umowy</button> <button class="button" name="contract_action" value="generate">Zapisz i przygotuj PDF</button></div></form></details>';
    if (!empty($saved['pdfBase64'])) $html.='<p><a class="button" href="/api/contract.php?session='.$id.'&amp;format=pdf">Pobierz PDF</a></p><form method="post" action="/api/contract.php" class="contract-send"><input type="hidden" name="contract_session" value="'.$id.'"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="expectedVersion" value="'.(int)$saved['version'].'"><button class="button" name="action" value="send">Wyślij PDF klientowi</button></form>';
    if (!empty($session['contractVersions'])) { $html.='<details><summary>Historia wersji</summary>'; foreach(array_reverse($session['contractVersions']) as $old) $html.='<p>Wersja '.(int)$old['version'].' · '.$e($old['status']??'DRAFT').(!empty($old['pdfBase64'])?' · <a href="/api/contract.php?session='.$id.'&amp;format=pdf&amp;version='.(int)$old['version'].'">Pobierz PDF</a>':'').'</p>'; $html.='</details>'; }
    return $html.'</section>';
}
