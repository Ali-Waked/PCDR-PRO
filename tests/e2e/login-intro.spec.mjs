import { expect, test } from '@playwright/test';

const LOGIN_URL = '/en/login';
const INTRO = '[data-auth-intro]';
const NAVY = 'rgb(62, 81, 114)';

test.beforeEach(async ({ page }) => {
    await page.route('https://fonts.googleapis.com/**', (route) => route.fulfill({
        status: 200,
        contentType: 'text/css',
        body: '',
    }));
    await page.route('https://fonts.gstatic.com/**', (route) => route.abort());
});

async function isAuthReady(page) {
    return page.evaluate(() => Boolean(document.querySelector('.pc-auth.is-ready')));
}

async function traceAuthIntro(page) {
    await page.addInitScript(() => {
        window.__authIntroTrace = { shownAt: null, readyAt: null, leaveAt: null, removedAt: null };

        const record = () => {
            const trace = window.__authIntroTrace;
            const intro = document.querySelector('[data-auth-intro]');

            if (intro && trace.shownAt === null) {
                trace.shownAt = performance.now();
            }
            if (trace.readyAt === null && document.querySelector('.pc-auth.is-ready')) {
                trace.readyAt = performance.now();
            }
            if (intro?.dataset.introState === 'leaving' && trace.leaveAt === null) {
                trace.leaveAt = performance.now();
            }
            if (!intro && trace.shownAt !== null && trace.removedAt === null) {
                trace.removedAt = performance.now();
            }
        };

        new MutationObserver(record).observe(document, {
            attributes: true,
            attributeFilter: ['class', 'data-intro-state'],
            childList: true,
            subtree: true,
        });
        record();
    });
}

test('holds the navy intro for at least three seconds on fast direct access', async ({ page }) => {
    await traceAuthIntro(page);
    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });

    const intro = page.locator(INTRO);
    await expect(intro).toHaveCount(1);
    await expect(intro).toBeVisible();
    expect(await intro.evaluate((node) => getComputedStyle(node).backgroundColor)).toBe(NAVY);

    await expect.poll(() => page.evaluate(() => window.__authIntroTrace.readyAt), { timeout: 10_000 }).toBeGreaterThan(0);
    await expect.poll(() => page.evaluate(() => window.__authIntroTrace.leaveAt), { timeout: 10_000 }).toBeGreaterThan(0);
    await expect(intro).toHaveCount(0, { timeout: 10_000 });

    const trace = await page.evaluate(() => window.__authIntroTrace);
    expect(trace.shownAt).toBeGreaterThan(0);
    expect(trace.leaveAt - trace.shownAt).toBeGreaterThanOrEqual(3000);
    expect(trace.leaveAt).toBeGreaterThanOrEqual(trace.readyAt);
    expect(trace.removedAt).toBeGreaterThanOrEqual(trace.leaveAt);
    await expect(page.locator('input[name="email"]')).toBeFocused();
});

test('keeps the intro opaque while a slow animated logo delays readiness', async ({ page }) => {
    await traceAuthIntro(page);
    await page.route('**/pcdr-logo-animated.svg', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 5000));
        await route.continue();
    });

    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });

    const intro = page.locator(INTRO);
    await expect(intro).toBeVisible();
    expect(await isAuthReady(page)).toBe(false);

    await page.waitForTimeout(3200);
    await expect(intro).toBeVisible();
    await expect(intro).toHaveAttribute('data-intro-state', 'visible');

    await expect.poll(() => isAuthReady(page), { timeout: 10_000 }).toBe(true);
    await expect.poll(() => page.evaluate(() => window.__authIntroTrace.leaveAt), { timeout: 10_000 }).toBeGreaterThan(0);
    await expect(intro).toHaveCount(0, { timeout: 10_000 });

    const trace = await page.evaluate(() => window.__authIntroTrace);
    expect(trace.leaveAt - trace.shownAt).toBeGreaterThanOrEqual(3000);
    expect(trace.leaveAt).toBeGreaterThanOrEqual(trace.readyAt);
    await expect(page.locator('input[name="email"]')).toBeVisible();
});

test('shows a navy boot canvas instead of a blank screen while CSS and JS are slow', async ({ page }) => {
    await page.route('**/build/assets/*.js', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 4000));
        await route.continue();
    });

    await page.goto(LOGIN_URL, { waitUntil: 'commit' });

    const boot = page.locator('#pcdr-boot-intro');
    await expect(boot).toBeVisible();

    const bootState = await page.evaluate(() => ({
        backgroundColor: getComputedStyle(document.querySelector('#pcdr-boot-intro')).backgroundColor,
        coversViewport: Boolean(document.elementFromPoint(innerWidth / 2, innerHeight / 2)?.closest('#pcdr-boot-intro')),
        scrollY: window.scrollY,
    }));

    expect(bootState.backgroundColor).toBe(NAVY);
    expect(bootState.coversViewport).toBe(true);
    expect(bootState.scrollY).toBe(0);

    await expect(page.locator(INTRO)).toBeVisible({ timeout: 15_000 });
    await expect(boot).toHaveCount(0);
});

test('falls back to the static logo when the animated asset fails', async ({ page }) => {
    await page.route('**/pcdr-logo-animated.svg', (route) => route.abort());
    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });

    const intro = page.locator(INTRO);
    await expect(intro).toBeVisible();
    await expect(intro.locator('img')).toHaveAttribute('src', /pcdr-logo\.png$/);
    await expect(intro).toHaveCount(0, { timeout: 10_000 });
    await expect(page.locator('input[name="email"]')).toBeVisible();
});

test('replays one fresh intro on Inertia return navigation and refresh', async ({ page }) => {
    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });

    const intro = page.locator(INTRO);
    await expect(intro).toBeVisible();
    await expect(intro).toHaveCount(1);
    await expect(intro).toHaveCount(0, { timeout: 10_000 });
    await expect(page.locator('input[name="email"]')).toBeFocused();

    await page.getByRole('link', { name: 'Forgot your password?' }).click();
    await expect(page).toHaveURL(/\/en\/forgot-password/);
    await expect(intro).toHaveCount(0);

    await page.goBack();
    await expect(page).toHaveURL(/\/en\/login/);
    await expect(intro).toBeVisible();
    await expect(intro).toHaveCount(1);
    await expect(intro).toHaveCount(0, { timeout: 10_000 });

    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(intro).toBeVisible();
    await expect(intro).toHaveCount(1);
    await expect(intro).toHaveCount(0, { timeout: 10_000 });
});

test('skips the intro entirely for reduced-motion visitors', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });

    await expect(page.locator('.pc-auth')).toBeVisible();
    await expect(page.locator('#pcdr-boot-intro')).toHaveCount(0);
    await expect(page.locator(INTRO)).toHaveCount(0);
    await expect(page.locator('input[name="email"]')).toBeFocused();
});
