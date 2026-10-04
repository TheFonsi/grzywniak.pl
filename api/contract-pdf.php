<?php
declare(strict_types=1);

/** Proportional A4 layout with Polish glyphs, searchable text and measured wrapping. */
final class ContractPdfLayout {
    private array $pages=[];
    private string $stream='';
    private float $y=92;
    private array $metrics;
    public function __construct(private array $contract) {
        $this->metrics=require __DIR__.'/contract-font-metrics.php'; $this->page();
    }
    public function pageBreak(): void { $this->page(); }
    private function bytes(string $text): string {
        return iconv('UTF-8','Windows-1250//TRANSLIT',str_replace("\t",'    ',$text)) ?: '';
    }
    private function width(string $text,float $size,bool $bold=false): float {
        $width=0; $metrics=$this->metrics[$bold?'Helvetica-Bold':'Helvetica'];
        foreach(str_split($this->bytes($text)) as $char) $width+=$metrics[ord($char)];
        return $width*$size/1000;
    }
    private function text(string $text,float $x,float $top,float $size=10.5,bool $bold=false,string $color='0.13 0.17 0.23'): void {
        $escaped=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$this->bytes($text));
        $this->stream.=sprintf("BT %s rg /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",$color,$bold?'F2':'F1',$size,$x,842-$top-$size,$escaped);
    }
    private function rule(float $top): void { $this->stream.=sprintf("0.84 0.87 0.91 RG 0.6 w 48 %.2F m 547 %.2F l S\n",842-$top,842-$top); }
    private function page(): void {
        if($this->stream!=='') $this->pages[]=$this->stream;
        $this->stream=''; $this->y=92;
        $this->text('GRZYWNIAK / PROJEKTY CYFROWE',48,32,9,true,'0.16 0.29 0.46');
        $this->text((string)($this->contract['number']??'UM').'  /  v'.($this->contract['version']??1),365,33,8);
        $this->rule(59);
    }
    private function ensure(float $height): void { if($this->y+$height>766) $this->page(); }
    private function wrap(string $text,float $width,float $size,bool $bold=false): array {
        $words=preg_split('/\s+/u',trim($text),-1,PREG_SPLIT_NO_EMPTY); $lines=[]; $line='';
        foreach($words as $word) {
            if($line!=='' && $this->width($line.' '.$word,$size,$bold)>$width) { $lines[]=$line; $line=''; }
            while($this->width($word,$size,$bold)>$width) {
                $chunk='';
                foreach(mb_str_split($word) as $char) { if($chunk!=='' && $this->width($chunk.$char,$size,$bold)>$width) break; $chunk.=$char; }
                $lines[]=$chunk; $word=mb_substr($word,mb_strlen($chunk));
            }
            $line.=($line!==''?' ':'').$word;
        }
        if($line!=='') $lines[]=$line;
        return $lines ?: [''];
    }
    public function paragraph(string $text,float $size=10.5,bool $bold=false,float $tailReserve=0): void {
        $leading=$size<10.5?14:15;
        $paragraphs=explode("\n",rtrim(str_replace(["\r\n","\r"],"\n",$text)));
        foreach($paragraphs as $index=>$paragraph) {
            $paragraph=trim($paragraph);
            if($paragraph==='') { $this->y+=5; continue; }
            $subheading=mb_strtoupper($paragraph)===$paragraph && mb_strlen($paragraph)<60 && preg_match('/\p{L}/u',$paragraph);
            $isBold=$bold || (bool)$subheading;
            $lines=$this->wrap($paragraph,499,$size,$isBold);
            $height=($isBold?55:min(count($lines),2)*15)+($subheading?20:0);
            if($tailReserve>0 && $index===count($paragraphs)-1) $height=max($height,min(count($lines)*15,674-$tailReserve)+$tailReserve);
            $this->ensure($height);
            if($subheading) $this->y+=6;
            foreach($lines as $line) { $this->ensure($leading); $this->text($line,48,$this->y,$size,$isBold); $this->y+=$leading; }
            $this->y+=6;
        }
    }
    public function heading(string $label,bool $compact=false): void {
        $size=$compact?11:12; $leading=$compact?16:17;
        $lines=$this->wrap($label,499,$size,true); $this->ensure(count($lines)*$leading+52); $this->y+=$compact?8:12;
        foreach($lines as $line) { $this->text($line,48,$this->y,$size,true,'0.16 0.29 0.46'); $this->y+=$leading; }
        $this->rule($this->y+3); $this->y+=$compact?9:14;
    }
    public function section(string $label,string $text,float $size=10.5,bool $compact=false): void {
        $height=($compact?33:43)+count($this->wrap($text,499,$size))*($size<10.5?14:15)+6;
        if($height<320) $this->ensure($height);
        $this->heading($label,$compact); $this->paragraph($text,$size);
    }
    public function title(string $title): void {
        $this->text(($this->contract['documentType']??'')==='offer'?'OFERTA / ZAKRES I WARUNKI REALIZACJI':'PROJEKT UMOWY / DO PODPISANIA',48,$this->y,8,true,'0.38 0.45 0.54'); $this->y+=22;
        foreach($this->wrap($title,499,23,true) as $line) { $this->text($line,48,$this->y,23,true); $this->y+=29; }
        $this->y+=10;
        $this->paragraph('Numer: '.($this->contract['number']??'UM').' | Wersja: '.($this->contract['version']??1),9);
        if(!empty($this->contract['facts']['contractDate'])) $this->paragraph('Planowana data zawarcia: '.$this->contract['facts']['contractDate'],9);
        $this->y+=4;
    }
    public function signatures(): void {
        $this->ensure(124); $this->y+=20;
        $this->paragraph('Podpisy stron',12,true);
        $this->paragraph('Projekt staje się umową po zawarciu jej przez strony w wymaganej formie.',9); $this->y+=35;
        foreach([48,315] as $x) $this->stream.=sprintf("0.55 0.60 0.67 RG 0.6 w %d %.2F m %d %.2F l S\n",$x,842-$this->y,$x+232,842-$this->y);
        $this->text('Zamawiający',48,$this->y+9,10,true); $this->text('Wykonawca',315,$this->y+9,10,true);
    }
    public function output(): string {
        $this->pages[]=$this->stream;
        $encoding='/Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences ['.$this->metrics['differences'].'] >>';
        $map=[];
        for($i=32;$i<256;$i++) { $utf=@iconv('Windows-1250','UTF-16BE',chr($i)); if($utf!==false) $map[]=sprintf('<%02X> <%s>',$i,strtoupper(bin2hex($utf))); }
        $cmap="/CIDInit /ProcSet findresource begin 12 dict begin begincmap /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def /CMapName /Polish def /CMapType 2 def 1 begincodespacerange <00> <FF> endcodespacerange\n";
        foreach(array_chunk($map,90) as $chunk) $cmap.=count($chunk)." beginbfchar\n".implode("\n",$chunk)."\nendbfchar\n";
        $cmap.='endcmap CMapName currentdict /CMap defineresource pop end end';
        $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($i)=>(7+$i*2).' 0 R',array_keys($this->pages))).'] /Count '.count($this->pages).' >>'];
        foreach(['Helvetica','Helvetica-Bold'] as $face) $objects[]='<< /Type /Font /Subtype /Type1 /BaseFont /'.$face.' '.$encoding.' /FirstChar 0 /LastChar 255 /Widths ['.implode(' ',$this->metrics[$face]).'] /ToUnicode 5 0 R >>';
        $objects[]='<< /Length '.strlen($cmap)." >>\nstream\n".$cmap."\nendstream";
        foreach($this->pages as $i=>$stream) {
            $this->stream=''; $this->rule(791);
            $this->text((($this->contract['documentType']??'')==='offer'?'Oferta':'Projekt do podpisania').' | '.($this->contract['number']??'UM'),48,802,8,false,'0.38 0.45 0.54');
            $this->text('Strona '.($i+1).' / '.count($this->pages),468,802,8,false,'0.38 0.45 0.54'); $stream.=$this->stream;
            $objects[]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $objects[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.(6+$i*2).' 0 R >>';
        }
        $pdf="%PDF-1.4\n"; $offsets=[];
        foreach($objects as $i=>$object) { $offsets[]=strlen($pdf); $pdf.=($i+1)." 0 obj\n".$object."\nendobj\n"; }
        $xref=strlen($pdf); $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        foreach($offsets as $offset) $pdf.=sprintf("%010d 00000 n \n",$offset);
        return $pdf."trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
function contractPdf(array $contract): string {
    $layout=new ContractPdfLayout($contract); $facts=$contract['facts']??[];
    $layout->title($contract['templateName']??'Umowa o realizację projektu cyfrowego');
    $groups=[
        ['Strony umowy',['party','provider'],['clientAddress','clientTaxId','clientRepresentative','clientType']],
        ['Przedmiot i zakres',['scope'],[]],
        ['Wynagrodzenie i płatności',['price','deposit','paymentDetails'],[]],
        ['Harmonogram i współpraca',['deadline'],[]],
        ['Odbiór i przekazanie',['acceptance'],[]],
        ['Publikacja, domena i hosting',['deploymentTerms'],['publicationDestination','productionDomain','domainRegistrar','domainOwnershipTerms','productionHosting','serverTarget','backupResponsibility','dnsTlsResponsibility']],
        ['Prawa autorskie',['ip'],['ipMode','rightsTerms','ipPayment','signing']],
        ['Składniki i licencje zewnętrzne',['exclusions'],[]],
        ['Wady, wsparcie i utrzymanie',['support'],[]],
        ['Zmiany i koszty dodatkowe',['extras'],[]],
        ['Odpowiedzialność i postanowienia końcowe',['terms'],['dataRole','consumerDocuments','dataProcessingTerms']],
    ];
    foreach($groups as $i=>[$label,$keys,$factKeys]) {
        if($i===10&&!empty($contract['package'])) {
            $layout->heading('Pakiet dokumentów');
            $layout->paragraph('Załączniki poniżej stanowią część projektu umowy. Identyfikator wersji pakietu: '.$contract['package']['hash'],9);
            foreach($contract['package']['documents'] as $doc) $layout->paragraph($doc['title'],10);
        }
        $layout->heading('§ '.($i+1).'. '.$label);
        foreach($keys as $key) {
            $value=trim((string)($contract[$key]??'')); if($value==='') continue;
            if(count($keys)>1) $layout->paragraph(match($key){'party'=>'Zamawiający','provider'=>'Wykonawca','price'=>'Wynagrodzenie','deposit'=>'Zaliczka i etapy płatności',default=>'Sposób płatności'},10.5,true);
            $layout->paragraph($value,10.5,false,$key==='terms'?180:0);
        }
        if($i===3&&!empty($contract['package'])&&in_array($facts['clientType']??'',['consumer','protected'],true)&&($facts['consumerChannel']??'')!=='premises') $layout->paragraph('Harmonogram rozpoczyna się po spełnieniu warunków startu oraz po upływie terminu odstąpienia wskazanego w informacji dla chronionego klienta. System nie przyjmuje domniemanej zgody na wcześniejsze świadczenie. Odmienne ustalenie wymaga odrębnego, zweryfikowanego prawnie dokumentu i rzeczywistych oświadczeń klienta.',10);
        foreach($factKeys as $key) if(trim((string)($facts[$key]??''))!=='') {
            if($key==='consumerDocuments' && !in_array($facts['clientType']??'',['consumer','protected'],true)) continue;
            if($key==='dataProcessingTerms' && ($facts['dataRole']??'')!=='processor') continue;
            $field=contractFacts()[$key]; $value=$field['options'][$facts[$key]]??$facts[$key];
            $label=preg_replace('/\s*\([^)]*\)/u','',$field['label']);
            $layout->paragraph($label.': '.$value,10);
        }
    }
    $layout->signatures();
    foreach($contract['package']['documents']??[] as $doc) {
        $layout->pageBreak(); $layout->title($doc['title']);
        foreach($doc['sections'] as $label=>$text) { $compact=in_array($doc['id'],['privacy','hosting'],true); $layout->section($label,$text,$compact?10:10.5,$compact); }
    }
    return $layout->output();
}
