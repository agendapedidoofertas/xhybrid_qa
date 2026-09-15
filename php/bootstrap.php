<?php

declare(strict_types=1);

/**
 * Bootstrap QA — carrega config e helpers de relatório.
 *
 * @return array<string, mixed>
 */
function qa_config(): array
{
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }
    $root = dirname(__DIR__);
    $local = $root . DIRECTORY_SEPARATOR . 'config.local.php';
    $main = $root . DIRECTORY_SEPARATOR . 'config.php';
    $example = $root . DIRECTORY_SEPARATOR . 'config.example.php';
    if (is_file($local)) {
        $cfg = require $local;
    } elseif (is_file($main)) {
        $cfg = require $main;
    } else {
        $cfg = require $example;
    }
    if (!is_array($cfg)) {
        throw new RuntimeException('config inválida');
    }
    return $cfg;
}

/**
 * @return array{ok:int,fail:int,pending:int,lines:list<string>,rows:list<array{status:string,id:string,detail:string}>}
 */
function qa_report_new(): array
{
    return ['ok' => 0, 'fail' => 0, 'pending' => 0, 'lines' => [], 'rows' => []];
}

/**
 * @param array{ok:int,fail:int,pending:int,lines:list<string>,rows:list<array{status:string,id:string,detail:string}>} $report
 */
function qa_log(array &$report, string $status, string $id, string $detail = ''): void
{
    $status = strtoupper($status);
    if ($status === 'OK') {
        $report['ok']++;
    } elseif ($status === 'PENDING' || $status === 'SKIP') {
        $report['pending']++;
        $status = 'PENDING';
    } else {
        $report['fail']++;
        $status = 'FAIL';
    }
    $line = sprintf('[%s] %s%s', $status, $id, $detail !== '' ? ' — ' . $detail : '');
    $report['lines'][] = $line;
    $report['rows'][] = ['status' => $status, 'id' => $id, 'detail' => $detail];
    echo $line . PHP_EOL;
}

/**
 * @param array{ok:int,fail:int,pending:int,lines:list<string>,rows:list<array{status:string,id:string,detail:string}>} $report
 */
function qa_write_log(array $report, string $suite): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stamp = date('Ymd-His');
    $path = $dir . DIRECTORY_SEPARATOR . 'qa-' . $suite . '-' . $stamp . '.md';
    $md = [];
    $md[] = '# QA ' . $suite . ' — ' . date('c');
    $md[] = '';
    $md[] = sprintf('OK: **%d** · FAIL: **%d** · PENDING: **%d**', $report['ok'], $report['fail'], $report['pending']);
    $md[] = '';
    $md[] = '## Resultados';
    $md[] = '';
    foreach ($report['rows'] as $row) {
        $md[] = sprintf('- **%s** `%s` %s', $row['status'], $row['id'], $row['detail']);
    }
    $md[] = '';
    $md[] = '## Pendências / falhas';
    $md[] = '';
    $has = false;
    foreach ($report['rows'] as $row) {
        if ($row['status'] === 'FAIL' || $row['status'] === 'PENDING') {
            $has = true;
            $md[] = sprintf('- [%s] %s — %s', $row['status'], $row['id'], $row['detail']);
        }
    }
    if (!$has) {
        $md[] = '- (nenhuma)';
    }
    $md[] = '';
    file_put_contents($path, implode("\n", $md));
    echo 'Log: ' . $path . PHP_EOL;
    return $path;
}

/**
 * GET HTTP — retorna status, URL final, pedaço do body.
 *
 * @return array{ok:bool,status:int,url:string,final_url:string,body:string,error:string,headers:array<string,string>}
 */
function qa_http_get(string $url, array $httpCfg = []): array
{
    $timeout = (int) ($httpCfg['timeout_sec'] ?? 20);
    $ua = (string) ($httpCfg['user_agent'] ?? 'xhybrid-qa-smoke/1.0');
    $follow = (bool) ($httpCfg['follow_redirects'] ?? true);
    $maxRedir = (int) ($httpCfg['max_redirects'] ?? 5);

    $out = [
        'ok' => false,
        'status' => 0,
        'url' => $url,
        'final_url' => $url,
        'body' => '',
        'error' => '',
        'headers' => [],
    ];

    if (!function_exists('curl_init')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "User-Agent: {$ua}\r\nAccept: text/html,*/*\r\n",
                'follow_location' => $follow ? 1 : 0,
                'max_redirects' => $maxRedir,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                    $out['status'] = (int) $m[1];
                }
            }
        }
        $out['body'] = is_string($body) ? substr($body, 0, 8000) : '';
        $out['ok'] = $out['status'] >= 200 && $out['status'] < 400;
        if ($body === false && $out['status'] === 0) {
            $out['error'] = 'file_get_contents falhou';
        }
        return $out;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => $maxRedir,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $out['error'] = curl_error($ch);
        curl_close($ch);
        return $out;
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headerBlob = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $out['status'] = $status;
    $out['final_url'] = $final !== '' ? $final : $url;
    $out['body'] = substr((string) $body, 0, 8000);
    $out['ok'] = $status >= 200 && $status < 400;
    foreach (explode("\r\n", (string) $headerBlob) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $out['headers'][strtolower($k)] = $v;
        }
    }
    return $out;
}

function qa_body_has(string $body, string $needle): bool
{
    if ($needle === '') {
        return false;
    }
    $hay = function_exists('mb_strtolower') ? mb_strtolower($body) : strtolower($body);
    $n = function_exists('mb_strtolower') ? mb_strtolower($needle) : strtolower($needle);
    return str_contains($hay, $n);
}
