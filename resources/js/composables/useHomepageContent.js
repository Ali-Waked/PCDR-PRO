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

    function mediaUrl(item) {
        const url = item?.media?.url
        return typeof url === 'string' && /^(https:\/\/|\/)/.test(url) ? url : ''
    }

    function mediaAlt(item) {
        return typeof item?.media?.alt === 'string' ? item.media.alt : ''
    }

    return { sectionsByKey, section, text, items, mediaUrl, mediaAlt }
}
