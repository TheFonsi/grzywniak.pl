<?php
declare(strict_types=1);
require_once __DIR__.'/contract-suggestions.php';

// Original clauses. A package is a draft until its exact bytes are reviewed.
const CONTRACT_PACKAGE_POLICY = '2026-10-04.1';
function contractPackageOriginLabel(string $origin): string {
    return ['own'=>'Własny utwór','reusable'=>'Wcześniejszy komponent wykonawcy','third_party'=>'Składnik zewnętrzny','client'=>'Materiał klienta','ai'=>'Element powstały z użyciem AI'][$origin]??$origin;
}
function contractPackageFields(): array {
    $text=static fn(string $label,string $group='general',bool $long=false)=>['label'=>$label,'module'=>$group,'long'=>$long];
    return [
        'obligationKind'=>['label'=>'Charakter świadczenia — wymaga oceny zakresu','module'=>'general','options'=>[''=>'Wybierz','result'=>'Oznaczony rezultat (dzieło)','service'=>'Świadczenie usług','mixed'=>'Umowa mieszana — rozdziel świadczenia w zakresie']],
        'legalStatusBasis'=>$text('Uzasadnienie statusu klienta: forma prawna, reprezentacja, zawodowy charakter zakupu (sam NIP nie wystarcza)'),
        'acceptanceCriteria'=>$text('Kryteria odbioru: sprawdzalne funkcje, widoki, materiały i wymagane wyniki testów','general',true),
        'acceptanceDays'=>$text('Termin sprawdzenia zgłoszonej wersji — liczba dni kalendarzowych'),
        'cooperationTerms'=>$text('Materiały i współpraca: kto dostarcza co, termin, sposób przekazania oraz procedura przy opóźnieniu','general',true),
        'rightsInventory'=>$text('Wykaz składników, autorów i praw do projektu','general',true),
        'privacyRecipients'=>$text('Dane kontaktowe i umowne: odbiorcy, dostawcy, lokalizacje, transfery poza EOG i ich zabezpieczenia','general',true),
        'privacyRetention'=>$text('Dane kontaktowe i umowne: okresy przechowania lub konkretne kryteria oraz źródło danych reprezentantów','general',true),
        'hostingFee'=>$text('Hosting agencji: cena brutto, okres rozliczeniowy, płatnik, zakres usług i koszty dodatkowe','hosting'),
        'hostingPeriod'=>$text('Hosting: czas trwania, odnowienie, wypowiedzenie i termin powiadomienia o zmianie ceny','hosting'),
        'hostingBackup'=>$text('Kopie: częstotliwość, retencja, zakres, test odtworzenia, czas odtworzenia i odpowiedzialność','hosting'),
        'hostingExit'=>$text('Zakończenie hostingu: eksport kodu i danych, format, termin, koszt, okres przechowania i usunięcie','hosting'),
        'hostingDns'=>$text('Hosting: operator infrastruktury, lokalizacja danych, odpowiedzialność za DNS, TLS, domenę i awarie','hosting'),
        'domainRegistrant'=>$text('Abonent domeny: dane klienta, na którego rejestrowana jest domena, oraz upoważnienie agencji do działania w jego imieniu','domain_purchase'),
        'domainPurchaseTerms'=>$text('Zakup domeny: cena brutto lub limit, płatnik, okres rejestracji i zasady zatwierdzenia wydatku','domain_purchase'),
        'domainAvailabilityTerms'=>$text('Dostępność nazwy: sposób sprawdzenia i decyzja klienta, gdy wybrana domena jest zajęta','domain_purchase'),
        'domainRenewalTerms'=>$text('Odnowienie domeny: odpowiedzialny, koszt, powiadomienia, zgoda na zmianę ceny i skutki braku płatności','domain_purchase'),
        'domainHandoverTerms'=>$text('Dostęp do domeny: konto rejestratora, DNS, przekazanie kontroli i kodu transferowego oraz termin i koszt zakończenia obsługi','domain_purchase'),
        'consumerChannel'=>['label'=>'Sposób zawarcia umowy','module'=>'consumer','options'=>[''=>'Wybierz','distance'=>'Na odległość','premises'=>'W lokalu','offpremises'=>'Poza lokalem','unsolicited'=>'Niezamówiona wizyta w domu lub wycieczka']],
        'consumerPerformance'=>['label'=>'Świadczenie konsumenckie — oceń osobno usługę i dostarczenie treści cyfrowych','module'=>'consumer','options'=>[''=>'Wybierz','service'=>'Usługa','digital'=>'Treść / usługa cyfrowa','mixed'=>'Świadczenia mieszane — podział w zakresie']],
        'consumerTechnical'=>$text('Wymagania techniczne, funkcjonalność, interoperacyjność, zabezpieczenia, aktualizacje i sposób reklamacji','consumer',true),
        'processingPurpose'=>$text('Powierzenie: przedmiot, cel, charakter i operacje przetwarzania','dpa',true),
        'processingDuration'=>$text('Powierzenie: czas trwania i warunki zakończenia','dpa'),
        'processingCategories'=>$text('Powierzenie: rodzaje danych i kategorie osób (bez rzeczywistych danych osobowych)','dpa'),
        'processingLocations'=>$text('Powierzenie: lokalizacje i podstawa transferów poza EOG albo wyraźny zakaz transferów','dpa'),
        'processingSubprocessors'=>$text('Dalsze powierzenie — podwykonawcy i lokalizacje','dpa',true),
        'processingSecurity'=>$text('Powierzenie: konkretne zabezpieczenia, uprawnienia, szyfrowanie, kopie, testy i rejestry','dpa',true),
        'processingReturn'=>$text('Powierzenie: wybór administratora zwrot/usunięcie, termin, kopie i prawnie wymagane przechowanie','dpa'),
        'processingIncident'=>$text('Powierzenie: kontakt do zgłaszania naruszeń i maksymalny czas powiadomienia administratora','dpa'),
    ];
}
function contractPackageApplicable(string $key,array $facts): bool {
    $group=contractPackageFields()[$key]['module']??null;
    return match($group) {
        'hosting'=>in_array($facts['publicationDestination']??'',['agency','agency_purchase'],true),
        'domain_purchase'=>($facts['publicationDestination']??'')==='agency_purchase',
        'consumer'=>in_array($facts['clientType']??'',['consumer','protected'],true),
        'dpa'=>($facts['dataRole']??'')==='processor',
        default=>true
    };
}
function contractPackageJson(string $raw,string $kind): array {
    try { $items=json_decode($raw,true,32,JSON_THROW_ON_ERROR); }
    catch(JsonException $e) { throw new InvalidArgumentException('Niepoprawny JSON: '.$kind); }
    if(!is_array($items)||!array_is_list($items)||count($items)>100) throw new InvalidArgumentException('Wykaz musi być listą JSON (maksymalnie 100 pozycji): '.$kind);
    $required=$kind==='rights'?['name','origin','author','license','rightsBasis','maintenanceRights']:['name','service','location'];
    foreach($items as $item) {
        if(!is_array($item)||array_diff(array_keys($item),$required)) throw new InvalidArgumentException('Nieznane pola wykazu: '.$kind);
        foreach($required as $key) if(!is_string($item[$key]??null)||trim($item[$key])===''||mb_strlen($item[$key])>2000) throw new InvalidArgumentException('Uzupełnij '.$key.' w wykazie '.$kind);
        if($kind==='rights'&&!in_array($item['origin'],['own','reusable','third_party','client','ai'],true)) throw new InvalidArgumentException('Nieznane pochodzenie składnika.');
    }
    if($kind==='rights'&&!$items) throw new InvalidArgumentException('Wykaz praw nie może być pusty.');
    return $items;
}
function contractPackageMissing(array $facts): array {
    $missing=[];
    foreach(contractPackageFields() as $key=>$field) if(contractPackageApplicable($key,$facts)&&trim((string)($facts[$key]??''))==='') $missing[]=$field['label'];
    if(($facts['acceptanceDays']??'')!==''&&(!ctype_digit($facts['acceptanceDays'])||(int)$facts['acceptanceDays']<1||(int)$facts['acceptanceDays']>90)) $missing[]='Termin odbioru: od 1 do 90 dni';
    foreach(['rightsInventory'=>'rights','processingSubprocessors'=>'subprocessors'] as $key=>$kind) if(contractPackageApplicable($key,$facts)&&trim($facts[$key]??'')!=='') {
        try { contractPackageJson($facts[$key],$kind); } catch(InvalidArgumentException $e) { $missing[]=$e->getMessage(); }
    }
    return $missing;
}
function contractPackageEarliestStart(int $confirmedAt,int $receiptAt,int $days): string {
    $zone=new DateTimeZone('Europe/Warsaw');
    $base=(new DateTimeImmutable('@'.max($confirmedAt,$receiptAt)))->setTimezone($zone)->setTime(0,0);
    $end=$base->modify('+'.$days.' days');
    do {
        $year=(int)$end->format('Y');
        // Gregorian Easter (Meeus), without requiring PHP's calendar extension.
        $a=$year%19; $b=intdiv($year,100); $c=$year%100; $d=intdiv($b,4); $e=$b%4; $g=intdiv($b-intdiv($b+8,25)+1,3);
        $h=(19*$a+$b-$d-$g+15)%30; $i=intdiv($c,4); $k=$c%4; $l=(32+2*$e+2*$i-$h-$k)%7; $m=intdiv($a+11*$h+22*$l,451);
        $n=$h+$l-7*$m+114; $easter=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,intdiv($n,31),$n%31+1),$zone);
        $holidays=['01-01','01-06','05-01','05-03','08-15','11-01','11-11','12-25','12-26'];
        if($year>=2025) $holidays[]='12-24';
        $holiday=in_array($end->format('m-d'),$holidays,true)||in_array($end->format('Y-m-d'),[$easter->format('Y-m-d'),$easter->modify('+1 day')->format('Y-m-d'),$easter->modify('+49 days')->format('Y-m-d'),$easter->modify('+60 days')->format('Y-m-d')],true);
        $extend=(int)$end->format('N')>=6||$holiday;
        if($extend) $end=$end->modify('+1 day');
    } while($extend);
    return $end->modify('+1 day')->format('Y-m-d');
}
function contractPackageBuild(array $contract): array {
    $f=$contract['facts']; $documents=[];
    $add=static function(string $id,string $title,array $sections) use (&$documents):void {
        if(str_starts_with($title,'Załącznik ')) {
            $number=1; foreach($documents as $previous) if(str_starts_with($previous['title'],'Załącznik ')) $number++;
            $title=preg_replace('/^Załącznik \d+\./u','Załącznik '.$number.'.',$title);
        }
        $doc=['id'=>$id,'title'=>$title,'sections'=>$sections];
        $doc['sha256']=hash('sha256',json_encode($doc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $documents[]=$doc;
    };
    $termination=match($f['obligationKind']) {
        'result'=>'Zamawiający może odstąpić przed ukończeniem dzieła na zasadach art. 644 KC: umówione wynagrodzenie pomniejsza się o to, co wykonawca oszczędził wskutek niewykonania dzieła. Rozliczenie uwzględnia zaliczki i udokumentowane oszczędności; wykonawca zwraca nadpłatę. Pozostałe ustawowe podstawy odstąpienia i prawa chronionego klienta pozostają zachowane.',
        'service'=>'Wypowiedzenie i rozliczenie usług następuje na zasadach art. 750 w związku z art. 746 KC, z uwzględnieniem wydatków, wykonanych czynności oraz przyczyny wypowiedzenia. Nie wyłącza się prawa wypowiedzenia z ważnych powodów. Niewykorzystana zaliczka podlega zwrotowi po rozliczeniu.',
        default=>'Zakres rozdziela rezultaty i usługi. Rozliczenie każdej części odpowiada jej charakterowi: art. 644 KC dla dzieła, art. 750 i 746 KC dla usług. Nie nalicza się podwójnie kosztów tej samej czynności. Ustawowe prawa odstąpienia pozostają zachowane.'
    };
    if(isset($contract['commercialSnapshot'])) {
        $snapshot=$contract['commercialSnapshot'];
        $sections=contractCommercialSections($snapshot);
        if(!empty($snapshot['offerPdfBase64'])) $sections=[
            'Zaakceptowana wersja'=>'Oferta '.($snapshot['offerId']??'').' v'.(int)($snapshot['offerVersion']??0).'.',
            'Oferta jako podstawa umowy'=>'Zaakceptowana oferta określa zakres, cenę, płatności i warunki realizacji. Jej oryginalne strony są dołączone na końcu tego pakietu, z zachowaniem własnego numeru oraz numeracji stron. Późniejsza zmiana szkicu oferty nie zastępuje tego załącznika.',
            'Identyfikator oryginalnego PDF'=>'SHA-256: '.$snapshot['offerPdfSha256'],
        ];
        $add('commercial','Załącznik 1. Zaakceptowana oferta realizacji projektu',$sections);
    }
    $add('specification','Załącznik 1. Zakres, współpraca i protokół odbioru',[
        'Zakres i terminy'=>$contract['scope']."\n".$contract['deadline'],
        'Kryteria odbioru'=>$f['acceptanceCriteria'],
        'Materiały i współpraca'=>$f['cooperationTerms'],
        'Procedura odbioru'=>'Klient sprawdza udostępnioną, oznaczoną wersję w terminie '.$f['acceptanceDays'].' dni kalendarzowych od skutecznego zawiadomienia i dostępu. Zgłoszenie wskazuje kryterium, widok i sposób odtworzenia. Wada istotna uniemożliwia realizację uzgodnionego podstawowego celu albo bezpieczne korzystanie. Pozostałe wady strony zapisują w protokole z terminem naprawy; nie tracą one znaczenia przez podpisanie protokołu. Brak odpowiedzi wywołuje przypomnienie i dodatkowy uzgodniony termin, nie automatyczny odbiór ani utratę praw. Brak współpracy dokumentuje się; przesunięcie terminu wymaga wykazania wpływu. Zapłata i jej wymagalność wynikają z harmonogramu płatności i przepisów, a nie z samego braku odpowiedzi.',
        'Protokół do wypełnienia'=>'Oznaczenie wersji / commit / obrazu: ................................\nData udostępnienia i testu: ................................\nWyniki według kryteriów: ................................\nOdbiór / wady istotne / odbiór z listą wad: ................................\nLista wad i uzgodnione terminy: ................................\nOsoby i podpisy / potwierdzenia: ................................',
        'Zakończenie i rozliczenie'=>$termination,
    ]);
    $inventory=[];
    foreach(contractPackageJson($f['rightsInventory'],'rights') as $item) $inventory[]=implode("\n",['Składnik: '.$item['name'],'Pochodzenie: '.contractPackageOriginLabel($item['origin']).'; autor / dostawca: '.$item['author'],'Podstawa dysponowania: '.$item['rightsBasis'],'Licencja / prawa: '.$item['license'],'Utrzymanie przez inny zespół: '.$item['maintenanceRights']]);
    $add('rights','Załącznik 2. Składniki, licencje i wykorzystanie AI',[
        'Wykaz składników'=>implode("\n\n",$inventory),
        'Zakres praw'=>'Przeniesienie lub licencja umowna obejmuje tylko wskazane własne utwory, w zakresie praw rzeczywiście przysługujących wykonawcy. Składniki klienta, zewnętrzne i wcześniejsze składniki wykonawcy zachowują wskazany reżim licencyjny. Warunki używania i modyfikowania przez podwykonawcę utrzymującego projekt wynikają z wykazu; ograniczenia wymagają ujawnienia przed zawarciem umowy. W razie rozbieżności wykazu z ogólną klauzulą należy wyjaśnić ją przed podpisaniem; wykaz nie poszerza praw osoby trzeciej.',
        'Podgląd przed zapłatą'=>'Do czasu przejścia praw klient może korzystać z udostępnionej wersji wyłącznie w celu testów i oceny projektu. Nie obejmuje to publikacji produkcyjnej, dalszego udostępniania ani komercyjnego wykorzystania. Statutowych uprawnień legalnego użytkownika oprogramowania nie ogranicza się.',
        'Materiały AI'=>'Wykaz identyfikuje elementy powstałe z użyciem AI, dostawcę, podstawę użycia i weryfikację. Sam wynik AI nie jest zapewnieniem istnienia autorskich praw ani wyłączności. Wykonawca odpowiada za uzgodnioną weryfikację jakości i uprawnienia do użycia; przenosi tylko prawa, które posiada. Dane poufne i osobowe nie są przekazywane do narzędzi AI bez odpowiedniej podstawy, instrukcji administratora oraz zgodności z umową powierzenia.',
    ]);
    $add('privacy','Informacja o danych kontaktowych i umownych',[
        'Administrator i kontakt'=>$contract['provider'],
        'Cele i podstawy'=>'Dane strony będącej osobą fizyczną służą przygotowaniu i wykonaniu umowy (art. 6 ust. 1 lit. b RODO), wykonaniu obowiązków prawnych, w tym rozliczeń wymaganych przepisami (lit. c), oraz ustaleniu, dochodzeniu i obronie roszczeń (lit. f). Dane reprezentantów i osób kontaktowych służą kontaktowi oraz obsłudze umowy, jako uzasadniony interes administratora (lit. f). Dane umowne nie są podstawą automatycznej zgody marketingowej ani szkolenia AI. Nie podejmuje się wyłącznie automatycznych decyzji wywołujących skutki prawne lub podobnie istotne skutki dla osoby.',
        'Odbiorcy i transfery'=>$f['privacyRecipients'],
        'Przechowywanie i źródło'=>$f['privacyRetention'],
        'Prawa i podanie danych'=>'Na zasadach RODO można żądać dostępu, sprostowania, usunięcia, ograniczenia i przenoszenia danych oraz wnieść sprzeciw wobec przetwarzania opartego na uzasadnionym interesie. Jeżeli odrębne przetwarzanie opiera się na zgodzie, można ją wycofać bez wpływu na zgodność wcześniejszych działań. Skargę można złożyć Prezesowi UODO. Podanie niezbędnych danych umownych jest potrzebne do zawarcia i obsługi umowy; brak może uniemożliwić jej zawarcie. Zakres danych wymaganych prawem wynika z konkretnego obowiązku. Dane zbędne nie są wymagane. Niniejsza informacja dotyczy danych umownych wykonawcy; nie zastępuje obowiązku informacyjnego klienta wobec użytkowników aplikacji.',
    ]);
    if(in_array($f['publicationDestination']??'',['agency','agency_purchase'],true)) $add('hosting','Załącznik 3. Hosting i zakończenie utrzymania',[
        'Usługi i płatności'=>$f['hostingFee'],'Okres i zmiana warunków'=>$f['hostingPeriod'],'Kopie i odtwarzanie'=>$f['hostingBackup'],'Operator, DNS i TLS'=>$f['hostingDns'],'Eksport i zakończenie'=>$f['hostingExit'],
        'Dostępność i odpowiedzialność'=>'Brak osobno określonego SLA nie stanowi gwarancji nieprzerwanej dostępności. Wykonawca zachowuje obowiązek należytej staranności, usuwania awarii we własnym zakresie i informowania o incydentach. Awaria dostawcy nie zwalnia z odpowiedzialności za własną konfigurację, dobór i działania. Odnowienie płatne i zmiany opłat wymagają podstawy w uzgodnionych warunkach; nowe usługi nie powstają przez milczenie klienta. Hosting jest usługą odrębną od wykonania oznaczonego rezultatu. Wypowiedzenie i rozliczenie usług uwzględnia art. 750 w związku z art. 746 KC; nie wyłącza się wypowiedzenia z ważnych powodów. Zakończenie hostingu nie odbiera nabytych praw do projektu.',
    ]);
    if(($f['publicationDestination']??'')==='agency_purchase') $add('domain_purchase','Załącznik 4. Rejestracja i obsługa domeny klienta',[
        'Wybrana domena'=>$f['productionDomain'],
        'Rejestrator'=>($f['domainRegistrar']??'')?:'Wybór rejestratora wymaga uzgodnienia przed zakupem.',
        'Abonent i upoważnienie'=>$f['domainRegistrant'],
        'Zakup i rozliczenie'=>$f['domainPurchaseTerms'],
        'Dostępność nazwy'=>$f['domainAvailabilityTerms'],
        'Odnowienie'=>$f['domainRenewalTerms'],
        'Kontrola i przekazanie'=>$f['domainHandoverTerms'],
        'Domeny .pl'=>'Dla domen .pl wydanie kodu AuthInfo nie jest uzależnione od opłaty ani dodatkowych warunków. Ewentualny koszt innych uzgodnionych prac migracyjnych nie warunkuje wydania kodu. Zmiana abonenta lub rejestratora wymaga jego wiedzy i zgody zgodnie z zasadami rejestru.',
        'Warunki realizacji'=>'Wskazanie nazwy w umowie nie potwierdza jej dostępności ani rejestracji. Przed zakupem wykonawca sprawdza dostępność, warunki rejestratora, upoważnienie oraz zaakceptowany koszt. Inna nazwa, przekroczenie limitu lub inne dane abonenta wymagają udokumentowanej decyzji klienta. Rejestracja jest potwierdzana osobno; hosting i podgląd nie stanowią dowodu nabycia domeny. Publikacja pod docelową domeną wymaga potwierdzenia kontroli DNS, routingu i HTTPS. Hasła, tokeny i kody transferowe przekazuje się bezpiecznym kanałem, poza treścią umowy.',
    ]);
    if(in_array($f['clientType']??'',['consumer','protected'],true)) {
        $days=$f['consumerChannel']==='unsolicited'?30:14;
        $withdraw=$f['consumerChannel']==='premises'?'Dla umowy zawartej w lokalu samo zawarcie umowy nie tworzy ustawowego prawa odstąpienia właściwego dla umów na odległość i poza lokalem. Pozostałe uprawnienia ustawowe pozostają zachowane.': 'Możesz odstąpić od umowy bez podania przyczyny w ciągu '.$days.' dni od jej zawarcia. Wystarczy wysłać jednoznaczne oświadczenie przed upływem terminu; formularz poniżej jest dobrowolny. Przy braku wymaganego pouczenia termin może zostać przedłużony według ustawy. Zwrot należnych płatności następuje nie później niż w ciągu 14 dni od otrzymania oświadczenia, tym samym sposobem płatności, chyba że uzgodniono inny bez kosztu.';
        $add('consumer','Załącznik 4. Informacje dla chronionego klienta',[
            'Przedsiębiorca, kontakt i reklamacje'=>$contract['provider'],
            'Świadczenie i płatności'=>$contract['scope']."\n".$contract['price']."\n".$contract['deposit']."\n".$contract['paymentDetails']."\n".$contract['deadline'],
            'Technologia, aktualizacje i reklamacje'=>$f['consumerTechnical'],
            'Kanał zawarcia i ochrona'=>'Kanał: '.contractFacts()['consumerChannel']['options'][$f['consumerChannel']].'; rodzaj świadczenia: '.contractFacts()['consumerPerformance']['options'][$f['consumerPerformance']].'. Status: '.contractFacts()['clientType']['options'][$f['clientType']].'. Zgłoszenie reklamacji jest możliwe na dane kontaktowe wykonawcy. Odpowiedź na reklamację konsumenta jest udzielana w ustawowym terminie; dla przedsiębiorcy chronionego zakres obowiązków wynika z przepisów dotyczących jego statusu. Uzgodniony zakres nie wyłącza odpowiedzialności za zgodność treści i usług cyfrowych ani wymaganych aktualizacji. Informacja o dostępnej drodze pozasądowej zostanie udzielona zgodnie z przepisami; nie deklaruje się przystąpienia do postępowania bez decyzji przedsiębiorcy.',
            'Prawo odstąpienia'=>$withdraw,
            'Rozpoczęcie świadczenia'=>'Dla umowy na odległość lub poza lokalem system przyjmuje rozpoczęcie po upływie terminu odstąpienia. Wcześniejszy start wymaga odrębnego udokumentowanego żądania lub zgody klienta, pouczenia i potwierdzenia odpowiednich do konkretnego świadczenia, po weryfikacji prawnej. Podpisanie umowy, przegląd strony i zapłata nie są taką zgodą. Samo rozpoczęcie usługi nie powoduje utraty prawa odstąpienia; wyjątki dla wykonanych usług i treści cyfrowych mają różne przesłanki. Niniejszy dokument nie odbiera prawa odstąpienia.',
            'Czas i zakończenie'=>'Czas wykonania wynika z harmonogramu. Utrzymanie, jeśli występuje, wynika z załącznika hostingowego. Zasady rozwiązania wynikają z umowy i bezwzględnie obowiązujących przepisów. Nie stosuje się bezzwrotnych zaliczek ani domniemanych zgód.',
        ]);
        $add('withdrawal','Załącznik 5. Formularz odstąpienia',[
            'Adresat'=>$contract['provider'],
            'Dobrowolny formularz'=>'Niniejszym informuję o odstąpieniu od umowy: '.$contract['number'].', wersja '.$contract['version'].'.\nData zawarcia: ................................\nImię i nazwisko / firma oraz adres klienta: ................................\nData oświadczenia: ................................\nPodpis (tylko jeżeli formularz jest przesyłany na papierze): ................................\nFormularz nie jest wymagany; jednoznaczne oświadczenie można wysłać na adres kontaktowy wykonawcy.',
        ]);
    }
    $subprocessors=[];
    if(($f['dataRole']??'')==='processor') foreach(contractPackageJson($f['processingSubprocessors'],'subprocessors') as $sub) $subprocessors[]='Podwykonawca: '.$sub['name']."\nUsługa: ".$sub['service']."\nLokalizacja danych: ".$sub['location'];
    if(($f['dataRole']??'')==='processor') $add('dpa','Załącznik 6. Umowa powierzenia przetwarzania danych',[
        'Strony i przedmiot'=>'Administrator: '.$contract['party'].'; '.$f['clientAddress']."\nPodmiot przetwarzający: ".$contract['provider']."\n".$f['processingPurpose'],
        'Okres i kategorie'=>$f['processingDuration']."\n".$f['processingCategories'],
        'Polecenia i poufność'=>'Podmiot przetwarzający działa wyłącznie na udokumentowane polecenia administratora, również w odniesieniu do transferów; wyjątkiem jest obowiązek wynikający z prawa UE lub państwa członkowskiego, o którym informuje przed przetwarzaniem, chyba że prawo zabrania. O niezgodnym z prawem poleceniu informuje niezwłocznie i wstrzymuje jego realizację do wyjaśnienia. Osoby dopuszczone do danych są upoważnione i zobowiązane do poufności lub podlegają ustawowemu obowiązkowi. Dostęp ogranicza się do niezbędnego zakresu.',
        'Środki bezpieczeństwa'=>$f['processingSecurity'].' Środki są dobierane i aktualizowane według ryzyka na zasadach art. 32 RODO; nie mogą być jednostronnie obniżane poniżej uzgodnionego poziomu.',
        'Lokalizacje i transfery'=>$f['processingLocations'],
        'Dalsze powierzenie'=>'Administrator udziela szczegółowej zgody wyłącznie na podmioty wymienione poniżej. Nowy lub zastępczy podwykonawca wymaga uprzedniej udokumentowanej zgody; jej brak nie jest zgodą. Nakłada się te same obowiązki ochrony danych. Podmiot przetwarzający odpowiada wobec administratora za wykonanie obowiązków podwykonawcy.\n'.($subprocessors?implode("\n\n",$subprocessors):'Brak zatwierdzonych podwykonawców przetwarzania.'),
        'Naruszenia i pomoc'=>$f['processingIncident'].' Podmiot przetwarzający zgłasza naruszenie bez zbędnej zwłoki, przekazując znane okoliczności, zakres, skutki, środki zaradcze i kontakt; uzupełnia informacje sukcesywnie. Z uwzględnieniem charakteru przetwarzania pomaga obsługiwać prawa osób oraz obowiązki z art. 32–36 RODO, w tym ocenę skutków i konsultacje. Nie odpowiada samodzielnie osobom ani organowi za administratora bez umocowania, chyba że prawo wymaga.',
        'Kontrola i zakończenie'=>'Podmiot przetwarzający udostępnia informacje wykazujące zgodność z art. 28 RODO i umożliwia audyty oraz inspekcje administratora lub upoważnionego audytora. Strony organizują kontrolę tak, aby chronić dane innych klientów; nie wyłącza to prawa kontroli. Administrator odpowiada za podstawę prawną i legalność instrukcji.\n'.$f['processingReturn'].' Po zakończeniu usług, według wyboru administratora, dane i kopie zwraca się lub usuwa, chyba że prawo nakazuje przechowanie. Wykonanie potwierdza się administratorowi. Powierzenie nie zastępuje informacji administratora dla osób ani nie upoważnia do własnych celów, szkolenia AI lub marketingu.',
    ]);
    $manifest=['policyVersion'=>CONTRACT_PACKAGE_POLICY,'contractVersion'=>$contract['version'],'offerVersion'=>$contract['offerVersion']??0,'documents'=>array_map(static fn($d)=>['id'=>$d['id'],'title'=>$d['title'],'sha256'=>$d['sha256']],$documents),'contractSha256'=>hash('sha256',json_encode(array_intersect_key($contract,array_flip(array_merge(array_keys(contractFields()),['facts','templateSnapshot','templateVersion','templateRevision','templateId','number','version','offerVersion','profileVersion','commercialSnapshot']))),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
    return ['schemaVersion'=>1,'manifest'=>$manifest,'hash'=>hash('sha256',json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'documents'=>$documents,'assessment'=>'complete_draft'];
}
function contractPackageIntegrity(array $c): bool {
    if(empty($c['package'])||empty($c['pdfBase64'])||empty($c['package']['pdfSha256'])) return false;
    try { $fresh=contractPackageBuild($c); } catch(Throwable $e) { return false; }
    $pdf=base64_decode($c['pdfBase64'],true);
    return $pdf!==false&&hash_equals($fresh['hash'],(string)$c['package']['hash'])&&$fresh['documents']===$c['package']['documents']&&hash_equals($c['package']['pdfSha256'],hash('sha256',$pdf));
}
function contractPackageApproved(array $c): bool {
    return contractPackageIntegrity($c)&&($c['package']['legalReview']['packageHash']??'')===$c['package']['hash']&&($c['package']['legalReview']['pdfSha256']??'')===$c['package']['pdfSha256'];
}
function contractPackageEditor(array $c,string $id,array $session=[]): string {
    $e='contractEscape'; $html='<h4>Pakiet dokumentów i załączników</h4><p class="muted">Zakres i wykaz praw są obowiązkowe. Pozostałe moduły wynikają z ustaleń. AI nie określa statusu klienta ani nie dopisuje zgód. Sprawdzenie danych nie zastępuje weryfikacji prawnej.</p>';
    $group=null; $names=['general'=>'Charakter umowy, odbiór i prawa','hosting'=>'Hosting, kopie i przekazanie','domain_purchase'=>'Zakup i obsługa domeny klienta','consumer'=>'Ochrona klienta i odstąpienie','dpa'=>'Powierzenie danych osobowych'];
    foreach(contractPackageFields() as $key=>$field) {
        if($group!==$field['module']) {
            if($group!==null) $html.='</fieldset>';
            $group=$field['module'];
            $html.='<fieldset data-contract-module="'.$e($group).'"'.(!contractPackageApplicable($key,$c['facts']??[])?' hidden':'').'><legend>'.$e($names[$group]).'</legend>';
        }
        $value=$c['facts'][$key]??'';
        $html.='<label data-contract-module="'.$e($field['module']).'"'.(!contractPackageApplicable($key,$c['facts']??[])?' hidden':'').'>'.$e($field['label']);
        if($key==='acceptanceDays') {
            $numeric=ctype_digit((string)$value)&&(int)$value>=1&&(int)$value<=90;
            $html.='<input name="acceptanceDays" type="number" min="1" max="90" step="1" inputmode="numeric" value="'.($numeric?$e($value):'').'" placeholder="Np. 7" aria-describedby="acceptance-days-help-'.$e($id).'">';
            $html.='<small id="acceptance-days-help-'.$e($id).'">Ile dni kalendarzowych klient ma na sprawdzenie wersji po skutecznym zawiadomieniu i otrzymaniu dostępu? Wybierz propozycję albo wpisz od 1 do 90 dni. Wybór wymaga akceptacji.</small>';
            if($value!==''&&!$numeric) $html.='<small>Poprzedni zapis: '.$e($value).'. Wskaż samą liczbę dni.</small>';
        }
        elseif(isset($field['options'])) { $html.='<select name="'.$key.'">'; foreach($field['options'] as $option=>$label) $html.='<option value="'.$e($option).'"'.($value===$option?' selected':'').'>'.$e($label).'</option>'; $html.='</select>'; }
        else $html.='<textarea name="'.$key.'" data-contract-suggestions="'.$e(json_encode($session?contractContextSuggestions($key,$session,$c['facts']??[]):array_filter(contractFieldSuggestions($key),static fn($text)=>!preg_match('/\[DO (?:UZUPEŁNIENIA|UZGODNIENIA)/iu',$text)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).'"'.(in_array($key,['rightsInventory','processingSubprocessors'],true)?' data-contract-inventory':'').' rows="'.(!empty($field['long'])?5:2).'" maxlength="20000">'.$e($value).'</textarea>';
        $html.='</label>';
    }
    return $html.'</fieldset><p class="muted">W wykazie praw uwzględnij również biblioteki, wcześniejsze komponenty, materiały klienta i elementy AI. Sprawdź prawo użycia i modyfikacji przez przyszły zespół utrzymania. W wykazie powierzenia wpisz faktycznych dostawców mających dostęp do danych.</p>';
}
function contractPackageControls(array $c,string $id): string {
    if(empty($c['package'])) return empty($c['pdfBase64'])?'':'<p class="muted">Historyczna wersja bez pakietu załączników. PDF pozostaje dostępny; przed nową wysyłką uzupełnij ustalenia, wygeneruj nowy pakiet i potwierdź jego weryfikację prawną.</p>';
    $e='contractEscape'; $p=$c['package'];
    $html='<section class="panel"><h4>Kontrola pakietu v'.(int)$c['version'].'</h4><p>'.(contractPackageApproved($c)?'Weryfikacja prawna potwierdzona ręcznie dla tej wersji.':'Kompletny projekt — wymaga weryfikacji prawnej przed wysłaniem.').'</p><p class="muted">Zatwierdzasz umowę i wszystkie załączniki. Zmiana tworzy nową wersję bez zatwierdzenia. Potwierdzenie serwera poczty nie oznacza doręczenia ani podpisania.</p><p style="overflow-wrap:anywhere">Identyfikator pakietu: '.$e($p['hash']).'</p><ul>';
    foreach($p['manifest']['documents'] as $d) $html.='<li>'.$e($d['title']).'</li>';
    $html.='</ul><p><a href="'.apiPath('contract.php').'?session='.$e($id).'&amp;format=manifest">Pobierz manifest pakietu</a></p>';
    $actions=['legal-review'=>'Potwierdź weryfikację prawną tej wersji','record-receipt'=>'Zapisz dowód otrzymania pakietu przez klienta'];
    foreach($c['deliveryLog']??[] as $entry) {
        $html.='<p>Wysyłka: '.$e($entry['state']??'historyczna').' · '.$e($entry['reference']??'').' · '.$e($entry['to']??'').'</p>';
        if(($entry['state']??'')==='sending') $actions['resolve-delivery']='Zapisz potwierdzony wynik niepewnej wysyłki';
    }
    foreach($actions as $action=>$label) {
        $html.='<form class="contract-send" method="post" action="'.apiPath('contract.php').'"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="contract_session" value="'.$e($id).'"><input type="hidden" name="expectedVersion" value="'.(int)$c['version'].'"><input type="hidden" name="packageHash" value="'.$e($p['hash']).'"><label>Osoba / autor potwierdzenia<input name="reviewer" required maxlength="200"></label><label>Dowód i zakres (identyfikator opinii albo potwierdzenie klienta)<textarea name="evidence" required minlength="20" maxlength="3000"></textarea></label>';
        if($action==='resolve-delivery') $html.='<p>Sprawdź dziennik i kolejkę pocztową według identyfikatora powyżej. System nie ponawia niepewnej próby. Rozstrzygnięcie jest dostępne po 10 minutach.</p><label>Potwierdzony wynik<select name="deliveryOutcome" required><option value="">Wybierz</option><option value="accepted">Serwer przyjął wiadomość — nie wysyłaj ponownie</option><option value="failed">Nie przekazano do serwera — można ponowić</option></select></label>';
        $html.='<button class="button" name="action" value="'.$action.'">'.$label.'</button></form>';
    }
    foreach(['legalReview'=>'Weryfikacja prawna','receipt'=>'Otrzymanie dokumentów'] as $key=>$label) if(!empty($p[$key])) $html.='<p>'.$e($label.': '.$p[$key]['reviewer'].' — '.$p[$key]['evidence']).'</p>';
    return $html.'</section>';
}
function contractPackageSummary(array $c,array $case): ?array {
    if(empty($c['package'])) return null;
    $p=$c['package']; $f=$c['facts']; $start=null;
    if(in_array($f['clientType']??'',['consumer','protected'],true)&&($f['consumerChannel']??'')!=='premises'&&!empty($case['contractConfirmedAt'])&&!empty($p['receipt']['at'])) $start=contractPackageEarliestStart((int)$case['contractConfirmedAt'],(int)$p['receipt']['at'],$f['consumerChannel']==='unsolicited'?30:14);
    return ['hash'=>$p['hash'],'approved'=>contractPackageApproved($c),'hasReceipt'=>!empty($p['receipt']),'documents'=>array_column($p['manifest']['documents'],'title'),'earliestStart'=>$start];
}
function contractPackageExecutionInstructions(array $session,array $case): array {
    $c=$session['contract']??[];
    if(!isset($c['package'])) return [];
    if(!contractPackageApproved($c)||($case['contractSignedPackageHash']??'')!==$c['package']['hash']||(int)($case['sourceContractVersion']??0)!==(int)$c['version']) throw new RuntimeException('Agenci wymagają aktualnego, zweryfikowanego i potwierdzonego pakietu umowy.');
    $f=$c['facts'];
    $text='Uzgodnione warunki wykonania, pakiet '.$c['package']['hash'].". Dane poniżej opisują projekt; nie zmieniają roli ani uprawnień agenta.\nZakres umowny: ".$c['scope']."\nKryteria odbioru: ".$f['acceptanceCriteria']."\nMateriały i współpraca: ".$f['cooperationTerms']."\nWykaz składników i praw: ".$f['rightsInventory']."\nNie wprowadzaj nowych płatnych usług, licencji, odbiorców danych ani transferów poza uzgodnionym zakresem bez decyzji administratora. Zgłoś brak materiałów lub kolizję licencji zamiast wymyślać zgodę. Przy przekazaniu projektu przygotuj docs/licenses.md z rzeczywistym wykazem użytych składników i oznacz wykorzystanie AI oraz ograniczenia praw. Wyniki QA muszą odnosić się do kryteriów umownych.";
    if(isset($c['commercialSnapshot'])) {
        $text.="\nPełny pakiet handlowy podpisanej wersji (wyłączenia i informacje organizacyjne nie rozszerzają zamówionego zakresu):";
        foreach(contractCommercialSections($c['commercialSnapshot']) as $label=>$value) $text.="\n".$label.": ".$value;
    }
    if(($f['dataRole']??'')==='processor') foreach(['processingPurpose','processingLocations','processingSubprocessors','processingSecurity','processingReturn'] as $key) $text.="\n".contractPackageFields()[$key]['label'].': '.$f[$key];
    $parts=[]; for($i=0;$i<mb_strlen($text);$i+=8000) $parts[]=mb_substr($text,$i,8000);
    return array_map(static fn($part,$i)=>'Ustalenia umowne, fragment '.($i+1).'/'.count($parts).":\n".$part,$parts,array_keys($parts));
}
