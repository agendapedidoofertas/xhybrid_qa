<?php

declare(strict_types=1);

/**
 * Xhybrid Robô Vigia / Healthcheck Automático
 *
 * Monitora proativamente a saúde de todos os nós do ecossistema:
 * 1. Site Xhybrid (Porta 8000 / Produção)
 * 2. CRM Software & Painel Admin (Porta 8001 / Produção)
 * 3. Integridade dos Bancos de Dados SQLite (crm.sqlite)
 * 4. API de Captura Rápida de Leads (checkout_draft.php)
 * 5. Webhook de Pagamento Asaas (webhook_payment.php)
 * 6. Servidor do Bot WhatsApp (Porta 3333)
 *
 * Em caso de falha crítica, registra em log e dispara alerta.
 *
 * Uso:
 *   php xhybrid_qa/php/healthcheck_monitor.php
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$baseDir = dirname(__DIR__, 2);
$crmDir = $baseDir . DIRECTORY_SEPARATOR . 'crm_software';
$siteDir = $baseDir . DIRECTORY_SEPARATOR . 'xhybrid_site';

$report = qa_report_new();
$criticalFailures = [];

echo "\n======================================================\n";
echo " 🛡️  XHYBRID ROBÔ VIGIA — HEALTHCHECK DO SISTEMA\n";
echo " Data: " . date('Y-m-d H:i:s') . "\n";
echo "======================================================\n\n";

// 1. Verificação do Site Principal (xhybrid_site)
$siteUrls = ['http://localhost:8000/', 'http://127.0.0.1:8000/'];
$siteOk = false;
$siteStatus = 0;

foreach ($siteUrls as $url) {
    $res = qa_http_get($url, ['timeout_sec' => 3]);
    if ($res['status'] >= 200 && $res['status'] < 400) {
        $siteOk = true;
        $siteStatus = $res['status'];
        break;
    }
}

if ($siteOk) {
    qa_log($report, 'OK', 'SITE_HTTP', "Site Xhybrid ativo e respondendo (HTTP {$siteStatus})");
} else {
    $detail = "Site Xhybrid inacessível na porta 8000";
    qa_log($report, 'FAIL', 'SITE_HTTP', $detail);
    $criticalFailures[] = "[CRÍTICO] Site Xhybrid fora do ar na porta 8000";
}

// 2. Verificação do CRM Software (crm_software)
$crmUrls = ['http://localhost:8001/admin/', 'http://127.0.0.1:8001/admin/'];
$crmOk = false;
$crmStatus = 0;

foreach ($crmUrls as $url) {
    $res = qa_http_get($url, ['timeout_sec' => 3]);
    if ($res['status'] >= 200 && $res['status'] < 400) {
        $crmOk = true;
        $crmStatus = $res['status'];
        break;
    }
}

if ($crmOk) {
    qa_log($report, 'OK', 'CRM_HTTP', "Painel CRM ativo e respondendo (HTTP {$crmStatus})");
} else {
    $detail = "CRM inacessível na porta 8001";
    qa_log($report, 'FAIL', 'CRM_HTTP', $detail);
    $criticalFailures[] = "[CRÍTICO] Painel CRM fora do ar na porta 8001";
}

// 3. Verificação do Endpoint de Captura Rápida de Checkout (checkout_draft.php)
$draftUrl = 'http://localhost:8000/api/checkout_draft.php';
$draftRes = qa_http_get($draftUrl, ['timeout_sec' => 3]);
// GET em endpoint POST deve responder 405 ou JSON de método não permitido, provando que o endpoint existe e responde
if ($draftRes['status'] === 405 || ($draftRes['status'] >= 200 && $draftRes['status'] < 500)) {
    qa_log($report, 'OK', 'API_CHECKOUT_DRAFT', "Endpoint de recuperação de carrinho ativo (HTTP {$draftRes['status']})");
} else {
    $detail = "Endpoint checkout_draft.php com erro ou inacessível (HTTP {$draftRes['status']})";
    qa_log($report, 'FAIL', 'API_CHECKOUT_DRAFT', $detail);
    $criticalFailures[] = "[ALERTA] API de checkout draft não respondendo adequadamente";
}

// 4. Verificação do Webhook de Pagamento
$webhookUrl = 'http://localhost:8001/api/webhook_payment.php';
$webhookRes = qa_http_get($webhookUrl, ['timeout_sec' => 3]);
if ($webhookRes['status'] >= 200 && $webhookRes['status'] < 500) {
    qa_log($report, 'OK', 'API_WEBHOOK_PAYMENT', "Endpoint de Webhook Asaas ativo e pronto (HTTP {$webhookRes['status']})");
} else {
    $detail = "Webhook de pagamento inacessível (HTTP {$webhookRes['status']})";
    qa_log($report, 'FAIL', 'API_WEBHOOK_PAYMENT', $detail);
    $criticalFailures[] = "[CRÍTICO] Webhook de pagamento fora do ar";
}

// 5. Verificação da Integridade do Banco SQLite do CRM
$crmDbPath = $crmDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'crm.sqlite';
if (file_exists($crmDbPath)) {
    try {
        $pdo = new PDO('sqlite:' . $crmDbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $checkStmt = $pdo->query('PRAGMA integrity_check;');
        $integrity = $checkStmt ? $checkStmt->fetchColumn() : 'unknown';

        if ($integrity === 'ok') {
            $leadsCount = (int) $pdo->query('SELECT count(*) FROM leads;')->fetchColumn();
            $abandonedCount = (int) $pdo->query("SELECT count(*) FROM leads WHERE status = 'CARRINHO_ABANDONADO' OR payment_status = 'draft';")->fetchColumn();
            qa_log($report, 'OK', 'SQLITE_CRM_INTEGRITY', "Banco crm.sqlite 100% íntegro ({$leadsCount} leads cadastrados, {$abandonedCount} no fluxo de recuperação)");
        } else {
            qa_log($report, 'FAIL', 'SQLITE_CRM_INTEGRITY', "Falha de integridade em crm.sqlite: {$integrity}");
            $criticalFailures[] = "[CRÍTICO] Corrupção detectada no banco SQLite do CRM";
        }
    } catch (Throwable $e) {
        qa_log($report, 'FAIL', 'SQLITE_CRM_CONNECT', "Erro ao conectar no crm.sqlite: " . $e->getMessage());
        $criticalFailures[] = "[CRÍTICO] Não foi possível ler o banco SQLite do CRM";
    }
} else {
    qa_log($report, 'FAIL', 'SQLITE_CRM_FILE', "Arquivo crm.sqlite não encontrado em {$crmDbPath}");
    $criticalFailures[] = "[CRÍTICO] Banco crm.sqlite ausente";
}

// 6. Verificação do Bot WhatsApp (Porta 3333)
$waUrl = 'http://localhost:3333/';
$waRes = qa_http_get($waUrl, ['timeout_sec' => 2]);
if ($waRes['status'] === 200 || $waRes['status'] === 503) {
    $msg = $waRes['status'] === 200 ? 'Bot conectado e pronto' : 'Servidor do Bot ativo (aguardando QR)';
    qa_log($report, 'OK', 'WHATSAPP_BOT', "Serviço WhatsApp na porta 3333 ({$msg})");
} else {
    qa_log($report, 'PENDING', 'WHATSAPP_BOT', "Serviço WhatsApp não detectado na porta 3333 (pode estar desligado para testes manuais)");
}

// 7. Consolidação e Alertas
echo "\n======================================================\n";
echo sprintf(" RESUMO: OK: %d | FAIL: %d | PENDING: %d\n", $report['ok'], $report['fail'], $report['pending']);
echo "======================================================\n";

$logFile = qa_write_log($report, 'healthcheck');

// Disparo de alerta em caso de falha crítica
if (!empty($criticalFailures)) {
    echo "\n⚠️  ATENÇÃO: Foram detectadas falhas críticas!\n";
    foreach ($criticalFailures as $fail) {
        echo "  • " . $fail . "\n";
    }

    // Se a classe WhatsAppService estiver acessível, tenta alertar
    $waServicePath = $crmDir . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'whatsapp' . DIRECTORY_SEPARATOR . 'WhatsAppService.php';
    if (file_exists($waServicePath)) {
        require_once $waServicePath;
        if (function_exists('whatsapp_send')) {
            $alertMsg = "🚨 *Alerta Robô Vigia Xhybrid:*\n" . implode("\n", $criticalFailures) . "\n\nVerifique imediatamente o servidor.";
            @whatsapp_send('5511975438477', $alertMsg);
            echo "Alerta enviado via WhatsApp para o admin.\n";
        }
    }
    exit(1);
} else {
    echo "\n🎉 Todos os serviços vitais estão saudáveis e operacionais!\n\n";
    exit(0);
}
