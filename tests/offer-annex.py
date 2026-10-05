"""Render fictional PDFs in a disposable API copy and verify the original offer appendix."""
from pathlib import Path
import os, shutil, subprocess, tempfile
import pymupdf as fitz
from PIL import Image, ImageDraw
root=Path(__file__).resolve().parents[1]
work=Path(tempfile.mkdtemp(prefix='offer-annex-',dir=root/'tmp'))
(work/'api').mkdir()
for file in (root/'api').glob('*.php'):shutil.copy2(file,work/'api'/file.name)
out=root/'output/pdf';out.mkdir(parents=True,exist_ok=True)
script=r'''
require_once 'api/contract-model.php';require_once 'api/offer-pdf.php';require_once 'api/contract-commercial.php';
$c=contractSampleContract('website');
$s=['projectState'=>[],'internalAnalysis'=>['recommendedScope'=>['One page: firma, usługi, realizacje, kontakt telefoniczny']]];
$offer=['offerId'=>'OF-DEMO','version'=>35,'status'=>'REVIEWED','createdAt'=>1791194400,'project'=>str_repeat('Długi opis problemu projektu. ',12),'summary'=>'Prosta strona firmy budowlanej z informacjami o ofercie i przyciskiem kontaktu.',
'provider'=>['legalName'=>'Studio Przykładowe sp. z o.o.','address'=>'ul. Przykładowa 1, 00-001 Warszawa','taxId'=>'0000000000','email'=>'studio@example.test','phone'=>'000 000 000'],
'contact'=>['name'=>'Firma Budowlana Przykład','address'=>'ul. Testowa 2, 50-001 Wrocław','taxId'=>'1111111111','phone'=>'111 111 111','email'=>'klient@example.test'],
'sections'=>[['title'=>'Zakres realizacji','items'=>$s['internalAnalysis']['recommendedScope']],['title'=>'Poza obecnym zakresem / możliwe później','items'=>['Galeria realizacji','Dodatkowa wersja językowa']]],
'pricing'=>['net'=>1600,'vatRate'=>23,'gross'=>1968,'vat'=>368],'payment'=>['depositRate'=>10],
'decisionCoverage'=>['internal-hash-abcdef'=>['target'=>'scope','targetLabel'=>'Zakres realizacji','question'=>'Czy klient chce standardowy układ?','answer'=>'Hipoteza do zatwierdzenia: prosty układ bez indywidualnego projektu graficznego.']]];
$offer['agreement']=offerAgreementDraft($s,[],$offer);
$pdf=offerPdf($offer);file_put_contents($argv[1],$pdf);
$offer['acceptedPdfBase64']=base64_encode($pdf);$offer['status']='ACCEPTED';$s['offer']=$offer;
$snapshot=contractCommercialSnapshot($s);
if($snapshot['offerPdfSha256']!==hash('sha256',$pdf))throw new RuntimeException('Snapshot must bind exact offer bytes');
$c=array_replace($c,$snapshot['fields']);$c['commercialSnapshot']=$snapshot;$c['offerVersion']=35;$c['package']=contractPackageBuild($c);
file_put_contents($argv[2],contractPdf($c));
$c['commercialSnapshot']['offerPdfSha256']=str_repeat('0',64);
try{contractPdf($c);throw new RuntimeException('Corrupt appendix must fail');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'nie odpowiada'))throw $e;}
'''
(work/'fixture.php').write_text('<?php\n'+script,encoding='utf-8')
subprocess.run(['php','fixture.php',str(out/'offer-layout-sample.pdf'),str(out/'contract-offer-annex-sample.pdf')],cwd=work,env=os.environ,check=True)
offer=fitz.open(out/'offer-layout-sample.pdf');contract=fitz.open(out/'contract-offer-annex-sample.pdf')
first=offer[0].get_text();text='\n'.join(page.get_text() for page in offer)
assert 'Wykonawca' in first and 'Zamawiający' in first and 'Telefon:' in first and 'NIP:' in first
assert 'Wartość realizacji' not in first and 'Wartość realizacji' in offer[-1].get_text()
assert 'Hipoteza do zatwierdzenia' not in text and 'internal-hash' not in text and 'Ustalenie 1' in text
start=len(contract)-len(offer)
assert start>0
contract_text='\n'.join(page.get_text() for page in list(contract)[:start])
assert 'Wynagrodzenie:' not in contract[0].get_text() and '1 600' not in contract[0].get_text()
assert contract_text.index('Wynagrodzenie i płatności')>contract_text.index('Odpowiedzialność i postanowienia końcowe')
assert contract_text.index('Wynagrodzenie i płatności')<contract_text.index('Podpisy stron')
for i,page in enumerate(offer):
    original=page.get_pixmap();appended=contract[start+i].get_pixmap()
    assert (original.width,original.height,original.samples)==(appended.width,appended.height,appended.samples),'Appended page must be visually identical to accepted offer'
scratch=root/'tmp/pdfs';scratch.mkdir(parents=True,exist_ok=True)
for name,document in [('offer',offer),('contract',contract)]:
    thumbs=[]
    for i,page in enumerate(document):
        pix=page.get_pixmap(matrix=fitz.Matrix(1,1));pix.save(scratch/f'{name}-layout-{i+1}.png')
        thumb=Image.frombytes('RGB',[pix.width,pix.height],pix.samples);thumb.thumbnail((240,340));thumbs.append(thumb)
    cols=4;sheet=Image.new('RGB',(cols*260,((len(thumbs)+cols-1)//cols)*370),'#d1d5db');draw=ImageDraw.Draw(sheet)
    for i,thumb in enumerate(thumbs):x=(i%cols)*260;y=(i//cols)*370;sheet.paste(thumb,(x+10,y+20));draw.text((x+10,y+3),f'{name} {i+1}',fill='black')
    sheet.save(scratch/f'{name}-layout-sheet.png')
print(f'Offer layout and exact PDF appendix passed: {len(offer)} offer pages, {len(contract)} contract pages; original render preserved and corrupt hash rejected.')
