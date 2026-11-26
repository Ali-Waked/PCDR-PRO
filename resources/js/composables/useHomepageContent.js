import { computed, toValue } from 'vue'

/**
 * Reads CMS-provided homepage sections and items without inventing content.
 * All components consuming this composable render only values that exist in
 * the backend response.
 */
export function useHomepageContent(sectionsSource) {
    // Accept a value, a ref or a getter. `unref` does not invoke getters, so a
    // getter would otherwise be treated as the sections array itself.
    const sections = computed(() => {
        const value = toValue(sectionsSource)
        return Array.isArray(value) ? value : []
    })

    const sectionsByKey = computed(() => Object.fromEntries(
        sections.value
            .filter((section) => section && section.key)
            .map((section) => [section.key, section]),
    ))

    function section(key) {
        return sectionsByKey.value[key] || null
    }

    function text(record, field, fallback = '') {
        const value = record?.[field]
        return typeof value === 'string' && value.trim() !== ''
            ? value
            : (value !== null && value !== undefined && value !== '' ? String(value) : fallback)
    }

    function items(key, prefix = '') {
        return [...(section(key)?.items || [])]
            .filter((item) => item && item.published !== false)
            .filter((item) => prefix === '' || String(item.key || '').startsWith(prefix))
            .sort((first, second) => (first.sortOrder ?? 0) - (second.sortOrder ?? 0))
    }

    function itemsByTab(sectionKey, tab) {
        return items(sectionKey).filter((item) => text(item, 'tab') === tab)
    }

    /** Only prototype-approved destinations become links; "#" stays inert. */
    function safeUrl(value) {
        if (typeof value !== 'string') {
            return ''
        }

        const url = value.trim()
        if (!url || url === '#') {
            return ''
        }
        if (/[\\\u0000-\u001F\u007F\s]/i.test(url) || /%5c/i.test(url)) {
            return ''
        }
        if (/^https:\/\/[^\s]+$/i.test(url)) {
            return url
        }
        if (/^#[A-Za-z0-9_:-]+$/.test(url)) {
            return url
        }
        if (url.startsWith('/') && !url.startsWith('//')) {
            return isRemovedPublicPath(url) ? '' : url
        }

        return ''
    }

    /** Public inner pages no longer exist, so stored links to them stay inert. */
    function isRemovedPublicPath(url) {
        let path = url.split('?')[0].split('#')[0]
        if (!path.startsWith('/')) {
            return false
        }
        if (/^\/(ar|en)\/?$/.test(path)) {
            return false
        }
        path = path.replace(/^\/(ar|en)(?=\/)/, '')

        return ['about', 'strategic-areas', 'programs', 'projects', 'news',
            'publications', 'transparency', 'partners', 'partnerships',
            'get-involved', 'contact', 'complaints', 'pages']
            .some((prefix) => path === `/${prefix}` || path.startsWith(`/${prefix}/`))
    }

    function mediaUrl(item) {
        return safeUrl(item?.media?.url)
    }

    function mediaAlt(item) {
        return typeof item?.media?.alt === 'string' ? item.media.alt : ''
    }

    return { sectionsByKey, section, text, items, itemsByTab, safeUrl, mediaUrl, mediaAlt }
}
