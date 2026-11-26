import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { mkdirSync } from 'node:fs'

const outputDir = 'tests/e2e/screenshots/social-links'
const platforms = ['facebook', 'x', 'instagram', 'linkedin', 'youtube', 'tiktok', 'snapchat', 'whatsapp']
const widths = [390, 1440]
const heights = { 390: 844, 1440: 900 }
const locales = ['ar', 'en']

const labels = {
  ar: {
    facebook: 'فيسبوك', x: 'إكس', instagram: 'إنستغرام', linkedin: 'لينكدإن',
    youtube: 'يوتيوب', tiktok: 'تيك توك', snapchat: 'سناب شات', whatsapp: 'واتساب',
  },
  en: {
    facebook: 'Facebook', x: 'X', instagram: 'Instagram', linkedin: 'LinkedIn',
    youtube: 'YouTube', tiktok: 'TikTok', snapchat: 'Snapchat', whatsapp: 'WhatsApp',
  },
}

// Fixture values only: they are injected into the Inertia payload in the
// browser and are never written to the CMS or treated as real PCDR accounts.
function fixtureLinks(locale) {
  return platforms.map((platform) => ({
    platform,
    label: labels[locale][platform],
    url: `https://www.${platform}.test/pcdr-test-handle`,
  })).map((link, index) => ({
    ...link,
    url: [
      'https://www.facebook.com/pcdr-test-handle',
      'https://x.com/pcdr-test-handle',
      'https://www.instagram.com/pcdr-test-handle/',
      'https://www.linkedin.com/in/pcdr-test-handle/',
      'https://www.youtube.com/@pcdr-test-handle',
      'https://www.tiktok.com/@pcdr-test-handle',
      'https://www.snapchat.com/add/pcdr-test-handle',
      'https://wa.me/970591234567',
    ][index],
  }))
}

async function routeWithFixture(page, locale) {
  await page.route(`**/${locale}/contact`, async (route) => {
    const response = await route.fetch()
    const html = await response.text()
    const patched = html.replace(
      /(<script data-page="app" type="application\/json">)(.*?)(<\/script>)/s,
      (_match, open, payload, close) => {
        const data = JSON.parse(payload)
        data.props.site = { ...(data.props.site || {}), social: fixtureLinks(locale) }
        return `${open}${JSON.stringify(data).replace(/</g, '\\u003c')}${close}`
      },
    )

    await route.fulfill({ response, body: patched })
  })
}

async function openContact(browser, locale, width, reducedMotion = 'no-preference') {
  const context = await browser.newContext({
    viewport: { width, height: heights[width] },
    locale: locale === 'ar' ? 'ar' : 'en-US',
    reducedMotion,
  })
  const page = await context.newPage()
  await routeWithFixture(page, locale)
  await page.goto(`/${locale}/contact`, { waitUntil: 'networkidle' })
  await expect(page.locator('.ps-social-links')).toBeVisible()
  return { context, page }
}

test.describe('public social links', () => {
  for (const locale of locales) {
    for (const width of widths) {
      test(`${locale} @ ${width}: accessible, non-shifting, no overflow`, async ({ browser }) => {
        mkdirSync(outputDir, { recursive: true })
        const { context, page } = await openContact(browser, locale, width)

        const links = page.locator('.ps-social-links__link')
        await expect(links).toHaveCount(platforms.length)

        for (let index = 0; index < platforms.length; index += 1) {
          const link = links.nth(index)
          await expect(link).toHaveAttribute('target', '_blank')
          await expect(link).toHaveAttribute('rel', /noopener/)
          await expect(link).toHaveAttribute('rel', /noreferrer/)
          await expect(link).toHaveAttribute('aria-label', /—.+/)

          const box = await link.boundingBox()
          expect(box.width, `${platforms[index]} hit width`).toBeGreaterThanOrEqual(44)
          expect(box.height, `${platforms[index]} hit height`).toBeGreaterThanOrEqual(44)
        }

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
        expect(overflow, 'horizontal overflow').toBeLessThanOrEqual(0)

        const first = links.first()
        const before = await first.boundingBox()
        await first.hover()
        const after = await first.boundingBox()
        expect(after.x, 'hover must not shift layout').toBe(before.x)
        expect(after.y, 'hover must not shift layout').toBe(before.y)

        const icon = first.locator('svg')
        await expect(icon).toHaveAttribute('viewBox', '0 0 24 24')
        await expect(icon).toHaveAttribute('aria-hidden', 'true')
        await expect(icon).toHaveAttribute('focusable', 'false')

        const axe = await new AxeBuilder({ page })
          .include('.ps-social-links')
          .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
          .analyze()
        expect(axe.violations, 'axe violations').toEqual([])

        await page.screenshot({ path: `${outputDir}/${locale}-${width}.png`, fullPage: false })
        await context.close()
      })
    }
  }

  test('reduced motion renders final state with no animations', async ({ browser }) => {
    const { context, page } = await openContact(browser, 'en', 1440, 'reduce')
    const animations = await page.locator('.ps-social-links').evaluate((root) => root.getAnimations().length)
    expect(animations).toBe(0)

    const link = page.locator('.ps-social-links__link').first()
    await link.hover()
    const transform = await link.evaluate((element) => getComputedStyle(element).transform)
    expect(['none', 'matrix(1, 0, 0, 1, 0, 0)']).toContain(transform)
    await context.close()
  })
})
