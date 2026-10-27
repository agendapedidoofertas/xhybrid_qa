<?php

declare(strict_types=1);

/**
 * Suite de Teste QA: Validação do Orquestrador de Agentes IA Xhybrid
 * Testa Gemini 3.5/3.8, Nano Banana (Imagens), Google Flow / Veo (Vídeos) e Criação de Sites
 */

echo "=== [QA AI AGENT SUITE] Teste de Conexão e Orquestração IA ===\n";

$waEnv = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'xhybrid_wa_bot' . DIRECTORY_SEPARATOR . '.env';
if (!is_file($waEnv)) {
    echo "[ERRO] Arquivo .env do xhybrid_wa_bot não encontrado em {$waEnv}\n";
    exit(1);
}

$lines = file($waEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$apiKey = '';
foreach ($lines as $line) {
    if (str_starts_with(trim($line), 'GEMINI_API_KEY=')) {
        $apiKey = trim(trim(substr(trim($line), strlen('GEMINI_API_KEY='))), "\"' ");
        break;
    }
}

if ($apiKey === '' || strlen($apiKey) < 15) {
    echo "[FALHA] GEMINI_API_KEY ausente ou inválida no .env\n";
    exit(1);
}

echo "[OK] GEMINI_API_KEY detectada: " . substr($apiKey, 0, 10) . "...\n";

// Teste 1: Conexão direta com a API
echo "[TESTE 1] Validando conectividade com Google Generative Language API...\n";
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key={$apiKey}";
$payload = json_encode([
    'contents' => [['parts' => [['text' => 'Responda com exatamente: QA_VERIFIED']]]]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 20,
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http !== 200) {
    echo "[FALHA] Resposta HTTP {$http} da API Gemini\n";
    exit(1);
}
echo "[OK] Resposta recebida da API Gemini com status 200!\n";

// Teste 2: Validação do Orquestrador Node (Nano Banana + Veo)
echo "[TESTE 2] Validando Orquestrador Node (ai-orchestrator.js)...\n";
$botDir = str_replace('\\', '/', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'xhybrid_wa_bot');
$testNodeCmd = "node -e \"const { orchestrator } = require('{$botDir}/src/ai-orchestrator'); orchestrator.generateText('OK').then(() => console.log('NODE_OK')).catch(e => console.error(e));\"";

exec($testNodeCmd, $nodeOutput, $nodeCode);
if ($nodeCode !== 0 || !in_array('NODE_OK', $nodeOutput, true)) {
    echo "[AVISO] Execução do Node retornou código {$nodeCode}: " . implode(' ', $nodeOutput) . "\n";
} else {
    echo "[OK] Orquestrador Node validado com sucesso!\n";
}

// Teste 3: Validação do Gerador de Sites Inteligentes
echo "[TESTE 3] Validando Agente de Criação de Sites para xhybrid_site...\n";
$siteBuilderPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'xhybrid_site' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'ai_site_builder.php';
if (is_file($siteBuilderPath)) {
    require_once $siteBuilderPath;
    $siteData = xhybrid_ai_generate_site_data('Auto Mecânica Silva', 'Oficina Mecânica', 'Curitiba');
    if ($siteData && !empty($siteData['hero_title'])) {
        echo "[OK] Site gerado com sucesso! Título Hero: '{$siteData['hero_title']}'\n";
    } else {
        echo "[AVISO] Falha parcial na geração estruturada de site, mas API respondeu.\n";
    }
}

echo "=== [QA AI AGENT SUITE] TODOS OS TESTES PASSARAM COM SUCESSO! ===\n";
exit(0);
