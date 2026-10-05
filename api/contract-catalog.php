<?php
declare(strict_types=1);

/** Original clauses, not copies of public contract forms. No project technology is imposed. */
function contractCatalog(): array {
    return [
        'website'=>['name'=>'Strona internetowa', 'title'=>'Umowa o wykonanie strony internetowej', 'description'=>'Wizytówka, landing page, one page lub serwis firmowy.', 'revision'=>1],
        'webapp'=>['name'=>'Aplikacja webowa', 'title'=>'Umowa o wykonanie aplikacji webowej', 'description'=>'System z funkcjami biznesowymi, kontami, bazą danych lub integracjami.', 'revision'=>1],
        'ecommerce'=>['name'=>'Sklep internetowy', 'title'=>'Umowa o wykonanie sklepu internetowego', 'description'=>'Katalog produktów, zamówienia, płatności i integracje sprzedażowe.', 'revision'=>1],
    ];
}
function contractSelectTemplate(array $offer): array {
    // Only accepted project scope, never personal identifiers, determines selection.
    $text=mb_strtolower(json_encode(contractOfferScopeSections($offer), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    if(preg_match('/sklep|e.?commerce|koszyk|zamówieni|checkout/u',$text)) return ['id'=>'ecommerce','reason'=>'Zakres zawiera sprzedaż internetową lub obsługę zamówień.'];
    if(preg_match('/aplikacj|system|panel|logowani|kont[ao] użytkownik|baz[ay] danych|integracj/u',$text)) return ['id'=>'webapp','reason'=>'Zakres zawiera funkcje aplikacji, konta lub integracje.'];
    return ['id'=>'website','reason'=>'Zakres odpowiada stronie prezentującej firmę lub ofertę.'];
}
function contractStandardClauses(string $id): array {
    if(!isset(contractCatalog()[$id])) throw new InvalidArgumentException('Nieznany wzór umowy.');
    $specific=match($id) {
        'website'=>'Odbiór obejmuje uzgodnione widoki, treści, działanie na urządzeniach mobilnych, nawigację i wskazane funkcje kontaktowe. Uzyskanie określonej pozycji w wyszukiwarce, liczby klientów lub przychodów nie jest gwarantowanym rezultatem wykonania strony.',
        'webapp'=>'Odbiór obejmuje scenariusze użytkowników i role wskazane w zakresie, zapis i odczyt danych oraz uzgodnione integracje. Wymagania obciążeniowe, migracja danych, dostępność i zgodność z konkretnymi normami obowiązują w zakresie wyraźnie wskazanym w umowie.',
        'ecommerce'=>'Odbiór obejmuje uzgodnioną ścieżkę od produktu do zamówienia oraz integracje płatności i dostaw w środowisku testowym. Uruchomienie płatności produkcyjnych zależy także od zawarcia przez Zamawiającego umowy z operatorem i zatwierdzenia jego konta.',
    };
    return [
        'deploymentTerms'=>"1. Podgląd służy weryfikacji projektu; jego udostępnienie nie oznacza publikacji produkcyjnej ani zawarcia umowy na stały hosting. Publikacja następuje po zatwierdzeniu konkretnej wersji i zgodnie z ustalonym sposobem wdrożenia.\n2. Zakup lub rejestracja domeny nie są objęte wynagrodzeniem, o ile nie wskazano ich w zakresie. Zamawiający zapewnia uprawnienie do używania swojej domeny i autoryzuje zmiany DNS. Wykonawca nie rejestruje domeny wyłącznie na podstawie propozycji jej nazwy.\n3. Przy przekazaniu na serwer Zamawiającego Wykonawca dostarcza uzgodniony pakiet, instrukcję uruchomienia i wymagania środowiska. Instalacja na serwerze klienta jest wykonywana tylko wtedy, gdy została objęta zakresem. Odpowiedzialność za hosting, DNS, TLS i kopie zapasowe wynika z poniższych ustaleń. Dostępy przekazywane są odrębnym bezpiecznym kanałem.",
        'acceptance'=>"1. Wykonawca udostępnia wersję do sprawdzenia i informuje o jej gotowości. Zamawiający zgłasza uwagi do oznaczonej wersji w panelu uwag lub uzgodnionym kanale, opisując niezgodność z zakresem i sposób jej odtworzenia.\n2. $specific\n3. Zamawiający kończy zbieranie uwag do wersji i potwierdza odbiór wyraźnym oświadczeniem. Sam brak odpowiedzi, zakończenie listy uwag lub otwarcie podglądu nie oznaczają odbioru. Wady istotne wymagają usunięcia przed odbiorem; lista pozostałych usterek i terminy ich usunięcia są utrwalane w protokole.\n4. Po poprawkach Wykonawca przedstawia nową wersję do sprawdzenia. Odbiór dokumentuje wersję projektu, spełnione kryteria, uwagi i uzgodnione elementy przekazania. Odbiór nie pozbawia Zamawiającego ustawowych roszczeń dotyczących wad.",
        'ip'=>"1. Postanowienia dotyczą wyłącznie indywidualnie wykonanych i przekazanych w ramach zakresu utworów, do których Wykonawca posiada odpowiednie prawa. Wykaz obejmuje kod źródłowy, indywidualną grafikę, teksty i dokumentację w zakresie ich rzeczywistego wykonania. Nie obejmuje wszystkich przyszłych utworów ani praw osób trzecich.\n2. W wariancie przeniesienia Wykonawca przenosi na Zamawiającego autorskie prawa majątkowe do tych utworów na polach wskazanych poniżej, z chwilą spełnienia warunku przejścia praw wskazanego w ustaleniach. W wariancie licencji Wykonawca udziela Zamawiającemu licencji wyłącznej lub niewyłącznej, zgodnie z wyborem, na tych polach, na czas, terytorium i w zakresie wskazanym w ustaleniach. Stosuje się wyłącznie wybrany wariant. Dla programów komputerowych pola eksploatacji obejmują trwałe i czasowe zwielokrotnianie całości lub części dowolnymi środkami i w dowolnej formie, tłumaczenie, przystosowywanie, zmianę układu i inne zmiany oraz rozpowszechnianie programu lub jego kopii, w tym użyczenie i najem.\n3. Dla indywidualnej grafiki, tekstów i dokumentacji pola obejmują utrwalanie i zwielokrotnianie techniką drukarską i cyfrową, wprowadzanie do obrotu, użyczenie i najem egzemplarzy oraz publiczne wystawianie, wyświetlanie, odtwarzanie i udostępnianie online tak, aby każdy miał dostęp w wybranym miejscu i czasie. Zakres praw obejmuje zezwolenie na wykonywanie i korzystanie z opracowań przekazanych utworów, w granicach uzgodnionych praw.\n4. Autorskie prawa osobiste pozostają przy twórcach. Przeniesienie autorskich praw majątkowych i udzielenie licencji wyłącznej wymagają formy pisemnej pod rygorem nieważności; kwalifikowany podpis elektroniczny jest równoważny formie pisemnej. Wysłanie PDF, skanu lub zwykła akceptacja e-mailowa nie zastępują tej formy.",
        'exclusions'=>"1. Biblioteki, gotowe motywy, fonty, zdjęcia, komponenty Wykonawcy i usługi osób trzecich pozostają objęte własnymi licencjami. Wykonawca przekazuje wykaz użytych składników, ich licencji, ograniczeń i wymaganych opłat wraz z dokumentacją przekazania. Nie przyznaje praw szerszych niż posiadane.\n2. Wykonawca dobiera składniki w sposób umożliwiający uzgodnione korzystanie z projektu i informuje przed ich zastosowaniem o istotnych obowiązkach licencyjnych lub kosztach. Konto, subskrypcja albo licencja wymagająca odrębnej umowy Zamawiającego nie przechodzi automatycznie wraz z kodem.",
        'support'=>"1. Wykonawca przyjmuje zgłoszenia niezgodności wykonania z uzgodnionym zakresem i usuwa wady, za które odpowiada, zgodnie z umową i obowiązującymi przepisami. Zgłoszenie powinno wskazywać wersję, objawy i warunki odtworzenia. Wykonawca potwierdza zgłoszenie i uzgadnia termin odpowiedni do rodzaju wady.\n2. Stałe utrzymanie, dyżur całodobowy, dodatkowa gwarancja, określone SLA, aktualizacje oraz rozwój są świadczone wyłącznie w zakresie i czasie wyraźnie uzgodnionym w zamówieniu. Ich brak nie wyłącza ustawowej odpowiedzialności za wykonanie umowy.\n3. Zmiany dokonane przez Zamawiającego lub osoby trzecie wymagają ustalenia przyczyny problemu. Mogą wyłączyć odpowiedzialność Wykonawcy tylko w zakresie, w jakim rzeczywiście spowodowały wadę.",
        'extras'=>"1. Zmiana zakresu wymaga opisania oczekiwanego rezultatu oraz uzgodnienia ceny lub stawki i limitu, a także wpływu na termin, przed rozpoczęciem dodatkowych prac. Uwagi usuwające niezgodność z uzgodnionym zakresem nie są automatycznie nowym płatnym zamówieniem.\n2. Koszty domen, hostingu, płatnych bibliotek, API i subskrypcji nie są ponoszone w imieniu Zamawiającego bez jego uprzedniej zgody. Każdy dodatkowy koszt wskazuje płatnika, okres i ewentualne odnowienie.\n3. Podwyższenie wynagrodzenia lub poszerzenie zakresu nie wynika z samego zgłoszenia uwagi w podglądzie; wymaga osobnego uzgodnienia.",
        'terms'=>"ODPOWIEDZIALNOŚĆ\n1. Każda ze stron odpowiada za niewykonanie lub nienależyte wykonanie swoich zobowiązań na zasadach prawa polskiego. Wykonawca dochowuje należytej staranności zawodowej i odpowiada za osoby oraz narzędzia, którymi posługuje się przy wykonaniu zobowiązania. Korzystanie z automatyzacji lub AI nie przenosi tej odpowiedzialności na Zamawiającego.\n2. Wykonawca nie gwarantuje nieprzerwanej dostępności usług dostawców zewnętrznych; pozostaje odpowiedzialny za własny dobór, konfigurację i integrację w uzgodnionym zakresie. Żadne postanowienie nie wyłącza odpowiedzialności za szkodę wyrządzoną umyślnie ani praw, których nie można ograniczyć umową. Nie ustanawia się automatycznych kar umownych ani limitu odszkodowania.\nMATERIAŁY I WSPÓŁPRACA\n3. Podział przygotowania tekstów, logo, zdjęć i danych wynika z zakresu. Strona dostarczająca materiały zapewnia prawa do ich wykorzystania i zgodność udzielonego upoważnienia z celem projektu. W razie uzasadnionych wątpliwości strony wyjaśniają je przed publikacją materiału.\n4. Opóźnienie w przekazaniu potrzebnych materiałów lub decyzji wymaga wskazania jego rzeczywistego wpływu na harmonogram oraz uzgodnienia aktualizacji terminu. Wykonawca informuje o przeszkodzie bez zbędnej zwłoki.\nPOUFNOŚĆ I DANE\n5. Strony chronią niepubliczne informacje uzyskane przy realizacji, udostępniając je tylko osobom potrzebnym do wykonania umowy, zobowiązanym do poufności, albo gdy wymaga tego prawo. Obowiązek nie dotyczy informacji publicznych lub legalnie znanych wcześniej i trwa także po zakończeniu współpracy.\n6. Każda ze stron chroni dane osobowe w zakresie swojej roli. Jeżeli Wykonawca przetwarza dane w imieniu Zamawiającego, przed rozpoczęciem przetwarzania strony zawierają odrębną umowę powierzenia zgodną z art. 28 RODO, określającą zakres, zabezpieczenia, podwykonawców, pomoc i zwrot lub usunięcie danych.\nZAKOŃCZENIE I SPORY\n7. W razie istotnego naruszenia druga strona wzywa do jego usunięcia w odpowiednim dodatkowym terminie, jeżeli przepisy nie przewidują innego trybu. Odstąpienie lub rozwiązanie i rozliczenie wykonanych prac następują z uwzględnieniem przepisów właściwych dla danego zobowiązania. Zaliczka nie jest automatycznie bezzwrotna; strony rozliczają należne świadczenia i zwracają nadpłatę.\n8. Umowa podlega prawu polskiemu. Spory strony najpierw próbują wyjaśnić polubownie; właściwość sądu wynika z przepisów. Zmiany praw autorskich wymagają właściwej formy określonej w części dotyczącej praw. Pozostałe uzgodnienia są utrwalane w sposób pozwalający ustalić treść i strony uzgodnienia.\n9. Jeżeli Zamawiający jest konsumentem lub przedsiębiorcą objętym ochroną konsumencką, umowa nie ogranicza jego obowiązkowych praw, w tym reklamacji i odstąpienia, gdy przysługują. Ustawowe obowiązki dotyczące zgodności treści lub usług cyfrowych, niezbędnych aktualizacji i rozpatrywania reklamacji pozostają niezależne od odpłatnego utrzymania. Przed zawarciem umowy otrzymuje wymagane informacje i właściwe pouczenia. Akceptacja oferty lub podglądu nie oznacza zgody na utratę prawa odstąpienia.",
    ];
}
function contractCatalogTemplate(string $id): array {
    $catalog=contractCatalog();
    if(!isset($catalog[$id])) throw new InvalidArgumentException('Nieznany wzór umowy.');
    $db=sessionDb();
    $db->exec('CREATE TABLE IF NOT EXISTS contract_template_catalog (id TEXT PRIMARY KEY, data TEXT NOT NULL)');
    $stmt=$db->prepare('SELECT data FROM contract_template_catalog WHERE id=:id'); $stmt->execute([':id'=>$id]);
    $json=$stmt->fetchColumn();
    return array_replace(contractTemplateDefaults(), contractStandardClauses($id), $json ? json_decode($json,true,512,JSON_THROW_ON_ERROR) : [], ['id'=>$id,'name'=>$catalog[$id]['name'],'title'=>$catalog[$id]['title'],'revision'=>$catalog[$id]['revision']]);
}
function contractTemplateFields(): array { return ['deploymentTerms','acceptance','ip','exclusions','support','extras','terms']; }
function contractTemplateProposal(array $template, array $facts): array {
    // Conditional annexes have separate required facts and generation gates;
    // completing them must not require removing editorial markers from clauses.
    return array_intersect_key($template,array_flip(contractTemplateFields()));
}
/** Fictional example: never reads client cases or provider credentials. */
function contractSampleContract(string $id,?array $template=null): array {
    $catalog=contractCatalog(); if(!isset($catalog[$id])) throw new InvalidArgumentException('Nieznany wzór umowy.');
    $template ??= array_replace(contractTemplateDefaults(),contractStandardClauses($id));
    $scope=match($id) {
        'website'=>"Strona firmowa typu one page: prezentacja firmy, oferta, realizacje i kontakt telefoniczny. Widok dostosowany do telefonu i komputera. Zamawiający dostarcza logo i zdjęcia; Wykonawca przygotowuje teksty na podstawie zaakceptowanego briefu.\nPrzekazanie obejmuje kod źródłowy, pliki strony, wykaz licencji i instrukcję uruchomienia. Stałe utrzymanie i zakup domeny nie są objęte zakresem.",
        'webapp'=>"Aplikacja do rejestracji zgłoszeń: logowanie, role użytkownik i administrator, lista zgłoszeń, historia statusów oraz eksport CSV. Zakres nie obejmuje migracji danych ani integracji niewymienionych w umowie.\nPrzekazanie obejmuje kod źródłowy, instrukcję wdrożenia, opis konfiguracji oraz scenariusze odbioru. Testy są wykonywane na danych syntetycznych, bez danych osobowych klienta.",
        'ecommerce'=>"Sklep internetowy: katalog do 50 produktów, koszyk, zamówienia oraz jedna uzgodniona integracja płatności i dostawy. Zamawiający dostarcza dane produktów, zdjęcia i zatwierdzone dokumenty sprzedaży.\nPrzekazanie obejmuje kod, instrukcję obsługi i konfiguracji oraz wykaz licencji. Konta operatorów i opłaty transakcyjne pozostają po stronie Zamawiającego. Testy wykorzystują dane syntetyczne.",
    };
    $contract=array_replace($template,[
        'number'=>'WZOR-'.strtoupper($id).'-01','version'=>1,'templateName'=>$catalog[$id]['title'],
        'provider'=>"Przykładowe Studio Cyfrowe sp. z o.o.\nAdres: ul. Przykładowa 1, 00-001 Warszawa\nReprezentacja: osoba uprawniona zgodnie z rejestrem\nE-mail: studio@example.test",
        'party'=>"Przykładowy Zamawiający sp. z o.o.\nDane w tym dokumencie są fikcyjne; przykład służy ocenie wzoru i wyglądu.",
        'scope'=>"Zamawiający zleca, a Wykonawca zobowiązuje się wykonać i przekazać projekt w następującym zakresie:\n".$scope,'price'=>"6 000,00 zł netto + VAT 23%, tj. 7 380,00 zł brutto. Cena obejmuje wykonanie uzgodnionego zakresu i wynagrodzenie za prawa wskazane w umowie.",
        'deposit'=>"Zaliczka: 30% ceny brutto, tj. 2 214,00 zł, przed rozpoczęciem prac. Pozostałe 5 166,00 zł po potwierdzonym odbiorze. Termin płatności: 7 dni od doręczenia faktury.",
        'paymentDetails'=>'Przelew na rachunek wskazany na fakturze. Wszystkie kwoty i terminy są przykładowe.',
        'deadline'=>"Rozpoczęcie po zawarciu umowy, wpłacie zaliczki i przekazaniu uzgodnionych materiałów. Realizacja: 20 dni roboczych od spełnienia tych warunków; projekt graficzny w pierwszych 5 dniach, następnie budowa, kontrola jakości i odbiór. Zmiany terminu wymagają udokumentowania przyczyny i uzgodnienia stron.",
        'facts'=>['clientType'=>'business','publicationDestination'=>'agency','ipMode'=>'transfer','rightsTerms'=>$template['defaultTransferTerms'],'ipPayment'=>$template['defaultIpPayment'],'signing'=>'paper','dataRole'=>'none','clientAddress'=>'ul. Testowa 2, 00-001 Warszawa','clientRepresentative'=>'Przykładowy przedstawiciel zgodnie z rejestrem','contractDate'=>'2026-10-03'],
    ]);
    $contract['facts']=array_replace($contract['facts'],contractSamplePackageFacts());
    $contract['facts']['acceptanceCriteria']=$scope;
    if($id==='webapp') $contract['facts']['dataRole']='processor';
    if($id==='ecommerce') {
        $contract['party']='Przykładowa Anna Testowa, prowadząca działalność gospodarczą. Dane fikcyjne.';
        $contract['facts']['clientType']='protected';
        $contract['facts']['legalStatusBasis']='Przykład JDG: zakup sklepu poza zawodowym charakterem działalności, do sprawdzenia względem rzeczywistych okoliczności.';
    }
    $contract['package']=contractPackageBuild($contract);
    return $contract;
}
/** Fictional values exclusively for examples and isolated tests, never project defaults. */
function contractSamplePackageFacts(): array {
    return [
        'obligationKind'=>'result','legalStatusBasis'=>'Przykład: spółka kapitałowa reprezentowana zgodnie z rejestrem; zakup na potrzeby przedsiębiorstwa.',
        'acceptanceCriteria'=>'Każdy uzgodniony widok działa na telefonie i komputerze. Linki kontaktowe i nawigacja działają. Brak błędów blokujących uzgodnione funkcje. Raport testów i instrukcja uruchomienia są przekazane.',
        'acceptanceDays'=>'7','cooperationTerms'=>'Przykład: klient przekazuje logo i zdjęcia przez prywatny formularz materiałów w ciągu 5 dni od zawarcia umowy. Wykonawca przygotowuje teksty w ciągu 3 dni od otrzymania materiałów. Przy brakach strony uzgadniają nowy termin po udokumentowaniu wpływu.',
        'rightsInventory'=>json_encode([['name'=>'Indywidualny układ i teksty','origin'=>'own','author'=>'Fikcyjny autor przykładu','license'=>'Przeniesienie na zasadach umowy; wynagrodzenie w cenie','rightsBasis'=>'Przykładowe własne autorstwo; w realnym projekcie wymagana weryfikacja praw','maintenanceRights'=>'Klient może zlecać modyfikację i utrzymanie innemu zespołowi na nabytych polach eksploatacji']],JSON_UNESCAPED_UNICODE),
        'privacyRecipients'=>'Przykład: upoważniona obsługa studia, dostawca poczty w Polsce, księgowość; brak transferów poza EOG. Rzeczywistych dostawców trzeba wskazać i sprawdzić przed użyciem.',
        'privacyRetention'=>'Przykład: dokumenty rozliczeniowe przez ustawowy okres właściwy dla obowiązków podatkowych; pozostałe dane umowne do upływu właściwego terminu przedawnienia roszczeń, bez zbędnych kopii. Dane reprezentanta otrzymano od zamawiającego w toku ustaleń.',
        'hostingFee'=>'Przykład: hosting przez 30 dni bez dodatkowej opłaty w cenie projektu. Dalsze utrzymanie nie jest zamówione; wymaga odrębnego uzgodnienia ceny i zakresu.',
        'hostingPeriod'=>'Przykład: 30 dni od publikacji, bez automatycznego płatnego odnowienia. Powiadomienie o końcu okresu 7 dni wcześniej. Zmiana ceny wymaga odrębnej zgody.',
        'hostingBackup'=>'Przykład: kopia codziennie, retencja 7 dni, pliki i baza; test odtworzenia przed publikacją. Odtworzenie w ciągu 2 dni roboczych od zgłoszenia. Wykonawca obsługuje kopie; klient zachowuje przekazany pakiet.',
        'hostingExit'=>'Przykład: eksport kodu, plików i bazy w otwartych formatach do 7 dni, bez dopłaty; odbiór przed końcem hostingu. Usunięcie danych 14 dni po zakończeniu, z uwzględnieniem retencji kopii i obowiązków prawnych.',
        'hostingDns'=>'Przykład: infrastruktura studia w Polsce; wykonawca obsługuje DNS i TLS na własnej subdomenie. Domenę klienta i dostęp do jej DNS uzgadnia się odrębnie. Awarie zgłaszane na kontakt wykonawcy.',
        'consumerChannel'=>'distance','consumerPerformance'=>'mixed','consumerTechnical'=>'Przykład: aktualne przeglądarki Chrome, Firefox i Safari, dostęp do internetu. Zakres obejmuje responsywny interfejs i eksport kodu. Aktualizacje wymagane do zachowania zgodności są zapewniane przez okres wymagany ustawą. Reklamacje na e-mail i adres wykonawcy, z opisem problemu; nie wymaga się szczególnego formularza.',
        'processingPurpose'=>'Przykład: utrzymanie formularza zgłoszeń, zapis, dostęp upoważnionych osób, eksport i usuwanie na instrukcję klienta.',
        'processingDuration'=>'Przykład: wyłącznie uzgodniony okres utrzymania; środowisko testowe zawiera dane syntetyczne.',
        'processingCategories'=>'Przykład: użytkownicy formularza; imię, służbowy e-mail, treść zgłoszenia i czas. Brak danych szczególnych kategorii.',
        'processingLocations'=>'Przykład: Polska; transfer poza EOG zabroniony bez odrębnej instrukcji i weryfikacji podstawy z rozdziału V RODO.',
        'processingSubprocessors'=>'[]','processingSecurity'=>'Przykład: imienne konta z MFA dla administracji, TLS, ograniczenie uprawnień, szyfrowane kopie, oddzielenie środowisk, ewidencja dostępu i kwartalna kontrola odtworzenia.',
        'processingReturn'=>'Przykład: administrator wybiera zwrot CSV/SQL i usunięcie albo samo usunięcie; termin 7 dni od instrukcji, kopie wygasają po 7 dniach, potwierdzenie pisemne. Zachowanie tylko danych wymaganych przepisami, z ograniczeniem dostępu.',
        'processingIncident'=>'Przykład: kontakt administratora privacy@example.test; pierwsze powiadomienie bez zbędnej zwłoki, nie później niż 24 godziny od stwierdzenia naruszenia.',
    ];
}
function contractCatalogSettings(): string {
    $id=(string)($_GET['template']??'website');
    if($id!=='legacy'&&!isset(contractCatalog()[$id])) $id='website';
    $template=$id==='legacy'?contractTemplate():contractCatalogTemplate($id); $e='contractEscape';
    $html='<section class="panel"><h2>Biblioteka wzorów umów</h2><p class="muted">Wzór jest dobierany z zaakceptowanego zakresu projektu. Możesz wybrać inny w formularzu umowy. Każdy typ ma własne klauzule i historię wersji; zapisane umowy zachowują swoją treść. Wzory są projektami do zatwierdzenia, nie opinią prawną.</p><nav class="nav">';
    foreach(contractCatalog() as $key=>$item) $html.='<a'.($id===$key?' class="active" aria-current="page"':'').' href="?view=contract-settings&amp;template='.$e($key).'">'.$e($item['name']).'</a>';
    $html.='<a'.($id==='legacy'?' class="active"':'').' href="?view=contract-settings&amp;template=legacy">Wcześniejszy wzór</a></nav>';
    $html.='<h3>'.$e($template['title']??'Wcześniejszy wzór wspólny').'</h3><p class="muted">'.($id!=='legacy'?$e(contractCatalog()[$id]['description']).' Rewizja biblioteki '.(int)$template['revision'].'. ':'').'Wersja ustawień: '.(int)$template['version'].'.</p><p>PDF: format A4, czytelna typografia, paragrafy, numeracja stron i podpisy. Dla klienta objętego ochroną konsumencką i powierzenia danych formularz wymaga uzupełnienia odpowiednich załączników.</p>';
    if($id!=='legacy') $html.='<a class="button" href="'.$e(apiPath('contract.php')).'?templatePreview='.$e($id).'">Pobierz przykład PDF — fikcyjne dane</a>';
    $html.='<form class="contract-form" method="post" action="'.apiPath('contract.php').'"><input type="hidden" name="action" value="save-template"><input type="hidden" name="templateId" value="'.$e($id).'"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="expectedVersion" value="'.(int)$template['version'].'">';
    foreach(contractTemplateFields() as $key) $html.='<label>'.$e(contractFields()[$key]).'<textarea rows="8" maxlength="20000" name="'.$e($key).'">'.$e($template[$key]).'</textarea></label>';
    foreach(['defaultTransferTerms'=>'Moment przeniesienia praw','defaultIpPayment'=>'Wynagrodzenie za prawa'] as $key=>$label) $html.='<label>'.$e($label).'<textarea rows="3" maxlength="2000" required name="'.$e($key).'">'.$e($template[$key]).'</textarea></label>';
    $html.=(isset($_GET['saved'])?'<p role="status">Wzór został zapisany.</p>':'').'<button type="submit" class="button">Zapisz wybrany wzór</button></form><details style="margin-top:20px"><summary>Źródła i zasady opracowania</summary><p>Własne klauzule; publiczne wzory posłużyły wyłącznie jako odniesienie dla struktury. Przed użyciem w działalności warto zatwierdzić bibliotekę z prawnikiem, zwłaszcza zasady praw autorskich i załączniki konsumenckie.</p><ul>';
    foreach([
        'https://bip.intibs.pl/uploads/files/migration/konkursy/2010/web_new_loks/wzor_umowy_o_wykonanie_serwisu_WWW_NEW_LOKS.pdf'=>'Publiczny wzór INTiBS PAN — układ umowy',
        'https://meetcosta.com/umowa'=>'Publiczny wzór wykonania strony — struktura',
        'https://api.sejm.gov.pl/eli/acts/DU/2026/795/text.pdf'=>'Kodeks cywilny — odpowiedzialność i wykonanie zobowiązań',
        'https://api.sejm.gov.pl/eli/acts/DU/2025/24/text.pdf'=>'Prawo autorskie — pola eksploatacji i forma umowy',
        'https://eli.gov.pl/api/acts/DU/2024/1796/text.html'=>'Ustawa o prawach konsumenta',
        'https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=pl'=>'RODO — umowa powierzenia',
    ] as $url=>$label) $html.='<li><a href="'.$e($url).'" target="_blank" rel="noopener noreferrer">'.$e($label).'</a></li>';
    return $html.'</ul><p class="muted">Opracowanie biblioteki: 03.10.2026. Zmiany przepisów i szczególne ryzyka projektu wymagają przeglądu wzoru.</p></details></section>';
}
