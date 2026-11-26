import { test, expect } from '@playwright/test'
import { gotoWithAuth } from './helpers/auth.mjs'
import { assertSingleShell } from './helpers/admin-shell.mjs'
import { discoverAdminRoutes, loadFixtureIds, resolveRoutePath } from './helpers/routes.mjs'

const TARGETS = [
  { name: 'access', path: 'admin/access', title: { ar: 'الأدوار والصلاحيات', en: 'Roles & Permissions' } },
  { name: 'users', path: 'admin/users', title: { ar: 'إدارة المستخدمين', en: 'User management' } },
  { name: 'audit-logs', path: 'admin/audit-logs', title: { ar: 'سجلات التدقيق', en: 'Audit logs' } },
  { name: 'audit-archives', path: 'admin/audit-archives', title: { ar: 'أرشيف سجلات التدقيق', en: 'Audit archives' } },
  { name: 'settings', path: 'admin/settings', title: { ar: 'إعدادات المنصة', en: 'Platform settings' } },
]
const LOCALES = ['ar', 'en']
const SIZES = [
  { width: 390, height: 844 },
  { width: 1440, height: 900 },
]

for (const locale of LOCALES) {
  for (const size of SIZES) {
    test.describe(`admin shell / ${locale} / ${size.width}x${size.height}`, () => {
      test.use({ viewport: size })

      for (const target of TARGETS) {
        test(`${target.name} keeps exactly one shell and its own page title`, async ({ page }) => {
          const outcome = await gotoWithAuth(page, `/${locale}/${target.path}`, locale)
          test.skip(Boolean(outcome.skipped || outcome.redirectedToLogin), outcome.appError || 'authentication redirected')
          await assertSingleShell(page, { expectedTitle: target.title[locale] })
        })
      }
    })
  }
}

test('all discoverable admin routes keep one shell', async ({ page }) => {
  const routes = discoverAdminRoutes()
  const fixtures = loadFixtureIds()
  let checked = 0

  for (const route of routes) {
    // Phase 5 delivery pages use a different shell; only admin/CMS nav
    // destinations must keep the shared DashboardChrome invariant.
    if (!/^(admin\.|cms\.)/.test(route.name) || /\.(download|export)$/.test(route.name) || route.name === 'cms.preview') {
      continue
    }

    const resolved = resolveRoutePath(route, 'ar', fixtures)
    if (!resolved.path) {
      continue
    }

    let outcome
    try {
      outcome = await gotoWithAuth(page, resolved.path, 'ar')
    } catch {
      continue
    }
    if (outcome.skipped || outcome.redirectedToLogin) {
      continue
    }

    await assertSingleShell(page)
    checked += 1
  }

  expect(checked).toBeGreaterThan(20)
})
