<?php
declare(strict_types=1);
function contractPdf(array $contract): string {
    $lines = ['PROJEKT UMOWY - DO PODPISANIA', 'Numer: '.($contract['number']??'UM').' | Wersja: '.($contract['version']??1), ''];
    $fields=contractFields();
    foreach(contractFacts() as $key=>$field) if(!empty($contract['facts'][$key])) {
        $fields['fact_'.$key]=$field['label'];
        $contract['fact_'.$key]=$field['options'][$contract['facts'][$key]]??$contract['facts'][$key];
    }
    foreach ($fields as $key=>$label) {
        $lines[] = mb_strtoupper($label);
        foreach (explode("\n", str_replace(["\r\n","\r"], "\n", (string)($contract[$key]??''))) as $line) {
            while(mb_strlen($line)>80) { $chunk=mb_substr($line,0,80); $space=mb_strrpos($chunk,' '); $length=$space!==false && $space>20?$space:80; $lines[]=mb_substr($line,0,$length); $line=ltrim(mb_substr($line,$length)); }
            $lines[]=$line;
        }
        $lines[]='';
    }
    $lines[]='Podpis klienta: ______________________________';
    $lines[]='Podpis wykonawcy: ____________________________';
    $pages=[]; $page=[];
    $headings=array_map('mb_strtoupper',array_values($fields));
    foreach($lines as $line) {
        $keepWithNext=in_array($line,$headings,true)||str_starts_with($line,'Podpis klienta:');
        if(count($page)>=48 || ($keepWithNext && count($page)>45)) { $pages[]=$page; $page=[]; }
        if(!$page && $line==='') continue;
        $page[]=$line;
    }
    if($page) $pages[]=$page;
    $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($i)=>($i*2+5).' 0 R',array_keys($pages))).'] /Count '.count($pages).' >>','<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [140 /Sacute 143 /Zacute 156 /sacute 159 /zacute 163 /Lslash 165 /Aogonek 175 /Zdotaccent 179 /lslash 185 /aogonek 191 /zdotaccent 198 /Cacute 202 /Eogonek 209 /Nacute 211 /Oacute 230 /cacute 234 /eogonek 241 /nacute 243 /oacute] >> >>'];
    $escape=static fn($s)=>str_replace(['\\','(',')'],['\\\\','\\(','\\)'], iconv('UTF-8','Windows-1250//TRANSLIT',(string)$s)?:'');
    foreach($pages as $i=>$page) {
        $stream="BT /F1 10 Tf 48 790 Td 14 TL\n";
        foreach($page as $line) $stream.='('.$escape($line).") Tj T*\n";
        $stream.="ET\nBT /F1 9 Tf 48 40 Td (Strona ".($i+1).' / '.count($pages).") Tj ET";
        $objects[]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        $objects[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.(4+$i*2).' 0 R >>';
    }
    $pdf="%PDF-1.4\n"; $offsets=[];
    foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($pdf); $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    foreach($offsets as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
    return $pdf."trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}
