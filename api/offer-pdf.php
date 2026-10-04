<?php
declare(strict_types=1);
require_once __DIR__.'/contract-pdf.php';
require_once __DIR__.'/offer-agreement.php';
function offerPdf(array $offer):string {
    $layout=new ContractPdfLayout(['documentType'=>'offer','number'=>$offer['offerId']??'OF','version'=>$offer['version']??1]);
    $layout->title('Oferta realizacji projektu');
    $layout->paragraph((string)($offer['project']??'Projekt cyfrowy'),15,true);
    $date=(new DateTimeImmutable('@'.(int)($offer['updatedAt']??$offer['createdAt']??time())))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y');
    $layout->paragraph('Data: '.$date.' | Status: '.(['DRAFT'=>'Projekt do weryfikacji','REVIEWED'=>'Zweryfikowana','SENT'=>'Wysłana','ACCEPTED'=>'Zaakceptowana','OUTDATED'=>'Nieaktualna'][$offer['status']??'DRAFT']??'Projekt'),9);
    if(!empty($offer['provider']['legalName'])) {
        $provider=$offer['provider'];$layout->paragraph(implode(' | ',array_filter([$provider['legalName'],$provider['address']??'',!empty($provider['taxId'])?'NIP '.$provider['taxId']:'',$provider['email']??'',$provider['phone']??''])),9);
    }
    $contact=$offer['contact']??[];
    $layout->section('Przygotowana dla',implode("\n",array_filter([$contact['name']??'',$contact['phone']??'',$contact['email']??''])),10,true);
    $pricing=$offer['pricing']??[];$payment=$offer['payment']??[];
    $money=static fn($n)=>number_format((float)$n,2,',',' ').' zł';
    $layout->section('Wartość realizacji',$money($pricing['gross']??0).' brutto',19,true);
    $layout->paragraph('Netto: '.$money($pricing['net']??0).' | VAT '.($pricing['vatRate']??23).'%: '.$money($pricing['vat']??0),10);
    $advance=round((float)($pricing['gross']??0)*(float)($payment['depositRate']??0)/100,2);
    $layout->paragraph('Zaliczka: '.($payment['depositRate']??0).'% — '.$money($advance).' brutto. Pozostałe płatności według harmonogramu.',10);
    if(!empty($offer['summary'])) $layout->section('Założenia projektu',$offer['summary'],10,true);
    foreach($offer['sections']??[] as $section) {
        if(preg_match('/zatwierdzone ustalenia|zmiany do potwierdzenia/iu',$section['title']??'')) continue;
        $layout->section($section['title']??'Zakres',implode("\n",array_map(static fn($v)=>'- '.$v,$section['items']??[])),10,true);
    }
    if(isset($offer['agreement'])) {
        $layout->pageBreak();$layout->heading('Warunki realizacji — podstawa umowy');
        foreach(offerAgreementSections($offer) as $section) $layout->section($section['title'],implode("\n",$section['items']),10,true);
    }
    $layout->section('Dalsze kroki i charakter dokumentu',($offer['note']??'').' Akceptacja tej wersji oferty stanowi podstawę przygotowania umowy. Nie zastępuje podpisania umowy w wymaganej formie ani potwierdzenia zakupu domeny. Zmiana zakresu, kosztów lub warunków wymaga nowej wersji i uzgodnienia.',9.5,true);
    return $layout->output();
}
