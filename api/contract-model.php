<?php
declare(strict_types=1);
require_once __DIR__.'/contract-profile.php';
require_once __DIR__.'/contract-catalog.php';
require_once __DIR__.'/contract-package.php';
require_once __DIR__.'/contract-brief.php';
require_once __DIR__.'/document-history-model.php';
require_once __DIR__.'/contract-commercial.php';
require_once __DIR__.'/contract-prepare.php';

function contractFields(): array {
    return ['deploymentTerms'=>'Domena, hosting, publikacja i przekazanie', 'provider'=>'Wykonawca — dane i reprezentacja', 'party'=>'Klient — dane i reprezentacja', 'paymentDetails'=>'Rachunek i zasady płatności', 'scope'=>'Przedmiot i zakres (aplikacja, strona, materiały)', 'price'=>'Wynagrodzenie, VAT i część za prawa IP', 'deposit'=>'Zaliczka i harmonogram płatności', 'deadline'=>'Termin i etapy', 'acceptance'=>'Odbiór i przekazanie kodu / dostępów', 'ip'=>'Prawa autorskie — zakres, pola eksploatacji i moment przejścia', 'exclusions'=>'Komponenty zewnętrzne, licencje i wyłączenia', 'support'=>'Usuwanie wad i wsparcie — okres, zakres, czasy reakcji', 'extras'=>'Dodatkowe płatne prace i koszty usług zewnętrznych', 'terms'=>'Pozostałe warunki, odpowiedzialność, rozwiązanie, dane osobowe'];
}
function contractTemplateDefaults(): array {
    return array_replace(['version'=>0, 'provider'=>'', 'defaultTransferTerms'=>'Autorskie prawa majątkowe do utworów wskazanych w umowie przechodzą na klienta po zapłacie pełnego wynagrodzenia określonego w ofercie.', 'defaultIpPayment'=>'Wynagrodzenie za przeniesienie autorskich praw majątkowych lub udzielenie wybranej w umowie licencji, na wskazanych w umowie polach eksploatacji, jest zawarte w cenie określonej w ofercie.'], contractStandardClauses('website'));
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
function contractDraftLayoutAllowed(array $session,array $contract):bool {
    if(($contract['status']??'')!=='DRAFT'||!empty($contract['sentAt'])||!empty($contract['deliveryLog'])) return false;
    $db=sessionDb();
    if(!$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='project_cases'")->fetchColumn()) return true;
    $query=$db->prepare('SELECT data FROM project_cases WHERE session_id=?');$query->execute([$session['id']]);
    $case=json_decode((string)($query->fetchColumn()?:'{}'),true,512,JSON_THROW_ON_ERROR);
    return (int)($case['contractSignedVersion']??0)!==(int)($contract['version']??0);
}
function contractEditor(array $session): string {
    $offer = $session['offer'] ?? [];
    if (($offer['status'] ?? '') !== 'ACCEPTED') return '<section id="contract-panel" class="conversation-contract"><h3>Umowa</h3><p class="muted">Przygotowanie umowy będzie dostępne po zaakceptowaniu oferty.</p></section>';
    $profile=contractProfile(); $saved = $session['contract'] ?? null;
    $selection=contractSelectTemplate($offer);
    $templateId=is_array($saved)?($saved['templateId']??'legacy'):$selection['id'];
    $template=$templateId==='legacy'?contractTemplate():contractCatalogTemplate($templateId);
    $commercial=contractCommercialSnapshot($session);
    $party=implode("\n",array_filter([$offer['contact']['name']??'',!empty($offer['contact']['email'])?'E-mail: '.$offer['contact']['email']:'',!empty($offer['contact']['phone'])?'Telefon: '.$offer['contact']['phone']:'']));
    $defaults = array_replace($template, ['party'=>$party],$commercial['fields']);
    if($profile['version']>0) $defaults['provider']=contractProviderText($profile);
    $defaults['paymentDetails']=implode("\n",array_filter([$profile['bankAccount']!==''?'Rachunek: '.$profile['bankAccount']:'',$profile['paymentTerms']]));
    $values = is_array($saved) ? $saved : $defaults;
    $briefProposal=contractBriefProposal($session,is_array($saved)?($saved['facts']??[]):[]);
    $editorFacts=is_array($saved)?($saved['facts']??[]):$briefProposal['facts'];
    if(!$saved) {$prepared=contractPreparedProposal($session,$profile,$template,['facts'=>$editorFacts]);$values=array_replace($defaults,$prepared['fields']);$editorFacts=$prepared['facts'];}
    $e = 'contractEscape'; $id=$e($session['id']);
    $contractUrl=apiPath('contract.php');
    $html='<section id="contract-panel" class="conversation-contract"><h3>Umowa</h3><p class="muted">'.($saved ? $e($saved['number']).' · wersja '.(int)$saved['version'].' · '.$e($saved['status']??'DRAFT') : 'Przygotuj projekt na podstawie zaakceptowanej oferty.').'</p><p class="muted">Przeniesienie praw wymaga podpisania w odpowiedniej formie. Akceptacja oferty i wysłanie PDF nie oznaczają podpisania umowy.</p><a class="button" href="?view=contract-settings">Ustawienia wzorów umów</a> <details'.(!$saved?' open':'').'><summary class="button">'.($saved?'Edytuj umowę':'Przygotuj umowę').'</summary><form method="post" action="'.$contractUrl.'" class="contract-form"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="contract_session" value="'.$id.'"><input type="hidden" name="expectedVersion" value="'.(int)($saved['version']??0).'"><input type="hidden" name="templateVersion" value="'.(int)($saved['templateVersion']??$template['version']).'">';
    $html.='<input type="hidden" name="templateId" value="'.$e($templateId).'"><input type="hidden" name="templateRevision" value="'.(int)($saved['templateRevision']??$template['revision']??0).'"><input type="hidden" name="profileVersion" value="'.(int)($saved['profileVersion']??$profile['version']).'">';
    $html.='<section style="padding:18px;border:1px solid #5368c7;border-radius:12px;background:#141f36"><h4>Wzór umowy</h4><p data-template-current>Aktualny: '.$e($saved['templateName']??$template['title']??'Wcześniejszy wzór umowy').'</p><p class="muted">Rekomendacja: '.$e(contractCatalog()[$selection['id']]['name']).'. '.$e($selection['reason']).' Zapisany dokument zachowuje własne zapisy.</p><p><select name="requestedTemplateId" aria-label="Wybierz wzór umowy" data-template-selector>';
    foreach(contractCatalog() as $key=>$item) $html.='<option value="'.$e($key).'"'.(($templateId==='legacy'?$selection['id']:$templateId)===$key?' selected':'').'>'.$e($item['name'].' — '.$item['description']).'</option>';
    $html.='</select></p><button type="submit" class="button" name="contract_action" value="apply-template" formnovalidate>Wczytaj wybrany wzór</button><p class="muted">Wczytanie zastępuje tylko klauzule wzoru i wymaga ich ponownej akceptacji. Dane stron, cena, zakres i terminy pozostają w formularzu.</p></section><h4>Dane i ustalenia do umowy</h4><p class="muted">Stałe klauzule pochodzą z wybranego wzoru. AI pomaga opisać zakres na podstawie oferty i nie przepisuje zaakceptowanych danych ani warunków prawnych. Przejrzyj propozycję i zatwierdź całość jednym przyciskiem. Brakujące rzeczywiste dane uzupełnij przed przygotowaniem PDF.</p>';
    $html.='<input type="hidden" name="reviewState" value="'.$e(json_encode($saved['review']??new stdClass(),JSON_UNESCAPED_UNICODE)).'"><div style="display:flex;gap:10px;flex-wrap:wrap;padding:14px;border:1px solid #5368c7;border-radius:10px;margin:14px 0"><button class="button" name="contract_action" value="auto-prepare" formnovalidate>Przygotuj automatycznie</button><button class="button" name="contract_action" value="approve-generate">Przejrzałem — zatwierdź całość i przygotuj PDF</button></div>';
    foreach(contractFacts() as $key=>$field) {
        if($key==='ipPayment') continue;
        if(isset($field['module'])) continue;
        $value=$editorFacts[$key]??'';
        if($key==='rightsTerms' && !in_array($editorFacts['ipMode']??'',['exclusive','nonexclusive'],true)) $value='';
        if($key==='publicationDestination') $html.='<h4>Publikacja i przekazanie ustalone z klientem</h4><p class="muted">Te ustalenia trafią do umowy i po jej potwierdzeniu zostaną automatycznie przeniesione do etapu publikacji.</p>' ;
        $clientOnly=in_array($key,['productionDomain','domainRegistrar','domainOwnershipTerms','productionHosting','serverTarget','backupResponsibility','dnsTlsResponsibility'],true);
        $hidden=!contractPublicationFieldApplicable($key,$editorFacts??[]);
        if($key==='consumerDocuments') $hidden=!in_array($editorFacts['clientType']??'',['consumer','protected'],true);
        if($key==='dataProcessingTerms') $hidden=($editorFacts['dataRole']??'')!=='processor';
        $html.='<label'.($clientOnly?' data-client-publication-field data-publication-key="'.$key.'"':'').($key==='rightsTerms'?' data-license-terms'.(!in_array($editorFacts['ipMode']??'',['exclusive','nonexclusive'],true)?' hidden':''):'').($hidden?' hidden':'').'>'.$e($field['label']);
        if(isset($field['options'])) { $html.='<select name="'.$key.'"'.($key==='publicationDestination'?' required':'').'><option value="">Wybierz</option>'; foreach($field['options'] as $option=>$label) { if($option==='') continue; $html.='<option value="'.$e($option).'"'.($value===$option?' selected':'').'>'.$e($label).'</option>'; } $html.='</select>'; }
        else $html.='<input name="'.$key.'" maxlength="2000" value="'.$e($value).'"'.($key==='contractDate'?' type="date"':'').'>';
        $html.='</label>';
    }
    $html.=contractPackageEditor(['facts'=>$editorFacts],$session['id']);
    $html.='<p class="muted">Moment przeniesienia praw i wynagrodzenie za IP są pobierane z ustawień wzoru. Zapisana umowa zachowuje własne warunki.</p>';
    $html.='<div><button class="button" name="contract_action" value="ai-fill" formnovalidate>Uzupełnij dane projektu AI</button></div><div data-ai-feedback role="status"></div>';
    $html.=contractCommercialEditor($commercial,$saved??[],$session['id']);
    foreach(contractFields() as $key=>$label) $html.='<label>'.$e($label).'<textarea name="'.$key.'"'.(isset($commercial['fields'][$key])?' readonly data-commercial-field':'').' rows="'.(in_array($key,['ip','terms','scope'],true)?5:3).'" maxlength="20000">'.$e($values[$key]??'').'</textarea></label>';
    $html.='<p class="muted">Puste ustalenia można uzupełnić z briefu i potwierdzonych odpowiedzi analizy. Zakres oferty daje propozycję kryteriów odbioru. Każdą propozycję sprawdź i zaakceptuj; ceny hostingu, licencje, dane dostawców i zabezpieczenia wymagają rzeczywistych ustaleń.</p><button class="button" name="contract_action" value="brief-fill" formnovalidate>Uzupełnij puste ustalenia z briefu</button>';
    if($briefProposal['sources']) $html.='<p class="muted">Źródła propozycji: '.$e(implode(' · ',$briefProposal['sources'])).'</p>';
    foreach(['hostingExpectations'=>'Oczekiwania klienta dotyczące hostingu','supportExpectations'=>'Oczekiwania klienta dotyczące wsparcia'] as $key=>$label) {
        $reference=$session['projectState'][$key]??'';
        if(is_string($reference)&&trim($reference)!=='') $html.='<p class="muted"><strong>'.$e($label).' (brief):</strong> '.$e($reference).'. Uzgodnij konkretne warunki przed akceptacją pakietu.</p>';
    }
    $html.='<div><button class="button" name="contract_action" value="auto-prepare" formnovalidate>Przygotuj automatycznie z oferty i aktualnego wzoru</button> <button class="button" name="contract_action" value="approve-generate">Przejrzałem — zatwierdź całość i przygotuj PDF</button> <button class="button" name="contract_action" value="save">Zapisz projekt umowy</button> <button class="button" name="contract_action" value="generate">Zapisz i przygotuj PDF</button></div><p class="muted">Automat dobiera wzór i pobiera zakres, kwoty, płatności, harmonogram oraz warunki z zaakceptowanej oferty. Zatwierdzenie całości zastępuje akceptowanie każdego pola osobno. Brakujących danych stron, praw do materiałów i rzeczywistych dostawców nie wymyślamy.</p></form></details>';
    if(!empty($saved['pdfBase64'])) {
        $freshLayout=contractDraftLayoutAllowed($session,$saved)&&(int)($saved['pdfLayoutVersion']??0)<3;
        $html.='<details open style="margin:18px 0"><summary>Podgląd PDF umowy</summary>'.($freshLayout?'<p class="muted">Poniżej aktualny układ szkicu z zachowaną treścią. Pobieranie i wysyłka korzystają z zapisanego pliku. Aby zapisać nowy wygląd, otwórz „Edytuj umowę” i kliknij „Przejrzałem — zatwierdź całość i przygotuj PDF”.</p>':'').'<iframe title="Podgląd PDF umowy" src="'.$contractUrl.'?session='.$id.'&amp;format='.($freshLayout?'layout-preview':'pdf').'&amp;inline=1&amp;layout=3" style="width:100%;height:80vh;border:1px solid #465474;border-radius:10px;background:#fff"></iframe></details>';
    }
    if (!empty($saved['pdfBase64'])) $html.='<p><a class="button" href="'.$contractUrl.'?session='.$id.'&amp;format=pdf">Pobierz PDF</a></p><form method="post" action="'.$contractUrl.'" class="contract-send"><input type="hidden" name="contract_session" value="'.$id.'"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="expectedVersion" value="'.(int)$saved['version'].'"><button class="button" name="action" value="send"'.(contractPackageApproved($saved)?'':' disabled').'>Wyślij PDF klientowi</button></form>';
    $html.=documentSourceLinks($saved??[],$session['id']);
    $html.=contractPackageControls($saved??[],$session['id']);
    if (!empty($session['contractVersions'])) { $html.='<details><summary>Historia wersji</summary>'; foreach(array_reverse($session['contractVersions']) as $old) $html.='<p>Wersja '.(int)$old['version'].' · '.$e($old['status']??'DRAFT').(!empty($old['pdfBase64'])?' · <a href="'.$contractUrl.'?session='.$id.'&amp;format=pdf&amp;version='.(int)$old['version'].'">Pobierz PDF</a>':'').'</p>'; $html.='</details>'; }
    return $html.'</section>';
}
