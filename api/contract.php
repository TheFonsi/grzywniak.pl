<?php
 declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/contract-model.php';
require_once __DIR__.'/contract-pdf.php';
require_once __DIR__.'/contract-ai.php';
require_once __DIR__.'/contract-review.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user=getenv('ADMIN_USERNAME')?:''; $password=getenv('ADMIN_PASSWORD')?:'';
if($user==='' || $password==='' || !hash_equals($user,(string)($_SERVER['PHP_AUTH_USER']??'')) || !hash_equals($password,(string)($_SERVER['PHP_AUTH_PW']??''))) { header('WWW-Authenticate: Basic realm="Grzywniak Discovery"'); http_response_code(401); exit; }
function contractError(int $status,string $message): never { http_response_code($status); echo json_encode(['message'=>$message],JSON_UNESCAPED_UNICODE); exit; }
function contractReply(array $data,string $location): never {
    sessionDb()->exec('COMMIT');
    if (!str_contains((string)($_SERVER['CONTENT_TYPE']??''),'application/json')) { header('Location: '.$location,true,303); exit; }
    echo json_encode($data,JSON_UNESCAPED_UNICODE); exit;
}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)) contractError(405,'Niedozwolona metoda.');
$body=[];
if($method==='POST') {
    $body=json_decode(file_get_contents('php://input')?:'{}',true);
    if(!is_array($body)) $body=[];
    $body=array_replace($body,$_POST);
    if(!hash_equals(contractToken(),(string)($body['csrf']??''))) contractError(403,'Sesja formularza wygasła. Odśwież panel.');
    // AI only fills the browser form; do not hold a SQLite write lock during HTTP.
    if(!in_array($body['action']??$body['contract_action']??'',['ai-fill','apply-template'],true)) sessionDb()->exec('BEGIN IMMEDIATE');
}
$action=(string)($body['action']??$body['contract_action']??'');
if($action==='save-profile') {
    $profile=contractProfile();
    if((int)($body['expectedVersion']??-1)!==(int)$profile['version']) contractError(409,'Dane wykonawcy zostały zmienione. Odśwież ustawienia.');
    foreach(contractProfileFields() as $key=>$label) {
        if(!is_string($body[$key]??null)||mb_strlen($body[$key])>2000) contractError(422,'Niepoprawne pole: '.$label);
        $profile[$key]=trim($body[$key]);
    }
    $missing=contractProfileMissing($profile);
    if($missing) contractError(422,'Uzupełnij dane wykonawcy: '.implode(', ',$missing));
    if(!filter_var($profile['email'],FILTER_VALIDATE_EMAIL)) contractError(422,'Podaj poprawny e-mail wykonawcy.');
    $profile['version']++; $profile['updatedAt']=time();
    $stmt=sessionDb()->prepare('INSERT INTO contract_profile(id,data) VALUES(1,:data) ON CONFLICT(id) DO UPDATE SET data=excluded.data');
    $stmt->execute([':data'=>json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    contractReply(['profileVersion'=>$profile['version']],apiPath('admin.php').'?view=contract-settings&profileSaved=1');
}
if($action==='save-template') {
    $templateId=(string)($body['templateId']??'legacy');
    try { $template=$templateId==='legacy'?contractTemplate():contractCatalogTemplate($templateId); }
    catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    if((int)($body['expectedVersion']??-1)!==(int)$template['version']) contractError(409,'Wzór został zmieniony. Odśwież ustawienia.');
    foreach(contractFields() as $key=>$label) if($key!=='provider' && array_key_exists($key,contractTemplateDefaults())) { if(!is_string($body[$key]??null)||mb_strlen($body[$key])>20000) contractError(422,'Niepoprawna treść pola: '.$label); $template[$key]=trim($body[$key]); }
    foreach(['defaultTransferTerms','defaultIpPayment'] as $key) {
        if(!is_string($body[$key]??null)||trim($body[$key])===''||mb_strlen($body[$key])>2000) contractError(422,'Uzupełnij domyślne warunki praw IP (maksymalnie 2000 znaków).');
        $template[$key]=trim($body[$key]);
    }
    $template['version']++; $template['updatedAt']=time();
    if($templateId==='legacy') {
        $stmt=sessionDb()->prepare('INSERT INTO contract_templates(id,data) VALUES(1,:data) ON CONFLICT(id) DO UPDATE SET data=excluded.data');
        $stmt->execute([':data'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    } else {
        $stmt=sessionDb()->prepare('INSERT INTO contract_template_catalog(id,data) VALUES(:id,:data) ON CONFLICT(id) DO UPDATE SET data=excluded.data');
        $stmt->execute([':id'=>$templateId, ':data'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
    contractReply(['template'=>$template],apiPath('admin.php').'?view=contract-settings&saved=1');
}
if($method==='GET' && isset($_GET['templatePreview'])) {
    $id=(string)$_GET['templatePreview'];
    try { $pdf=contractPdf(contractSampleContract($id,contractCatalogTemplate($id))); }
    catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="wzor-'.$id.'.pdf"');
    header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
}
$id=(string)($_GET['session']??$body['contract_session']??'');
if(!preg_match('/^[a-f0-9]{32}$/',$id)) contractError(400,'Niepoprawny identyfikator rozmowy.');
$s=readSession($id);
if(!$s) contractError(404,'Nie znaleziono rozmowy.');
if($method==='GET' && ($_GET['format']??'')==='editor') { header('Content-Type: text/html; charset=utf-8'); echo contractEditor($s); exit; }
if($method==='GET') {
    if(($_GET['format']??'')==='manifest') {
        $c=$s['contract']??[];
        if(!isset($c['package'])) contractError(404,'Ta wersja nie ma manifestu pakietu.');
        header('Content-Disposition: attachment; filename="pakiet-v'.(int)$c['version'].'.json"');
        echo json_encode(['hash'=>$c['package']['hash'],'pdfSha256'=>$c['package']['pdfSha256'],'manifest'=>$c['package']['manifest']],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR); exit;
    }
    if(($_GET['format']??'')==='pdf') {
        $contract=$s['contract']??null;
        if(isset($_GET['version']) && (int)$_GET['version']!==(int)($contract['version']??0)) { $contract=null; foreach($s['contractVersions']??[] as $old) if((int)$old['version']===(int)$_GET['version']) $contract=$old; }
        $pdf=base64_decode((string)($contract['pdfBase64']??''),true);
        if(!$pdf || !str_starts_with($pdf,'%PDF-')) contractError(404,'Ta wersja nie ma przygotowanego PDF.');
        header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="umowa-'.preg_replace('/[^A-Za-z0-9-]/','',$contract['number']).'-v'.(int)$contract['version'].'.pdf"'); header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
    }
    echo json_encode(['contract'=>$s['contract']??null,'contractVersions'=>$s['contractVersions']??[]],JSON_UNESCAPED_UNICODE); exit;
}
if(($s['offer']['status']??'')!=='ACCEPTED') contractError(409,'Najpierw zaakceptuj ofertę.');
if((int)($body['expectedVersion']??-1)!==(int)($s['contract']['version']??0)) contractError(409,'Umowa została zmieniona. Odśwież panel przed zapisem.');
$templateId=(string)($body['templateId']??$s['contract']['templateId']??(isset($s['contract'])?'legacy':contractSelectTemplate($s['offer'])['id']));
try { $activeTemplate=$templateId==='legacy'?contractTemplate():contractCatalogTemplate($templateId); }
catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
if($action==='apply-template') {
    try {
        $template=contractCatalogTemplate((string)($body['requestedTemplateId']??''));
        $facts=contractReadFacts($body,$s['contract']['facts']??[],$template);
        echo json_encode(['fields'=>contractTemplateProposal($template,$facts),'facts'=>[], 'replaceTemplate'=>true,'template'=>['id'=>$template['id'],'title'=>$template['title'],'revision'=>$template['revision'],'version'=>$template['version']], 'missing'=>contractFactsMissing($facts)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
    } catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
}
if($action==='ai-fill') {
    $profile=contractProfile();
    if(!isset($s['contract']) && (int)($body['profileVersion']??-1)!==(int)$profile['version']) contractError(409,'Dane wykonawcy zmieniły się. Odśwież formularz, aby pobrać aktualne dane.');
    try {
        $facts=contractReadFacts($body,$s['contract']['facts']??[],$activeTemplate); $draft=[];
        foreach(contractFields() as $key=>$label) { if(!is_string($body[$key]??null)||mb_strlen($body[$key])>20000) throw new InvalidArgumentException('Niepoprawne pole: '.$label); $draft[$key]=$body[$key]; }
        $template=$activeTemplate;
        $review=contractReviewState($body['reviewState']??null,$draft,$facts,$s['contract']['review']??[]);
        $accepted=[]; foreach($review as $key=>$entry) if($entry['accepted']) $accepted[$key]=$entry['value'];
        session_write_close();
        $result=contractAiDraft($s['offer'],$draft,$facts,$template,$accepted);
        // The model fills project particulars; legal clauses are controlled by the
        // selected template or manual edits, never replaced by generated boilerplate.
        $standard=contractTemplateProposal($template,$facts);
        foreach(contractTemplateFields() as $key) $result['fields'][$key]=trim($draft[$key])!==''?$draft[$key]:$standard[$key];
        // Factual identifiers never originate from model guesses. Existing commercial
        // terms are copied exactly; only absent negotiable terms can be proposed.
        foreach(['provider','party'] as $key) $result['fields'][$key]=trim($draft[$key])!==''?$draft[$key]:'[DO UZUPEŁNIENIA: '.contractFields()[$key].']';
        $result['fields']['paymentDetails']=trim($draft['paymentDetails'])!==''?$draft['paymentDetails']:'Płatność przelewem na rachunek wskazany na fakturze.';
        foreach(['price','deposit','deadline'] as $key) if(trim($draft[$key])!=='') $result['fields'][$key]=$draft[$key];
        if(trim($draft['price'])==='') $result['fields']['price']='[DO UZUPEŁNIENIA: wynagrodzenie zgodne z ofertą]';
        foreach(['clientAddress','clientTaxId','clientRepresentative','clientType','dataRole','publicationDestination','productionDomain','domainRegistrar','domainOwnershipTerms','productionHosting','serverTarget','backupResponsibility','dnsTlsResponsibility','consumerDocuments','dataProcessingTerms','ipMode','signing','contractDate'] as $key) $result['facts'][$key]=$facts[$key];
        foreach($facts as $key=>$value) if($value!=='' && !($key==='rightsTerms'&&$facts['ipMode']==='')) $result['facts'][$key]=$value;
        foreach(contractPackageFields() as $key=>$field) $result['facts'][$key]=$facts[$key];
        $result['facts']=contractReadFacts($result['facts'],$s['contract']['facts']??[],$template);
        foreach($accepted as $key=>$value) { if(array_key_exists($key,$result['fields'])) $result['fields'][$key]=$value; elseif(array_key_exists($key,$result['facts'])) $result['facts'][$key]=$value; }
        $latest=readSession($id);
        if(!$latest || ($latest['offer']['status']??'')!=='ACCEPTED' || ($latest['offer']??[])!==$s['offer'] || (int)($latest['contract']['version']??0)!==(int)($s['contract']['version']??0)) contractError(409,'Oferta lub umowa zmieniła się podczas generowania. Odśwież panel.');
        $result['missing']=array_values(array_unique(array_merge(contractFactsMissing($result['facts']),$result['missing'])));
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
    } catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    catch(Throwable $error) { contractError(502,$error instanceof RuntimeException?$error->getMessage():'Nie udało się przygotować projektu AI.'); }
}
if(in_array($action,['save','generate'],true)) {
    if(!is_array($s['contract']??null)) {
        $profile=contractProfile();
        if((int)($body['profileVersion']??0)!==(int)$profile['version']) contractError(409,'Dane wykonawcy zmieniły się. Odśwież formularz przed zapisem.');
        if($action==='generate' && contractProfileMissing($profile)) contractError(422,'Najpierw uzupełnij „Moje dane do umów” w ustawieniach.');
    }
    try { $facts=contractReadFacts($body,$s['contract']['facts']??[],$activeTemplate); } catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    $c=[];
    foreach(contractFields() as $key=>$label) { if(!is_string($body[$key]??null)||mb_strlen($body[$key])>20000) contractError(422,'Niepoprawna treść pola: '.$label); $c[$key]=trim($body[$key]); }
    try { $review=contractReviewState($body['reviewState']??null,$c,$facts,$s['contract']['review']??[]); } catch(InvalidArgumentException $error) { contractError(422,$error->getMessage()); }
    if($action==='generate') foreach(['provider','party','scope','price','deadline','ip'] as $key) if($c[$key]==='') contractError(422,'Uzupełnij: '.contractFields()[$key]);
    if($action==='generate') {
        if($templateId!=='legacy') foreach(contractRequiredReview($c,$facts) as $key) {
            if(!isset($review[$key])) $review[$key]=['value'=>(string)($c[$key]??$facts[$key]??''),'accepted'=>false];
        }
        $pending=contractPendingReview($review);
        if($pending) contractError(422,'Zaakceptuj lub zmień propozycje przed PDF: '.implode(', ',$pending));
        $missing=contractFactsMissing($facts);
        if($missing) contractError(422,'Uzupełnij ustalenia umowy: '.implode(', ',$missing));
        foreach(array_merge($c,$facts) as $value) if(preg_match('/\[DO (UZUPEŁNIENIA|UZGODNIENIA):/iu',$value)) contractError(422,'Uzupełnij oznaczone braki danych przed wygenerowaniem PDF. Projekt możesz zapisać w obecnej postaci.');
    }
    $c+=['number'=>$s['contract']['number']??'UM-'.date('Y').'-'.strtoupper(substr(hash('sha256',$id),0,8)), 'version'=>(int)($s['contract']['version']??0)+1, 'createdAt'=>time(), 'templateVersion'=>(int)($s['contract']['templateVersion']??$body['templateVersion']??0), 'offerVersion'=>(int)($s['offer']['version']??1), 'status'=>'DRAFT'];
    $c['facts']=$facts;
    $sameTemplate=isset($s['contract']) && ($s['contract']['templateId']??'legacy')===$templateId && (int)($body['templateVersion']??$s['contract']['templateVersion']??0)===(int)($s['contract']['templateVersion']??0) && (int)($body['templateRevision']??$s['contract']['templateRevision']??0)===(int)($s['contract']['templateRevision']??0);
    if(!$sameTemplate && ((int)($body['templateVersion']??0)!==(int)$activeTemplate['version'] || (isset($body['templateRevision']) && (int)$body['templateRevision']!==(int)($activeTemplate['revision']??0)))) contractError(409,'Wybrany wzór zmienił się. Wczytaj go ponownie przed zapisem.');
    $c['templateId']=$templateId;
    $c['templateName']=$sameTemplate?($s['contract']['templateName']??'Umowa o realizację projektu cyfrowego'):($activeTemplate['title']??'Umowa o realizację projektu cyfrowego');
    $c['templateRevision']=$sameTemplate?($s['contract']['templateRevision']??0):($activeTemplate['revision']??0);
    $c['templateVersion']=$sameTemplate?($s['contract']['templateVersion']??0):$activeTemplate['version'];
    $c['templateSnapshot']=$sameTemplate?($s['contract']['templateSnapshot']??[]):array_intersect_key($activeTemplate,array_flip(contractTemplateFields()));
    $c['review']=$review;
    $c['profileVersion']=(int)($s['contract']['profileVersion']??$body['profileVersion']??0);
    if($action==='generate') {
        $c['package']=contractPackageBuild($c);
        $pdf=contractPdf($c);
        $c['pdfBase64']=base64_encode($pdf);
        $c['package']['pdfSha256']=hash('sha256',$pdf);
    }
    if(is_array($s['contract']??null)) $s['contractVersions'][]=$s['contract'];
    $s['contract']=$c; writeSession($s);
    contractReply(['contract'=>$c],apiPath('admin.php').'?view=all&session='.$id.'#contract-panel');
}
if(in_array($action,['legal-review','record-receipt','resolve-delivery'],true)) {
    $c=$s['contract']??[];
    if(!contractPackageIntegrity($c)) contractError(409,'Przygotuj kompletny, aktualny pakiet PDF.');
    if(!hash_equals($c['package']['hash'],(string)($body['packageHash']??''))) contractError(409,'Pakiet zmienił się. Odśwież panel.');
    $reviewer=trim((string)($body['reviewer']??'')); $evidence=trim((string)($body['evidence']??''));
    if(mb_strlen($reviewer)<2||mb_strlen($reviewer)>200||mb_strlen($evidence)<20||mb_strlen($evidence)>3000) contractError(422,'Podaj osobę oraz konkretny dowód i zakres potwierdzenia (20–3000 znaków).');
    $record=['packageHash'=>$c['package']['hash'],'pdfSha256'=>$c['package']['pdfSha256'],'reviewer'=>$reviewer,'evidence'=>$evidence,'recordedBy'=>$user,'at'=>time(),'source'=>'manual_evidence'];
    if($action==='resolve-delivery') {
        $hasPending=false; foreach($c['deliveryLog']??[] as $entry) if(($entry['state']??'')==='sending') $hasPending=true;
        if(!$hasPending) contractError(409,'Ta wersja nie ma niepewnej próby wysyłki.');
        $outcome=(string)($body['deliveryOutcome']??'');
        if(!in_array($outcome,['accepted','failed'],true)) contractError(422,'Wybierz potwierdzony wynik wysyłki.');
        $find=sessionDb()->prepare('SELECT state,updated_at FROM contract_delivery_outbox WHERE session_id=? AND version=? AND package_hash=?');
        $find->execute([$id,$c['version'],$c['package']['hash']]); $attempt=$find->fetch(PDO::FETCH_ASSOC);
        if(!$attempt||$attempt['state']!=='sending'||(int)$attempt['updated_at']>time()-600) contractError(409,'Rozstrzygnięcie dotyczy wyłącznie niepewnej próby starszej niż 10 minut. Sprawdź kolejkę i dziennik serwera poczty.');
        sessionDb()->prepare('UPDATE contract_delivery_outbox SET state=?,data=?,updated_at=? WHERE session_id=? AND version=? AND package_hash=?')->execute([$outcome,json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time(),$id,$c['version'],$c['package']['hash']]);
        foreach($s['contract']['deliveryLog']??[] as $i=>$entry) if(($entry['state']??'')==='sending') { $s['contract']['deliveryLog'][$i]['state']=$outcome; $s['contract']['deliveryLog'][$i]['resolution']=$record; }
        if($outcome==='accepted') $s['contract']['status']='SENT';
        $s['contract']['package']['evidenceLog'][]=['kind'=>'delivery_resolution','outcome'=>$outcome]+$record;
        writeSession($s); contractReply(['contract'=>$s['contract']],apiPath('admin.php').'?view=all&session='.$id.'#contract-panel');
    }
    $key=$action==='legal-review'?'legalReview':'receipt';
    $s['contract']['package'][$key]=$record;
    $s['contract']['package']['evidenceLog'][]=['kind'=>$key]+$record;
    writeSession($s);
    contractReply(['contract'=>$s['contract']],apiPath('admin.php').'?view=all&session='.$id.'#contract-panel');
}
if($action==='send') {
    $c=$s['contract']??null;
    if(empty($c['pdfBase64'])) contractError(409,'Najpierw przygotuj PDF aktualnej wersji.');
    if(!contractPackageApproved($c)) contractError(409,'Wysyłka wymaga kompletnego pakietu i udokumentowanej weryfikacji prawnej tej wersji.');
    if((int)$c['offerVersion']!==(int)($s['offer']['version']??1)) contractError(409,'Oferta zmieniła się. Przygotuj nowy pakiet.');
    foreach($c['deliveryLog']??[] as $delivery) if(in_array($delivery['state']??'accepted',['accepted','mail_server_accepted','mock'],true)) contractError(409,'Ten pakiet został już przekazany do serwera poczty. Sprawdź historię wysyłki; ponowienie nie wysyła duplikatu.');
    $to=(string)(($s['offer']['contact']['email']??'')?:($s['projectState']['contactEmail']??''));
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)) contractError(422,'Brak poprawnego adresu e-mail klienta.');
    // Persist intent before the external mail side effect. A crash during send
    // leaves an explicit uncertain attempt, never an automatic duplicate email.
    $db=sessionDb();
    $db->exec('CREATE TABLE IF NOT EXISTS contract_delivery_outbox (session_id TEXT NOT NULL, version INTEGER NOT NULL, package_hash TEXT NOT NULL, state TEXT NOT NULL, data TEXT NOT NULL, updated_at INTEGER NOT NULL, PRIMARY KEY(session_id,version,package_hash))');
    $find=$db->prepare('SELECT state FROM contract_delivery_outbox WHERE session_id=? AND version=? AND package_hash=?');
    $key=[$id,(int)$c['version'],$c['package']['hash']]; $find->execute($key); $prior=$find->fetchColumn();
    if($prior!==false&&$prior!=='failed') contractError(409,'Wysyłka jest już zapisana lub jej wynik jest niepewny. Sprawdź kolejkę pocztową; system nie powiela wiadomości.');
    $attempt=['reference'=>bin2hex(random_bytes(12)),'to'=>$to,'at'=>time(),'packageHash'=>$c['package']['hash'],'pdfSha256'=>$c['package']['pdfSha256'],'manifest'=>$c['package']['manifest'],'actor'=>$user,'state'=>'sending'];
    $reserve=$db->prepare('INSERT INTO contract_delivery_outbox(session_id,version,package_hash,state,data,updated_at) VALUES(?,?,?,\'sending\',?,?) ON CONFLICT(session_id,version,package_hash) DO UPDATE SET state=excluded.state,data=excluded.data,updated_at=excluded.updated_at');
    $reserve->execute([...$key,json_encode($attempt,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time()]);
    $s['contract']['deliveryLog'][]=$attempt; writeSession($s);
    $db->exec('COMMIT'); session_write_close();
    $boundary='contract_'.bin2hex(random_bytes(16));
    $headers='MIME-Version: 1.0'."\r\n".'X-Grzywniak-Delivery: '.$attempt['reference']."\r\n".'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
    $message='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode('W załączeniu projekt umowy wraz ze wszystkimi załącznikami do zapoznania się i podpisania. Identyfikator pakietu: '.$c['package']['hash'].'. Prosimy o potwierdzenie otrzymania dokumentów. Wiadomość nie zastępuje wymaganych podpisów ani odrębnej zgody na wcześniejsze rozpoczęcie świadczenia.')).'--'.$boundary."\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"pakiet-v".(int)$c['version'].".pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split($c['pdfBase64']);
    $manifest=json_encode(['hash'=>$c['package']['hash'],'pdfSha256'=>$c['package']['pdfSha256'],'manifest'=>$c['package']['manifest']],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    $message.='--'.$boundary."\r\nContent-Type: application/json; charset=UTF-8\r\nContent-Disposition: attachment; filename=\"manifest-v".(int)$c['version'].".json\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($manifest)).'--'.$boundary."--\r\n";
    $mock=getenv('DISCOVERY_MAIL_MOCK')==='true';
    $ok=$mock||@mail($to,'=?UTF-8?B?'.base64_encode('Projekt umowy — Grzywniak.pl').'?=',$message,$headers);
    $attempt['mock']=$mock; $attempt['state']=$ok?($mock?'mock':'mail_server_accepted'):'failed'; $attempt['finishedAt']=time();
    $db->exec('BEGIN IMMEDIATE');
    $db->prepare('UPDATE contract_delivery_outbox SET state=?,data=?,updated_at=? WHERE session_id=? AND version=? AND package_hash=?')->execute([$ok?'accepted':'failed',json_encode($attempt,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time(),...$key]);
    $latest=readSession($id);
    if($latest) {
        // Editing while mail is in flight archives the version. Update only its
        // delivery metadata; retain the actual payload, PDFs and approvals.
        $record=static function(array &$target) use($c,$attempt,$ok,$mock,$to): void {
            if((int)($target['version']??0)!==(int)$c['version']||($target['package']['hash']??'')!==$c['package']['hash']) return;
            foreach($target['deliveryLog']??[] as $i=>$entry) if(($entry['reference']??'')===$attempt['reference']) $target['deliveryLog'][$i]=$attempt;
            if($ok) { $target['status']=$mock?'MOCK_SENT':'SENT'; $target['sentAt']=time(); $target['sentTo']=$to; }
        };
        if(isset($latest['contract'])) $record($latest['contract']);
        if(isset($latest['contractVersions'])) foreach($latest['contractVersions'] as &$old) $record($old);
        unset($old); writeSession($latest);
    }
    if(!$ok) { $db->exec('COMMIT'); contractError(502,'Serwer poczty odrzucił wysyłkę. Próba została zapisana i można ją ponowić.'); }
    contractReply(['contract'=>$latest['contract']??null,'sentVersion'=>(int)$c['version']],apiPath('admin.php').'?view=all&session='.$id.'#contract-panel');
}
contractError(400,'Nieznana operacja.');
