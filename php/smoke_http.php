<?php

declare(strict_types=1);

/**
 * Varredura HTTP — layout alvo Hostgator:
 *   https://8xd.com.br     → Xhybrid
 *   https://crm.8xd.com.br → CRM
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

echo "=== smoke_http (apex + crm subdomain) ===\n";

// --- Apex = Xhybrid ---
if ($apex !== '') {
    $res = qa_http_get($apex . '/', $httpCfg);
    $apexIsApp = !empty($exp['apex_is_xhybrid']);
    if (!$apexIsApp) {
        qa_log($report, 'PENDING', 'apex.root', "HTTP {$res['status']} — expectation apex_is_xhybrid=false");
    } else {
        smoke_expect($report, 'apex.root', $res, [200], static function (array $r): string {
            if (qa_body_has($r['body'], 'Esse link saiu do ar') && !qa_body_has($r['body'], 'xhybrid')) {
                return 'apex parece 404 do app, não a vitrine';
            }
            return '';
        });
    }
}

// --- Site (mesmo host do apex no layout final) ---
if ($site !== '' && $site !== $apex) {
    smoke_expect($report, 'site.home', qa_http_get($site . '/', $httpCfg), [200]);
} elseif ($site !== '' && $site === $apex) {
    qa_log($report, 'OK', 'site.home', 'mesmo que apex.root (layout raiz)');
}

if ($site !== '') {
    smoke_expect($report, 'site.index_html', qa_http_get($site . '/index.html', $httpCfg), [200]);

    foreach (['/css/styles.css', '/js/main.js', '/js/data.js', '/router.php'] as $path) {
        $accept = $path === '/router.php' ? [200, 302, 301] : [200];
        smoke_expect($report, 'site.asset' . $path, qa_http_get($site . $path, $httpCfg), $accept);
    }

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

    smoke_expect($report, 'site_admin.root', qa_http_get($siteAdmin . '/', $httpCfg), [200, 301, 302]);

    foreach (['/appearance.php', '/leads.php', '/texts.php', '/setup.php'] as $p) {
        $r = qa_http_get($siteAdmin . $p, $httpCfg);
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

// --- CRM subdomínio ---
if ($crmAdmin !== '') {
    $crmLogin = qa_http_get($crmAdmin . '/login.php', $httpCfg);
    $crmReady = !empty($exp['crm_subdomain_ready']);
    if (!$crmReady && ($crmLogin['error'] !== '' || $crmLogin['status'] === 0)) {
        qa_log($report, 'PENDING', 'crm_admin.login', 'subdomínio ainda não pronto: ' . ($crmLogin['error'] ?: 'HTTP ' . $crmLogin['status']));
    } else {
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
}

if ($crmSub !== '') {
    $ready = !empty($exp['crm_subdomain_ready']);
    $res = qa_http_get($crmSub . '/', $httpCfg);
    if (!$ready) {
        qa_log($report, 'PENDING', 'crm.subdomain', 'crm_subdomain_ready=false');
    } elseif ($res['error'] !== '') {
        qa_log($report, 'FAIL', 'crm.subdomain', $res['error'] . ' — crie subdomínio no cPanel + SSL');
    } else {
        smoke_expect($report, 'crm.subdomain', $res, [200, 301, 302]);
    }
}

// --- Lead público ---
$probeLead = trim((string) ($urls['probe_lead'] ?? ''));
if ($probeLead !== '' && $site !== '') {
    $leadUrl = str_starts_with($probeLead, 'http') ? $probeLead : ($site . '/' . ltrim($probeLead, '/'));
    $lr = qa_http_get($leadUrl, $httpCfg);
    if ($lr['error'] !== '') {
        qa_log($report, 'FAIL', 'site.lead_public', $lr['error'] . ' url=' . $leadUrl);
    } elseif ($lr['status'] === 200 && !qa_body_has($lr['body'], 'Esse link saiu do ar')) {
        qa_log($report, 'OK', 'site.lead_public', $leadUrl);
    } elseif ($lr['status'] === 403) {
        qa_log($report, 'PENDING', 'site.lead_public', 'inativo? ' . $leadUrl);
    } else {
        qa_log($report, 'FAIL', 'site.lead_public', "HTTP {$lr['status']} url={$leadUrl} — confira publish + DocumentRoot na raiz");
    }
} else {
    qa_log($report, 'PENDING', 'site.lead_public', 'urls.probe_lead vazio');
}

// Legado /xhybrid_site (deve redirecionar ou 404 após migração — só informa)
$legacy = qa_http_get('https://8xd.com.br/xhybrid_site/', array_merge($httpCfg, ['follow_redirects' => false]));
if ($legacy['status'] === 200) {
    qa_log($report, 'PENDING', 'legacy.xhybrid_site_path', 'ainda responde 200 — após migrar DocumentRoot, pode remover/redirecionar subpasta');
} elseif (in_array($legacy['status'], [301, 302], true)) {
    qa_log($report, 'OK', 'legacy.xhybrid_site_path', 'redirect HTTP ' . $legacy['status']);
} else {
    qa_log($report, 'OK', 'legacy.xhybrid_site_path', 'HTTP ' . ($legacy['status'] ?: 'n/a') . ' (subpasta antiga)');
}

$path = qa_write_log($report, 'smoke-http');
echo sprintf(
    "\nResumo: OK=%d FAIL=%d PENDING=%d\n",
    $report['ok'],
    $report['fail'],
    $report['pending']
);

exit($report['fail'] > 0 ? 1 : 0);
