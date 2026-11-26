import { firefox } from '@playwright/test'
import { gotoWithAuth } from './helpers/auth.mjs'
import { discoverAdminRoutes, loadFixtureIds, resolveRoutePath } from './helpers/routes.mjs'

const browser = await firefox.launch({ headless: true })
const context = await browser.newContext({
  baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
  viewport: { width: 1440, height: 900 },
})
const page = await context.newPage()
const routes = discoverAdminRoutes()
const fixtures = loadFixtureIds()

console.log(JSON.stringify({ route: 'route', topBars: 'topBars', genericHeader: 'genericHeader', h1Count: 'h1Count' }))
for (const route of routes) {
  const resolved = resolveRoutePath(route, 'ar', fixtures)
  if (!resolved.path) {
    console.log(JSON.stringify({ route: route.name, topBars: null, genericHeader: null, h1Count: null, skipped: resolved.reason }))
    continue
  }

  let outcome
  try {
    outcome = await gotoWithAuth(page, resolved.path, 'ar')
  } catch (error) {
    console.log(JSON.stringify({ route: route.name, topBars: null, genericHeader: null, h1Count: null, skipped: String(error.message || error).split('\n')[0] }))
    continue
  }
  if (outcome.skipped || outcome.redirectedToLogin) {
    console.log(JSON.stringify({ route: route.name, topBars: null, genericHeader: null, h1Count: null, skipped: outcome.appError || 'redirected to login' }))
    continue
  }

  const state = await page.evaluate(() => {
    const text = document.body.innerText
    return {
      topBars: document.querySelectorAll('.pc-topbar').length,
      genericHeader: /مساحة إدارة الموقع|Website management workspace/.test(text)
        && /مساحة عمل مركّزة للموقع العام ومحتواه|A focused workspace for the public website and its content\./.test(text),
      h1Count: document.querySelectorAll('h1').length,
      h1Texts: [...document.querySelectorAll('h1')].map((node) => node.innerText.trim()),
    }
  })
  console.log(JSON.stringify({ route: route.name, topBars: state.topBars, genericHeader: state.genericHeader, h1Count: state.h1Count, h1Texts: state.h1Texts }))
}

await browser.close()
