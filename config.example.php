<?php

declare(strict_types=1);

/**
 * Copie para config.php (gitignored) se precisar customizar.
 *
 * Layout alvo Hostgator:
 *   https://8xd.com.br          → Xhybrid (DocumentRoot = pasta do site)
 *   https://crm.8xd.com.br      → CRM
 *
 * Localhost: paths das pastas irmãs; URLs abaixo são só para smoke HTTP / Playwright.
 */
return [
    'env' => 'hostgator',

    'paths' => [
        'xhybrid' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'xhybrid_site',
        'crm' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'crm_software',
        // Paths absolutos no Hostgator (referência; não usados pelo PHP local)
        'hostgator_site' => '/home2/sonawe03/8xd.com.br/xhybrid_site',
        'hostgator_crm' => '/home2/sonawe03/crm',
    ],

    'urls' => [
        'apex' => 'https://8xd.com.br',
        'site' => 'https://8xd.com.br',
        'site_admin' => 'https://8xd.com.br/admin',
        'crm_admin' => 'https://crm.8xd.com.br/admin',
        'crm_subdomain' => 'https://crm.8xd.com.br',
        // Lead de smoke (raiz do domínio — sem /xhybrid_site)
        'probe_lead' => 'eletricistaton/x22',
    ],

    'expectations' => [
        'apex_is_xhybrid' => true,
        'crm_subdomain_ready' => true,
    ],

    'http' => [
        'timeout_sec' => 20,
        'user_agent' => 'xhybrid-qa-smoke/1.0',
        'follow_redirects' => true,
        'max_redirects' => 5,
    ],
];
