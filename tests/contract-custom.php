<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../api/contract-model.php';
require_once __DIR__.'/../api/contract-pdf.php';
require_once __DIR__.'/../api/contract-review.php';
function customAssert(bool $ok,string $text):void{if(!$ok)throw new RuntimeException($text);}
$c=contractSampleContract('website');
$c['facts']['customClauses']=json_encode([['title'=>'Materiały klienta','text'=>'Klient przekazuje dodatkowe zdjęcia do zatwierdzenia przed publikacją.'],['title'=>'Spotkanie odbiorowe','text'=>'Strony uzgadniają jedno spotkanie online po przygotowaniu podglądu.']],JSON_UNESCAPED_UNICODE);
customAssert(in_array('customClauses',contractRequiredReview(array_intersect_key($c,contractFields()),$c['facts']),true),'Custom terms require explicit approval');
$c['package']=contractPackageBuild($c);$doc=end($c['package']['documents']);
customAssert($doc['id']==='custom_clauses'&&count($doc['sections'])===2,'Each custom term must enter annex and manifest');
$c['pdfBase64']=base64_encode(contractPdf($c));$c['package']['pdfSha256']=hash('sha256',base64_decode($c['pdfBase64']));
customAssert(contractPackageIntegrity($c),'Custom clauses must be covered by package integrity');
$changed=$c;$changed['facts']['customClauses']='[]';customAssert(!contractPackageIntegrity($changed),'Changing a clause must invalidate the approved package');
foreach(['not-json','{}','[{"title":"","text":"x"}]','[{"title":"x","text":""}]'] as $bad){try{contractCustomClauses($bad);throw new LogicException('Invalid clause accepted');}catch(InvalidArgumentException){}}
$none=$c;$none['facts']['customClauses']='';customAssert(!in_array('customClauses',contractRequiredReview(array_intersect_key($none,contractFields()),$none['facts']),true),'Empty optional clauses do not block PDF');
$dir=__DIR__.'/../tmp/pdfs';if(!is_dir($dir))mkdir($dir,0700,true);file_put_contents($dir.'/contract-custom.pdf',base64_decode($c['pdfBase64']));
echo "Custom contract clauses passed: validation, review, annex, manifest and tamper detection.\n";
