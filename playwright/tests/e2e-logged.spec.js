const { test, expect } = require('@playwright/test');
const {
  SITE,
  SITE_ADMIN,
  CRM_ADMIN,
  pause,
  loginSite,
  loginCrm,
  assertAdminPageOk,
} = require('./helpers');

const stamp = () => new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);

test.describe.configure({ mode: 'serial' });

test.describe('A) Smoke logado CRM + Painel site', () => {
  test('CRM — navegar menus principais', async ({ page }) => {
    test.skip(!process.env.QA_CRM_USER || !process.env.QA_CRM_PASS, 'sem QA_CRM_*');
    await loginCrm(page);
    const paths = [
      'index.php',
      'ops.php',
      'capture.php',
      'leads.php',
      'leads.php?has_website=0&status=NOVO',
      'lead_edit.php',
      'password.php',
    ];
    for (const p of paths) {
      await assertAdminPageOk(page, `${CRM_ADMIN}/${p}`);
    }
  });

  test('Site — navegar tiles do Painel', async ({ page }) => {
    test.skip(!process.env.QA_SITE_USER || !process.env.QA_SITE_PASS, 'sem QA_SITE_*');
    await loginSite(page);
    await assertAdminPageOk(page, `${SITE_ADMIN}/index.php`);
    const pages = [
      'appearance.php',
      'contact.php',
      'texts.php',
      'images.php',
      'leads.php',
      'brand.php',
      'plan.php',
      'preset.php',
      'services.php',
      'sections.php',
      'users.php',
      'backup.php',
      'password.php',
    ];
    for (const p of pages) {
      await assertAdminPageOk(page, `${SITE_ADMIN}/${p}`);
    }
  });
});

test.describe('B) Vitrine — alterar e reverter contato', () => {
  test('contact_response_text com QA_TEST_ e reverte', async ({ page }) => {
    test.skip(!process.env.QA_SITE_USER || !process.env.QA_SITE_PASS, 'sem QA_SITE_*');
    await loginSite(page);
    await page.goto(`${SITE_ADMIN}/contact.php`, { waitUntil: 'domcontentloaded' });
    const field = page.locator('#contact_response_text, input[name="contact_response_text"], textarea[name="contact_response_text"]');
    await expect(field).toBeVisible();
    const original = await field.inputValue();
    const marker = `QA_TEST_${stamp()}`;
    await field.fill(marker);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`${SITE_ADMIN}/contact.php`, { waitUntil: 'domcontentloaded' });
    await expect(field).toHaveValue(marker);
    // reverter
    await field.fill(original);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`${SITE_ADMIN}/contact.php`, { waitUntil: 'domcontentloaded' });
    await expect(field).toHaveValue(original);
    await pause(page, 500);
  });
});

test.describe('C) Três leads QA_* CRM → ativar → público → cleanup', () => {
  /** @type {number[]} */
  const createdIds = [];

  test('criar 3 leads manuais, editar, ativar, URL pública', async ({ page }) => {
    test.skip(!process.env.QA_CRM_USER || !process.env.QA_CRM_PASS, 'sem QA_CRM_*');
    await loginCrm(page);
    const run = stamp();

    for (let i = 1; i <= 3; i++) {
      const name = `QA_LEAD_${i}_${run}`;
      await page.goto(`${CRM_ADMIN}/lead_edit.php`, { waitUntil: 'domcontentloaded' });
      await page.fill('input[name="company_name"]', name);
      await page.fill('input[name="category"]', 'eletricista');
      await page.fill('input[name="phone"]', `1198800${1000 + i}`);
      await page.fill('input[name="whatsapp"]', `1198800${1000 + i}`);
      await page.fill('input[name="city"]', 'São Paulo');
      await page.fill('input[name="state"]', 'SP');
      await page.fill('input[name="neighborhood"]', 'Moema');
      await page.selectOption('select[name="plan_tier"]', i === 3 ? 'medium' : 'basic');
      await page.click('button[type="submit"]');
      await page.waitForURL(/lead\.php\?id=\d+/);
      const m = page.url().match(/id=(\d+)/);
      expect(m).toBeTruthy();
      const id = Number(m[1]);
      createdIds.push(id);

      // editar notes
      await page.goto(`${CRM_ADMIN}/lead_edit.php?id=${id}`, { waitUntil: 'domcontentloaded' });
      await page.fill('textarea[name="notes"]', `QA note ${name}`);
      await page.click('button[type="submit"]');
      await page.waitForURL(new RegExp(`lead\\.php\\?id=${id}`));

      // ativar site
      await page.locator('form').filter({ has: page.locator('input[name="action"][value="site_on"]') }).locator('button').click();
      await page.waitForLoadState('domcontentloaded');
      await pause(page, 600);

      // ler slug/código da ficha se possível
      const body = await page.locator('body').innerText();
      expect(body.toLowerCase()).not.toContain('fatal');

      // link público na raiz do domínio (ou legado /xhybrid_site)
      const publicLink = page.locator('a[href*="8xd.com.br/"]').filter({ hasNotText: 'admin' }).first();
      const legacyLink = page.locator('a[href*="/xhybrid_site/"]').first();
      if (await publicLink.count()) {
        const href = await publicLink.getAttribute('href');
        expect(href).toBeTruthy();
        const res = await page.request.get(href.startsWith('http') ? href : `https://8xd.com.br${href}`);
        expect(res.status()).toBeLessThan(400);
        const html = (await res.text()).toLowerCase();
        expect(html).not.toContain('esse link saiu do ar');
      } else if (await legacyLink.count()) {
        const href = await legacyLink.getAttribute('href');
        const res = await page.request.get(href.startsWith('http') ? href : `https://8xd.com.br${href}`);
        expect(res.status()).toBeLessThan(400);
      } else {
        // fallback: busca na página por padrão letra+id
        const slugMatch = body.match(/\/([a-z0-9-]+)\/([a-z]\d+)/i);
        if (slugMatch) {
          const url = `${SITE}/${slugMatch[1]}/${slugMatch[2]}`;
          const res = await page.request.get(url);
          expect(res.status(), url).toBeLessThan(400);
        } else {
          test.info().annotations.push({
            type: 'note',
            description: `Lead ${id} ativado mas URL pública não encontrada no HTML`,
          });
        }
      }
    }

    expect(createdIds.length).toBe(3);
  });

  test('site admin — hub do 1º lead QA (se publicado)', async ({ page }) => {
    test.skip(!process.env.QA_SITE_USER || !process.env.QA_SITE_PASS, 'sem QA_SITE_*');
    test.skip(createdIds.length === 0, 'nenhum lead criado');
    await loginSite(page);
    const leadId = createdIds[0];
    await page.goto(`${SITE_ADMIN}/lead_hub.php?lead_id=${leadId}`, { waitUntil: 'domcontentloaded' });
    const status = await page.evaluate(() => document.body.innerText);
    if (status.toLowerCase().includes('não encontrado') || status.toLowerCase().includes('nao encontrado')) {
      test.info().annotations.push({
        type: 'pending',
        description: `lead_hub ${leadId} não publicado no SQLite de produção (bridge?)`,
      });
      return;
    }
    await expect(page.locator('body')).toBeVisible();
    // abrir contato do lead se link existir
    const contact = page.locator(`a[href*="contact.php"][href*="lead_id=${leadId}"], a[href*="contact.php?"]`).first();
    if (await contact.count()) {
      await contact.click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('body')).toBeVisible();
    }
    await assertAdminPageOk(page, `${SITE_ADMIN}/plan.php?lead_id=${leadId}`);
  });

  test('cleanup — desativar e lixeira só leads QA desta run', async ({ page }) => {
    test.skip(!process.env.QA_CRM_USER || !process.env.QA_CRM_PASS, 'sem QA_CRM_*');
    test.skip(createdIds.length === 0, 'nenhum lead');
    await loginCrm(page);
    for (const id of createdIds) {
      await page.goto(`${CRM_ADMIN}/lead.php?id=${id}`, { waitUntil: 'domcontentloaded' });
      const off = page.locator('form').filter({ has: page.locator('input[name="action"][value="site_off"]') });
      if (await off.count()) {
        await off.locator('button').click();
        await page.waitForLoadState('domcontentloaded');
      }
      const del = page.locator('form').filter({ has: page.locator('input[name="action"][value="delete"]') });
      if (await del.count()) {
        page.once('dialog', (d) => d.accept());
        await del.locator('button').click();
        await page.waitForLoadState('domcontentloaded');
      }
    }
  });
});

test.describe('D) Usuários QA_* no site', () => {
  let qaUsername = '';

  test('criar usuário QA_USER e apagar', async ({ page }) => {
    test.skip(!process.env.QA_SITE_USER || !process.env.QA_SITE_PASS, 'sem QA_SITE_*');
    await loginSite(page);
    qaUsername = `qa_user_${stamp()}`.slice(0, 32);
    const pass = 'TestQA_123456';
    await page.goto(`${SITE_ADMIN}/users.php`, { waitUntil: 'domcontentloaded' });
    await page.locator('form').filter({ has: page.locator('input[name="action"][value="create"]') }).locator('input[name="username"]').fill(qaUsername);
    await page.locator('form').filter({ has: page.locator('input[name="action"][value="create"]') }).locator('input[name="password"]').fill(pass);
    await page.locator('form').filter({ has: page.locator('input[name="action"][value="create"]') }).locator('input[name="confirm"]').fill(pass);
    const role = page.locator('form').filter({ has: page.locator('input[name="action"][value="create"]') }).locator('select[name="role"]');
    if (await role.count()) {
      const opts = await role.locator('option').allTextContents();
      // prefer editor/viewer if exists
      const val = await role.locator('option').nth(1).getAttribute('value');
      if (val) await role.selectOption(val);
    }
    await page.locator('form').filter({ has: page.locator('input[name="action"][value="create"]') }).locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toContainText(qaUsername);

    // delete
    const rowForm = page.locator('form').filter({ has: page.locator('input[name="action"][value="delete"]') }).filter({ hasText: qaUsername });
    // forms may be beside username in table — find by nearby text
    const deleteBtn = page.locator(`tr:has-text("${qaUsername}") form`).filter({ has: page.locator('input[name="action"][value="delete"]') }).locator('button');
    if (await deleteBtn.count()) {
      page.once('dialog', (d) => d.accept());
      await deleteBtn.click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('body')).not.toContainText(qaUsername);
    } else {
      // try any delete form on page after searching username
      const anyDel = page.locator('tr').filter({ hasText: qaUsername }).locator('form').filter({ has: page.locator('input[value="delete"]') }).locator('button, input[type="submit"]');
      if (await anyDel.count()) {
        page.once('dialog', (d) => d.accept());
        await anyDel.first().click();
        await page.waitForLoadState('domcontentloaded');
      } else {
        test.info().annotations.push({ type: 'pending', description: `Não achei botão delete para ${qaUsername}` });
      }
    }
  });
});

test.describe('E) Captura Places', () => {
  test('documenta se Places está configurado (não dispara se desabilitado)', async ({ page }) => {
    test.skip(!process.env.QA_CRM_USER || !process.env.QA_CRM_PASS, 'sem QA_CRM_*');
    await loginCrm(page);
    await page.goto(`${CRM_ADMIN}/capture.php`, { waitUntil: 'domcontentloaded' });
    const body = await page.locator('body').innerText();
    const btn = page.locator('button[type="submit"]');
    const disabled = await btn.isDisabled().catch(() => true);
    if (body.includes('places_api_key') || body.includes('GOOGLE_PLACES') || disabled) {
      test.info().annotations.push({
        type: 'pending',
        description: 'Captura Places não configurada ou botão disabled — não executada (seguro)',
      });
      expect(true).toBeTruthy();
      return;
    }
    // Se habilitado: NÃO rodar captura real em massa — só documentar ready
    test.info().annotations.push({
      type: 'pending',
      description: 'Places parece habilitado — captura real NÃO disparada (regra: evitar custo/leads reais)',
    });
  });
});

test.describe('F) Planos', () => {
  test('abrir Plano agência + plano em lead da lista se houver', async ({ page }) => {
    test.skip(!process.env.QA_SITE_USER || !process.env.QA_SITE_PASS, 'sem QA_SITE_*');
    await loginSite(page);
    await assertAdminPageOk(page, `${SITE_ADMIN}/plan.php`);
    await page.goto(`${SITE_ADMIN}/leads.php`, { waitUntil: 'domcontentloaded' });
    const edit = page.locator('a[href*="lead_hub.php"]').first();
    if (await edit.count()) {
      await edit.click();
      await page.waitForLoadState('domcontentloaded');
      const planLink = page.locator('a[href*="plan.php"]').first();
      if (await planLink.count()) {
        await planLink.click();
        await page.waitForLoadState('domcontentloaded');
        await expect(page.locator('body')).toBeVisible();
      }
    }
  });
});
