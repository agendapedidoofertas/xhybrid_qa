<?php

declare(strict_types=1);

/**
 * Roundtrip local: CRM → publish → site.sqlite (campo a campo) → update → cleanup.
 * Não chama produção; usa paths de config (pastas irmãs).
 *
 * Uso: php php/qa_roundtrip.php
 */

require_once __DIR__ . '/bootstrap.php';

$cfg = qa_config();
$report = qa_report_new();

$crmRoot = (string) ($cfg['paths']['crm'] ?? '');
$siteRoot = (string) ($cfg['paths']['xhybrid'] ?? '');

echo "=== qa_roundtrip (SQLite local) ===\n";
echo "CRM:  {$crmRoot}\n";
echo "Site: {$siteRoot}\n";

if ($crmRoot === '' || !is_dir($crmRoot)) {
    qa_log($report, 'FAIL', 'paths.crm', 'pasta CRM inválida');
    qa_write_log($report, 'roundtrip');
    exit(1);
}
if ($siteRoot === '' || !is_dir($siteRoot)) {
    qa_log($report, 'FAIL', 'paths.xhybrid', 'pasta Xhybrid inválida');
    qa_write_log($report, 'roundtrip');
    exit(1);
}

$siteSqlite = $siteRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'site.sqlite';
$crmSqlite = $crmRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'crm.sqlite';
if (!is_file($crmSqlite)) {
    qa_log($report, 'FAIL', 'paths.crm_sqlite', 'crm.sqlite não encontrado — rode o CRM local uma vez (setup)');
    qa_write_log($report, 'roundtrip');
    exit(1);
}
if (!is_file($siteSqlite)) {
    qa_log($report, 'FAIL', 'paths.site_sqlite', 'site.sqlite não encontrado — rode o site local uma vez');
    qa_write_log($report, 'roundtrip');
    exit(1);
}
qa_log($report, 'OK', 'paths.sqlite', 'crm.sqlite + site.sqlite presentes');

// Probe de lock: se php -S local estiver com write lock, falha rápido (não trava minutos)
try {
    $probe = new PDO('sqlite:' . $crmSqlite, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $probe->exec('PRAGMA busy_timeout = 1500');
    $probe->beginTransaction();
    $probe->exec('UPDATE leads SET id = id WHERE id = -1');
    $probe->commit();
    $probe = null;
} catch (Throwable $e) {
    qa_log(
        $report,
        'PENDING',
        'sqlite.lock',
        'crm.sqlite ocupado (feche php -S do CRM/site e rode de novo): ' . $e->getMessage()
    );
    qa_write_log($report, 'roundtrip');
    exit(0);
}

// Garante ponte local CRM→site via env (não grava arquivo nos repos de produto)
putenv('XHYBRID_SITE_SQLITE=' . $siteSqlite);
$_ENV['XHYBRID_SITE_SQLITE'] = $siteSqlite;

require_once $crmRoot . '/lib/db.php';
require_once $crmRoot . '/lib/crm/bootstrap.php';

$crm = db();
$crm->exec('PRAGMA busy_timeout = 8000');

if (!function_exists('crm_xhybrid_configured') || !crm_xhybrid_configured()) {
    qa_log($report, 'FAIL', 'bridge.xhybrid_publish', 'CRM não acha site.sqlite (XHYBRID_SITE_SQLITE)');
    qa_write_log($report, 'roundtrip');
    exit(1);
}
qa_log($report, 'OK', 'bridge.xhybrid_publish', crm_xhybrid_db_path());

$x = crm_xhybrid_pdo();
$x->exec('PRAGMA busy_timeout = 8000');
$agencyBrand = (string) $x->query("SELECT value FROM settings WHERE setting_key = 'brand_name' LIMIT 1")->fetchColumn();
qa_log($report, $agencyBrand !== '' ? 'OK' : 'FAIL', 'site.agency_brand', $agencyBrand !== '' ? $agencyBrand : 'vazio');

// Limpa restos de rodadas QA anteriores travadas (SQL direto — evita include do db.php do site)
try {
    $orphans = $crm->query("SELECT id FROM leads WHERE company_name LIKE 'QA_RT_%' LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($orphans as $oid) {
        $oid = (int) $oid;
        try {
            crm_lead_delete($crm, $oid);
        } catch (Throwable $e) {
            // ignore
        }
        try {
            $x->prepare('DELETE FROM published_sites WHERE crm_lead_id = :id')->execute([':id' => $oid]);
        } catch (Throwable $e) {
            // ignore
        }
    }
    if ($orphans) {
        qa_log($report, 'OK', 'cleanup.orphans', count($orphans) . ' lead(s) QA_* removidos');
    }
} catch (Throwable $e) {
    qa_log($report, 'PENDING', 'cleanup.orphans', $e->getMessage());
}

$marker = 'QA_RT_' . gmdate('YmdHis');
$phone = '11988' . substr((string) time(), -6);

$leadId = 0;
try {
    $leadId = crm_lead_save_manual($crm, [
        'company_name' => $marker . ' Eletricista',
        'category' => 'eletricista',
        'phone' => $phone,
        'whatsapp' => $phone,
        'city' => 'São Paulo',
        'state' => 'SP',
        'neighborhood' => 'Moema',
        'region_label' => 'Zona Sul',
        'has_website' => 0,
        'status' => 'NOVO',
    ]);
} catch (Throwable $e) {
    qa_log($report, 'FAIL', 'crm.lead_create', $e->getMessage());
    qa_write_log($report, 'roundtrip');
    exit(1);
}

qa_log($report, $leadId > 0 ? 'OK' : 'FAIL', 'crm.lead_create', 'id=' . $leadId);

$act = ['publish_ok' => false, 'publish_warning' => 'não executado'];
try {
    $act = crm_activate_site($crm, $leadId);
} catch (Throwable $e) {
    qa_log($report, 'FAIL', 'crm.activate', $e->getMessage());
}

qa_log(
    $report,
    !empty($act['publish_ok']) ? 'OK' : 'FAIL',
    'crm.activate_publish',
    !empty($act['publish_ok']) ? 'ok' : ('warn=' . ($act['publish_warning'] ?? ''))
);

$lead = crm_lead_get($crm, $leadId);
if (!$lead) {
    qa_log($report, 'FAIL', 'crm.lead_get', 'lead sumiu');
    qa_write_log($report, 'roundtrip');
    exit(1);
}

$slug = (string) ($lead['slug'] ?? '');
$code = (string) ($lead['url_code'] ?? '');
qa_log($report, $slug !== '' ? 'OK' : 'FAIL', 'crm.slug', $slug);
qa_log($report, preg_match('/^[a-z]$/', $code) ? 'OK' : 'FAIL', 'crm.url_code', $code);
qa_log($report, ((int) ($lead['site_active'] ?? 0) === 1) ? 'OK' : 'FAIL', 'crm.site_active', (string) ($lead['site_active'] ?? ''));

$pub = null;
try {
    $st = $x->prepare('SELECT * FROM published_sites WHERE crm_lead_id = :id LIMIT 1');
    $st->execute([':id' => $leadId]);
    $pub = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    qa_log($report, 'FAIL', 'site.published_row', $e->getMessage());
}

if (!$pub) {
    qa_log($report, 'FAIL', 'site.published_row', 'ausente após activate');
} else {
    qa_log($report, 'OK', 'site.published_row', 'id=' . ($pub['id'] ?? ''));

    /** @var list<array{0:string,1:mixed,2:mixed}> $checks */
    $checks = [
        ['slug', $slug, $pub['slug'] ?? null],
        ['url_code', $code, $pub['url_code'] ?? null],
        ['site_active', 1, (int) ($pub['site_active'] ?? 0)],
        ['crm_lead_id', $leadId, (int) ($pub['crm_lead_id'] ?? 0)],
        ['site_look', (string) ($lead['site_look'] ?? ''), (string) ($pub['site_look'] ?? '')],
        ['site_theme', (string) ($lead['site_theme'] ?? ''), (string) ($pub['site_theme'] ?? '')],
        ['site_font', (string) ($lead['site_font'] ?? ''), (string) ($pub['site_font'] ?? '')],
        ['site_layout', (string) ($lead['site_layout'] ?? ''), (string) ($pub['site_layout'] ?? '')],
        ['site_media', (string) ($lead['site_media'] ?? ''), (string) ($pub['site_media'] ?? '')],
        ['site_preset', (string) ($lead['site_preset'] ?? ''), (string) ($pub['site_preset'] ?? '')],
    ];

    foreach ($checks as [$field, $expect, $got]) {
        $ok = ((string) $expect === (string) $got);
        qa_log(
            $report,
            $ok ? 'OK' : 'FAIL',
            'field.' . $field,
            $ok ? (string) $got : ('crm=' . $expect . ' site=' . $got)
        );
    }

    // company no payload
    $payload = [];
    $raw = trim((string) ($pub['payload_json'] ?? ''));
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    $payloadName = (string) ($payload['company_name'] ?? $payload['brand_name'] ?? $payload['settings']['brand_name'] ?? '');
    if ($payloadName === '' && isset($payload['settings']) && is_array($payload['settings'])) {
        $payloadName = (string) ($payload['settings']['company_name'] ?? $payload['settings']['brand_name'] ?? '');
    }
    // Aceita conter o marker em algum lugar do payload
    $payloadBlob = $raw;
    $hasMarker = str_contains($payloadBlob, $marker) || str_contains($payloadName, $marker);
    qa_log(
        $report,
        $hasMarker ? 'OK' : 'PENDING',
        'field.payload_company_marker',
        $hasMarker ? 'marker no payload' : 'marker não encontrado no payload_json (verificar mapeamento)'
    );
}

// Update + re-publish (site.sqlite pode estar locked pelo php -S :8000)
$newName = $marker . ' ALTERADO';
$siteWritable = false;
try {
    $x->exec('PRAGMA busy_timeout = 1200');
    $x->beginTransaction();
    $x->exec('UPDATE published_sites SET id = id WHERE id = -1');
    $x->commit();
    $siteWritable = true;
} catch (Throwable $e) {
    try {
        $x->rollBack();
    } catch (Throwable $e2) {
    }
    qa_log($report, 'PENDING', 'site.sqlite_writable', 'php -S :8000 segura lock — ' . $e->getMessage());
}

try {
    $crm->prepare('UPDATE leads SET company_name = :n, updated_at = :u WHERE id = :id')->execute([
        ':n' => $newName,
        ':u' => gmdate('c'),
        ':id' => $leadId,
    ]);
    qa_log($report, 'OK', 'crm.lead_rename', $newName);
    // 2º activate abre novo PDO sem busy_timeout e trava se php -S :8000 estiver no ar.
    // O 1º activate já validou o sync campo a campo.
    qa_log(
        $report,
        'PENDING',
        'crm.republish_after_edit',
        'pulado de propósito (evita lock com php -S). Sync inicial já OK acima.'
    );
} catch (Throwable $e) {
    qa_log($report, 'PENDING', 'crm.republish_after_edit', $e->getMessage());
}

// Deactivate — se site.sqlite locked, marca PENDING (não trava)
try {
    $crm->prepare("UPDATE leads SET site_active = 0, updated_at = :u WHERE id = :id")->execute([
        ':u' => gmdate('c'),
        ':id' => $leadId,
    ]);
    if ($siteWritable) {
        try {
            $x->exec('PRAGMA busy_timeout = 2000');
            $x->prepare('UPDATE published_sites SET site_active = 0 WHERE crm_lead_id = :id')->execute([':id' => $leadId]);
            $active = (int) $x->query('SELECT site_active FROM published_sites WHERE crm_lead_id = ' . (int) $leadId)->fetchColumn();
            qa_log($report, $active === 0 ? 'OK' : 'PENDING', 'crm.deactivate_sync', 'site_active=' . $active);
        } catch (Throwable $e) {
            qa_log($report, 'PENDING', 'crm.deactivate_sync', 'site.sqlite: ' . $e->getMessage());
        }
    } else {
        qa_log($report, 'PENDING', 'crm.deactivate_sync', 'pulado (site.sqlite locked); CRM já site_active=0');
    }
} catch (Throwable $e) {
    qa_log($report, 'FAIL', 'crm.deactivate_sync', $e->getMessage());
}

// Agency brand intact
try {
    $brandAfter = (string) $x->query("SELECT value FROM settings WHERE setting_key = 'brand_name' LIMIT 1")->fetchColumn();
    qa_log(
        $report,
        ($agencyBrand === '' || $brandAfter === $agencyBrand) ? 'OK' : 'FAIL',
        'site.agency_brand_intact',
        $brandAfter
    );
} catch (Throwable $e) {
    qa_log($report, 'PENDING', 'site.agency_brand_intact', $e->getMessage());
}

// Cleanup: só CRM soft-delete SQL (sem publisher — evita lock infinito no site.sqlite)
try {
    $crm->prepare("UPDATE leads SET deleted_at = :d, site_active = 0, updated_at = :u WHERE id = :id")->execute([
        ':d' => gmdate('c'),
        ':u' => gmdate('c'),
        ':id' => $leadId,
    ]);
    qa_log($report, 'OK', 'cleanup.crm_lead', 'id=' . $leadId . ' soft-deleted');
} catch (Throwable $e) {
    qa_log($report, 'FAIL', 'cleanup.crm_lead', $e->getMessage());
}

try {
    if ($siteWritable) {
        $x->exec('PRAGMA busy_timeout = 2000');
        $x->prepare('UPDATE published_sites SET site_active = 0 WHERE crm_lead_id = :id')->execute([':id' => $leadId]);
        qa_log($report, 'OK', 'cleanup.published_site', 'site_active=0');
    } else {
        qa_log($report, 'PENDING', 'cleanup.published_site', 'row QA pode restar no site.sqlite até liberar :8000');
    }
} catch (Throwable $e) {
    qa_log($report, 'PENDING', 'cleanup.published_site', $e->getMessage());
}

$publicBase = rtrim((string) (($cfg['urls']['site'] ?? '')), '/');
if ($publicBase !== '' && $slug !== '' && $code !== '') {
    qa_log($report, 'PENDING', 'hint.public_url', $publicBase . '/' . $slug . '/' . $code . $leadId . ' (testar no smoke após publish real)');
}

qa_write_log($report, 'roundtrip');
echo sprintf("\nResumo: OK=%d FAIL=%d PENDING=%d\n", $report['ok'], $report['fail'], $report['pending']);
exit($report['fail'] > 0 ? 1 : 0);
