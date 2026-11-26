<script setup>
import { computed, reactive, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    preview: { type: Array, default: () => [] },
    media: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ view: false, update: false }) },
})

const { t, locale, localPath } = useI18n()

const locales = ['ar', 'en']
const statuses = ['draft', 'pending_review', 'approved', 'published', 'archived']
const itemTypes = ['inline', 'pillar', 'article', 'publication', 'page', 'project']
const motifKeys = ['m-sun', 'm-wheat', 'm-bars', 'm-home', 'm-drop', 'm-doc', 'm-book', 'm-people']
const tabs = ['news', 'publications', 'projects', 'mission', 'objectives', 'vision']

/** The approved prototype sections, in display order. Labels are UI chrome only; content always comes from CMS values. */
const canonicalSections = ['hero', 'ticker', 'impact', 'programs', 'news', 'albums', 'about', 'stories', 'footer']
const canonicalKeys = canonicalSections

const sectionMeta = {
    hero: { ar: 'شريحة الواجهة', en: 'Hero', hintAr: 'شرائح الواجهة الرئيسية والنصوص المصاحبة.', hintEn: 'Hero slides and their copy.' },
    ticker: { ar: 'شريط الأخبار', en: 'Ticker', hintAr: 'عنوان الشريط ونص الخبر المتحرك.', hintEn: 'Ticker label and headline copy.' },
    impact: { ar: 'بصمة الأثر', en: 'Impact', hintAr: 'مؤشرات الأثر والأرقام التعريفية.', hintEn: 'Impact indicators and headline figures.' },
    programs: { ar: 'البرامج', en: 'Programs', hintAr: 'محاور العمل والبرامج.', hintEn: 'Programme axes and milestones.' },
    news: { ar: 'الأخبار والإصدارات', en: 'News & publications', hintAr: 'أخبار وإصدارات ومشاريع معرضة في تبويبات.', hintEn: 'News, publications and projects shown as tabs.' },
    albums: { ar: 'ألبومات الصور', en: 'Albums', hintAr: 'صور الألبوم مع وصف كل صورة.', hintEn: 'Album images with captions.' },
    about: { ar: 'من نحن', en: 'About', hintAr: 'الرسالة والأهداف والرؤية في تبويبات.', hintEn: 'Mission, objectives and vision shown as tabs.' },
    stories: { ar: 'قصص النجاح', en: 'Stories', hintAr: 'قصص ميدانية مختصرة.', hintEn: 'Short field stories.' },
    footer: { ar: 'التذييل', en: 'Footer', hintAr: 'نصوص التذييل وروابطه ووسائطه.', hintEn: 'Footer copy, links and media.' },
}

const tabCopy = {
    news: { ar: 'الأخبار', en: 'News' },
    publications: { ar: 'الإصدارات', en: 'Publications' },
    projects: { ar: 'المشاريع', en: 'Projects' },
    mission: { ar: 'الرسالة', en: 'Mission' },
    objectives: { ar: 'الأهداف', en: 'Objectives' },
    vision: { ar: 'الرؤية', en: 'Vision' },
}

const statusCopy = {
    draft: { ar: 'مسودة', en: 'Draft' },
    pending_review: { ar: 'قيد المراجعة', en: 'Pending review' },
    approved: { ar: 'معتمد', en: 'Approved' },
    published: { ar: 'منشور', en: 'Published' },
    archived: { ar: 'مؤرشف', en: 'Archived' },
}

/** Suggested tab and motif values for the canonical prototype sections. */
const sectionTabDefaults = {
    news: 'news',
    about: 'mission',
}
const sectionMotifDefaults = {
    hero: 'm-sun',
    ticker: 'm-doc',
    impact: 'm-bars',
    programs: 'm-home',
    news: 'm-doc',
    albums: 'm-people',
    about: 'm-book',
    stories: 'm-people',
    footer: 'm-drop',
}

const aliasFields = {
    key: ['item_key', 'itemKey'],
    type: ['item_type', 'itemType'],
    order: ['display_order'],
    enabled: ['is_enabled'],
    publishedAt: ['published_at'],
    published: ['is_published', 'isPublished'],
    featured: ['is_featured', 'isFeatured'],
    value: ['numeric_value', 'numericValue'],
    display: ['display_value'],
    itemId: ['item_id'],
    mediaId: ['media_id'],
    sortOrder: ['sort_order'],
    motifKey: ['motif_key'],
    ctaLabel: ['cta_label'],
    ctaLink: ['cta_link'],
    tickerLabel: ['ticker_label'],
    tabLabels: ['tab_labels'],
    dateLabel: ['date_label'],
    videoUrl: ['video_url'],
    videoCaption: ['video_caption'],
}

const activeLocale = reactive({})
const clientErrors = reactive({})
const newSectionChoice = ref('')

const canUpdate = computed(() => props.can?.update === true)
const canView = computed(() => props.can?.view === true)

const labels = computed(() => ({
    addSection: uiText('cms_homepage_add_section', 'إضافة قسم', 'Add section'),
    deleteSection: uiText('delete', 'حذف', 'Delete'),
    publish: uiText('cms_homepage_publish', 'نشر', 'Publish'),
    unpublish: uiText('cms_homepage_unpublish', 'إلغاء النشر', 'Unpublish'),
    itemPublished: uiText('cms_homepage_item_published', 'منشور', 'Published'),
    featured: uiText('cms_homepage_featured', 'مميز', 'Featured'),
    motif: uiText('cms_homepage_motif', 'الرمز', 'Motif'),
    tab: uiText('cms_homepage_tab', 'التبويب', 'Tab'),
    value: uiText('cms_homepage_value', 'القيمة', 'Value'),
    unit: uiText('cms_homepage_unit', 'الوحدة', 'Unit'),
    dateLabel: uiText('cms_homepage_date_label', 'تاريخ العرض', 'Date label'),
    ctaLabel: uiText('cms_homepage_item_cta_label', 'نص الرابط', 'CTA label'),
    caption: uiText('cms_homepage_caption', 'الوصف', 'Caption'),
    tickerLabel: uiText('cms_homepage_ticker_label', 'عنوان الشريط', 'Ticker label'),
    tabLabels: uiText('cms_homepage_tab_labels', 'أسماء التبويبات (JSON)', 'Tab labels (JSON)'),
    videoUrl: uiText('cms_homepage_video_url', 'رابط الفيديو', 'Video URL'),
    videoCaption: uiText('cms_homepage_video_caption', 'وصف الفيديو', 'Video caption'),
    safeLink: uiText(
        'cms_homepage_safe_link_hint',
        'يُسمح فقط بالمسارات الداخلية (مثل /about) وروابط # وروابط https://.',
        'Only internal paths (e.g. /about), #anchors and https:// URLs are accepted.',
    ),
    jsonHint: uiText(
        'cms_homepage_json_hint',
        'أدخل JSON صالحاً، مثل {"news":"الأخبار"}، أو اتركه فارغاً.',
        'Enter valid JSON, e.g. {"news":"News"}, or leave it empty.',
    ),
    invalidJson: uiText('cms_homepage_json_invalid', 'JSON غير صالح.', 'Invalid JSON.'),
    invalidLink: uiText(
        'cms_homepage_link_invalid',
        'استخدم مساراً داخلياً أو رابط # أو رابط https:// فقط.',
        'Use an internal path, a #anchor or an https:// URL only.',
    ),
    deleteConfirm: uiText('cms_homepage_delete_confirm', 'حذف هذا القسم؟', 'Delete this section?'),
    ungrouped: uiText('cms_homepage_ungrouped', 'بدون تبويب', 'No tab'),
    public: uiText('cms_homepage_public', 'متاح للعامة', 'Public'),
    notPublic: uiText('cms_homepage_not_public', 'غير متاح للعامة', 'Not public'),
    emptyPreview: uiText('cms_homepage_preview_empty', 'لا توجد عناصر منشورة بعد.', 'No published items yet.'),
    missingPreview: uiText('cms_homepage_preview_missing', 'هذا القسم غير موجود في المعاينة.', 'This section is not present in the preview.'),
    publicSections: uiText('cms_homepage_public_sections', 'أقسام عامة', 'Public sections'),
    publicItems: uiText('cms_homepage_public_items', 'عناصر عامة', 'Public items'),
    draftItems: uiText('cms_homepage_draft_items', 'عناصر غير منشورة', 'Unpublished items'),
}))

const mediaOptions = computed(() => [
    { value: '', label: locale.value === 'ar' ? 'بدون صورة' : 'No image' },
    ...props.media.map((asset) => ({
        value: asset.id,
        label: asset.name || asset.original_name || `#${asset.id}`,
    })),
])

const statusOptions = computed(() => statuses.map((status) => ({
    value: status,
    label: t(`ui.cms_homepage_status_${status}`, statusCopy[status]?.[locale.value] || status),
})))

const tabOptions = computed(() => [
    { value: '', label: locale.value === 'ar' ? 'بدون تبويب' : 'No tab' },
    ...tabs.map((tab) => ({ value: tab, label: tabLabel(tab) })),
])

const motifOptions = computed(() => motifKeys.map((key) => ({ value: key, label: key })))
const itemTypeOptions = itemTypes.map((type) => ({ value: type, label: type }))

function uiText(key, arabic, english) {
    return t(`ui.${key}`, locale.value === 'ar' ? arabic : english)
}

function sectionLabel(key) {
    return t(`ui.cms_homepage_section_${key}`, sectionMeta[key]?.[locale.value] || key)
}

function sectionHint(key) {
    return t(
        `ui.cms_homepage_section_${key}_hint`,
        locale.value === 'ar' ? sectionMeta[key]?.hintAr || '' : sectionMeta[key]?.hintEn || '',
    )
}

function tabLabel(tab) {
    return t(`ui.cms_homepage_tab_${tab}`, tabCopy[tab]?.[locale.value] || tab)
}

function statusLabel(status) {
    return t(`ui.cms_homepage_status_${status}`, statusCopy[status]?.[locale.value] || status)
}

function statusTone(status) {
    if (status === 'published') {
        return 'pc-badge--green'
    }
    if (status === 'draft' || status === 'archived') {
        return 'pc-badge--amber'
    }
    if (status === 'pending_review') {
        return 'pc-badge--slate'
    }

    return 'pc-badge--teal'
}

function localeFor(sectionId) {
    if (!activeLocale[sectionId]) {
        activeLocale[sectionId] = 'ar'
    }

    return activeLocale[sectionId]
}

function pick(source, field, fallback = '') {
    if (source == null) {
        return fallback
    }
    if (source[field] !== undefined && source[field] !== null) {
        return source[field]
    }

    for (const alias of aliasFields[field] || []) {
        if (source[alias] !== undefined && source[alias] !== null) {
            return source[alias]
        }
    }

    return fallback
}

function pickLocalized(source, code, field, fallback = '') {
    const translation = source?.translations?.[code]

    return pick(translation, field, pick(source, field, fallback))
}

function normalizeJsonText(value) {
    if (value === null || value === undefined || value === '') {
        return ''
    }
    if (typeof value === 'string') {
        return value
    }

    try {
        return JSON.stringify(value, null, 2)
    } catch {
        return String(value)
    }
}

function isStructuredJson(value) {
    return value !== null && typeof value === 'object'
}

function hasVideoFields(section) {
    const keys = ['videoUrl', 'video_url', 'videoCaption', 'video_caption']
    const hasOwn = (source) => keys.some((key) => Object.prototype.hasOwnProperty.call(source || {}, key))

    return hasOwn(section) || locales.some((code) => hasOwn(section?.translations?.[code]))
}

function emptyItemTranslations() {
    return Object.fromEntries(locales.map((code) => [code, {
        title: '',
        label: '',
        body: '',
        display: '',
        url: '',
        dateLabel: '',
        ctaLabel: '',
        caption: '',
        unit: '',
    }]))
}

function cloneSections(sections = props.sections) {
    return sections.map((section, sectionIndex) => ({
        id: section.id,
        key: pick(section, 'key', ''),
        type: pick(section, 'type', pick(section, 'key', canonicalKeys[sectionIndex] || '')),
        status: section.status || 'published',
        publishedAt: pick(section, 'publishedAt', ''),
        enabled: pick(section, 'enabled', true) !== false,
        order: Number(pick(section, 'order', sectionIndex)) || 0,
        videoFields: hasVideoFields(section),
        videoUrl: pick(section, 'videoUrl', ''),
        videoCaption: pick(section, 'videoCaption', ''),
        translations: Object.fromEntries(locales.map((code) => [code, {
            eyebrow: pickLocalized(section, code, 'eyebrow', ''),
            title: pickLocalized(section, code, 'title', ''),
            body: pickLocalized(section, code, 'body', ''),
            ctaLabel: pickLocalized(section, code, 'ctaLabel', ''),
            ctaLink: pickLocalized(section, code, 'ctaLink', ''),
            tickerLabel: pickLocalized(section, code, 'tickerLabel', ''),
            tabLabels: normalizeJsonText(pickLocalized(section, code, 'tabLabels', '')),
            tabLabelsStructured: isStructuredJson(pickLocalized(section, code, 'tabLabels', null)),
            videoUrl: pickLocalized(section, code, 'videoUrl', ''),
            videoCaption: pickLocalized(section, code, 'videoCaption', ''),
        }])),
        items: (Array.isArray(section.items) ? section.items : []).map((item, itemIndex) => ({
            id: item.id,
            key: pick(item, 'key', ''),
            type: pick(item, 'type', 'inline'),
            itemId: pick(item, 'itemId', ''),
            mediaId: item.media?.id ?? pick(item, 'mediaId', ''),
            sortOrder: Number(pick(item, 'sortOrder', itemIndex)) || 0,
            published: pick(item, 'published', true) !== false,
            featured: pick(item, 'featured', false) === true,
            motifKey: pick(item, 'motifKey', ''),
            tab: pick(item, 'tab', ''),
            value: pick(item, 'value', ''),
            dateLabel: pick(item, 'dateLabel', ''),
            ctaLabel: pick(item, 'ctaLabel', ''),
            caption: pick(item, 'caption', ''),
            unit: pick(item, 'unit', ''),
            translations: Object.fromEntries(locales.map((code) => [code, (() => {
                const translations = emptyItemTranslations()[code]
                translations.title = pickLocalized(item, code, 'title', '')
                translations.label = pickLocalized(item, code, 'label', '')
                translations.body = pickLocalized(item, code, 'body', '')
                translations.display = pickLocalized(item, code, 'display', '')
                translations.url = pickLocalized(item, code, 'url', '')
                translations.dateLabel = pickLocalized(item, code, 'dateLabel', '')
                translations.ctaLabel = pickLocalized(item, code, 'ctaLabel', '')
                translations.caption = pickLocalized(item, code, 'caption', '')
                translations.unit = pickLocalized(item, code, 'unit', '')

                return translations
            })()])),
        })),
    }))
}

const form = useForm({ sections: cloneSections() })

const availableSectionKeys = computed(() => canonicalKeys.filter(
    (key) => !form.sections.some((section) => section.key === key),
))

const sectionChoices = computed(() => availableSectionKeys.value.map((key) => ({
    value: key,
    label: sectionLabel(key),
})))

const newSectionSelection = computed({
    get: () => newSectionChoice.value || availableSectionKeys.value[0] || '',
    set: (value) => { newSectionChoice.value = value },
})

const hasSections = computed(() => form.sections.length > 0)

function resetFormFrom(sections) {
    form.sections = cloneSections(sections)
    form.clearErrors()
    Object.keys(clientErrors).forEach((key) => { delete clientErrors[key] })
    Object.keys(activeLocale).forEach((key) => { delete activeLocale[key] })
    newSectionChoice.value = ''
}

function syncAfterMutation(page) {
    const sections = page?.props?.sections
    if (Array.isArray(sections)) {
        resetFormFrom(sections)

        return
    }

    router.reload({
        only: ['sections', 'preview'],
        preserveScroll: true,
        onSuccess: (refreshed) => resetFormFrom(refreshed?.props?.sections ?? props.sections),
    })
}

function renumberSections() {
    form.sections.forEach((section, index) => {
        section.order = index
    })
}

function addSection() {
    if (!canUpdate.value || form.processing || !newSectionSelection.value) {
        return
    }

    const key = newSectionSelection.value
    const order = form.sections.length

    router.post(localPath('cms/homepage/sections'), {
        key,
        type: key,
        sectionKey: key,
        section_key: key,
        sectionType: key,
        section_type: key,
        status: 'draft',
        enabled: true,
        is_enabled: true,
        order,
        display_order: order,
    }, {
        preserveScroll: true,
        onSuccess: syncAfterMutation,
    })
}

function deleteSection(section) {
    if (!canUpdate.value) {
        return
    }
    if (!window.confirm(labels.value.deleteConfirm)) {
        return
    }

    const index = form.sections.indexOf(section)
    if (index < 0) {
        return
    }
    if (!section.id) {
        form.sections.splice(index, 1)
        renumberSections()

        return
    }

    router.delete(localPath(`cms/homepage/${section.id}`), {}, {
        preserveScroll: true,
        onSuccess: syncAfterMutation,
    })
}

function addItem(section) {
    if (!canUpdate.value) {
        return
    }

    section.items.push({
        id: null,
        key: '',
        type: 'inline',
        itemId: '',
        mediaId: '',
        sortOrder: section.items.filter((item) => !item.remove).length,
        published: true,
        featured: false,
        motifKey: sectionMotifDefaults[section.key] || '',
        tab: sectionTabDefaults[section.key] || '',
        value: '',
        dateLabel: '',
        ctaLabel: '',
        caption: '',
        unit: '',
        translations: emptyItemTranslations(),
    })
}

/** Mark existing items for removal so the backend deletes them on save. */
function removeItem(section, itemIndex) {
    const item = section.items[itemIndex]
    if (!item) {
        return
    }
    if (item.id) {
        item.remove = true

        return
    }
    section.items.splice(itemIndex, 1)
}

function moveItem(section, item, offset) {
    if (!canUpdate.value) {
        return
    }

    const visible = section.items.filter((candidate) => !candidate.remove)
    const from = visible.indexOf(item)
    const destination = from + offset
    if (from < 0 || destination < 0 || destination >= visible.length) {
        return
    }

    const [moved] = visible.splice(from, 1)
    visible.splice(destination, 0, moved)

    let cursor = 0
    section.items.forEach((entry, index) => {
        if (!entry.remove) {
            section.items[index] = visible[cursor++]
        }
    })
    visible.forEach((entry, index) => {
        entry.sortOrder = index
        entry.sort_order = index
    })
}

function moveSection(index, offset) {
    if (!canUpdate.value) {
        return
    }

    const destination = index + offset
    if (destination < 0 || destination >= form.sections.length) {
        return
    }

    const current = form.sections[index]
    form.sections[index] = form.sections[destination]
    form.sections[destination] = current
    renumberSections()

    router.post(localPath('cms/homepage/reorder'), {
        order: form.sections.map((section) => ({
            id: section.id,
            display_order: section.order,
            order: section.order,
        })),
    }, { preserveScroll: true })
}

function toggleSection(section) {
    if (!canUpdate.value) {
        return
    }

    const enabled = !section.enabled
    router.post(localPath(`cms/homepage/${section.id}/toggle`), {
        is_enabled: enabled,
        enabled,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            section.enabled = enabled
            section.is_enabled = enabled
        },
    })
}

function setPublication(section, publish) {
    if (!canUpdate.value) {
        return
    }

    const publishedAt = publish ? (section.publishedAt || new Date().toISOString()) : null
    router.patch(localPath(`cms/homepage/${section.id}/status`), {
        status: publish ? 'published' : 'draft',
        published_at: publishedAt,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            section.status = publish ? 'published' : 'draft'
            section.publishedAt = publishedAt || ''
        },
    })
}

function pathForSection(sectionIndex, code, field) {
    return `sections.${sectionIndex}.translations.${code}.${field}`
}

function pathForItem(sectionIndex, itemIndex, code, field) {
    return `sections.${sectionIndex}.items.${itemIndex}.translations.${code}.${field}`
}

function errorFor(path) {
    const candidates = [path]
    const aliases = [
        ['cta_link', 'ctaLabel'],
        ['cta_label', 'ctaLabel'],
        ['display_value', 'display'],
        ['tab_labels', 'tabLabels'],
        ['date_label', 'dateLabel'],
        ['video_url', 'videoUrl'],
    ]

    aliases.forEach(([snake, camel]) => {
        if (path.includes(snake)) {
            candidates.push(path.split(snake).join(camel))
        }
        if (path.includes(camel)) {
            candidates.push(path.split(camel).join(snake))
        }
    })

    for (const candidate of candidates) {
        const message = form.errors[candidate] || clientErrors[candidate]
        if (message) {
            return message
        }
    }

    return ''
}

function clearError(path) {
    delete clientErrors[path]
}

function isSafeLink(value) {
    const text = String(value ?? '').trim()
    if (!text) {
        return true
    }
    if (text.startsWith('#') && !/\s/.test(text)) {
        return true
    }
    if (text.startsWith('/') && !text.startsWith('//') && !text.includes('\\')) {
        return true
    }

    try {
        const parsed = new URL(text)

        return parsed.protocol === 'https:' && parsed.hostname !== ''
    } catch {
        return false
    }
}

function validateSectionLink(section, sectionIndex, code, field) {
    const path = pathForSection(sectionIndex, code, field)
    const value = field === 'video_url'
        ? section.translations[code].videoUrl
        : section.translations[code].ctaLink
    if (isSafeLink(value)) {
        clearError(path)

        return true
    }

    clientErrors[path] = labels.value.invalidLink

    return false
}

function validateJsonField(section, sectionIndex, code) {
    const path = pathForSection(sectionIndex, code, 'tab_labels')
    const raw = String(section.translations[code].tabLabels ?? '')
    if (!raw.trim()) {
        clearError(path)

        return true
    }

    try {
        JSON.parse(raw)
        clearError(path)

        return true
    } catch {
        clientErrors[path] = labels.value.invalidJson

        return false
    }
}

function validateItemUrl(sectionIndex, itemIndex, item, code) {
    const path = pathForItem(sectionIndex, itemIndex, code, 'url')
    if (isSafeLink(item.translations[code].url)) {
        clearError(path)

        return true
    }

    clientErrors[path] = labels.value.invalidLink

    return false
}

function validateForm() {
    let valid = true

    form.sections.forEach((section, sectionIndex) => {
        locales.forEach((code) => {
            if (!validateJsonField(section, sectionIndex, code)) {
                valid = false
            }
            if (!validateSectionLink(section, sectionIndex, code, 'cta_link')) {
                valid = false
            }
            if (section.videoFields && !validateSectionLink(section, sectionIndex, code, 'video_url')) {
                valid = false
            }
        })

        section.items.forEach((item, itemIndex) => {
            if (item.remove) {
                return
            }
            locales.forEach((code) => {
                if (!validateItemUrl(sectionIndex, itemIndex, item, code)) {
                    valid = false
                }
            })
        })
    })

    return valid
}

function parsedTabLabels(raw, structured) {
    const text = String(raw ?? '')
    if (!text.trim()) {
        return ''
    }

    try {
        const parsed = JSON.parse(text)

        return structured ? parsed : text
    } catch {
        return text
    }
}

function buildPayloadSections() {
    return form.sections.map((section) => ({
        id: section.id,
        key: section.key,
        type: section.type,
        status: section.status,
        publishedAt: section.publishedAt || null,
        published_at: section.publishedAt || null,
        enabled: section.enabled,
        is_enabled: section.enabled,
        order: Number(section.order) || 0,
        display_order: Number(section.order) || 0,
        videoUrl: section.videoUrl,
        video_url: section.videoUrl,
        videoCaption: section.videoCaption,
        video_caption: section.videoCaption,
        translations: Object.fromEntries(locales.map((code) => {
            const translation = section.translations[code]
            const values = {
                eyebrow: translation.eyebrow,
                title: translation.title,
                body: translation.body,
                ctaLabel: translation.ctaLabel,
                cta_label: translation.ctaLabel,
                ctaLink: translation.ctaLink,
                cta_link: translation.ctaLink,
                tickerLabel: translation.tickerLabel,
                ticker_label: translation.tickerLabel,
                tabLabels: parsedTabLabels(translation.tabLabels, translation.tabLabelsStructured),
                tab_labels: translation.tabLabels,
            }

            if (section.videoFields) {
                values.videoUrl = translation.videoUrl
                values.video_url = translation.videoUrl
                values.videoCaption = translation.videoCaption
                values.video_caption = translation.videoCaption
            }

            return [code, values]
        })),
        items: section.items.map((item, itemIndex) => ({
            id: item.id,
            remove: Boolean(item.remove),
            key: item.key,
            item_key: item.key,
            type: item.type,
            item_type: item.type,
            itemId: item.itemId,
            item_id: item.itemId,
            mediaId: item.mediaId,
            media_id: item.mediaId,
            sortOrder: item.sortOrder ?? itemIndex,
            sort_order: item.sortOrder ?? itemIndex,
            published: item.published,
            is_published: item.published,
            featured: item.featured,
            is_featured: item.featured,
            motifKey: item.motifKey,
            motif_key: item.motifKey,
            tab: item.tab,
            value: item.value,
            numeric_value: item.value,
            dateLabel: item.translations.ar.dateLabel || item.translations.en.dateLabel || item.dateLabel,
            date_label: item.translations.ar.dateLabel || item.translations.en.dateLabel || item.dateLabel,
            ctaLabel: item.translations.ar.ctaLabel || item.translations.en.ctaLabel || item.ctaLabel,
            cta_label: item.translations.ar.ctaLabel || item.translations.en.ctaLabel || item.ctaLabel,
            caption: item.translations.ar.caption || item.translations.en.caption || item.caption,
            unit: item.translations.ar.unit || item.translations.en.unit || item.unit,
            translations: Object.fromEntries(locales.map((code) => {
                const translation = item.translations[code]

                return [code, {
                    title: translation.title,
                    label: translation.label,
                    body: translation.body,
                    display: translation.display,
                    display_value: translation.display,
                    url: translation.url,
                    dateLabel: translation.dateLabel,
                    date_label: translation.dateLabel,
                    ctaLabel: translation.ctaLabel,
                    cta_label: translation.ctaLabel,
                    caption: translation.caption,
                    unit: translation.unit,
                }]
            })),
        })),
    }))
}

function save() {
    if (!canUpdate.value || form.processing) {
        return
    }
    if (!validateForm()) {
        return
    }

    form.transform(() => ({ sections: buildPayloadSections() })).put(localPath('cms/homepage'), {
        preserveScroll: true,
        onSuccess: syncAfterMutation,
    })
}

/* Preview helpers: the service serializer is locale-aware, so preview values are already localized. */
function previewField(section, field) {
    const direct = pick(section, field, null)
    if (direct !== null) {
        return direct
    }

    const translation = section?.translations?.[locale.value] || section?.translations?.ar || section?.translations?.en

    return pick(translation, field, '')
}

function textValue(value) {
    if (value === null || value === undefined) {
        return ''
    }
    if (typeof value === 'object') {
        return value[locale.value] ?? value.ar ?? value.en ?? ''
    }

    return String(value)
}

function parsedPreviewTabLabels(section) {
    const raw = previewField(section, 'tabLabels')
    if (raw && typeof raw === 'object') {
        return raw
    }

    try {
        const parsed = JSON.parse(String(raw ?? ''))

        return parsed && typeof parsed === 'object' ? parsed : {}
    } catch {
        return {}
    }
}

function tabLabelFor(section, tab) {
    const configured = parsedPreviewTabLabels(section)[tab]
    if (configured !== undefined && configured !== null && configured !== '') {
        return textValue(configured)
    }

    return tabLabel(tab)
}

function isPublicPreviewItem(item) {
    return pick(item, 'published', true) !== false
}

function tabGroupsFor(section, items) {
    const grouped = new Map()
    items.forEach((item) => {
        const tab = String(pick(item, 'tab', '') || '')
        if (!grouped.has(tab)) {
            grouped.set(tab, [])
        }
        grouped.get(tab).push(item)
    })

    return [...grouped.entries()].map(([tab, groupedItems]) => ({
        key: tab,
        label: tab ? tabLabelFor(section, tab) : labels.value.ungrouped,
        items: groupedItems,
    }))
}

const previewByKey = computed(() => {
    const map = new Map()
    props.preview.forEach((section) => {
        const key = String(pick(section, 'key', ''))
        if (key && !map.has(key)) {
            map.set(key, section)
        }
    })

    return map
})

const previewGroups = computed(() => {
    const keys = props.preview
        .map((section) => String(pick(section, 'key', '')))
        .filter((key) => key && !canonicalKeys.includes(key))
    const orderedKeys = [...canonicalKeys, ...keys]

    return orderedKeys.map((key) => {
        const section = previewByKey.value.get(key) || null
        const items = Array.isArray(section?.items) ? section.items : []
        const sectionIsPublic = Boolean(section)
            && section.status === 'published'
            && pick(section, 'enabled', true) !== false
        const publicItems = sectionIsPublic ? items.filter(isPublicPreviewItem) : []
        const publicSet = new Set(publicItems)

        return {
            key,
            label: sectionLabel(key),
            section,
            sectionIsPublic,
            publicItems,
            draftItems: items.filter((item) => !publicSet.has(item)),
            groups: tabGroupsFor(section, publicItems),
            status: section?.status || 'missing',
            title: textValue(previewField(section, 'title')),
        }
    })
})

const previewTotals = computed(() => ({
    publicSections: previewGroups.value.filter((group) => group.sectionIsPublic).length,
    publicItems: previewGroups.value.reduce((total, group) => total + group.publicItems.length, 0),
    draftItems: previewGroups.value.reduce((total, group) => total + group.draftItems.length, 0),
}))
</script>

<template>
    <CmsDashboardLayout :title="t('ui.cms_homepage')" :subtitle="t('cms_sections')">
        <div class="pc-stack pcdr-enter">
            <div class="pc-page-actions">
                <button
                    class="pc-btn pc-btn--primary"
                    type="submit"
                    form="homepage-form"
                    :disabled="form.processing || !canUpdate"
                >
                    <UiIcon name="check" :size="16" />
                    {{ t('cms_save') }}
                </button>
            </div>

            <form id="homepage-form" class="pc-stack" @submit.prevent="save">
                <section class="pc-card">
                    <div class="pc-card__head">
                        <div>
                            <h2 class="pc-card__title">{{ labels.addSection }}</h2>
                            <p class="pc-card__subtitle">{{ sectionHint(newSectionSelection) }}</p>
                        </div>
                        <span class="pc-badge pc-badge--slate">{{ canonicalKeys.length }}</span>
                    </div>
                    <div class="pc-card__body">
                        <div class="pc-form-grid pc-form-grid--2">
                            <SelectionControl
                                v-model="newSectionSelection"
                                :label="labels.addSection"
                                :options="sectionChoices"
                                :searchable="false"
                                :disabled="!canUpdate || !sectionChoices.length"
                                size="sm"
                            />
                            <div class="pc-field pc-page-actions">
                                <button
                                    class="pc-btn pc-btn--secondary pc-btn--sm"
                                    type="button"
                                    :disabled="!canUpdate || form.processing || !newSectionSelection"
                                    @click="addSection"
                                >
                                    <UiIcon name="plus" :size="15" />
                                    {{ labels.addSection }}
                                </button>
                            </div>
                        </div>
                        <p class="pc-hint">
                            {{ locale === 'ar'
                                ? 'يُنشأ القسم الجديد كمسودة؛ انشره بعد إدخال المحتوى.'
                                : 'New sections are created as drafts; publish them after adding content.' }}
                        </p>
                    </div>
                </section>

                <div v-if="hasSections" class="pc-accordion">
                    <details
                        v-for="(section, index) in form.sections"
                        :key="section.id || `section-${index}`"
                        class="pc-accordion__item"
                        :open="index < 3"
                    >
                        <summary class="pc-accordion__trigger">
                            <UiIcon name="chevronRight" :size="17" />
                            <span>{{ section.translations.ar.title || section.translations.en.title || sectionLabel(section.key) }}</span>
                            <span class="pc-badge pc-badge--slate">{{ sectionLabel(section.key) }}</span>
                            <span :class="['pc-badge', statusTone(section.status)]">{{ statusLabel(section.status) }}</span>
                            <span class="pc-badge pc-badge--slate">{{ section.order }}</span>
                        </summary>

                        <div class="pc-accordion__body pc-stack">
                            <p class="pc-hint">{{ sectionHint(section.key) }}</p>

                            <div class="pc-form-grid pc-form-grid--2">
                                <label class="pc-checkbox">
                                    <input
                                        :checked="section.enabled"
                                        type="checkbox"
                                        :disabled="!canUpdate"
                                        @change="toggleSection(section)"
                                    >
                                    <span>{{ t('cms_section_enabled') }}</span>
                                </label>
                                <div class="pc-field">
                                    <SelectionControl
                                        v-model="section.status"
                                        :label="t('cms_status', 'Publication status')"
                                        :options="statusOptions"
                                        :searchable="false"
                                        :disabled="!canUpdate"
                                        size="sm"
                                    />
                                </div>
                                <label class="pc-field">
                                    <span class="pc-label">{{ t('cms_order') }}</span>
                                    <input v-model.number="section.order" class="pc-input" type="number" min="0" :disabled="!canUpdate" dir="ltr">
                                </label>
                                <div class="pc-field pc-page-actions">
                                    <button
                                        class="pc-btn pc-btn--secondary pc-btn--sm"
                                        type="button"
                                        :disabled="!canUpdate || index === 0"
                                        :aria-label="locale === 'ar' ? 'نقل للأعلى' : 'Move up'"
                                        @click="moveSection(index, -1)"
                                    >
                                        ↑
                                    </button>
                                    <button
                                        class="pc-btn pc-btn--secondary pc-btn--sm"
                                        type="button"
                                        :disabled="!canUpdate || index === form.sections.length - 1"
                                        :aria-label="locale === 'ar' ? 'نقل للأسفل' : 'Move down'"
                                        @click="moveSection(index, 1)"
                                    >
                                        ↓
                                    </button>
                                    <button
                                        class="pc-btn pc-btn--secondary pc-btn--sm"
                                        type="button"
                                        :disabled="!canUpdate"
                                        @click="setPublication(section, section.status !== 'published')"
                                    >
                                        <UiIcon :name="section.status === 'published' ? 'eyeOff' : 'check'" :size="14" />
                                        {{ section.status === 'published' ? labels.unpublish : labels.publish }}
                                    </button>
                                    <button
                                        class="pc-btn pc-btn--danger pc-btn--sm"
                                        type="button"
                                        :disabled="!canUpdate"
                                        @click="deleteSection(section)"
                                    >
                                        <UiIcon name="trash" :size="14" />
                                        {{ labels.deleteSection }}
                                    </button>
                                </div>
                            </div>

                            <div class="pc-tabs" role="tablist" :aria-label="locale === 'ar' ? 'اللغة' : 'Language'">
                                <button
                                    v-for="code in locales"
                                    :key="code"
                                    type="button"
                                    role="tab"
                                    :aria-selected="localeFor(section.id) === code ? 'true' : 'false'"
                                    :tabindex="localeFor(section.id) === code ? 0 : -1"
                                    @click="activeLocale[section.id] = code"
                                >
                                    {{ code === 'ar' ? t('cms_arabic') : t('cms_english') }}
                                </button>
                            </div>

                            <div v-for="code in locales" v-show="localeFor(section.id) === code" :key="code" class="pc-translation-pane">
                                <h3 class="pc-eyebrow">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }}</h3>
                                <div class="pc-form-grid">
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_intro') }}</span>
                                        <input v-model="section.translations[code].eyebrow" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_title') }}</span>
                                        <input v-model="section.translations[code].title" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_body') }}</span>
                                        <textarea v-model="section.translations[code].body" class="pc-textarea" rows="3" maxlength="5000" :disabled="!canUpdate"></textarea>
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_cta') }}</span>
                                        <input v-model="section.translations[code].ctaLabel" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('ui.website') }}</span>
                                        <input
                                            v-model="section.translations[code].ctaLink"
                                            class="pc-input"
                                            type="text"
                                            maxlength="255"
                                            :disabled="!canUpdate"
                                            :aria-invalid="Boolean(errorFor(pathForSection(index, code, 'cta_link')))"
                                            dir="ltr"
                                            @input="validateSectionLink(section, index, code, 'cta_link')"
                                        >
                                        <span class="pc-hint">{{ labels.safeLink }}</span>
                                        <span v-if="errorFor(pathForSection(index, code, 'cta_link'))" class="pc-error">
                                            {{ errorFor(pathForSection(index, code, 'cta_link')) }}
                                        </span>
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ labels.tickerLabel }}</span>
                                        <input v-model="section.translations[code].tickerLabel" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                    </label>
                                    <label class="pc-field pc-field--full">
                                        <span class="pc-label">{{ labels.tabLabels }}</span>
                                        <textarea
                                            v-model="section.translations[code].tabLabels"
                                            class="pc-textarea"
                                            rows="3"
                                            :disabled="!canUpdate"
                                            :aria-invalid="Boolean(errorFor(pathForSection(index, code, 'tab_labels')))"
                                            dir="ltr"
                                            @input="validateJsonField(section, index, code)"
                                        ></textarea>
                                        <span class="pc-hint">{{ labels.jsonHint }}</span>
                                        <span v-if="errorFor(pathForSection(index, code, 'tab_labels'))" class="pc-error">
                                            {{ errorFor(pathForSection(index, code, 'tab_labels')) }}
                                        </span>
                                    </label>
                                    <template v-if="section.videoFields">
                                        <label class="pc-field">
                                            <span class="pc-label">{{ labels.videoUrl }}</span>
                                            <input
                                                v-model="section.translations[code].videoUrl"
                                                class="pc-input"
                                                type="text"
                                                maxlength="255"
                                                :disabled="!canUpdate"
                                                :aria-invalid="Boolean(errorFor(pathForSection(index, code, 'video_url')))"
                                                dir="ltr"
                                                @input="validateSectionLink(section, index, code, 'video_url')"
                                            >
                                            <span class="pc-hint">{{ labels.safeLink }}</span>
                                            <span v-if="errorFor(pathForSection(index, code, 'video_url'))" class="pc-error">
                                                {{ errorFor(pathForSection(index, code, 'video_url')) }}
                                            </span>
                                        </label>
                                        <label class="pc-field">
                                            <span class="pc-label">{{ labels.videoCaption }}</span>
                                            <input v-model="section.translations[code].videoCaption" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate">
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <div class="pc-stack">
                                <h3 class="pc-eyebrow">{{ t('cms_add_item') }}</h3>
                                <div
                                    v-for="(item, itemIndex) in section.items"
                                    v-show="!item.remove"
                                    :key="item.id || `new-${itemIndex}`"
                                    class="pc-stack pc-picker"
                                >
                                    <div class="pc-form-grid pc-form-grid--2">
                                        <div class="pc-field">
                                            <SelectionControl
                                                v-model="item.type"
                                                :label="t('ui.category')"
                                                :options="itemTypeOptions"
                                                :searchable="false"
                                                :disabled="!canUpdate"
                                                size="sm"
                                            />
                                        </div>
                                        <label v-if="item.type !== 'inline'" class="pc-field">
                                            <span class="pc-label">ID</span>
                                            <input v-model="item.itemId" class="pc-input" type="number" min="1" :disabled="!canUpdate" dir="ltr" required>
                                        </label>
                                        <label class="pc-field">
                                            <span class="pc-label">Key</span>
                                            <input v-model="item.key" class="pc-input" type="text" maxlength="64" :disabled="!canUpdate" dir="ltr">
                                        </label>
                                        <label class="pc-field">
                                            <span class="pc-label">{{ labels.value }}</span>
                                            <input v-model="item.value" class="pc-input" type="number" step="any" :disabled="!canUpdate" dir="ltr">
                                        </label>
                                        <div class="pc-field">
                                            <SelectionControl
                                                v-model="item.motifKey"
                                                :label="labels.motif"
                                                :options="motifOptions"
                                                :searchable="false"
                                                :disabled="!canUpdate"
                                                size="sm"
                                            />
                                        </div>
                                        <div class="pc-field">
                                            <SelectionControl
                                                v-model="item.tab"
                                                :label="labels.tab"
                                                :options="tabOptions"
                                                :searchable="false"
                                                :disabled="!canUpdate"
                                                size="sm"
                                            />
                                        </div>
                                        <label class="pc-checkbox">
                                            <input v-model="item.published" type="checkbox" :disabled="!canUpdate">
                                            <span>{{ labels.itemPublished }}</span>
                                        </label>
                                        <label class="pc-checkbox">
                                            <input v-model="item.featured" type="checkbox" :disabled="!canUpdate">
                                            <span>{{ labels.featured }}</span>
                                        </label>
                                        <div class="pc-field">
                                            <SelectionControl
                                                v-model="item.mediaId"
                                                :label="t('cms_media_picker', 'Media library')"
                                                :options="mediaOptions"
                                                :searchable="false"
                                                :disabled="!canUpdate"
                                                size="sm"
                                            />
                                        </div>
                                        <div class="pc-field pc-page-actions">
                                            <button
                                                class="pc-btn pc-btn--secondary pc-btn--sm"
                                                type="button"
                                                :disabled="!canUpdate"
                                                :aria-label="locale === 'ar' ? 'نقل للأعلى' : 'Move up'"
                                                @click="moveItem(section, item, -1)"
                                            >
                                                ↑
                                            </button>
                                            <button
                                                class="pc-btn pc-btn--secondary pc-btn--sm"
                                                type="button"
                                                :disabled="!canUpdate"
                                                :aria-label="locale === 'ar' ? 'نقل للأسفل' : 'Move down'"
                                                @click="moveItem(section, item, 1)"
                                            >
                                                ↓
                                            </button>
                                            <button class="pc-btn pc-btn--danger pc-btn--sm" type="button" :disabled="!canUpdate" @click="removeItem(section, itemIndex)">
                                                <UiIcon name="trash" :size="14" />
                                                {{ t('cms_remove_item') }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="pc-stack">
                                        <div v-for="code in locales" v-show="localeFor(section.id) === code" :key="code" class="pc-translation-pane">
                                            <h4 class="pc-eyebrow">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }}</h4>
                                            <div class="pc-form-grid">
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('cms_title') }}</span>
                                                    <input v-model="item.translations[code].title" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('cms_cta') }}</span>
                                                    <input v-model="item.translations[code].label" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('cms_body') }}</span>
                                                    <textarea v-model="item.translations[code].body" class="pc-textarea" rows="2" maxlength="5000" :disabled="!canUpdate"></textarea>
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ labels.dateLabel }}</span>
                                                    <input v-model="item.translations[code].dateLabel" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ labels.ctaLabel }}</span>
                                                    <input v-model="item.translations[code].ctaLabel" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ labels.caption }}</span>
                                                    <input v-model="item.translations[code].caption" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ labels.unit }}</span>
                                                    <input v-model="item.translations[code].unit" class="pc-input" type="text" maxlength="64" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('cms_intro') }}</span>
                                                    <input v-model="item.translations[code].display" class="pc-input" type="text" maxlength="64" :disabled="!canUpdate" dir="ltr">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('ui.website') }}</span>
                                                    <input
                                                        v-model="item.translations[code].url"
                                                        class="pc-input"
                                                        type="text"
                                                        maxlength="255"
                                                        :disabled="!canUpdate"
                                                        :aria-invalid="Boolean(errorFor(pathForItem(index, itemIndex, code, 'url')))"
                                                        dir="ltr"
                                                        @input="validateItemUrl(index, itemIndex, item, code)"
                                                    >
                                                    <span class="pc-hint">{{ labels.safeLink }}</span>
                                                    <span v-if="errorFor(pathForItem(index, itemIndex, code, 'url'))" class="pc-error">
                                                        {{ errorFor(pathForItem(index, itemIndex, code, 'url')) }}
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button class="pc-btn pc-btn--secondary pc-btn--sm" type="button" :disabled="!canUpdate" @click="addItem(section)">
                                <UiIcon name="plus" :size="15" />
                                {{ t('cms_add_item') }}
                            </button>
                        </div>
                    </details>
                </div>

                <section v-if="canView" class="pc-card">
                    <div class="pc-card__head">
                        <div>
                            <h2 class="pc-card__title">{{ t('cms_preview') }}</h2>
                            <p class="pc-card__subtitle">{{ t('cms_preview_notice', 'This preview is for editors only and is not publicly accessible.') }}</p>
                        </div>
                        <span class="pc-badge pc-badge--teal">{{ previewTotals.publicItems }}</span>
                    </div>
                    <div class="pc-card__body pc-stack">
                        <div class="pc-callout">
                            <UiIcon name="info" :size="16" />
                            <span>{{ t('cms_preview_notice', 'This preview is for editors only and is not publicly accessible.') }}</span>
                        </div>
                        <div class="pc-form-grid pc-form-grid--2">
                            <div class="pc-field">
                                <span class="pc-label">{{ labels.publicSections }}</span>
                                <span class="pc-badge pc-badge--green">{{ previewTotals.publicSections }}</span>
                            </div>
                            <div class="pc-field">
                                <span class="pc-label">{{ labels.publicItems }}</span>
                                <span class="pc-badge pc-badge--teal">{{ previewTotals.publicItems }}</span>
                            </div>
                            <div class="pc-field">
                                <span class="pc-label">{{ labels.draftItems }}</span>
                                <span class="pc-badge pc-badge--amber">{{ previewTotals.draftItems }}</span>
                            </div>
                        </div>

                        <details
                            v-for="group in previewGroups"
                            :key="group.key"
                            class="pc-accordion__item"
                            :open="group.sectionIsPublic && group.publicItems.length > 0"
                        >
                            <summary class="pc-accordion__trigger">
                                <UiIcon name="chevronRight" :size="16" />
                                <span>{{ group.label }}</span>
                                <span class="pc-badge pc-badge--slate">{{ group.key }}</span>
                                <span :class="['pc-badge', statusTone(group.status)]">
                                    {{ group.status === 'missing' ? labels.missingPreview : statusLabel(group.status) }}
                                </span>
                                <span class="pc-badge pc-badge--green">{{ group.publicItems.length }}</span>
                                <span v-if="group.draftItems.length" class="pc-badge pc-badge--amber">{{ group.draftItems.length }}</span>
                            </summary>
                            <div class="pc-accordion__body pc-stack">
                                <template v-if="group.section">
                                    <p v-if="group.title" class="pc-eyebrow">{{ group.title }}</p>
                                    <span :class="['pc-badge', group.sectionIsPublic ? 'pc-badge--green' : 'pc-badge--amber']">
                                        {{ group.sectionIsPublic ? labels.public : labels.notPublic }}
                                    </span>

                                    <div v-for="tabGroup in group.groups" :key="tabGroup.key || 'ungrouped'" class="pc-stack">
                                        <h4 class="pc-eyebrow">
                                            {{ tabGroup.label }}
                                            <span class="pc-badge pc-badge--teal">{{ tabGroup.items.length }}</span>
                                        </h4>
                                        <ul class="pc-list">
                                            <li v-for="item in tabGroup.items" :key="item.id || item.key || item.title" class="pc-list__item">
                                                <div class="pc-list__copy">
                                                    <span class="pc-list__title">{{ textValue(item.title) || textValue(item.label) || pick(item, 'key', '') }}</span>
                                                    <span v-if="textValue(item.dateLabel)" class="pc-list__meta">{{ textValue(item.dateLabel) }}</span>
                                                </div>
                                                <span class="pc-badge pc-badge--green">{{ labels.itemPublished }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div v-if="group.draftItems.length" class="pc-stack">
                                        <h4 class="pc-eyebrow">
                                            {{ labels.notPublic }}
                                            <span class="pc-badge pc-badge--amber">{{ group.draftItems.length }}</span>
                                        </h4>
                                        <ul class="pc-list">
                                            <li v-for="item in group.draftItems" :key="`draft-${item.id || item.key || item.title}`" class="pc-list__item">
                                                <div class="pc-list__copy">
                                                    <span class="pc-list__title">{{ textValue(item.title) || textValue(item.label) || pick(item, 'key', '') }}</span>
                                                </div>
                                                <span class="pc-badge pc-badge--amber">{{ statusLabel('draft') }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <p v-if="!group.publicItems.length && !group.draftItems.length" class="pc-hint">{{ labels.emptyPreview }}</p>
                                </template>
                                <p v-else class="pc-hint">{{ labels.missingPreview }}</p>
                            </div>
                        </details>
                    </div>
                </section>

                <div class="pc-form-actions">
                    <button class="pc-btn pc-btn--primary" type="submit" :disabled="form.processing || !canUpdate" :aria-busy="form.processing">
                        <UiIcon name="check" :size="16" />
                        {{ t('cms_save') }}
                    </button>
                </div>
            </form>
        </div>
    </CmsDashboardLayout>
</template>
