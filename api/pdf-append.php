<?php
declare(strict_types=1);

/** Append PDFs from ContractPdfLayout only; not a general-purpose PDF importer. */
function appendGeneratedPdf(string $first,string $second):string {
    $objects=['<< /Type /Catalog /Pages 2 0 R >>',''];$pages=[];
    foreach([$first,$second] as $pdf) {
        if(!preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/D',$pdf,$match)) throw new RuntimeException('Niepoprawny PDF załącznika oferty.');
        $xref=substr($pdf,(int)$match[1]);
        if(!preg_match('/^xref\s+0 (\d+)\s+([\s\S]*?)trailer/',$xref,$table)) throw new RuntimeException('Nieobsługiwany format PDF oferty.');
        $rows=preg_split('/\r?\n/',trim($table[2]));$count=(int)$table[1];$map=[];$source=[];
        if(count($rows)!==$count) throw new RuntimeException('Niepełny indeks PDF oferty.');
        for($i=1;$i<$count;$i++) {
            $offset=(int)substr($rows[$i],0,10);
            $end=$i+1<$count?(int)substr($rows[$i+1],0,10):(int)$match[1];
            $object=substr($pdf,$offset,$end-$offset);
            if(!preg_match('/^'.$i.' 0 obj\s+([\s\S]*)\s+endobj\s*$/D',$object,$body)) throw new RuntimeException('Niepoprawny obiekt PDF oferty.');
            $source[$i]=$body[1];$map[$i]=$i<=2?$i:count($objects)+count($map)-1;
        }
        if(!str_contains($source[1]??'','/Type /Catalog')||!preg_match('/\/Kids \[([^\]]*)\]/',$source[2]??'',$kids)) throw new RuntimeException('Niepoprawne strony PDF oferty.');
        preg_match_all('/(\d+) 0 R/',$kids[1],$refs);
        foreach($refs[1] as $id) {if(!isset($map[(int)$id])) throw new RuntimeException('Brak strony PDF oferty.');$pages[]=$map[(int)$id].' 0 R';}
        for($i=3;$i<$count;$i++) {
            $body=$source[$i];$streamAt=strpos($body,"\nstream\n");
            $header=$streamAt===false?$body:substr($body,0,$streamAt);
            $header=preg_replace_callback('/(?<!\d)(\d+) 0 R/',static function($ref)use($map){if(!isset($map[(int)$ref[1]]))throw new RuntimeException('Brak referencji PDF.');return $map[(int)$ref[1]].' 0 R';},$header);
            $objects[]=$header.($streamAt===false?'':substr($body,$streamAt));
        }
    }
    $objects[1]='<< /Type /Pages /Kids ['.implode(' ',$pages).'] /Count '.count($pages).' >>';
    $pdf="%PDF-1.4\n";$offsets=[];
    foreach($objects as $i=>$body){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$body."\nendobj\n";}
    $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    foreach($offsets as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
    return $pdf."trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}
