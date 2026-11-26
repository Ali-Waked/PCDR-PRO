import fs from 'node:fs'
import path from 'node:path'
import { firefox } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { gotoWithAuth, waitForApp } from './helpers/auth.mjs'

const OUT = path.resolve('tests/e2e/screenshots/theme')
fs.mkdirSync(OUT, { recursive: true })

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000'
const browser = await firefox.launch({ headless: true })
const problems = []

const LABELS = {
    en: { light: 'Light', dark: 'Dark', system: 'System' },
    ar: { light: 'فاتح', dark: 'داكن', system: 'النظام' },
}

async function open(page, locale, route) {
    const outcome = await gotoWithAuth(page, `/${locale}${route}`, locale)
    if (outcome.skipped || outcome.redirectedToLogin) {
        throw new Error(outcome.appError || `could not open ${route} as ${locale}`)
    }
    await waitForApp(page)
}

async function setTheme(page, locale, preference) {
    await open(page, locale, '/dashboard/profile')

    const save = page.locator('form button[type="submit"]').first()
    const responsePromise = page.waitForResponse((response) => response.request().method() === 'PUT'
        && response.url().includes('/dashboard/profile'), { timeout: 20_000 })
    await page.locator('.pc-theme-selector:not(.pc-theme-selector--menu)')
        .getByRole('radio', { name: LABELS[locale][preference] })
        .click()
    await save.click()
    await responsePromise
    await waitForApp(page)

    const persisted = await page.locator('.pc-theme-selector:not(.pc-theme-selector--menu)')
        .getByRole('radio', { name: LABELS[locale][preference] })
        .getAttribute('aria-checked')
    if (persisted !== 'true') {
        problems.push(`${locale}: ${preference} did not remain selected after save`)
    }
}

async function themeState(page) {
    return page.evaluate(() => {
        const root = document.documentElement
        const app = document.querySelector('.pc-app')
        const menu = document.querySelector('.pc-menu')

        function channels(value) {
            const rgb = value.match(/rgba?\(([^)]+)\)/)
            if (rgb) {
                return rgb[1].split(/[\s,/]+/).filter(Boolean).slice(0, 3).map(Number)
            }
            const srgb = value.match(/color\(srgb\s+([^)]+)\)/)
            if (srgb) {
                return srgb[1].split('/')[0].trim().split(/\s+/).map((part) => Number(part) * 255)
            }
            return null
        }

        function luminance(value) {
            const parts = channels(value)
            if (!parts || parts.length !== 3 || parts.some(Number.isNaN)) return null
            const [red, green, blue] = parts.map((part) => {
                const channel = part / 255
                return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
            })
            return 0.2126 * red + 0.7152 * green + 0.0722 * blue
        }

        const appStyle = app ? getComputedStyle(app) : null
        const menuStyle = menu ? getComputedStyle(menu) : null

        return {
            preference: root.dataset.themePreference || null,
            resolved: root.dataset.theme || null,
            colorScheme: root.style.colorScheme || null,
            metaColorScheme: document.querySelector('meta[name="color-scheme"]')?.getAttribute('content') || null,
            appBackground: appStyle?.backgroundColor || null,
            appLuminance: appStyle ? luminance(appStyle.backgroundColor) : null,
            menuBackground: menuStyle?.backgroundColor || null,
            menuLuminance: menuStyle ? luminance(menuStyle.backgroundColor) : null,
            scrollWidth: document.documentElement.scrollWidth,
            innerWidth: window.innerWidth,
        }
    })
}

function assertState(state, preference, expectedResolved, label) {
    if (state.preference !== preference) {
        problems.push(`${label}: preference ${state.preference}, expected ${preference}`)
    }
    if (state.resolved !== expectedResolved) {
        problems.push(`${label}: resolved ${state.resolved}, expected ${expectedResolved}`)
    }
    if (state.metaColorScheme !== expectedResolved) {
        problems.push(`${label}: meta color-scheme ${state.metaColorScheme}, expected ${expectedResolved}`)
    }
    if (state.scrollWidth > state.innerWidth) {
        problems.push(`${label}: horizontal overflow ${state.scrollWidth} > ${state.innerWidth}`)
    }
    if (state.appLuminance !== null) {
        const darkExpected = expectedResolved === 'dark'
        if (darkExpected && state.appLuminance > 0.08) {
            problems.push(`${label}: dark app background is too light (${state.appLuminance.toFixed(3)})`)
        }
        if (!darkExpected && state.appLuminance < 0.6) {
            problems.push(`${label}: light app background is too dark (${state.appLuminance.toFixed(3)})`)
        }
    }
}

async function captureOverlays(page, file) {
    await page.evaluate(() => {
        const probe = document.createElement('div')
        probe.id = 'theme-overlay-probe'
        probe.innerHTML = `
            <div class="pc-modal-backdrop">
                <div class="pc-modal">
                    <div class="pc-modal__head"><strong>Theme overlay check</strong></div>
                    <div class="pc-modal__body">Semantic surfaces must follow the active theme.</div>
                </div>
            </div>`
        document.body.appendChild(probe)
    })
    await page.screenshot({ path: file })
    return page.evaluate(() => {
        const modal = document.querySelector('#theme-overlay-probe .pc-modal')
        const backdrop = document.querySelector('#theme-overlay-probe .pc-modal-backdrop')
        return {
            modalBackground: modal ? getComputedStyle(modal).backgroundColor : null,
            modalColor: modal ? getComputedStyle(modal).color : null,
            backdropBackground: backdrop ? getComputedStyle(backdrop).backgroundColor : null,
        }
    }).finally(() => page.evaluate(() => document.querySelector('#theme-overlay-probe')?.remove()))
}

const ALL_SIZES = [
    { name: 'desktop', viewport: { width: 1440, height: 900 } },
    { name: 'mobile', viewport: { width: 390, height: 844 } },
]
const LOCALES = (process.env.THEME_LOCALES || 'en,ar').split(',').map((value) => value.trim()).filter(Boolean)
const SIZES = ALL_SIZES.filter((size) => (process.env.THEME_SIZES || 'desktop,mobile').split(',').map((value) => value.trim()).includes(size.name))
const PREFERENCES = (process.env.THEME_PREFERENCES || 'light,dark,system').split(',').map((value) => value.trim()).filter((value) => value && value !== 'none')
const RUN_AXE = process.env.THEME_AXE === '1'
const RUN_OS_CHANGE = process.env.THEME_OS_CHANGE !== '0'
const RUN_GUEST = process.env.THEME_GUEST === '1'
const FAST = process.env.THEME_FAST === '1'

for (const size of SIZES) {
    for (const locale of LOCALES) {
        const context = await browser.newContext({
            baseURL: BASE_URL,
            viewport: size.viewport,
            colorScheme: 'light',
        })
        const page = await context.newPage()
        page.setDefaultNavigationTimeout(90_000)

        for (const preference of PREFERENCES) {
            await setTheme(page, locale, preference)
            await open(page, locale, '/dashboard')
            const state = await themeState(page)
            assertState(state, preference, preference === 'system' ? 'light' : preference, `${locale}/${size.name}/${preference}`)
            await page.screenshot({ path: path.join(OUT, `dashboard-${locale}-${size.name}-${preference}.png`) })

            if (!FAST) {
                await page.locator('.pc-user__trigger').click()
                const menuState = await themeState(page)
                await page.screenshot({ path: path.join(OUT, `menu-${locale}-${size.name}-${preference}.png`) })
                await page.keyboard.press('Escape')
                if (menuState.menuBackground === null) {
                    problems.push(`${locale}/${size.name}/${preference}: user menu did not open`)
                }

                const overlays = await captureOverlays(page, path.join(OUT, `overlay-${locale}-${size.name}-${preference}.png`))
                if (!overlays.modalBackground || !overlays.backdropBackground) {
                    problems.push(`${locale}/${size.name}/${preference}: overlay probe missing surfaces`)
                }
            }
        }

        if (RUN_OS_CHANGE) {
            await setTheme(page, locale, 'system')
            await open(page, locale, '/dashboard')
            await page.emulateMedia({ colorScheme: 'dark' })
            await waitForApp(page)
            const osDark = await themeState(page)
            assertState(osDark, 'system', 'dark', `${locale}/${size.name}/system-os-dark`)
            await page.screenshot({ path: path.join(OUT, `dashboard-${locale}-${size.name}-system-os-dark.png`) })
        }

        if (RUN_AXE && size.name === 'desktop') {
            const results = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
                .analyze()
            const serious = results.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact))
            if (serious.length > 0) {
                problems.push(`${locale}/axe: ${serious.map((violation) => `${violation.id}(${violation.impact})`).join(', ')}`)
            }
            fs.writeFileSync(path.join(OUT, `axe-${locale}.json`), JSON.stringify(results.violations, null, 2))
        }

        await context.close()
    }
}

if (RUN_GUEST) {
    const guestContext = await browser.newContext({
        baseURL: BASE_URL,
        viewport: ALL_SIZES[0].viewport,
        colorScheme: 'light',
    })
    const guestPage = await guestContext.newPage()
    await guestPage.addInitScript(() => window.localStorage.setItem('pcdr-theme-preference', 'dark'))
    await guestPage.goto('/en', { waitUntil: 'domcontentloaded' })
    await waitForApp(guestPage)
    const guestState = await themeState(guestPage)
    assertState(guestState, 'dark', 'dark', 'guest-local-dark')
    await guestContext.close()
}

await browser.close()

if (problems.length > 0) {
    console.error(problems.join('\n'))
    process.exit(1)
}
console.log(`Theme visual checks passed. Screenshots: ${OUT}`)
