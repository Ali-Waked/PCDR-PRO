import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { mkdirSync, writeFileSync } from 'node:fs'

const widths = [1024, 1100, 1180, 1280, 1366, 1440, 1536, 1600, 1920]
const scales = [1, 1.25, 1.5]
const locales = ['ar', 'en']
const outputDir = 'tests/e2e/screenshots/nav'

const prototypeLabels = {
    ar: ['من نحن', 'برامجنا', 'المركز الإعلامي', 'شركاؤنا', 'اتصل بنا'],
    en: ['About us', 'Our programmes', 'Media centre', 'Our partners', 'Contact us'],
}

function intersects(a, b) {
    return !(a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top)
}

function inside(box, container) {
    return box.left >= container.left - 0.5
        && box.right <= container.right + 0.5
        && box.top >= container.top - 0.5
        && box.bottom <= container.bottom + 0.5
}

async function prototypeState(page) {
    return page.locator('.pcdr-prototype').evaluate((root) => {
        const rect = (element) => {
            if (!element || element.getClientRects().length === 0) return null
            const box = element.getBoundingClientRect()
            return {
                left: box.left, right: box.right, top: box.top, bottom: box.bottom,
                width: box.width, height: box.height,
            }
        }
        const burger = root.querySelector('.burger')

        return {
            shell: rect(root),
            pill: rect(root.querySelector('.pill')),
            brand: rect(root.querySelector('.brand')),
            burger: rect(burger),
            burgerExpanded: burger?.getAttribute('aria-expanded') ?? null,
            menuHidden: root.querySelector('.menu')?.hidden === true,
            menu: rect(root.querySelector('.menu')),
            footer: rect(root.querySelector('footer.foot#contact')),
            scrollWidth: document.documentElement.scrollWidth,
            innerWidth: window.innerWidth,
        }
    })
}

async function gotoHome(page, locale) {
    await page.goto(`/${locale}`, { waitUntil: 'domcontentloaded', timeout: 60_000 })
    await page.locator('.pcdr-prototype').waitFor()
    await expect(page.locator('.pcdr-prototype .burger')).toBeVisible()
}

for (const scale of scales) {
    test.describe(`prototype header layout at deviceScaleFactor ${scale}`, () => {
        test.use({ deviceScaleFactor: scale })

        for (const locale of locales) {
            test(`${locale}: pill, burger and menu fit at every width`, async ({ page }) => {
                const rows = []

                for (const width of widths) {
                    await page.setViewportSize({ width, height: 900 })
                    await gotoHome(page, locale)
                    const state = await prototypeState(page)

                    expect(state.scrollWidth, `${locale} ${width} horizontal scroll`).toBeLessThanOrEqual(state.innerWidth)
                    expect(state.footer, `${locale} ${width} prototype footer`).not.toBeNull()

                    for (const [name, box] of [['pill', state.pill], ['brand', state.brand], ['burger', state.burger]]) {
                        expect(box, `${locale} ${width} ${name}`).not.toBeNull()
                        expect(inside(box, state.shell), `${locale} ${width} ${name} inside shell`).toBe(true)
                    }
                    expect(inside(state.brand, state.pill), `${locale} ${width} brand inside pill`).toBe(true)
                    expect(inside(state.burger, state.pill), `${locale} ${width} burger inside pill`).toBe(true)
                    expect(intersects(state.brand, state.burger), `${locale} ${width} brand/burger overlap`).toBe(false)
                    expect(state.burgerExpanded, `${locale} ${width} burger state`).toBe('false')
                    expect(state.menuHidden, `${locale} ${width} menu initially hidden`).toBe(true)

                    const burger = page.locator('.pcdr-prototype .burger')
                    const menu = page.locator('.pcdr-prototype .menu')
                    await burger.click()
                    await expect(menu).toBeVisible()
                    const openState = await prototypeState(page)
                    expect(openState.menu, `${locale} ${width} open menu`).not.toBeNull()
                    expect(openState.menu.left, `${locale} ${width} menu left`).toBeGreaterThanOrEqual(-0.5)
                    expect(openState.menu.right, `${locale} ${width} menu right`).toBeLessThanOrEqual(width + 0.5)
                    expect(openState.burgerExpanded, `${locale} ${width} expanded burger`).toBe('true')

                    await page.keyboard.press('Escape')
                    await expect(menu).toBeHidden()
                    await expect(burger).toBeFocused()

                    rows.push({ locale, scale, width, pill: state.pill, menu: openState.menu })
                }

                mkdirSync(outputDir, { recursive: true })
                writeFileSync(`${outputDir}/prototype-measurements-${locale}-${scale}.json`, JSON.stringify({
                    locale, scale, rows,
                }, null, 2))
                console.log(`${locale} scale=${scale}: prototype pill/menu measured at ${widths.length} widths`)
            })
        }
    })
}

test.describe('prototype pill menu', () => {
    test.use({ colorScheme: 'light' })

    test('burger toggles the menu; outside click and Escape close it', async ({ page }) => {
        await page.setViewportSize({ width: 1600, height: 900 })
        await gotoHome(page, 'ar')
        const burger = page.locator('.pcdr-prototype .burger')
        const menu = page.locator('.pcdr-prototype .menu')

        await expect(menu).toBeHidden()
        await expect(burger).toHaveAttribute('aria-expanded', 'false')

        await burger.click()
        await expect(menu).toBeVisible()
        await expect(burger).toHaveAttribute('aria-expanded', 'true')

        await burger.click()
        await expect(menu).toBeHidden()
        await expect(burger).toHaveAttribute('aria-expanded', 'false')

        await burger.click()
        await page.locator('footer.foot#contact').click({ position: { x: 5, y: 5 }, force: true })
        await expect(menu).toBeHidden()
        await expect(burger).toHaveAttribute('aria-expanded', 'false')

        await burger.click()
        await page.keyboard.press('Escape')
        await expect(menu).toBeHidden()
        await expect(burger).toBeFocused()
    })

    test('group toggles reveal only their own submenu and inert links stay spans', async ({ page }) => {
        await page.setViewportSize({ width: 1600, height: 900 })
        await gotoHome(page, 'en')
        await page.locator('.pcdr-prototype .burger').click()

        const aboutTrigger = page.locator('.pcdr-prototype .mi-h[aria-controls="sub-about"]')
        const mediaTrigger = page.locator('.pcdr-prototype .mi-h[aria-controls="sub-media"]')
        await expect(aboutTrigger).toHaveAttribute('aria-expanded', 'true')
        await expect(page.locator('#sub-about')).toBeVisible()
        await expect(mediaTrigger).toHaveAttribute('aria-expanded', 'false')
        await expect(page.locator('#sub-media')).toBeHidden()

        await mediaTrigger.click()
        await expect(mediaTrigger).toHaveAttribute('aria-expanded', 'true')
        await expect(page.locator('#sub-media')).toBeVisible()
        await expect(aboutTrigger).toHaveAttribute('aria-expanded', 'false')
        await expect(page.locator('#sub-about')).toBeHidden()

        await mediaTrigger.click()
        await expect(page.locator('#sub-media')).toBeHidden()

        const inertAbout = page.locator('#sub-about .sub-inert').first()
        await expect(inertAbout).toHaveCount(1)
        expect(await inertAbout.evaluate((el) => el.tagName)).toBe('SPAN')
        expect(await inertAbout.evaluate((el) => el.getAttribute('href'))).toBeNull()
        for (const inert of await page.locator('.pcdr-prototype .mi-h.mi-inert').all()) {
            expect(await inert.evaluate((el) => el.tagName)).toBe('SPAN')
        }
    })

    test('language row exposes both locales and marks the current one', async ({ page }) => {
        await page.setViewportSize({ width: 1600, height: 900 })
        await gotoHome(page, 'en')
        await page.locator('.pcdr-prototype .burger').click()

        const row = page.locator('.pcdr-prototype .lang-row')
        await expect(row).toBeVisible()
        await expect(row.locator('a')).toHaveCount(2)
        await expect(row.locator('a[aria-current="page"]')).toHaveCount(1)
        await expect(row.locator('a[lang="en"]')).toHaveAttribute('aria-current', 'page')
        await expect(row.locator('a[lang="ar"]')).toHaveAttribute('href', /\/ar\/?$/)
    })

    test('submenu links switch the matching prototype tabs', async ({ page }) => {
        await page.setViewportSize({ width: 1600, height: 900 })
        await gotoHome(page, 'en')
        const burger = page.locator('.pcdr-prototype .burger')
        const menu = page.locator('.pcdr-prototype .menu')

        await burger.click()
        await page.locator('#sub-about a[href="#about"]').first().click()
        await expect(page.locator('#about-vision')).toBeVisible()
        await expect(page.locator('#about-mission')).toBeHidden()
        await expect(menu).toBeHidden()

        await burger.click()
        await page.locator('#sub-media a[href="#news"]').nth(1).click()
        await expect(page.locator('#tab-publications')).toHaveAttribute('aria-selected', 'true')
        await expect(page.locator('#p-publications')).toBeVisible()
        await expect(menu).toBeHidden()
    })

    test('open menu stays readable over the pill', async ({ page }) => {
        await page.setViewportSize({ width: 1600, height: 900 })
        await gotoHome(page, 'ar')
        await expect(page.locator('.pcdr-prototype .pill')).toBeVisible()
        await page.locator('.pcdr-prototype .burger').click()

        const style = await page.locator('.pcdr-prototype .menu').evaluate((el) => {
            const cs = getComputedStyle(el)
            return { background: cs.backgroundColor, color: cs.color }
        })
        expect(style.background).toBe('rgb(255, 255, 255)')
        expect(style.color).toBe('rgb(20, 33, 59)')
    })
})

test.describe('prototype menu and footer on mobile', () => {
    test.use({ viewport: { width: 390, height: 844 } })

    for (const locale of locales) {
        test(`${locale}: burger labels, language row and prototype footer`, async ({ page }) => {
            await gotoHome(page, locale)
            const burger = page.locator('.pcdr-prototype .burger')
            const menu = page.locator('.pcdr-prototype .menu')

            await expect(burger).toBeVisible()
            await expect(menu).toBeHidden()
            await burger.click()
            await expect(menu).toBeVisible()

            const labels = (await menu.locator(':scope > .mi > .mi-h').allTextContents()).map((label) => label.trim())
            expect(labels).toEqual(prototypeLabels[locale])
            await expect(menu.locator('.lang-row')).toBeVisible()
            await expect(page.locator('footer.foot#contact')).toHaveCount(1)
            await expect(page.locator('footer.ps-footer')).toHaveCount(0)
        })
    }
})

test.describe('axe-core on the prototype header', () => {
    for (const locale of locales) {
        test(`${locale}: no violations with the menu closed and open`, async ({ page }) => {
            await page.setViewportSize({ width: 1600, height: 900 })
            await gotoHome(page, locale)

            const closed = await new AxeBuilder({ page })
                .include('.pcdr-prototype .head')
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
                .analyze()
            expect(closed.violations, 'closed menu').toEqual([])

            await page.locator('.pcdr-prototype .burger').click()
            await expect(page.locator('.pcdr-prototype .menu')).toBeVisible()
            const open = await new AxeBuilder({ page })
                .include('.pcdr-prototype .head')
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
                .analyze()
            expect(open.violations, 'open menu').toEqual([])
        })
    }
})

test('save before/after screenshots of the prototype header at 1280, 1366 and 1600', async ({ page }) => {
    mkdirSync(`${outputDir}/after`, { recursive: true })
    for (const locale of locales) {
        for (const width of [1280, 1366, 1600]) {
            await page.setViewportSize({ width, height: 900 })
            await gotoHome(page, locale)
            await expect(page.locator('.pcdr-prototype .burger')).toBeVisible()
            await page.screenshot({
                path: `${outputDir}/after/${locale}-${width}-prototype-closed.png`,
                clip: { x: 0, y: 0, width, height: 90 },
            })
            await page.locator('.pcdr-prototype .burger').click()
            await expect(page.locator('.pcdr-prototype .menu')).toBeVisible()
            await page.waitForTimeout(200)
            await page.screenshot({
                path: `${outputDir}/after/${locale}-${width}-prototype-open.png`,
                clip: { x: 0, y: 0, width, height: 420 },
            })
        }
    }
})
