<?php
declare(strict_types=1);
require_once __DIR__.'/project-model.php';
require_once __DIR__.'/project-templates.php';

/** Remove local case data without leaving a legacy JSON that could be imported again. */
function deleteProjectSession(string $id): bool {
    if(!preg_match('/^[a-f0-9]{32}$/',$id)) throw new InvalidArgumentException('Niepoprawny identyfikator rozmowy.');
    $directory=__DIR__.'/storage';
    $lock=fopen($directory.'/'.$id.'.lock','c');
    if($lock===false || !flock($lock,LOCK_EX|LOCK_NB)) { if(is_resource($lock)) fclose($lock); throw new DomainException('Sprawa jest teraz przetwarzana. Spróbuj ponownie później.'); }
    $db=projectDb(); projectTemplatesDb();
    try {
        $db->exec('BEGIN IMMEDIATE');
        $running=$db->prepare("SELECT COUNT(*) FROM project_jobs WHERE session_id=? AND state IN ('queued','running')");
        $running->execute([$id]);
        if((int)$running->fetchColumn()>0) throw new DomainException('Sprawa ma zadania w toku lub kolejce. Najpierw je zakończ.');
        $external=$db->prepare("SELECT COUNT(*) FROM project_jobs WHERE session_id=? AND kind IN ('create_repository','provision_preview') AND attempts>0");
        $external->execute([$id]);
        if((int)$external->fetchColumn()>0) throw new DomainException('Sprawa może mieć zasoby w GitHub, Cloudflare lub VPS. Zarchiwizuj ją do czasu sprawdzenia i usunięcia tych zasobów.');
        $exists=$db->prepare('SELECT 1 FROM sessions WHERE id=?'); $exists->execute([$id]);
        $file=$directory.'/'.$id.'.json';
        $found=(bool)$exists->fetchColumn() || is_file($file);
        if(!$found) { $db->exec('ROLLBACK'); return false; }
        $db->prepare("DELETE FROM project_templates WHERE source_session=? AND status='proposed'")->execute([$id]);
        $db->prepare("UPDATE project_templates SET source_session=NULL WHERE source_session=? AND status='active'")->execute([$id]);
        foreach(['project_jobs','project_events','project_cases'] as $table) $db->prepare('DELETE FROM '.$table.' WHERE session_id=?')->execute([$id]);
        $db->prepare('DELETE FROM sessions WHERE id=?')->execute([$id]);
        if(is_file($file) && !unlink($file)) throw new RuntimeException('Nie udało się usunąć starego pliku rozmowy.');
        $db->exec('COMMIT');
        return true;
    } catch(Throwable $error) { if($db->inTransaction()) $db->exec('ROLLBACK'); throw $error; }
    finally { flock($lock,LOCK_UN); fclose($lock); }
}
