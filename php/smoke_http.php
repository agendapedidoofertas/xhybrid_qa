<?php

declare(strict_types=1);

/**
 * Varredura HTTP geral — layout Hostgator atual (subpastas).
 * Não altera bancos; só GET.
 *
 * Uso: php php/smoke_http.php
 */

require_once __DIR__ . '/bootstrap.php';

$cfg = qa_config();
$report = qa_report_new();
$urls = $cfg['urls'] ?? [];
$exp = $cfg['expectations'] ?? [];
$httpCfg = $cfg['http'] ?? [];

$site = rtrim((string) ($urls['site'] ?? ''), '/');
$siteAdmin = rtrim((string) ($urls['site_admin'] ?? ''), '/');
$crmAdmin = rtrim((string) ($urls['crm_admin'] ?? ''), '/');
$apex = rtrim((string) ($urls['apex'] ?? ''), '/');
$crmSub = rtrim((string) ($urls['crm_subdomain'] ?? ''), '/');

/**
 * @param list<int> $accept
 */
function smoke_expect(array &$report, string $id, array $res, array $accept, ?callable $extra = null): void
{
    if ($res['error'] !== '') {
        qa_log($report, 'FAIL', $id, 'erro rede: ' . $res['error']);
        return;
    }
    $st = $res['status'];
    if (!in_array($st, $accept, true)) {
        qa_log($report, 'FAIL', $id, "HTTP {$st} (esperado " . implode('|', $accept) . ') final=' . $res['final_url']);
        return;
    }
    if ($extra !== null) {
        $msg = $extra($res);
        if (is_string($msg) && $msg !== '') {
            qa_log($report, 'FAIL', $id, $msg);
            return;
        }
    }
    qa_log($report, 'OK', $id, "HTTP {$st} → {$res['final_url']}");
}

echo "=== smoke_http (varredura geral) ===\n";

// --- Apex (ainda NÃO é o Xhybrid) ---
if ($apex !== '') {
    $res = qa_http_get($apex . '/', $httpCfg);
    $apexIsApp = !empty($exp['apex_is_xhybrid']);
    if (!$apexIsApp) {
        if ($res['error'] !== '') {
            qa_log($report, 'PENDING', 'apex.root', 'apex inacessível por enquanto: ' . $res['error']);
        } elseif ($res['status'] >= 200 && $res['status'] < 500) {
            qa_log(
                $report,
                'PENDING',
                'apex.root',
                "HTTP {$res['status']} — apex NÃO é o app ainda (esperado). App em {$site}/"
            );
        } else {
            qa_log($report, 'PENDING', 'apex.root', "HTTP {$res['status']} — apex ainda fora do app");
        }
    } else {
        smoke_expect($report, 'apex.root', $res, [200]);
    }
}

// --- Site Xhybrid (subpasta) ---
if ($site !== '') {
    smoke_expect($report, 'site.home', qa_http_get($site . '/', $httpCfg), [200], static function (array $res): string {
        if (qa_body_has($res['body'], 'Esse link saiu do ar') && !qa_body_has($res['body'], 'xhybrid')) {
            return 'body parece 404 do app';
        }
        return '';
    });

    smoke_expect($report, 'site.index_html', qa_http_get($site . '/index.html', $httpCfg), [200]);

    // Assets críticos
    foreach (['/css/styles.css', '/js/main.js', '/js/data.js', '/router.php'] as $path) {
        $accept = $path === '/router.php' ? [200, 302, 301] : [200];
        smoke_expect($report, 'site.asset' . $path, qa_http_get($site . $path, $httpCfg), $accept);
    }

    // data/ deve ser bloqueado
    $sqlite = qa_http_get($site . '/data/site.sqlite', array_merge($httpCfg, ['follow_redirects' => false]));
    if ($sqlite['error'] !== '') {
        qa_log($report, 'FAIL', 'site.data_sqlite_blocked', $sqlite['error']);
    } elseif (in_array($sqlite['status'], [401, 403, 404], true)) {
        qa_log($report, 'OK', 'site.data_sqlite_blocked', 'HTTP ' . $sqlite['status']);
    } elseif ($sqlite['status'] === 200) {
        qa_log($report, 'FAIL', 'site.data_sqlite_blocked', 'HTTP 200 — SQLite público!');
    } else {
        qa_log($report, 'PENDING', 'site.data_sqlite_blocked', 'HTTP ' . $sqlite['status']);
    }

    // Páginas estáticas
    foreach (['/planos.html', '/termos.html', '/privacidade.html', '/404.html'] as $page) {
        smoke_expect($report, 'site.page' . $page, qa_http_get($site . $page, $httpCfg), [200]);
    }
}

// --- Admin site ---
if ($siteAdmin !== '') {
    $login = qa_http_get($siteAdmin . '/login.php', $httpCfg);
    smoke_expect($report, 'site_admin.login', $login, [200], static function (array $res): string {
        if (!qa_body_has($res['body'], 'password') && !qa_body_has($res['body'], 'senha') && !qa_body_has($res['body'], 'login')) {
            return 'não parece formulário de login';
        }
        return '';
    });

    $adminRoot = qa_http_get($siteAdmin . '/', $httpCfg);
    // Sem cookie: redirect login (302) ou 200 login
    smoke_expect($report, 'site_admin.root', $adminRoot, [200, 301, 302]);

    foreach (['/appearance.php', '/leads.php', '/texts.php', '/setup.php'] as $p) {
        $r = qa_http_get($siteAdmin . $p, $httpCfg);
        // Sem auth: login redirect OK; 500 = FAIL
        if ($r['error'] !== '') {
            qa_log($report, 'FAIL', 'site_admin' . $p, $r['error']);
        } elseif ($r['status'] >= 500) {
            qa_log($report, 'FAIL', 'site_admin' . $p, 'HTTP ' . $r['status']);
        } elseif (in_array($r['status'], [200, 301, 302, 303, 401, 403], true)) {
            qa_log($report, 'OK', 'site_admin' . $p, 'HTTP ' . $r['status'] . ' (sem sessão)');
        } else {
            qa_log($report, 'PENDING', 'site_admin' . $p, 'HTTP ' . $r['status']);
        }
    }
}

// --- CRM em subpasta (layout atual) ---
if ($crmAdmin !== '') {
    $crmLogin = qa_http_get($crmAdmin . '/login.php', $httpCfg);
    smoke_expect($report, 'crm_admin.login', $crmLogin, [200], static function (array $res): string {
        if (!qa_body_has($res['body'], 'password') && !qa_body_has($res['body'], 'senha') && !qa_body_has($res['body'], 'login')) {
            return 'não parece formulário de login';
        }
        return '';
    });

    smoke_expect($report, 'crm_admin.root', qa_http_get($crmAdmin . '/', $httpCfg), [200, 301, 302]);

    foreach (['/leads.php', '/ops.php', '/capture.php', '/setup.php'] as $p) {
        $r = qa_http_get($crmAdmin . $p, $httpCfg);
        if ($r['error'] !== '') {
            qa_log($report, 'FAIL', 'crm_admin' . $p, $r['error']);
        } elseif ($r['status'] >= 500) {
            qa_log($report, 'FAIL', 'crm_admin' . $p, 'HTTP ' . $r['status']);
        } elseif (in_array($r['status'], [200, 301, 302, 303, 401, 403], true)) {
            qa_log($report, 'OK', 'crm_admin' . $p, 'HTTP ' . $r['status'] . ' (sem sessão)');
        } else {
            qa_log($report, 'PENDING', 'crm_admin' . $p, 'HTTP ' . $r['status']);
        }
    }

    // CRM data protegido
    $crmBase = preg_replace('#/admin$#', '', $crmAdmin) ?: $crmAdmin;
    $crmDb = qa_http_get($crmBase . '/data/crm.sqlite', array_merge($httpCfg, ['follow_redirects' => false]));
    if ($crmDb['error'] !== '') {
        qa_log($report, 'PENDING', 'crm.data_sqlite_blocked', $crmDb['error']);
    } elseif (in_array($crmDb['status'], [401, 403, 404], true)) {
        qa_log($report, 'OK', 'crm.data_sqlite_blocked', 'HTTP ' . $crmDb['status']);
    } elseif ($crmDb['status'] === 200) {
        qa_log($report, 'FAIL', 'crm.data_sqlite_blocked', 'HTTP 200 — SQLite público!');
    } else {
        qa_log($report, 'PENDING', 'crm.data_sqlite_blocked', 'HTTP ' . $crmDb['status']);
    }
}

// --- Subdomínio CRM (ainda não) ---
if ($crmSub !== '') {
    $ready = !empty($exp['crm_subdomain_ready']);
    $res = qa_http_get($crmSub . '/', $httpCfg);
    if (!$ready) {
        if ($res['error'] !== '' || $res['status'] === 0 || $res['status'] >= 400) {
            qa_log(
                $report,
                'PENDING',
                'crm.subdomain',
                'ainda não é subdomínio — use ' . $crmAdmin . '/ (erro/status=' . ($res['error'] ?: (string) $res['status']) . ')'
            );
        } else {
            qa_log(
                $report,
                'PENDING',
                'crm.subdomain',
                "HTTP {$res['status']} — subdomínio respondeu, mas expectation=false; migrar DNS depois"
            );
        }
    } else {
        smoke_expect($report, 'crm.subdomain', $res, [200, 301, 302]);
    }
}

// --- Lead público: só usa SQLite local se env=local; em hostgator o banco local ≠ produção ---
$envName = (string) ($cfg['env'] ?? 'hostgator');
$xhybridPath = (string) (($cfg['paths']['xhybrid'] ?? ''));
$siteSqlite = $xhybridPath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'site.sqlite';
if ($envName !== 'local') {
    qa_log(
        $report,
        'PENDING',
        'site.lead_public',
        'env=' . $envName . ' — probe de lead exige slug de produção (SQLite local não reflete Hostgator). Ex.: configure urls.probe_lead'
    );
    $probeLead = trim((string) (($urls['probe_lead'] ?? '')));
    if ($probeLead !== '' && $site !== '') {
        $leadUrl = str_starts_with($probeLead, 'http') ? $probeLead : ($site . '/' . ltrim($probeLead, '/'));
        $lr = qa_http_get($leadUrl, $httpCfg);
        if ($lr['error'] !== '') {
            qa_log($report, 'FAIL', 'site.lead_public_cfg', $lr['error'] . ' url=' . $leadUrl);
        } elseif ($lr['status'] === 200 && !qa_body_has($lr['body'], 'Esse link saiu do ar')) {
            qa_log($report, 'OK', 'site.lead_public_cfg', $leadUrl);
        } else {
            qa_log($report, 'FAIL', 'site.lead_public_cfg', "HTTP {$lr['status']} url={$leadUrl}");
        }
    }
} elseif (is_file($siteSqlite) && $site !== '') {
    try {
        $pdo = new PDO('sqlite:' . $siteSqlite, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA busy_timeout = 2000');
        $pdo->exec('PRAGMA query_only = ON');
        $row = $pdo->query(
            "SELECT slug, url_code, crm_lead_id, site_active FROM published_sites WHERE site_active = 1 ORDER BY updated_at DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && !empty($row['slug']) && !empty($row['url_code']) && (int) $row['crm_lead_id'] > 0) {
            $leadPath = '/' . $row['slug'] . '/' . $row['url_code'] . (int) $row['crm_lead_id'];
            $leadUrl = $site . $leadPath;
            $lr = qa_http_get($leadUrl, $httpCfg);
            if ($lr['error'] !== '') {
                qa_log($report, 'FAIL', 'site.lead_public', $lr['error'] . ' url=' . $leadUrl);
            } elseif ($lr['status'] === 200 && !qa_body_has($lr['body'], 'Esse link saiu do ar')) {
                qa_log($report, 'OK', 'site.lead_public', $leadUrl);
            } elseif ($lr['status'] === 403 || qa_body_has($lr['body'], 'inativ')) {
                qa_log($report, 'PENDING', 'site.lead_public', 'inativo/grace? ' . $leadUrl . ' HTTP ' . $lr['status']);
            } else {
                qa_log($report, 'FAIL', 'site.lead_public', "HTTP {$lr['status']} url={$leadUrl}");
            }
        } else {
            qa_log($report, 'PENDING', 'site.lead_public', 'nenhum published_sites ativo no SQLite local');
        }
    } catch (Throwable $e) {
        qa_log($report, 'PENDING', 'site.lead_public', 'sqlite local: ' . $e->getMessage());
    }
} else {
    qa_log($report, 'PENDING', 'site.lead_public', 'site.sqlite local ausente — pulei probe de lead');
}

$path = qa_write_log($report, 'smoke-http');
echo sprintf(
    "\nResumo: OK=%d FAIL=%d PENDING=%d\n",
    $report['ok'],
    $report['fail'],
    $report['pending']
);

exit($report['fail'] > 0 ? 1 : 0);
