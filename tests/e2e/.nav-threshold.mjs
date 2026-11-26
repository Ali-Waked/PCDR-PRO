import { firefox } from '@playwright/test'

const browser = await firefox.launch({ headless: true })
const page = await browser.newPage({ viewport: { width: 1600, height: 900 }, deviceScaleFactor: 1 })
const widths = [1024, 1100, 1180, 1280, 1300, 1320, 1340, 1366, 1400, 1440, 1536, 1600, 1920]

for (const locale of ['ar', 'en']) {
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto(`http://127.0.0.1:8000/${locale}`, { waitUntil: 'networkidle' })
    await page.waitForSelector('.ps-header')
    await page.waitForFunction(() => Number(document.querySelector('.ps-header')?.dataset.navRequired) > 0)
    const state = await page.locator('.ps-header').evaluate((el) => ({
      nav: el.dataset.nav,
      required: Number(el.dataset.navRequired),
      available: Number(el.dataset.navAvailable),
      free: Number(el.dataset.navFree),
    }))
    console.log(JSON.stringify({ locale, width, ...state }))
  }
}
await browser.close()
