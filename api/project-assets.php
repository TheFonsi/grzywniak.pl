<?php
declare(strict_types=1);
require_once __DIR__.'/project-assets-lib.php';
require_once __DIR__.'/contract-model.php';
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
function assetReply(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
$id=(string)($_GET['session']??''); if(!preg_match('/^[a-f0-9]{32}$/',$id)) assetReply(['message'=>'Niepoprawny identyfikator sprawy.'],400);
$assetId=(string)($_GET['asset']??'');
if($_SERVER['REQUEST_METHOD']==='GET' && $assetId!=='') {
    $stmt=projectDb()->prepare('SELECT * FROM project_assets WHERE session_id=? AND asset_key=?'); $stmt->execute([$id,$assetId]); $asset=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$asset) assetReply(['message'=>'Nie znaleziono pliku.'],404);
    $user=getenv('ADMIN_USERNAME')?:''; $password=getenv('ADMIN_PASSWORD')?:'';
    $admin=$user!=='' && $password!=='' && hash_equals($user,(string)($_SERVER['PHP_AUTH_USER']??'')) && hash_equals($password,(string)($_SERVER['PHP_AUTH_PW']??''));
    $expires=(int)($_GET['expires']??0); $sig=(string)($_GET['signature']??'');
    $signed=$expires>=time() && $expires<=time()+86400 && $sig!=='' && hash_equals(projectAssetSignature($id,$assetId,$expires),$sig) && $asset['status']==='approved';
    if(!$admin && !$signed) { http_response_code(403); exit; }
    $path=projectAssetRoot($id).'/'.$assetId.'.bin'; if(!is_file($path)) { http_response_code(404); exit; }
    $inline=$admin && ($_GET['inline']??'')==='1' && in_array($asset['mime'],['image/jpeg','image/png','image/webp','image/gif'],true);
    $mime=$inline?$asset['mime']:'application/octet-stream';
    header('Content-Type: '.$mime); header('Content-Length: '.(string)filesize($path)); header("Content-Disposition: ".($inline?'inline':'attachment')."; filename*=UTF-8''".rawurlencode((string)$asset['filename'])); readfile($path); exit;
}
$user=getenv('ADMIN_USERNAME')?:''; $password=getenv('ADMIN_PASSWORD')?:'';
if($user===''||$password===''||!hash_equals($user,(string)($_SERVER['PHP_AUTH_USER']??''))||!hash_equals($password,(string)($_SERVER['PHP_AUTH_PW']??''))) { header('WWW-Authenticate: Basic realm="Grzywniak Discovery"'); http_response_code(401); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST') assetReply(['message'=>'Niedozwolona metoda.'],405);
if(!hash_equals(contractToken(),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) assetReply(['message'=>'Odśwież panel i ponów działanie.'],403);
$session=readSession($id); if(!$session || ($session['status']??'')!=='COMPLETED') assetReply(['message'=>'Nie znaleziono przekazanego briefu.'],404);
$action=(string)($_POST['action']??''); $db=projectDb();
try {
 if($action==='upload') {
    if(!empty(projectCase($id)['closedAt'])) assetReply(['message'=>'Do zamkniętej sprawy nie można dodawać materiałów.'],409);
    $category=(string)($_POST['category']??'other'); if(!in_array($category,['logo','photo','text','other'],true)) assetReply(['message'=>'Wybierz kategorię pliku.'],422);
    $source=trim((string)($_POST['source_note']??'')); if(mb_strlen($source)>1000) assetReply(['message'=>'Notatka o pochodzeniu jest za długa.'],422);
    $files=$_FILES['files']??null; if(!is_array($files)||!is_array($files['name']??null)) assetReply(['message'=>'Wybierz przynajmniej jeden plik.'],422);
    $accepted=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif','pdf'=>'application/pdf','txt'=>'text/plain','md'=>'text/plain','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $root=projectAssetRoot($id); if(!is_dir($root) && !mkdir($root,0700,true) && !is_dir($root)) throw new RuntimeException('Nie udało się przygotować prywatnego katalogu materiałów.'); @chmod($root,0700);
    $current=$db->prepare('SELECT COALESCE(SUM(size),0),COUNT(*) FROM project_assets WHERE session_id=?'); $current->execute([$id]); [$total,$count]=$current->fetch(PDO::FETCH_NUM); $total=(int)$total; $count=(int)$count; $added=[];
    foreach($files['name'] as $i=>$original) {
      if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new DomainException('Co najmniej jeden plik nie został poprawnie przesłany.');
      $tmp=(string)$files['tmp_name'][$i]; $size=(int)$files['size'][$i]; if(!is_uploaded_file($tmp)||$size<1||$size>12582912) throw new DomainException('Każdy plik musi mieć maksymalnie 12 MB.');
      $ext=strtolower(pathinfo((string)$original,PATHINFO_EXTENSION)); if(!isset($accepted[$ext])) throw new DomainException('Dozwolone formaty: JPG, PNG, WebP, GIF, PDF, TXT, MD i DOCX.');
      $finfo=new finfo(FILEINFO_MIME_TYPE); $detected=$finfo->file($tmp)?:'application/octet-stream';
      $mime=$accepted[$ext]; $valid=$detected===$mime || ($ext==='docx'&&$detected==='application/zip') || ($ext==='txt'||$ext==='md')&&in_array($detected,['text/plain','application/octet-stream'],true);
      if(!$valid) throw new DomainException('Rozszerzenie pliku nie pasuje do jego zawartości.');
      if(++$count>50 || ($total+=$size)>104857600) throw new DomainException('Limit sprawy wynosi 50 plików i 100 MB łącznie.');
      $key=bin2hex(random_bytes(24)); $path=$root.'/'.$key.'.bin'; if(!move_uploaded_file($tmp,$path)) throw new RuntimeException('Nie udało się zapisać pliku.'); @chmod($path,0600);
      $name=trim(basename(str_replace('\\','/',(string)$original))); $name=mb_substr($name?:'plik.'.$ext,0,180);
      $stmt=$db->prepare("INSERT INTO project_assets(session_id,asset_key,category,filename,mime,size,sha256,status,source_note,uploaded_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'received',?,?,?,?)");
      $stmt->execute([$id,$key,$category,$name,$mime,$size,hash_file('sha256',$path),$source,$user,time(),time()]); $added[]=(int)$db->lastInsertId();
    }
    projectEvent($id,'brief','assets_uploaded',$user,'Dodano '.count($added).' materiałów klienta do sprawdzenia.'); assetReply(['ok'=>true,'added'=>count($added)]);
 }
 if($action==='review') {
    $assetNum=(int)($_POST['asset_id']??0); $status=(string)($_POST['status']??''); $note=trim((string)($_POST['admin_note']??''));
    if(!in_array($status,['received','approved','needs_changes','rejected'],true)||mb_strlen($note)>1000) assetReply(['message'=>'Niepoprawna ocena materiału.'],422);
    $stmt=$db->prepare('UPDATE project_assets SET status=?,admin_note=?,updated_at=? WHERE id=? AND session_id=?'); $stmt->execute([$status,$note,time(),$assetNum,$id]); if($stmt->rowCount()!==1) assetReply(['message'=>'Nie znaleziono materiału.'],404);
    projectEvent($id,'brief','asset_reviewed',$user,'Zmieniono ocenę materiału #'.$assetNum.' na '.$status.'.'); assetReply(['ok'=>true]);
 }
 assetReply(['message'=>'Nieznana operacja.'],400);
} catch(DomainException $e) { assetReply(['message'=>$e->getMessage()],422); } catch(Throwable $e) { error_log('Project asset error: '.$e->getMessage()); assetReply(['message'=>'Nie udało się zapisać materiałów. Sprawdź format i rozmiar plików.'],500); }
