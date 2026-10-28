<?php

declare(strict_types=1);

/**
 * Roda smoke HTTP + roundtrip local + AI Agent suite e grava resumo.
 *
 * Uso: php php/run_all.php
 */

$root = dirname(__DIR__);
$php = PHP_BINARY !== '' ? PHP_BINARY : 'php';

echo "======== xhybrid_qa run_all ========\n";
echo 'PHP: ' . $php . "\n";
echo 'Root: ' . $root . "\n\n";

$suites = [
    'healthcheck'    => $root . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'healthcheck_monitor.php',
    'smoke_http'     => $root . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'smoke_http.php',
    'qa_roundtrip'   => $root . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'qa_roundtrip.php',
    'ai_agent_suite' => $root . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'test_ai_agent.php',
];

$codes = [];
foreach ($suites as $name => $script) {
    echo "-------- {$name} --------\n";
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script);
    passthru($cmd, $code);
    $codes[$name] = $code;
    echo "exit={$code}\n\n";
}

$dir = $root . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$summary = $dir . DIRECTORY_SEPARATOR . 'qa-summary-' . date('Ymd-His') . '.md';
$lines = [
    '# QA summary — ' . date('c'),
    '',
    '| Suite | Exit |',
    '|-------|------|',
];
foreach ($codes as $name => $code) {
    $lines[] = '| ' . $name . ' | ' . $code . ' (' . ($code === 0 ? 'OK' : 'FAIL') . ') |';
}
$lines[] = '';
$lines[] = 'Pendências conhecidas de infra (não são bugs de código):';
$lines[] = '- `https://8xd.com.br` ainda **não** é o Xhybrid (app em `/xhybrid_site`)';
$lines[] = '- `https://crm.8xd.com.br` ainda **não** é o CRM (admin em `/crm_software/admin`)';
$lines[] = '';

file_put_contents($summary, implode("\n", $lines) . "\n");
echo "Resumo gravado em: {$summary}\n";

$failed = array_filter($codes, static fn (int $c): bool => $c !== 0);
exit($failed !== [] ? 1 : 0);
