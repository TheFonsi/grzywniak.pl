<?php
declare(strict_types=1);
require_once __DIR__.'/contract-pdf.php';
require_once __DIR__.'/offer-agreement.php';
function offerPdf(array $offer):string {
    $layout=new ContractPdfLayout(['documentType'=>'offer','number'=>$offer['offerId']??'OF','version'=>$offer['version']??1]);
    $layout->title('Oferta realizacji projektu');
    $contact=$offer['contact']??[];
    $project=(string)($offer['project']??'Projekt cyfrowy');
    if(mb_strlen($project)>110) $project=!empty($contact['name'])?'Projekt: '.$contact['name']:'Projekt cyfrowy';
    $layout->paragraph($project,13,true);
    $date=(new DateTimeImmutable('@'.(int)($offer['updatedAt']??$offer['createdAt']??time())))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y');
    $layout->paragraph('Data: '.$date.' | Status: '.(['DRAFT'=>'Projekt do weryfikacji','REVIEWED'=>'Zweryfikowana','SENT'=>'Wysłana','ACCEPTED'=>'Zaakceptowana','OUTDATED'=>'Nieaktualna'][$offer['status']??'DRAFT']??'Projekt'),9);
    if(!empty($offer['provider']['legalName'])) {
        $provider=$offer['provider'];$layout->section('Wykonawca',implode("\n",array_filter([$provider['legalName'],$provider['address']??'',!empty($provider['taxId'])?'NIP: '.$provider['taxId']:'',!empty($provider['email'])?'E-mail: '.$provider['email']:'',!empty($provider['phone'])?'Telefon: '.$provider['phone']:''])),10,true);
    }
    $layout->section('Przygotowana dla — Zamawiający',implode("\n",array_filter([$contact['name']??'', $contact['address']??'', !empty($contact['taxId'])?'NIP: '.$contact['taxId']:'', !empty($contact['phone'])?'Telefon: '.$contact['phone']:'',!empty($contact['email'])?'E-mail: '.$contact['email']:''])),10,true);
    $pricing=$offer['pricing']??[];$payment=$offer['payment']??[];
    $money=static fn($n)=>number_format((float)$n,2,',',' ').' zł';
    $advance=round((float)($pricing['gross']??0)*(float)($payment['depositRate']??0)/100,2);
    if(!empty($offer['summary'])) $layout->section('Założenia projektu',$offer['summary'],10,true);
    foreach($offer['sections']??[] as $section) {
        if(preg_match('/zatwierdzone ustalenia|zmiany do potwierdzenia/iu',$section['title']??'')) continue;
        $layout->section($section['title']??'Zakres',implode("\n",array_map(static fn($v)=>'- '.$v,$section['items']??[])),10,true);
    }
    if(isset($offer['agreement'])) {
        $layout->pageBreak();$layout->heading('Warunki realizacji — podstawa umowy');
        foreach(offerAgreementSections($offer) as $section) $layout->section($section['title'],implode("\n",$section['items']),10,true);
    }
    $layout->pageBreak();$layout->heading('Podsumowanie finansowe');
    $layout->section('Wartość realizacji',$money($pricing['gross']??0).' brutto',19,true);
    $layout->paragraph('Netto: '.$money($pricing['net']??0).' | VAT '.($pricing['vatRate']??23).'%: '.$money($pricing['vat']??((float)($pricing['gross']??0)-(float)($pricing['net']??0))),10);
    $layout->paragraph('Zaliczka: '.($payment['depositRate']??0).'% — '.$money($advance).' brutto. Pozostałe płatności według harmonogramu.',10);
    $layout->section('Dalsze kroki i charakter dokumentu',($offer['note']??'').' Zaakceptowana oferta o tym numerze i wersji jest załącznikiem do umowy i określa jej zakres, wynagrodzenie oraz warunki realizacji. Nie zastępuje podpisania umowy w wymaganej formie ani potwierdzenia zakupu domeny. Zmiana zakresu, kosztów lub warunków wymaga nowej wersji i uzgodnienia.',9.5,true);
    return $layout->output();
}
