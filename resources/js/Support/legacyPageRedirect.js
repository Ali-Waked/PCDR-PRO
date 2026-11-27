/**
 * Deliberate compatibility redirects for old aggregate-page hash links.
 * The hash is never used for primary navigation; it is only translated once
 * into the new localized route, preserving any existing query string.
 */
const HASH_TO_PATH = Object.freeze({
    '#about': '/about/vision',
    '#vision': '/about/vision',
    '#objectives': '/about/objectives',
    '#achievements': '/about/achievements',
    '#board': '/about/board',
    '#news': '/media/news',
    '#albums': '/media/albums',
    '#stories': '/media/stories',
    '#contact': '/contact',
})

const LOCALES = ['ar', 'en']

export function legacyPathForHash(hash, locale = 'ar') {
    if (! LOCALES.includes(locale)) {
        return null
    }

    const key = String(hash || '').trim().toLowerCase()
    const path = HASH_TO_PATH[key]

    return path ? `/${locale}${path}` : null
}

export function legacyRedirectUrl(href, locale = 'ar') {
    try {
        const url = new URL(String(href), 'https://pcdr.local')
        const path = legacyPathForHash(url.hash, locale)

        if (! path) {
            return null
        }

        url.pathname = path
        url.hash = ''

        return `${url.pathname}${url.search}`
    } catch {
        return null
    }
}
