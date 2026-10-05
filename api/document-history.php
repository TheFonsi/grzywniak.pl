<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/contract-model.php';
require_once __DIR__.'/offer-agreement.php';
require_once __DIR__.'/admin-shell.php';
$user=getenv('ADMIN_USERNAME')?:''; $password=getenv('ADMIN_PASSWORD')?:'';
if($user===''||$password===''||!hash_equals($user,(string)($_SERVER['PHP_AUTH_USER']??''))||!hash_equals($password,(string)($_SERVER['PHP_AUTH_PW']??''))) {
    header('WWW-Authenticate: Basic realm="Grzywniak Discovery"'); http_response_code(401); exit;
}
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { http_response_code(405); exit('Archiwum jest tylko do odczytu.'); }
$id=(string)($_GET['session']??'');
$s=preg_match('/^[a-f0-9]{32}$/',$id)?readSession($id):null;
if(!$s) { http_response_code(404); exit('Nie znaleziono sprawy.'); }
// Expose older archived documents without inventing their missing historical inputs or writing on GET.
foreach(['analysis'=>'internalAnalysis','offer'=>'offer','contract'=>'contract'] as $kind=>$key) {
    $versions=$s[$kind==='analysis'?'analysisVersions':$key.'Versions']??[];
    if(is_array($s[$key]??null)) $versions[]=$s[$key];
    foreach($versions as $doc) if(is_array($doc)) documentArchivePut($s,$kind,$doc,$doc['sourceRefs']??[]);
}
$archive=$s['documentArchive']??[]; $ref=(string)($_GET['ref']??'');
if($ref!==''&&(!preg_match('/^[a-f0-9]{64}$/',$ref)||!isset($archive[$ref]))) { http_response_code(404); exit('Nie znaleziono zapisanej wersji dokumentu.'); }
$record=$ref!==''?$archive[$ref]:null;
function historyEscape(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function historyLabel(string $key): string {
    $fields=contractFacts()+offerAgreementFields();
    if(isset($fields[$key])) return $fields[$key]['label'];
    return ['title'=>'Tytuł','items'=>'Zakres','name'=>'Nazwa','email'=>'E-mail','phone'=>'Telefon','net'=>'Netto (PLN)','gross'=>'Brutto (PLN)','vat'=>'VAT (PLN)','vatRate'=>'Stawka VAT (%)','hours'=>'Szacowane godziny','deposit'=>'Zaliczka netto (PLN)','depositRate'=>'Zaliczka (%)','source'=>'Źródło','answer'=>'Odpowiedź','summary'=>'Podsumowanie','projectSummary'=>'Cel projektu','businessProblem'=>'Problem do rozwiązania','businessGoals'=>'Cele biznesowe','targetUsers'=>'Użytkownicy','mustHaveFeatures'=>'Najważniejszy zakres','budget'=>'Budżet','deadline'=>'Termin','contactName'=>'Kontakt / firma','contactEmail'=>'E-mail','contactPhone'=>'Telefon','materialsTerms'=>'Materiały i współpraca','publicationPreference'=>'Sposób publikacji','domainName'=>'Domena','hostingExpectations'=>'Oczekiwania dotyczące hostingu','supportExpectations'=>'Oczekiwania dotyczące wsparcia'][$key]??$key;
}
function historyValue(mixed $value): string {
    if(!is_array($value)) return '<p>'.nl2br(historyEscape(is_bool($value)?($value?'Tak':'Nie'):($value??'—'))).'</p>';
    $html='<ul>';
    foreach($value as $key=>$item) {
        if(!is_int($key)&&is_string($item)) $item=contractFacts()[$key]['options'][$item]??offerAgreementFields()[$key]['options'][$item]??$item;
        $html.='<li>'.(!is_int($key)?'<strong>'.historyEscape(historyLabel((string)$key)).':</strong> ':'').historyValue($item).'</li>';
    }
    return $html.'</ul>';
}
$labels=['brief'=>'Brief','analysis'=>'Analiza','offer'=>'Oferta','contract'=>'Umowa'];
$date=static fn($v)=>(new DateTimeImmutable('@'.(int)$v))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('d.m.Y H:i:s');
ob_start();
?><!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Archiwum dokumentów — Grzywniak</title><style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#080e19;color:#e5edff;font:15px/1.6 system-ui}main{max-width:1150px;margin:auto;padding:28px 20px}a{color:#a7bcff}h1{margin:0 0 8px}h2{font-size:18px}p{margin:6px 0;white-space:pre-wrap;overflow-wrap:anywhere}.muted{color:#99abc8}.layout{display:grid;grid-template-columns:280px 1fr;gap:20px}.panel{background:#111b2c;border:1px solid #304365;border-radius:14px;padding:20px;min-width:0}nav ul{list-style:none;padding:0}nav a{display:block;padding:9px;border-radius:8px;text-decoration:none}nav a:hover,nav a[aria-current]{background:#253d68}section{border-bottom:1px solid #304365;padding:8px 0 16px}small{display:block;color:#99abc8}.badge{display:inline-block;padding:4px 9px;border:1px solid #4a6185;border-radius:20px;font-size:12px}.document-sources a{display:inline-block;margin:3px 0}li p{display:inline}.back{display:inline-block;margin:14px 0}details{margin:12px 0}@media(max-width:750px){.layout{grid-template-columns:1fr}}
</style></head><body><main><h1>Archiwum dokumentów</h1><p class="muted">Zapisane wersje i ich źródła. Podgląd tylko do odczytu.</p><a class="back" href="<?=historyEscape(apiPath('admin.php').'?view=all&session='.$id)?>">← Wróć do briefu i szczegółów</a><div class="layout"><nav class="panel" aria-label="Wersje dokumentów">
<?php foreach($labels as $kind=>$label): ?><h2><?=historyEscape($label)?></h2><ul><?php
$rows=array_filter($archive,static fn($r)=>($r['kind']??'')===$kind);
uasort($rows,static fn($a,$b)=>((int)$b['version']<=>(int)$a['version'])?:((int)$b['capturedAt']<=>(int)$a['capturedAt']));
foreach($rows as $key=>$row): ?><li><a <?=($ref===$key?'aria-current="page"':'')?> href="<?=historyEscape(documentHistoryUrl($id,$key))?>">Wersja <?=(int)$row['version']?><small><?=historyEscape($row['data']['status']??'Zapis źródłowy')?> · <?=historyEscape(substr($key,0,8))?></small></a></li><?php endforeach; if(!$rows): ?><li class="muted">Brak zapisanych wersji.</li><?php endif; ?></ul><?php endforeach; ?></nav><article class="panel">
<?php if(!$record): ?><h2>Wybierz dokument i wersję</h2><p>Link „Na podstawie” otwiera dokładnie zapisany dokument źródłowy, również po kolejnych zmianach.</p><p class="muted">Dla wcześniejszych dokumentów bez zapisanych powiązań nie przypisujemy bieżącego briefu jako historycznego źródła.</p>
<?php else: $kind=$record['kind']; $doc=$record['data']; ?><span class="badge">Tylko do odczytu</span><h2><?=historyEscape($labels[$kind]??$kind)?> · wersja <?=(int)$record['version']?></h2><p class="muted">Zapis w archiwum: <?=historyEscape($date($record['capturedAt']))?> · identyfikator <?=historyEscape(substr($ref,0,12))?></p>
<?php if($kind!=='brief') echo documentSourceLinks(['sourceRefs'=>$record['sources']],$id); else echo '<p class="muted">Dane briefu zapisane jako źródło dokumentów projektu.</p>'; ?>
<?php if($kind==='brief'):
foreach($doc['summary']['sections']??[] as $part): ?><section><h2><?=historyEscape($part['title']??'Brief')?></h2><?=historyValue($part['content']??[])?></section><?php endforeach; ?>
<details><summary>Zebrane dane briefu</summary><?=historyValue($doc['projectState']??[])?></details>
<?php else:
$fields=match($kind) {
    'analysis'=>['summary'=>'Podsumowanie','readiness'=>'Gotowość','missingInformation'=>'Brakujące informacje','risks'=>'Ryzyka','recommendedScope'=>'Rekomendowany zakres','optionalScope'=>'Opcjonalnie później','questionsForClient'=>'Pytania klienta','nextStep'=>'Następny krok','confirmedDecisions'=>'Ustalenia administratora','sections'=>'Zmiany opracowane dla oferty'],
    'offer'=>['project'=>'Projekt','summary'=>'Podsumowanie','sections'=>'Zakres oferty','pricing'=>'Wycena','payment'=>'Płatności','agreement'=>'Warunki realizacji','decisionCoverage'=>'Przypisane ustalenia administratora','dependencyReview'=>'Przegląd po zmianie zakresu','contact'=>'Kontakt klienta'],
    'contract'=>contractFields()+['facts'=>'Uzgodnione dane i warunki','commercialSnapshot'=>'Pakiet warunków zaakceptowanej oferty'],
    default=>[],
};
foreach($fields as $key=>$label) if(isset($doc[$key])): ?><section><h2><?=historyEscape($label)?></h2><?=historyValue($doc[$key])?></section><?php endif;
endif; endif; ?></article></div></main></body></html><?php
echo adminShellPage((string)ob_get_clean(),'briefs');
