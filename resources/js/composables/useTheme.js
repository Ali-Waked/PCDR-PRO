import { ref } from 'vue'

export const THEME_STORAGE_KEY = 'pcdr-theme-preference'
export const THEME_PREFERENCES = ['light', 'dark', 'system']

const systemMedia = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-color-scheme: dark)')
    : null
const reducedMotion = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : null
let transitionTimer = null

export function normalizeThemePreference(value) {
    return THEME_PREFERENCES.includes(value) ? value : 'system'
}

export function readStoredPreference() {
    try {
        return normalizeThemePreference(window.localStorage.getItem(THEME_STORAGE_KEY))
    } catch (error) {
        return 'system'
    }
}

export function resolveThemePreference(preference) {
    const normalized = normalizeThemePreference(preference)

    if (normalized !== 'system') {
        return normalized
    }

    return systemMedia?.matches ? 'dark' : 'light'
}

const initialPreference = normalizeThemePreference(
    typeof document !== 'undefined'
        ? document.documentElement.dataset.themePreference || readStoredPreference()
        : 'system',
)

export const themePreference = ref(initialPreference)
export const resolvedTheme = ref(resolveThemePreference(initialPreference))

export function applyThemePreference(value, options = {}) {
    const { persist = true, transition = true } = options
    const preference = normalizeThemePreference(value)
    const resolved = resolveThemePreference(preference)

    if (typeof document !== 'undefined') {
        const root = document.documentElement

        // data-theme always carries the resolved theme; data-theme-preference
        // keeps the raw choice so System can follow later OS changes.
        root.dataset.theme = resolved
        root.dataset.themePreference = preference
        root.classList.toggle('dark', resolved === 'dark')
        root.classList.toggle('light', resolved === 'light')
        root.style.colorScheme = resolved

        if (transition && ! reducedMotion?.matches) {
            root.classList.add('pcdr-theme-transition')
            window.clearTimeout(transitionTimer)
            transitionTimer = window.setTimeout(() => {
                root.classList.remove('pcdr-theme-transition')
            }, 180)
        }

        const colorSchemeMeta = document.querySelector('meta[name="color-scheme"]')
        if (colorSchemeMeta) {
            colorSchemeMeta.setAttribute('content', resolved)
        }

        const themeColorMeta = document.querySelector('meta[name="theme-color"]')
        if (themeColorMeta) {
            themeColorMeta.setAttribute('content', resolved === 'dark' ? '#111827' : '#3E5172')
        }
    }

    themePreference.value = preference
    resolvedTheme.value = resolved

    if (persist && typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(THEME_STORAGE_KEY, preference)
        } catch (error) {
            // Local storage is optional; the authenticated copy remains authoritative.
        }
    }

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('pcdr:themechange', {
            detail: { preference, resolved },
        }))
    }

    return resolved
}

export function useTheme() {
    return {
        themePreference,
        resolvedTheme,
        setThemePreference: applyThemePreference,
        readStoredPreference,
        normalizeThemePreference,
        resolveThemePreference,
    }
}

if (systemMedia && typeof systemMedia.addEventListener === 'function') {
    systemMedia.addEventListener('change', () => {
        if (themePreference.value === 'system') {
            applyThemePreference('system', { persist: false })
        }
    })
}

if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
        if (event.key === THEME_STORAGE_KEY) {
            applyThemePreference(event.newValue, { persist: false })
        }
    })
}
