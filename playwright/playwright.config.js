// @ts-check
require('./load-env');
const { defineConfig, devices } = require('@playwright/test');

/**
 * Smoke UI — só Chromium (mínimo necessário).
 * Credenciais: preencha playwright/.env (gitignored).
 */
module.exports = defineConfig({
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  timeout: 90_000,
  use: {
    ...devices['Desktop Chrome'],
    headless: true,
    launchOptions: {
      slowMo: process.env.PW_SLOWMO ? Number(process.env.PW_SLOWMO) : 0,
    },
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
