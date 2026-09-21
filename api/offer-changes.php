<?php
declare(strict_types=1);

function offerTextChanges(array $previous, array $current): array {
    $changes = [];
    $add = static function (array $path, mixed $old, mixed $new, string $label) use (&$changes): void {
        if ($old === $new) return;
        $changes[] = ['key' => 'text.' . count($changes), 'path' => $path, 'label' => $label, 'previous' => $old ?? '', 'current' => $new ?? '', 'previousValue' => $old, 'currentValue' => $new, 'confirmed' => false];
    };
    foreach (['project'=>'Nazwa projektu', 'summary'=>'Podsumowanie'] as $key=>$label) $add([$key], $previous[$key] ?? '', $current[$key] ?? '', $label);
    foreach (($current['sections'] ?? []) as $sectionIndex=>$section) {
        $oldSection = $previous['sections'][$sectionIndex] ?? [];
        $add(['sections',$sectionIndex,'title'], $oldSection['title'] ?? '', $section['title'], 'Tytuł sekcji');
        $a = $oldSection['items'] ?? []; $b = $section['items'] ?? [];
        $n=count($a); $m=count($b); $dp=array_fill(0,$n+1,array_fill(0,$m+1,0));
        for($i=$n-1;$i>=0;$i--) for($j=$m-1;$j>=0;$j--) $dp[$i][$j]=$a[$i]===$b[$j]?1+$dp[$i+1][$j+1]:max($dp[$i+1][$j],$dp[$i][$j+1]);
        $i=0; $j=0;
        while($i<$n || $j<$m) {
            if($i<$n && $j<$m && $a[$i]===$b[$j]) { $i++; $j++; continue; }
            $start=$j; $removed=[]; $added=[];
            while(($i<$n || $j<$m) && !($i<$n && $j<$m && $a[$i]===$b[$j])) {
                if($i<$n && ($j===$m || $dp[$i+1][$j]>=$dp[$i][$j+1])) $removed[]=$a[$i++];
                else $added[]=$b[$j++];
            }
            for($k=0;$k<max(count($removed),count($added));$k++) $add(['sections',$sectionIndex,'items',$start+min($k,count($added))], $removed[$k]??null, $added[$k]??null, (string)$section['title']);
        }
    }
    return $changes;
}

function restoreOfferChange(array $offer, string $key): array {
    $index=array_search($key,array_column($offer['changeLog']??[],'key'),true);
    if($index===false) throw new RuntimeException('Nie znaleziono zmiany.');
    $change=$offer['changeLog'][$index]; $path=$change['path']??[];
    if(!empty($change['confirmed']) || !empty($change['restored'])) throw new RuntimeException('Ta zmiana została już rozstrzygnięta.');
    if(!$path || !array_key_exists('previousValue',$change)) throw new RuntimeException('Ta starsza zmiana nie obsługuje przywracania fragmentu.');
    if(count($path)===4 && $path[0]==='sections' && $path[2]==='items') {
        $items=&$offer['sections'][$path[1]]['items']; $position=$path[3];
        if($change['currentValue']!==null && ($items[$position]??null)!==$change['currentValue']) throw new RuntimeException('Fragment został już zmieniony. Odśwież ofertę.');
        $remove=$change['currentValue']===null?0:1;
        $replacement=$change['previousValue']===null?[]:[$change['previousValue']];
        array_splice($items,$position,$remove,$replacement);
        $shift=count($replacement)-$remove;
        foreach($offer['changeLog'] as $otherIndex=>&$other) if($otherIndex!==$index && ($other['path'][0]??'')==='sections' && ($other['path'][1]??-1)===$path[1] && ($other['path'][2]??'')==='items' && (($other['path'][3]??-1)>$position || (($other['path'][3]??-1)===$position && ($shift<0 || $otherIndex>$index)))) $other['path'][3]+=$shift;
        unset($other,$items);
    } else {
        if(count($path)===1 && in_array($path[0],['project','summary'],true)) $target=&$offer[$path[0]];
        elseif(count($path)===3 && $path[0]==='sections' && $path[2]==='title') $target=&$offer['sections'][$path[1]]['title'];
        else throw new RuntimeException('Nieprawidłowy fragment.');
        if($target!==$change['currentValue']) throw new RuntimeException('Fragment został już zmieniony.');
        $target=$change['previousValue']; unset($target);
    }
    $offer['changeLog'][$index]['restored']=true;
    $offer['changeLog'][$index]['confirmed']=true;
    return $offer;
}
