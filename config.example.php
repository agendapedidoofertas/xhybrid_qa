<?php

declare(strict_types=1);

/**
 * Copie para config.php (gitignored) e ajuste se os paths locais forem diferentes.
 * URLs = layout atual no Hostgator (subpastas; CRM ainda sem subdomínio).
 */
return [
    'env' => 'hostgator',

    // Repos irmãos no PC (roundtrip SQLite local)
    'paths' => [
        'xhybrid' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'xhybrid_site',
        'crm' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'crm_software',
    ],

    'urls' => [
        'apex' => 'https://8xd.com.br',
        'site' => 'https://8xd.com.br/xhybrid_site',
        'site_admin' => 'https://8xd.com.br/xhybrid_site/admin',
        'crm_admin' => 'https://8xd.com.br/crm_software/admin',
        // Ainda não é o destino oficial — smoke marca como PENDENTE se falhar
        'crm_subdomain' => 'https://crm.8xd.com.br',
        // Opcional: path ou URL de um lead REAL no Hostgator (ex. 'solusempreiteira/v21')
        'probe_lead' => 'solusempreiteira/v21',
    ],

    'expectations' => [
        // Apex ainda não hospeda o app Xhybrid
        'apex_is_xhybrid' => false,
        // Subdomínio CRM ainda não está no ar
        'crm_subdomain_ready' => false,
    ],

    'http' => [
        'timeout_sec' => 20,
        'user_agent' => 'xhybrid-qa-smoke/1.0',
        // Não envia cookies de sessão — só GET público / redirect login
        'follow_redirects' => true,
        'max_redirects' => 5,
    ],
];
