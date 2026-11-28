import { expect, test } from '@playwright/test'
import {
  credentialsMissingReason,
  getCredentials,
  gotoWithAuth,
  waitForApp,
} from './helpers/auth.mjs'

const VIDEO_A = 'https://www.youtube.com/watch?v=abcdefghijk'
const VIDEO_B = 'https://www.youtube.com/shorts/zyxwvutsrqp'
const FACEBOOK_VIDEO = 'https://www.facebook.com/watch?v=123456789012345'
const YOUTUBE_TITLE_EN = 'E2E Introduction Film'
const YOUTUBE_TITLE_AR = 'الفيلم التعريفي التجريبي'

const widths = [360, 390, 430, 768, 1280, 1440]

async function openSettings(page) {
  const outcome = await gotoWithAuth(page, '/en/cms/settings', 'en')
  await waitForApp(page).catch(() => {})
  test.skip(
    outcome.skipped || outcome.redirectedToLogin || outcome.response?.status() === 403,
    outcome.appError || 'The seeded user cannot open CMS Website Settings.',
  )
  await expect(page.locator('[data-cms-footer-video]')).toBeVisible()
  return page.locator('[data-cms-footer-video]')
}

async function choose(page, card, testId, label) {
  const control = card.getByTestId(testId)
  await control.locator('.pc-selection__trigger').click()
  const listbox = page.locator('.pc-selection__panel .pc-selection__list')
  await expect(listbox).toBeVisible()
  await listbox.getByRole('option', { name: label, exact: true }).click()
}

async function saveSettings(page) {
  const save = page.locator('button[form="settings-form"]')
  await save.click()
  await expect(save).toBeEnabled()
  await expect(page.locator('[data-cms-footer-video]')).toBeVisible()
}

async function openGuest(browser, locale, width) {
  const context = await browser.newContext({
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    locale: locale === 'ar' ? 'ar-SA' : 'en-US',
    viewport: { width, height: 900 },
  })
  await context.route('https://www.youtube-nocookie.com/**', (route) => route.fulfill({
    status: 200,
    contentType: 'text/html',
    body: '<!doctype html><html><body>Embedded player</body></html>',
  }))
  await context.route('https://www.facebook.com/plugins/**', (route) => route.fulfill({
    status: 200,
    contentType: 'text/html',
    body: '<!doctype html><html><body>Embedded player</body></html>',
  }))

  const page = await context.newPage()
  const response = await page.goto(`/${locale}/contact`, { waitUntil: 'domcontentloaded' })
  await waitForApp(page)
  expect(response?.status()).toBe(200)

  return { context, page }
}

async function assertFooterVideo(page, locale, expectedWatchUrl, expectedEmbedPart) {
  await expect(page.locator('[data-footer-video]')).toBeVisible()
  await expect(page.locator('[data-footer-video] h3')).toHaveText(
    locale === 'ar' ? 'المرئيات' : 'Audiovisuals',
  )
  await expect(page.locator('[data-footer-video-external]')).toHaveText(
    locale === 'ar' ? 'مشاهدة الفيديو' : 'Watch video',
  )
  await expect(page.locator('[data-footer-video-external]')).toHaveAttribute('href', expectedWatchUrl)

  const play = page.locator('[data-footer-video-play]')
  await expect(play).toHaveAttribute(
    'aria-label',
    locale === 'ar' ? `تشغيل الفيديو: ${YOUTUBE_TITLE_AR}` : `Play video: ${YOUTUBE_TITLE_EN}`,
  )
  await play.click()

  const iframe = page.locator('[data-footer-video-player]')
  await expect(iframe).toHaveAttribute('src', expectedEmbedPart)
  await expect(iframe).toHaveAttribute('referrerpolicy', 'strict-origin-when-cross-origin')
  await expect(iframe).toHaveAttribute('loading', 'lazy')
  await expect(iframe).toHaveAttribute('sandbox', /allow-scripts/)
  await expect(iframe).toHaveAttribute('allow', /encrypted-media/)
}

test('administrator configures, changes, hides and restores the public footer video', async ({ browser }) => {
  const { email, password } = getCredentials()
  test.skip(!email || !password, credentialsMissingReason())

  const context = await browser.newContext({
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    locale: 'en-US',
    viewport: { width: 1280, height: 900 },
  })
  const page = await context.newPage()

  try {
    let card = await openSettings(page)
    await choose(page, card, 'cms-footer-video-provider', 'YouTube')
    await card.getByTestId('cms-footer-video-url').fill(VIDEO_A)
    await card.getByTestId('cms-footer-video-title-en').fill(YOUTUBE_TITLE_EN)
    await card.getByTestId('cms-footer-video-title-ar').fill(YOUTUBE_TITLE_AR)
    await choose(page, card, 'cms-footer-video-visible', 'Enabled')
    await saveSettings(page)

    let guest = await openGuest(browser, 'en', 1280)
    try {
      await assertFooterVideo(pageForGuest(guest), 'en', 'https://www.youtube.com/watch?v=abcdefghijk', 'https://www.youtube-nocookie.com/embed/abcdefghijk')
    } finally {
      await guest.context.close()
    }

    card = await openSettings(page)
    await card.getByTestId('cms-footer-video-url').fill(VIDEO_B)
    await saveSettings(page)

    guest = await openGuest(browser, 'en', 1280)
    try {
      await assertFooterVideo(pageForGuest(guest), 'en', 'https://www.youtube.com/watch?v=zyxwvutsrqp', 'https://www.youtube-nocookie.com/embed/zyxwvutsrqp')
    } finally {
      await guest.context.close()
    }

    card = await openSettings(page)
    await choose(page, card, 'cms-footer-video-visible', 'Disabled')
    await saveSettings(page)

    guest = await openGuest(browser, 'en', 1280)
    try {
      await expect(guest.page.locator('[data-footer-video]')).toHaveCount(0)
    } finally {
      await guest.context.close()
    }

    card = await openSettings(page)
    await expect(card.getByTestId('cms-footer-video-url')).toHaveValue(VIDEO_B)
    await choose(page, card, 'cms-footer-video-visible', 'Enabled')
    await choose(page, card, 'cms-footer-video-provider', 'Facebook')
    await card.getByTestId('cms-footer-video-url').fill(FACEBOOK_VIDEO)
    await card.getByTestId('cms-footer-video-title-en').fill('E2E Facebook Film')
    await card.getByTestId('cms-footer-video-title-ar').fill('فيلم فيسبوك التجريبي')
    await saveSettings(page)

    guest = await openGuest(browser, 'en', 1280)
    try {
      await expect(guest.page.locator('[data-footer-video-external]')).toHaveAttribute(
        'href',
        'https://www.facebook.com/watch/?v=123456789012345',
      )
      await guest.page.locator('[data-footer-video-play]').click()
      await expect(guest.page.locator('[data-footer-video-player]')).toHaveAttribute(
        'src',
        /facebook\.com\/plugins\/video\.php/,
      )
    } finally {
      await guest.context.close()
    }
  } finally {
    await context.close()
  }
})

test('public footer player is responsive and mirrored in both locales', async ({ browser }) => {
  const { email, password } = getCredentials()
  test.skip(!email || !password, credentialsMissingReason())

  const context = await browser.newContext({
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    locale: 'en-US',
    viewport: { width: 1280, height: 900 },
  })
  const page = await context.newPage()

  try {
    const card = await openSettings(page)
    await choose(page, card, 'cms-footer-video-provider', 'YouTube')
    await card.getByTestId('cms-footer-video-url').fill(VIDEO_B)
    await card.getByTestId('cms-footer-video-title-en').fill(YOUTUBE_TITLE_EN)
    await card.getByTestId('cms-footer-video-title-ar').fill(YOUTUBE_TITLE_AR)
    await choose(page, card, 'cms-footer-video-visible', 'Enabled')
    await saveSettings(page)
  } finally {
    await context.close()
  }

  for (const width of widths) {
    for (const locale of ['ar', 'en']) {
      const guest = await openGuest(browser, locale, width)
      try {
        const metrics = await guest.page.evaluate(() => {
          const viewport = document.documentElement.clientWidth || window.innerWidth
          const frame = document.querySelector('[data-footer-video-frame]')
          const base = document.querySelector('.foot-base')
          const box = frame?.getBoundingClientRect()

          return {
            direction: document.documentElement.dir,
            viewport,
            scrollWidth: document.documentElement.scrollWidth,
            frameWidth: box?.width ?? 0,
            frameHeight: box?.height ?? 0,
            frameRight: box?.right ?? 0,
            baseTop: base?.getBoundingClientRect().top ?? Number.POSITIVE_INFINITY,
            frameBottom: box?.bottom ?? 0,
          }
        })

        expect(metrics.direction).toBe(locale === 'ar' ? 'rtl' : 'ltr')
        expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.viewport + 1)
        expect(metrics.frameWidth).toBeGreaterThan(0)
        expect(metrics.frameWidth).toBeLessThanOrEqual(metrics.viewport + 1)
        expect(metrics.frameRight).toBeLessThanOrEqual(metrics.viewport + 1)
        expect(Math.abs(metrics.frameWidth / metrics.frameHeight - 16 / 9)).toBeLessThan(0.05)
        expect(metrics.frameBottom).toBeLessThanOrEqual(metrics.baseTop + 1)

        await expect(guest.page.locator('[data-footer-video-external]')).toBeVisible()
      } finally {
        await guest.context.close()
      }
    }
  }
})

function pageForGuest(guest) {
  return guest.page
}
