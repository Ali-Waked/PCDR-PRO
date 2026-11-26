import { test, expect } from '@playwright/test'

const routes = ['/ar/about','/ar/strategic-areas','/ar/programs','/ar/news','/ar/publications','/ar/transparency','/ar/partnerships','/ar/get-involved','/ar/contact','/en/about','/en/strategic-areas','/en/programs','/en/news','/en/publications','/en/transparency','/en/partnerships','/en/get-involved','/en/contact']
const homepageRoutes = ['/ar', '/en']

function normalizeLocale(value) {
  return String(value || '').replace(/\/(ar|en)(?=\/|["'\s<]|$)/g, '/{locale}')
}

function normalizeFooter(html) {
  return normalizeLocale(html)
    .replace(/\s+/g, ' ')
    .replace(/lang="[^"]+"/g, '')
    .replace(/dir="[^"]+"/g, '')
    .replace(/aria-label="[^"]+"/g, '')
    .replace(/>[^<]+</g, '><')
    .trim()
}

test('shared footer is identical across inner public routes', async ({ page }) => {
  let baseline = null
  let baselineHrefs = null
  for (const route of routes) {
    await page.goto(route, { waitUntil: 'networkidle' })
    const footers = page.locator('footer.ps-footer')
    await expect(footers).toHaveCount(1)
    const groups = footers.locator('.ps-footer__group')
    await expect(groups, route).toHaveCount(3)
    for (let i = 0; i < 3; i++) {
      await expect(groups.nth(i), `${route} group ${i}`).toBeVisible()
    }
    const hrefs = await footers.locator('a[href]').evaluateAll((as) => {
      const normalizeHref = (value) => String(value || '').replace(/\/(ar|en)(?=\/|["'\s<]|$)/g, '/{locale}')
      return as
        .map((a) => a.getAttribute('href'))
        .filter(Boolean)
        .map(normalizeHref)
        .sort()
    })
    const html = normalizeFooter(await footers.first().evaluate((el) => el.outerHTML))
    if (!baseline) { baseline = html; baselineHrefs = hrefs }
    expect(hrefs, route).toEqual(baselineHrefs)
    expect(html, route).toBe(baseline)
  }
})

test('inner pages show all three footer groups on mobile accordion', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  for (const route of ['/ar/contact', '/en/contact']) {
    await page.goto(route, { waitUntil: 'networkidle' })
    const footer = page.locator('footer.ps-footer')
    const buttons = footer.locator('.ps-footer__accordion-btn')
    await expect(buttons, route).toHaveCount(3)
    for (let i = 0; i < 3; i++) {
      await expect(buttons.nth(i), `${route} accordion ${i}`).toBeVisible()
    }
    await buttons.first().click()
    await expect(footer.locator('.ps-footer__group.is-open .ps-footer__link').first(), route).toBeVisible()
  }
})

test('homepage uses the prototype footer instead of the shared footer', async ({ page }) => {
  for (const route of homepageRoutes) {
    await page.goto(route, { waitUntil: 'networkidle' })
    await expect(page.locator('footer.ps-footer'), route).toHaveCount(0)

    const prototype = page.locator('.pcdr-prototype')
    await expect(prototype, route).toHaveCount(1)
    const footer = prototype.locator('footer.foot#contact')
    await expect(footer, route).toHaveCount(1)
    await expect(footer, route).toBeVisible()

    const sections = footer.locator('.foot-grid > section')
    await expect(sections, `${route} prototype footer sections`).toHaveCount(3)
    for (let i = 0; i < 3; i++) {
      await expect(sections.nth(i), `${route} prototype footer section ${i}`).toBeVisible()
    }
    await expect(footer.locator('a.link[href="#about"]'), route).toHaveCount(1)
    await expect(footer.locator('a.link[href="#contact"]'), route).toHaveCount(1)
    await expect(footer.locator('.foot-base'), route).toBeVisible()
    await expect(prototype.locator('a.fab[href="#contact"]'), route).toHaveCount(1)
  }
})
