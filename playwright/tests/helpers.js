const { expect } = require('@playwright/test');

/** Layout alvo: apex = site, CRM = subdomínio */
const SITE = 'https://8xd.com.br';
const SITE_ADMIN = `${SITE}/adminn`;
const CRM_ADMIN = 'https://crm.8xd.com.br/adminn';

async function pause(page, ms = 400) {
  await page.waitForTimeout(ms);
}

async function loginSite(page) {
  const user = process.env.QA_SITE_USER || '';
  const pass = process.env.QA_SITE_PASS || '';
  if (!user || !pass) {
    throw new Error('QA_SITE_USER/QA_SITE_PASS ausentes no .env');
  }
  await page.goto(`${SITE_ADMIN}/login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/login\.php$/);
}

async function loginCrm(page) {
  const user = process.env.QA_CRM_USER || '';
  const pass = process.env.QA_CRM_PASS || '';
  if (!user || !pass) {
    throw new Error('QA_CRM_USER/QA_CRM_PASS ausentes no .env');
  }
  await page.goto(`${CRM_ADMIN}/login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/login\.php$/);
}

async function assertAdminPageOk(page, url) {
  const res = await page.goto(url, { waitUntil: 'domcontentloaded' });
  expect(res?.status() ?? 0, url).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  const body = (await page.locator('body').innerText()).toLowerCase();
  expect(body).not.toContain('fatal error');
  expect(body).not.toContain('uncaught');
  await pause(page, 350);
}

module.exports = {
  SITE,
  SITE_ADMIN,
  CRM_ADMIN,
  pause,
  loginSite,
  loginCrm,
  assertAdminPageOk,
};
