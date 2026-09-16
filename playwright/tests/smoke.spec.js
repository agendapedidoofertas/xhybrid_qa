const { test, expect } = require('@playwright/test');

const SITE = 'https://8xd.com.br';
const SITE_ADMIN = `${SITE}/admin`;
const CRM_ADMIN = 'https://crm.8xd.com.br/admin';
const LEAD = `${SITE}/eletricistaton/x22`;
const CRM_SUB = 'https://crm.8xd.com.br';

async function pause(page, ms = 800) {
  await page.waitForTimeout(ms);
}

test.describe('1) Site público (apex)', () => {
  test('home https://8xd.com.br/', async ({ page }) => {
    const res = await page.goto(`${SITE}/`, { waitUntil: 'domcontentloaded' });
    expect(res?.ok() || res?.status() === 304).toBeTruthy();
    await expect(page.locator('body')).toBeVisible();
    await pause(page);
  });

  test('index.html', async ({ page }) => {
    const res = await page.goto(`${SITE}/index.html`, { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBeLessThan(400);
    await pause(page, 600);
  });

  test('planos.html', async ({ page }) => {
    await page.goto(`${SITE}/planos.html`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 600);
  });

  test('termos.html', async ({ page }) => {
    await page.goto(`${SITE}/termos.html`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 500);
  });

  test('privacidade.html', async ({ page }) => {
    await page.goto(`${SITE}/privacidade.html`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 500);
  });

  test('404.html', async ({ page }) => {
    await page.goto(`${SITE}/404.html`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 500);
  });

  test('assets css/js carregam', async ({ request }) => {
    for (const path of ['/css/styles.css', '/js/main.js', '/js/data.js']) {
      const res = await request.get(`${SITE}${path}`);
      expect(res.status(), path).toBe(200);
    }
  });
});

test.describe('2) Lead na raiz do domínio', () => {
  test('eletricistaton/x22', async ({ page }) => {
    const res = await page.goto(LEAD, { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBeLessThan(400);
    const body = (await page.locator('body').innerText()).toLowerCase();
    expect(body).not.toContain('esse link saiu do ar');
    await pause(page, 1200);
  });
});

test.describe('3) Admin site (sem login)', () => {
  test('login.php', async ({ page }) => {
    await page.goto(`${SITE_ADMIN}/login.php`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible();
    await pause(page, 700);
  });

  test('root admin', async ({ page }) => {
    await page.goto(`${SITE_ADMIN}/`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 600);
  });

  for (const p of ['appearance.php', 'leads.php', 'texts.php', 'setup.php']) {
    test(`${p} sem sessão (não 500)`, async ({ page }) => {
      const res = await page.goto(`${SITE_ADMIN}/${p}`, { waitUntil: 'domcontentloaded' });
      expect(res?.status() ?? 0).toBeLessThan(500);
      await pause(page, 500);
    });
  }
});

test.describe('4) Admin CRM subdomínio (sem login)', () => {
  test('login.php em crm.8xd.com.br', async ({ page }) => {
    await page.goto(`${CRM_ADMIN}/login.php`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible();
    await pause(page, 700);
  });

  test('root CRM', async ({ page }) => {
    await page.goto(`${CRM_ADMIN}/`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 600);
  });

  for (const p of ['leads.php', 'ops.php', 'capture.php', 'setup.php']) {
    test(`${p} sem sessão (não 500)`, async ({ page }) => {
      const res = await page.goto(`${CRM_ADMIN}/${p}`, { waitUntil: 'domcontentloaded' });
      expect(res?.status() ?? 0).toBeLessThan(500);
      await pause(page, 500);
    });
  }
});

test.describe('5) Segurança data/', () => {
  test('site.sqlite bloqueado', async ({ request }) => {
    const res = await request.get(`${SITE}/data/site.sqlite`);
    expect([401, 403, 404]).toContain(res.status());
  });

  test('crm.sqlite bloqueado no subdomínio', async ({ request }) => {
    const res = await request.get(`${CRM_SUB}/data/crm.sqlite`);
    expect([401, 403, 404]).toContain(res.status());
  });
});

test.describe('6) Infra alvo', () => {
  test('apex é o app Xhybrid', async ({ page }) => {
    const res = await page.goto(`${SITE}/`, { waitUntil: 'domcontentloaded' });
    expect(res?.status()).toBeLessThan(400);
    await expect(page.locator('body')).toBeVisible();
  });

  test('crm.8xd.com.br responde', async ({ request }) => {
    const res = await request.get(`${CRM_SUB}/`, { timeout: 15000 });
    expect(res.status()).toBeLessThan(500);
  });
});

test.describe('7) CRUD logado (precisa .env)', () => {
  test('login site + appearance', async ({ page }) => {
    test.skip(!process.env.QA_SITE_USER || !process.env.QA_SITE_PASS, 'Defina QA_SITE_* no .env');
    await page.goto(`${SITE_ADMIN}/login.php`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="username"]', process.env.QA_SITE_USER);
    await page.fill('input[type="password"]', process.env.QA_SITE_PASS);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`${SITE_ADMIN}/appearance.php`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 1000);
  });

  test('login CRM + leads', async ({ page }) => {
    test.skip(!process.env.QA_CRM_USER || !process.env.QA_CRM_PASS, 'Defina QA_CRM_* no .env');
    await page.goto(`${CRM_ADMIN}/login.php`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="username"]', process.env.QA_CRM_USER);
    await page.fill('input[type="password"]', process.env.QA_CRM_PASS);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`${CRM_ADMIN}/leads.php`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
    await pause(page, 1000);
  });
});
