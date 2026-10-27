const { test, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

// Carrega o orquestrador do xhybrid_wa_bot
const botDir = path.resolve(__dirname, '../../../xhybrid_wa_bot');
const { orchestrator } = require(path.join(botDir, 'src/ai-orchestrator'));

test.describe('Validação do Agente e Orquestrador IA Xhybrid', () => {
  test('1. Chave de API GEMINI_API_KEY deve estar configurada', () => {
    expect(orchestrator.hasApiKey()).toBe(true);
  });

  test('2. Geração de texto inteligente (Gemini 3.5 / 3.8)', async () => {
    const response = await orchestrator.generateText('Responda estritamente: QA_CONFIRMED');
    expect(response).toContain('QA_CONFIRMED');
  });

  test('3. Agente de Criação de Sites para xhybrid_site', async () => {
    const pack = await orchestrator.generateSitePack({
      businessName: 'Clínica Sorriso QA',
      segment: 'Odontologia',
      city: 'São Paulo'
    });

    expect(pack).toBeDefined();
    expect(pack.hero_title).toBeTruthy();
    expect(Array.isArray(pack.services)).toBe(true);
    expect(pack.services.length).toBeGreaterThan(0);
    expect(pack.image_prompts).toBeDefined();
  });

  test('4. Geração de Imagem via Nano Banana (com fallback inteligente)', async () => {
    const testOut = path.resolve(__dirname, '../test-results/test-image.svg');
    const img = await orchestrator.generateImage('Logo moderno Xhybrid', testOut);

    expect(img).toBeDefined();
    expect(img.buffer).toBeInstanceOf(Buffer);
    expect(img.buffer.length).toBeGreaterThan(100);
    expect(fs.existsSync(testOut)).toBe(true);
  });

  test('5. Conceito e Roteiro de Vídeo (Google Flow / Veo Engine)', async () => {
    const video = await orchestrator.generateVideoConcept('Comercial de lançamento Xhybrid');

    expect(video).toBeDefined();
    expect(video.title || video.veo_prompt).toBeTruthy();
  });
});
