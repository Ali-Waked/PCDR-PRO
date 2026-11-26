import { expect } from '@playwright/test'

export const GENERIC_HEADER_STRINGS = [
  'مساحة إدارة الموقع',
  'Website management workspace',
  'مساحة عمل مركّزة للموقع العام ومحتواه',
  'A focused workspace for the public website and its content.',
]

/**
 * Shared invariant for every admin shell: one chrome, one page header,
 * no generic CMS fallback copy, no overflow, and readable header text.
 */
export async function assertSingleShell(page, { expectedTitle = null } = {}) {
  const state = await page.evaluate(() => {
    const headerRoots = [...document.querySelectorAll('.pc-topbar, .pc-page-head, .pc-access-header')]
    const textNodes = headerRoots.flatMap((root) => [...root.querySelectorAll('*')])
      .filter((node) => node.children.length === 0 && node.textContent.trim() !== '' && node.getClientRects().length > 0)
      .map((node) => ({
        text: node.textContent.trim().slice(0, 80),
        size: Number.parseFloat(getComputedStyle(node).fontSize),
      }))

    return {
      topBars: document.querySelectorAll('.pc-topbar').length,
      languageSwitchers: document.querySelectorAll('.lang-switcher[role="group"]').length,
      userMenus: document.querySelectorAll('.pc-user').length,
      h1: [...document.querySelectorAll('h1')].map((node) => node.textContent.trim()),
      bodyText: document.body.innerText,
      smallHeaderText: textNodes.filter((node) => node.size < 13),
      scrollWidth: document.documentElement.scrollWidth,
      innerWidth: window.innerWidth,
    }
  })

  expect(state.topBars, `expected one .pc-topbar, saw ${state.topBars}`).toBe(1)
  expect(state.languageSwitchers, `expected one language switcher, saw ${state.languageSwitchers}`).toBe(1)
  expect(state.userMenus, `expected one user menu, saw ${state.userMenus}`).toBe(1)
  expect(state.h1, 'expected exactly one page h1').toHaveLength(1)

  if (expectedTitle !== null) {
    expect(state.h1[0], 'h1 must be the page title').toBe(expectedTitle)
    expect(state.h1[0], 'generic dashboard title must not be the page h1 on these routes').not.toBe('لوحة التحكم')
    expect(state.h1[0], 'generic dashboard title must not be the page h1 on these routes').not.toBe('Dashboard')
  }

  for (const banned of GENERIC_HEADER_STRINGS) {
    expect(state.bodyText, `generic header string still present: ${banned}`).not.toContain(banned)
  }

  expect(state.scrollWidth, 'horizontal scroll detected').toBeLessThanOrEqual(state.innerWidth)
  expect(state.smallHeaderText, `header text below 13px: ${JSON.stringify(state.smallHeaderText)}`).toEqual([])

  return state
}
