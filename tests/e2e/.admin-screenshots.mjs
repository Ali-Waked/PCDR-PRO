import fs from 'node:fs'
import path from 'node:path'
import { firefox } from '@playwright/test'
import { gotoWithAuth, waitForApp } from './helpers/auth.mjs'

const PHASE = process.env.SCREENSHOT_PHASE || 'before'
const OUT = path.resolve('tests/e2e/screenshots')
fs.mkdirSync(OUT, { recursive: true })

const ROUTES = [
  ['access', '/admin/access'],
  ['users', '/admin/users'],
  ['audit-logs', '/admin/audit-logs'],
  ['audit-archives', '/admin/audit-archives'],
  ['settings', '/admin/settings'],
]
const LOCALES = ['ar', 'en']
const SIZES = [
  { width: 390, height: 844 },
  { width: 1440, height: 900 },
]

const browser = await firefox.launch({ headless: true })
for (const size of SIZES) {
  const context = await browser.newContext({
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    viewport: size,
  })
  const page = await context.newPage()
  for (const [name, route] of ROUTES) {
    for (const locale of LOCALES) {
      const outcome = await gotoWithAuth(page, route, locale)
      if (outcome.skipped) {
        console.error(`skip ${name}/${locale}: ${outcome.appError}`)
        continue
      }
      await waitForApp(page)
      const file = path.join(OUT, `admin-${PHASE}-${name}-${locale}-${size.width}.png`)
      await page.screenshot({ path: file })
      console.log(file)
    }
  }
  await context.close()
}
await browser.close()
