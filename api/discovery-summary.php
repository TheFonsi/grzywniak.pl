<?php
declare(strict_types=1);

function discoverySummaryValueIsUnknown(string $value): bool {
    return preg_match('/^(?:do ustalenia|do określenia|nieustalone|nie ustalono|nie podano|brak danych|brak informacji)[.! ]*$/iu',trim($value))===1;
}

function buildSummary(array $state): array {
    $sections=[];
    $fields=[
        'contactName'=>'Kontakt / firma',
        'contactPhone'=>'Telefon kontaktowy',
        'contactEmail'=>'E-mail kontaktowy',
        'businessGoals'=>'Cel projektu',
        'businessProblem'=>'Problem do rozwiązania',
        'targetUsers'=>'Użytkownicy',
        'coreProcesses'=>'Główne działania',
        'mustHaveFeatures'=>'Najważniejszy zakres pierwszej wersji',
        'niceToHaveFeatures'=>'Dodatkowe potrzeby i założenia',
        'integrations'=>'Integracje',
        'budget'=>'Podany budżet',
        'deadline'=>'Oczekiwany termin',
    ];
    foreach($fields as $key=>$title) {
        $value=$state[$key]??null;
        if(is_array($value)) {
            $content=array_values(array_filter(array_map(static fn($item)=>trim((string)$item),$value),static fn($item)=>$item!==''&&!discoverySummaryValueIsUnknown($item)));
        } else {
            $text=trim((string)$value);
            $content=$text!==''&&!discoverySummaryValueIsUnknown($text)?[$text]:[];
        }
        if(!$content) continue;
        $sections[]=['title'=>$title,'content'=>$content];
    }
    $sections[]=['title'=>'Kolejne kroki','content'=>[
        '1. Zespół zweryfikuje zebrane informacje, zakres i najważniejsze założenia projektu.',
        '2. Przygotujemy propozycję rozwiązania, harmonogram i szczegółową wycenę.',
        '3. Przedstawimy ofertę po sprawdzeniu informacji i opracowaniu zakresu.',
        '4. Skontaktujemy się z Tobą podanym kanałem, aby omówić propozycję.',
    ]];
    return ['title'=>'Brief projektu','sections'=>$sections];
}
