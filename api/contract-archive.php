<?php
declare(strict_types=1);

function contractArchiveDb(): PDO {
    $db=sessionDb();
    $db->exec('CREATE TABLE IF NOT EXISTS contract_files (id TEXT PRIMARY KEY, session_id TEXT NOT NULL, contract_version INTEGER NOT NULL, kind TEXT NOT NULL, filename TEXT NOT NULL, sha256 TEXT NOT NULL, generated_sha256 TEXT NOT NULL, uploaded_at INTEGER NOT NULL, uploaded_by TEXT NOT NULL, content BLOB NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS contract_files_session ON contract_files(session_id,contract_version)');
    return $db;
}
function contractArchiveEditor(array $session): string {
    $id=$session['id'];$e='contractEscape';$url=apiPath('contract.php');$html='<details><summary>Archiwum umów i podpisanych dokumentów</summary><p>Wygenerowane PDF są dostępne w historii wersji. Wgraj podpisany obustronnie PDF albo dodatkowy dokument. Wgranie pliku nie potwierdza automatycznie podpisów ani nie uruchamia realizacji.</p>';
    $versions=$session['contractVersions']??[];if(isset($session['contract']))$versions[]=$session['contract'];
    foreach(array_reverse($versions) as $c)if(!empty($c['pdfBase64']))$html.='<p>Wygenerowana umowa '.$e($c['number']).' · v'.(int)$c['version'].' · oferta v'.(int)($c['offerVersion']??0).' · <a href="'.$url.'?session='.$e($id).'&amp;format=pdf&amp;version='.(int)$c['version'].'">Pobierz PDF</a></p>';
    if($versions){
        $html.='<form class="contract-archive-upload" method="post" enctype="multipart/form-data" action="'.$url.'"><input type="hidden" name="csrf" value="'.$e(contractToken()).'"><input type="hidden" name="contract_session" value="'.$e($id).'"><input type="hidden" name="expectedVersion" value="'.(int)($session['contract']['version']??0).'"><input type="hidden" name="action" value="upload-contract-file"><label>Wersja umowy<select name="contractVersion">';
        foreach(array_reverse($versions) as $c)$html.='<option value="'.(int)$c['version'].'">'.$e($c['number']).' · v'.(int)$c['version'].'</option>';
        $html.='</select></label><label>Rodzaj dokumentu<select name="kind"><option value="signed">Umowa podpisana obustronnie</option><option value="reference">Dodatkowy dokument / umowa klienta</option></select></label><label>Plik PDF (maks. 10 MB)<input type="file" name="document" accept="application/pdf,.pdf" required></label><button class="button">Dodaj do archiwum</button><p role="alert"></p></form>';
    }
    $query=contractArchiveDb()->prepare('SELECT id,contract_version,kind,filename,sha256,uploaded_at FROM contract_files WHERE session_id=? ORDER BY uploaded_at DESC,id');$query->execute([$id]);
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $file)$html.='<p>Wersja '.(int)$file['contract_version'].' · '.$e($file['kind']==='signed'?'Podpisana obustronnie':'Dodatkowy dokument').' · '.$e((new DateTimeImmutable('@'.$file['uploaded_at']))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y H:i')).' · <a href="'.$url.'?session='.$e($id).'&amp;format=archive-file&amp;file='.$e($file['id']).'">'.$e($file['filename']).'</a><br><small>SHA-256: '.$e($file['sha256']).'</small></p>';
    return $html.'</details>';
}
function contractCustomClauses(string $raw): array {
    if(trim($raw)==='')return [];
    try{$items=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new InvalidArgumentException('Popraw format dodatkowych postanowień.');}
    if(!str_starts_with(ltrim($raw),'[')||!is_array($items)||!array_is_list($items)||count($items)>30)throw new InvalidArgumentException('Dodatkowe postanowienia: lista maksymalnie 30 pozycji.');
    foreach($items as $item){
        if(!is_array($item)||array_diff(array_keys($item),['title','text'])||!is_string($item['title']??null)||!is_string($item['text']??null)||trim($item['title'])===''||trim($item['text'])===''||mb_strlen($item['title'])>200||mb_strlen($item['text'])>5000)throw new InvalidArgumentException('Uzupełnij tytuł i treść każdego dodatkowego postanowienia (tytuł do 200, treść do 5000 znaków).');
    }
    return $items;
}
