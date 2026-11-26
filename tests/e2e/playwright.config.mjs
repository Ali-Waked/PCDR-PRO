import { defineConfig, devices } from '@playwright/test'

const baseURL = process.env.BASE_URL || 'http://127.0.0.1:8000'
const browserName = process.env.E2E_BROWSER === 'firefox' ? 'firefox' : 'chromium'
const launchOptions = browserName === 'chromium'
  ? {
      args: process.env.E2E_CHROME_ARGS ? process.env.E2E_CHROME_ARGS.trim().split(/\s+/) : [],
      ...(process.env.E2E_HEADED === '1' ? { headless: false } : {}),
    }
  : {}

export default defineConfig({
  testDir: '.',
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: 0,
  timeout: 15 * 60 * 1000,
  expect: { timeout: 10_000 },
  outputDir: './screenshots/.artifacts',
  reporter: [
    ['list'],
    ['html', { outputFolder: './screenshots/playwright-report', open: 'never' }],
  ],
  use: {
    baseURL,
    locale: 'en-US',
    trace: 'off',
    screenshot: 'off',
    video: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 60_000,
    launchOptions,
  },
  projects: [
    {
      name: browserName,
      use: {
        ...(browserName === 'firefox' ? devices['Desktop Firefox'] : devices['Desktop Chrome']),
        viewport: { width: 1440, height: 900 },
      },
    },
  ],
  // Laravel is started separately by the caller (php artisan serve). No webServer is configured.
})
