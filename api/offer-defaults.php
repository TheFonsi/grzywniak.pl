<?php
declare(strict_types=1);

/** Agency proposals, approved only when the administrator saves the offer. */
function offerAgreementDefaultPackage(array $session,array $offer=[]):array {
    $scope=[];
    foreach(contractOfferScopeSections($offer) as $section) foreach($section['items']??[] as $item) if(is_string($item)) $scope[]=trim($item);
    if(!$scope) $scope=$session['internalAnalysis']['recommendedScope']??[];
    $scope=array_values(array_filter($scope,'is_string'));
    $scopeText=implode(' ', $scope);
    $application=preg_match('/aplikacj|sklep|koszyk|logowan|rezerwac|panel użytkownik/iu',$scopeText)===1;
    $personal=$application||preg_match('/formularz|konto użytkownik|płatno/iu',$scopeText)===1;
    $criteria='Odbiór obejmuje wyłącznie zakres tej wersji oferty'.($scope?":\n- ".implode("\n- ",$scope):'.')."\nSprawdzamy działanie uzgodnionych funkcji, kontaktu, linków i nawigacji; zgodność z zatwierdzonymi materiałami; brak błędów blokujących. Testy: aktualne Chrome, Firefox i Safari oraz widoki o szerokości 360, 768 i 1440 px. Brak poziomego przewijania, czytelny tekst, obsługa nawigacji klawiaturą oraz widoczny fokus. Wyniki kontroli są przekazywane z wersją do odbioru.";
    return [
        'acceptanceCriteria'=>mb_substr($criteria,0,2000),'acceptanceDays'=>'7',
        'cooperationTerms'=>'Klient przekazuje posiadane logo, zdjęcia i informacje o firmie przez prywatny formularz materiałów projektu w ciągu 7 dni od zawarcia umowy. Wykonawca opracowuje teksty na ich podstawie. Bez zdjęć wykorzystuje układ tekstowy i graficzny, bez dodatkowo płatnych materiałów. Klient potwierdza prawa do swoich materiałów; osoby i dane bez zgody nie są publikowane. Brak materiałów jest zgłaszany klientowi, a wpływ na termin dokumentowany i uzgadniany przed zmianą harmonogramu.',
        'publicationDestination'=>'agency','ipMode'=>'transfer','rightsTerms'=>'',
        'dataRole'=>$personal?'processor':'none',
        'deliverySchedule'=>'Start po zawarciu umowy, wpłacie zaliczki wskazanej w wycenie i otrzymaniu wymaganych materiałów. Pierwszy podgląd do '.($application?'30':'14').' dni kalendarzowych od spełnienia tych warunków. Klient sprawdza wersję w terminie odbioru wskazanym w ofercie. Po przekazaniu kompletnej listy uwag poprawki w uzgodnionym zakresie są wykonywane do 7 dni roboczych. Publikacja lub przekazanie następuje po odbiorze i rozliczeniu. Nowy zakres wymaga oddzielnego uzgodnienia terminu i ceny.',
        'paymentSchedule'=>'Zaliczka w wysokości wskazanej w wycenie tej oferty, płatna do 7 dni od zawarcia umowy i otrzymania dokumentu płatności. Pozostała kwota płatna do 7 dni od odbioru i otrzymania dokumentu płatności. Publikacja produkcyjna lub przekazanie pakietu następuje po rozliczeniu. Usługi dodatkowe i dalsze utrzymanie wymagają osobnego zamówienia.',
        'supportPlan'=>'Przez 30 dni od odbioru wykonawca usuwa bez dodatkowej opłaty zgłoszone niezgodności z uzgodnionym zakresem. Zgłoszenia przez panel projektu lub e-mail wykonawcy wskazany w ofercie. Pierwsza odpowiedź do 2 dni roboczych; termin poprawki zależy od problemu i jest przekazywany klientowi. Nowe funkcje, nowe treści i zmiany wykonane przez inne osoby wymagają odrębnej wyceny. Warunki nie ograniczają ustawowych uprawnień klienta.',
        'rightsSummary'=>'Cena obejmuje wynagrodzenie za prawa do indywidualnych elementów wykonanych dla projektu. Przeniesienie praw po pełnej zapłacie, na polach eksploatacji i w formie określonych w umowie. Kod oraz materiały projektu są przekazywane przy zakończeniu realizacji. Biblioteki, wcześniejsze komponenty, materiały klienta i elementy na licencjach zewnętrznych pozostają na swoich licencjach; ich wykaz i ograniczenia są przekazywane z projektem. Elementy AI wymagają oznaczenia i sprawdzenia podstaw użycia przed przekazaniem.',
        'externalCosts'=>'Cena obejmuje wyłącznie zakres tej oferty. Przy braku innych uzgodnień stosujemy wariant o najniższym koszcie realizacji: standardowy układ i dostępne komponenty, materiały klienta lub oprawę tekstową, bez płatnych zdjęć i licencji. Nie dodajemy CMS, formularzy, analityki ani integracji niewymienionych w zakresie. Domena, usługi zewnętrzne i dalsze utrzymanie nie są kupowane automatycznie. Każdy dodatkowy koszt, nowa funkcja oraz wpływ na termin wymagają uprzedniej akceptacji klienta i zapisania nowych ustaleń.',
        'dataPlan'=>$personal?'Klient określa cele przetwarzania danych użytkowników. Dostęp wykonawcy ograniczony do uzgodnionych prac; środowisko testowe korzysta z danych syntetycznych. Przed dostępem do rzeczywistych danych strony ustalają zakres, dostawców i podpisują odpowiedni załącznik powierzenia. Integracje i pomiary niewymienione w zakresie nie są uruchamiane.':'Projekt nie obejmuje kont, formularzy ani płatności zbierających dane użytkowników. Wykonawca nie otrzymuje dostępu do baz danych klienta. Logi techniczne hostingu służą bezpieczeństwu i utrzymaniu; nie uruchamiamy marketingowych pomiarów bez odrębnego uzgodnienia. Dodanie przetwarzania danych wymaga ponownego ustalenia ról i dokumentów przed uruchomieniem.',
        'hostingFee'=>'Publikacja na subdomenie agencji i 30 dni hostingu od publikacji są zawarte w cenie projektu. Dalszy hosting nie jest zamówiony; wymaga osobnej akceptacji zakresu, okresu i ceny. Brak automatycznych dodatkowych opłat.',
        'hostingPeriod'=>'30 dni od publikacji, bez automatycznego płatnego odnowienia. Powiadomienie o końcu okresu co najmniej 7 dni wcześniej; strony ustalają dalszy hosting albo przekazanie projektu.',
        'hostingBackup'=>'Przed publikacją wykonawca potwierdza konfigurację kopii plików i bazy, jeśli występuje. W okresie hostingu kopie codziennie z retencją 7 dni. Test odtworzenia przed publikacją. Przy awarii wykonawca przekazuje ocenę i plan odtworzenia; klient zachowuje także przekazany pakiet projektu.',
        'hostingExit'=>'Na żądanie klient otrzymuje eksport kodu, plików i bazy w otwartych formatach do 7 dni, bez dodatkowej opłaty za samo przekazanie. Migracja na inną infrastrukturę jest osobnym zakresem. Usunięcie danych środowiska po 14 dniach od zakończenia hostingu, z uwzględnieniem retencji kopii oraz obowiązków prawnych.',
        'hostingDns'=>'Wykonawca obsługuje DNS i HTTPS na subdomenie agencji. Podłączenie domeny klienta wymaga potwierdzenia prawa do domeny i dostępu do DNS; sam wpis nazwy nie upoważnia do zakupu ani zmian DNS.',
        'domainOwnershipTerms'=>'Domena pozostaje własnością klienta. Przed publikacją klient potwierdza kontrolę nad domeną i udziela dostępu niezbędnego do zatwierdzonych zmian DNS.',
        'productionHosting'=>'Hosting wskazany przez klienta przed przekazaniem pakietu; jego zakup i utrzymanie są po stronie klienta.',
        'backupResponsibility'=>'Po przekazaniu klient lub jego operator odpowiada za kopie i odtwarzanie. Wykonawca przekazuje pakiet projektu oraz instrukcję uruchomienia.',
        'dnsTlsResponsibility'=>'Po przekazaniu konfiguracja DNS, certyfikatów i ich odnowień należy do klienta lub jego operatora. Wykonawca przekazuje wymagania techniczne.',
        'domainPurchaseTerms'=>'Rejestracja dopiero po sprawdzeniu dostępności domeny i odrębnej akceptacji przez klienta dokładnej ceny brutto oraz okresu. Brak zgody nie uruchamia zakupu; opłata rejestracyjna jest dodatkowa do ceny projektu.',
        'domainRenewalTerms'=>'Odnowienie wymaga osobnej akceptacji aktualnej ceny i okresu przed końcem rejestracji; bez automatycznych płatnych odnowień.',
        'domainRegistrant'=>'Domena rejestrowana na klienta z danymi strony zamawiającej potwierdzonymi w umowie. Wykonawca otrzymuje jedynie upoważnienie potrzebne do uzgodnionych operacji.',
        'domainAvailabilityTerms'=>'Przed rejestracją sprawdzamy dostępność i cenę u wybranego operatora. Przy zajętej nazwie proponujemy alternatywy; bez wyboru i zgody klienta nie rejestrujemy innej domeny.',
        'domainHandoverTerms'=>'Klient otrzymuje kontrolę nad domeną i dostępem do DNS po rozliczeniu kosztu rejestracji. Na żądanie kod transferowy i instrukcja przekazania do 7 dni, bez dodatkowej opłaty za samo przekazanie.',
    ];
}
function offerAgreementPrepared(array $session,array $values,array $offer=[]):array {
    $defaults=offerAgreementDefaultPackage($session,$offer);
    if(in_array($values['ipMode']??'',['exclusive','nonexclusive'],true)) {
        $defaults['rightsTerms']='Licencja od pełnej zapłaty, bez ograniczeń terytorialnych, na czas nieoznaczony: uruchamianie projektu, kopiowanie na potrzeby hostingu, udostępnianie użytkownikom oraz modyfikacje na własne potrzeby. Szczegółowe pola eksploatacji i zasady wypowiedzenia określa umowa; składniki zewnętrzne pozostają na swoich licencjach.';
        $defaults['rightsSummary']='Cena obejmuje licencję wskazaną w warunkach praw do projektu, udzielaną po pełnej zapłacie. Kod i materiały są przekazywane z projektem. Wykaz składników i ograniczeń licencyjnych jest przekazywany przy odbiorze; komponenty zewnętrzne, klienta, wcześniejsze oraz AI wymagają wskazania podstaw użycia. Nie obiecujemy przeniesienia praw do składników na licencjach zewnętrznych.';
    }
    foreach(offerAgreementFields() as $key=>$field) {
        $value=trim((string)($values[$key]??''));
        if(isset($defaults[$key])&&($value===''||preg_match('/\[DO (UZUPEŁNIENIA|UZGODNIENIA):/iu',$value)||preg_match('/^(do ustalenia|nie wiem)$/iu',$value))) $values[$key]=$defaults[$key];
    }
    return offerAgreementRead($values);
}
