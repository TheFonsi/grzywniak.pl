<?php
declare(strict_types=1);

function offerOptionalSection(string $title):bool {return preg_match('/poza.*zakres|możliwe później|opcjonal|optional|out of scope/iu',$title)===1;}
function offerOptionalIdeas(array $scope,array $ideas):array {
    $included=mb_strtolower(implode(' ',array_map('strval',$scope)));
    $pool=[];
    if(preg_match('/sklep|koszyk|e.?commerce|checkout/iu',$included)) $pool=[
        ['program lojalności','Program lojalnościowy z punktami za zakupy i rabatami dla powracających klientów.'],
        ['porzucon.*koszyk','Przypomnienia o porzuconym koszyku za zgodą klienta.'],
        ['rekomendacj','Rekomendacje produktów uzupełniających przy zakupie.'],
        ['zwrot|reklamacj','Panel zgłaszania zwrotów i reklamacji z historią statusów.'],
        ['magazyn','Integracja stanów magazynowych z wybranym systemem firmy.'],
    ];
    elseif(preg_match('/aplikacj|system|panel|logowan|użytkownik|rezerwac/iu',$included)) $pool=[
        ['raport|dashboard','Panel raportów z kluczowymi wynikami i filtrami okresów.'],
        ['powiadomie','Powiadomienia o ważnych zmianach z ustawieniami użytkownika.'],
        ['integracj','Integracja z wybranym narzędziem używanym w firmie.'],
        ['eksport','Eksport danych i raportów do plików CSV.'],
        ['histori.*zmian|audyt','Historia zmian z wyszukiwaniem zdarzeń i wykonawców.'],
    ];
    else $pool=[
        ['galeri|realizacj|portfolio','Galeria realizacji z osobnymi opisami projektów i zdjęciami.'],
        ['formularz','Formularz zapytania z możliwością dołączenia materiałów.'],
        ['cms|edycj.*treści','Panel samodzielnej edycji treści i zdjęć przez firmę.'],
        ['wersj.*język|wielojęzycz','Dodatkowa wersja językowa dla klientów zagranicznych.'],
        ['opini|referencj','Sekcja opinii i referencji klientów z przykładami współpracy.'],
    ];
    $pool=array_merge($pool,[
        ['faq|najczę.*pyt','Sekcja najczęstszych pytań ułatwiająca klientom wybór usługi.'],
        ['kampani|landing','Osobna strona dla wybranej kampanii lub usługi z pomiarem zapytań.'],
        ['kalkulator','Kalkulator orientacyjnych kosztów według reguł zatwierdzonych przez firmę.'],
        ['analityk|pomiar','Panel pomiaru skuteczności zapytań i źródeł ruchu z uzgodnionymi zasadami prywatności.'],
        ['dostępnoś','Rozszerzony audyt dostępności i usprawnienia dla wskazanych grup użytkowników.'],
        ['wyszukiw','Wyszukiwanie treści z filtrami dopasowanymi do oferty firmy.'],
    ]);
    $out=[];$seen=[];
    foreach($ideas as $idea) if(is_string($idea)&&trim($idea)!=='') {
        $idea=trim($idea);$key=mb_strtolower($idea);
        if(in_array($idea,$scope,true)||isset($seen[$key])) continue;
        $seen[$key]=true;$out[]=$idea;
    }
    foreach($pool as [$pattern,$idea]) {
        if(count($out)>=5) break;
        if(preg_match('/'.$pattern.'/iu',$included)||isset($seen[mb_strtolower($idea)])) continue;
        $seen[mb_strtolower($idea)]=true;$out[]=$idea;
    }
    return $out;
}
function offerExpandOptional(array $offer):array {
    $scope=[];$index=null;
    foreach($offer['sections']??[] as $i=>$section) {
        if(offerOptionalSection($section['title']??'')) {$index??=$i;continue;}
        if(preg_match('/zakres realizacji|przedmiot projektu|^scope$/iu',$section['title']??'')) $scope=array_merge($scope,$section['items']??[]);
    }
    if($index===null) {$index=count($offer['sections']??[]);$offer['sections'][]=['title'=>'Poza obecnym zakresem / możliwe później','items'=>[]];}
    $offer['sections'][$index]['items']=offerOptionalIdeas($scope,$offer['sections'][$index]['items']??[]);
    return $offer;
}
function offerPromoteOptional(array $offer,int $section,int $item,string $expectedItem):array {
    $source=$offer['sections'][$section]??null;
    if(!is_array($source)||!offerOptionalSection($source['title']??'')||!isset($source['items'][$item])||$source['items'][$item]!==$expectedItem) throw new InvalidArgumentException('Pomysł zmienił się lub nie należy do części opcjonalnej. Odśwież ofertę.');
    $target=null;
    foreach($offer['sections'] as $i=>$part) if(preg_match('/^zakres realizacji$|^przedmiot projektu$|^scope$/iu',trim($part['title']??''))) {$target=$i;break;}
    if($target===null) {$target=count($offer['sections']);$offer['sections'][]=['title'=>'Zakres realizacji','items'=>[]];}
    if(!in_array($expectedItem,$offer['sections'][$target]['items'],true)) $offer['sections'][$target]['items'][]=$expectedItem;
    array_splice($offer['sections'][$section]['items'],$item,1);
    return $offer;
}
