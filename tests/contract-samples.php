<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../api/contract-model.php';
require_once __DIR__.'/../api/contract-pdf.php';
$out=__DIR__.'/../output/pdf';
if(!is_dir($out)) mkdir($out,0777,true);
foreach(array_keys(contractCatalog()) as $id) {
    file_put_contents($out.'/umowa-'.$id.'-przyklad.pdf',contractPdf(contractSampleContract($id)));
    echo "Sample: $id\n";
}
