import { computed } from 'vue'
import { useI18n } from './useI18n'

/**
 * Single source of truth for header, mobile menu and footer.
 *
 * `group` and `showInBar` only describe desktop-bar placement. The mobile menu
 * still renders the complete flat `links` list, and the footer still reads the
 * same items through `FOOTER_GROUPS`.
 */
const ITEMS = [
    { key: 'home', path: '', ar: 'الرئيسية', en: 'Home', shortAr: 'الرئيسية', shortEn: 'Home', group: null, showInBar: true, barOrder: 1 },
    { key: 'about', path: 'about', ar: 'عن الجمعية', en: 'About', shortAr: 'عن الجمعية', shortEn: 'About', group: 'about', showInBar: false, barOrder: 0 },
    { key: 'pillars', path: 'strategic-areas', ar: 'المحاور الاستراتيجية', en: 'Strategic Areas', shortAr: 'المحاور', shortEn: 'Areas', group: 'about', showInBar: false, barOrder: 0 },
    { key: 'programs', path: 'programs', ar: 'البرامج والمشاريع', en: 'Programs & Projects', shortAr: 'البرامج', shortEn: 'Programs', group: 'about', showInBar: false, barOrder: 0 },
    { key: 'news', path: 'news', ar: 'الأخبار والأنشطة', en: 'News & Activities', shortAr: 'الأخبار', shortEn: 'News', group: 'knowledge', showInBar: false, barOrder: 0 },
    { key: 'publications', path: 'publications', ar: 'التقارير والإصدارات', en: 'Reports & Publications', shortAr: 'التقارير', shortEn: 'Reports', group: 'knowledge', showInBar: false, barOrder: 0 },
    { key: 'transparency', path: 'transparency', ar: 'الشفافية والمساءلة', en: 'Transparency & Accountability', shortAr: 'الشفافية', shortEn: 'Transparency', group: 'knowledge', showInBar: false, barOrder: 0 },
    { key: 'partnerships', path: 'partnerships', ar: 'الشراكات', en: 'Partnerships', shortAr: 'الشراكات', shortEn: 'Partners', group: null, showInBar: true, barOrder: 4 },
    { key: 'contact', path: 'contact', ar: 'اتصل بنا', en: 'Contact', shortAr: 'اتصل بنا', shortEn: 'Contact', group: null, showInBar: true, barOrder: 5 },
]

/** Group titles are translation keys; group names never duplicate a page name. */
const BAR_GROUPS = [
    { key: 'about', labelKey: 'nav.groups.about', order: 2, keys: ['about', 'pillars', 'programs'] },
    { key: 'knowledge', labelKey: 'nav.groups.knowledge', order: 3, keys: ['news', 'publications', 'transparency'] },
]

const FOOTER_GROUPS = [
    {
        key: 'about',
        ar: 'عن الجمعية',
        en: 'About',
        items: [
            { key: 'about' },
            { key: 'pillars' },
            { key: 'programs' },
        ],
    },
    {
        key: 'knowledge',
        ar: 'المعرفة والمساءلة',
        en: 'Knowledge & Accountability',
        items: [
            { key: 'news' },
            { key: 'publications' },
            { key: 'transparency' },
            { key: 'complaints' },
        ],
    },
    {
        key: 'involve',
        ar: 'شارك معنا',
        en: 'Get Involved',
        items: [
            { key: 'partnerships' },
            { key: 'involve' },
            { key: 'contact', ar: 'تواصل معنا', en: 'Contact Us' },
        ],
    },
]

const EXTRA_ITEMS = {
    involve: { key: 'involve', path: 'get-involved', ar: 'شارك معنا', en: 'Get Involved', shortAr: 'شارك معنا', shortEn: 'Get Involved', group: null, showInBar: false, barOrder: 0 },
    'get-involved': { key: 'get-involved', path: 'get-involved', ar: 'شارك معنا', en: 'Get Involved', shortAr: 'شارك معنا', shortEn: 'Get Involved', group: null, showInBar: false, barOrder: 0 },
    complaints: { key: 'complaints', path: 'complaints', ar: 'صندوق الشكاوى', en: 'Complaints Box', shortAr: 'صندوق الشكاوى', shortEn: 'Complaints Box', group: null, showInBar: false, barOrder: 0 },
}

const ALL_ITEMS = [...ITEMS, ...Object.values(EXTRA_ITEMS)]
const ITEMS_BY_KEY = new Map(ITEMS.map((item) => [item.key, item]))

function resolveItem(entry) {
    const key = typeof entry === 'string' ? entry : entry?.key
    const base = ITEMS.find((item) => item.key === key) || EXTRA_ITEMS[key] || null
    if (!base) return null
    if (typeof entry === 'string' || !entry) return base

    return { ...base, ...entry }
}

const origin = typeof window === 'undefined' ? '' : window.location.origin

function pathOf(url) {
    try {
        return new URL(String(url || ''), origin).pathname.replace(/\/+$/, '') || '/'
    } catch (_) {
        return String(url || '')
    }
}

function relativePath(url) {
    return pathOf(url).replace(/^\/(ar|en)(?=\/|$)/, '') || ''
}

function canonicalKeyFor(url) {
    const relative = relativePath(url)

    return ALL_ITEMS.find((item) => item.path === relative)?.key
        || relative.split('/').filter(Boolean).pop()
        || ''
}

export function usePublicNavigation() {
    const { page, locale, localPath } = useI18n()
    const isEn = computed(() => locale.value === 'en')

    /**
     * CMS-seeded menus are the source of truth. The compiled ITEMS/FOOTER_GROUPS
     * below remain as a deterministic fallback when the site has not been seeded.
     */
    const seededNavigation = computed(() => page.props.navigation || {})

    function seededLinks(menu) {
        const items = menu && Array.isArray(menu) ? menu : []

        return items
            .filter((item) => item && item.url)
            .map((item, index) => ({
                key: canonicalKeyFor(item.url) || `seeded-${index}`,
                label: item.label || '',
                shortLabel: item.label || '',
                url: item.url,
                external: !!item.external,
                path: pathOf(item.url),
            }))
            .filter((item) => item.label)
    }

    const seededPrimary = computed(() => seededLinks(seededNavigation.value.primary))
    const seededFooter = computed(() => seededLinks(seededNavigation.value.footer))

    const links = computed(() => {
        if (seededPrimary.value.length > 0) {
            return seededPrimary.value
        }

        return ITEMS.map((item) => ({
            key: item.key,
            label: isEn.value ? item.en : item.ar,
            shortLabel: isEn.value ? item.shortEn : item.shortAr,
            url: localPath(item.path),
            path: item.path,
        }))
    })

    const cta = computed(() => {
        const seeded = seededPrimary.value.find((item) => item.path === pathOf(localPath('get-involved')))
        if (seeded) {
            return { key: 'get-involved', label: seeded.label, url: seeded.url }
        }

        return {
            key: 'get-involved',
            label: isEn.value ? 'Join Us' : 'شارك معنا',
            url: localPath('get-involved'),
        }
    })

    /**
     * Desktop bar entries are derived from the same `links` used by the mobile
     * menu. A group with one visible route degrades to a direct link; an empty
     * group disappears. Unknown seeded links stay in the mobile menu only.
     */
    const barEntries = computed(() => {
        const byKey = new Map(links.value.map((item) => [item.key, item]))

        function directEntry(item, order) {
            return {
                kind: 'direct',
                key: item.key,
                label: item.label,
                url: item.url,
                external: !!item.external,
                order,
            }
        }

        const direct = links.value
            .filter((item) => ITEMS_BY_KEY.get(item.key)?.showInBar)
            .map((item) => directEntry(item, ITEMS_BY_KEY.get(item.key).barOrder))

        const groups = BAR_GROUPS.map((group) => {
            const visible = group.keys
                .map((key) => byKey.get(key))
                .filter((item) => item && item.url)

            if (visible.length === 0) {
                return null
            }

            if (visible.length === 1) {
                return directEntry(visible[0], group.order)
            }

            return {
                kind: 'dropdown',
                key: group.key,
                labelKey: group.labelKey,
                order: group.order,
                items: visible.map((item) => ({
                    ...item,
                    descriptionKey: `nav.descriptions.${item.key}`,
                })),
            }
        }).filter(Boolean)

        return [...direct, ...groups].sort((a, b) => a.order - b.order)
    })

    const footerGroups = computed(() => {
        return FOOTER_GROUPS.map((group) => {
            const items = group.items
                .map(resolveItem)
                .filter(Boolean)
                .map((item) => {
                    const url = localPath(item.path)
                    const label = (isEn.value ? item.en : item.ar || '').trim()

                    return {
                        key: item.key,
                        label,
                        url,
                        external: false,
                    }
                })
                .filter((item) => item.label && item.url)

            return {
                key: group.key,
                title: isEn.value ? group.en : group.ar,
                items,
            }
        }).filter((group) => group.items.length > 0)
    })

    return { links, cta, barEntries, footerGroups }
}
