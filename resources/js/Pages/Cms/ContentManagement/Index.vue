<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    pages: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
})

const { locale, localPath } = useI18n()
const { confirm } = useAdminNotifications()

const search = ref('')
const visibility = ref('all')

const canUpdate = computed(() => props.can?.update === true)
const canManageContent = computed(() => props.can?.contentUpdate === true)

const copy = computed(() => locale.value === 'ar' ? {
    title: 'إدارة محتوى الموقع',
    subtitle: 'تحكم واحد في ظهور الصفحات والأقسام، وفي المحتوى المعروض في الموقع.',
    hint: 'إخفاء أي عنصر يحفظ محتواه بالكامل؛ إظهاره مرة أخرى يستعيد العرض دون فقدان البيانات.',
    pages: 'الصفحات',
    sections: 'الأقسام',
    records: 'سجلات المحتوى',
    hiddenSections: 'أقسام مخفية',
    search: 'ابحث باسم الصفحة أو القسم أو المسار',
    all: 'الكل',
    visible: 'ظاهر',
    hidden: 'مخفي',
    filterLabel: 'تصفية حسب الظهور',
    path: 'المسار',
    pageVisibility: 'ظهور الصفحة',
    sectionVisibility: 'ظهور القسم',
    manage: 'إدارة المحتوى',
    moveUp: 'تحريك للأعلى',
    moveDown: 'تحريك للأسفل',
    hideTitle: 'إخفاء العنصر؟',
    hideMessage: 'سيختفي العنصر من الموقع العام وسيبقى محتواه محفوظاً بالكامل.',
    confirmHide: 'إخفاء',
    cancel: 'إلغاء',
    noSections: 'لا توجد أقسام معرفة لهذه الصفحة.',
    noResults: 'لا توجد نتائج مطابقة للبحث أو التصفية.',
    showing: 'عرض',
} : {
    title: 'Content Management',
    subtitle: 'One place to control page visibility, section visibility and homepage display.',
    hint: 'Hiding anything keeps its content intact. Re-enabling restores presentation without data loss.',
    pages: 'Pages',
    sections: 'Sections',
    records: 'Content records',
    hiddenSections: 'Hidden sections',
    search: 'Search by page, section or path',
    all: 'All',
    visible: 'Visible',
    hidden: 'Hidden',
    filterLabel: 'Filter by visibility',
    path: 'Path',
    pageVisibility: 'Page visibility',
    sectionVisibility: 'Section visibility',
    manage: 'Manage content',
    moveUp: 'Move up',
    moveDown: 'Move down',
    hideTitle: 'Hide this item?',
    hideMessage: 'The item will disappear from the public website while all of its content is preserved.',
    confirmHide: 'Hide',
    cancel: 'Cancel',
    noSections: 'No predefined sections belong to this page.',
    noResults: 'No results match the search or filter.',
    showing: 'Showing',
})

const allSections = computed(() => props.sections.length
    ? props.sections
    : props.pages.flatMap((page) => page.sections || []))

const summary = computed(() => ({
    pages: props.pages.length,
    sections: allSections.value.length,
    records: allSections.value.reduce((total, section) => total + Number(section.recordCount || 0), 0),
    hiddenSections: allSections.value.filter((section) => ! section.enabled).length,
}))

const summaryCards = computed(() => [
    { key: 'pages', label: copy.value.pages, value: summary.value.pages },
    { key: 'sections', label: copy.value.sections, value: summary.value.sections },
    { key: 'records', label: copy.value.records, value: summary.value.records },
    { key: 'hiddenSections', label: copy.value.hiddenSections, value: summary.value.hiddenSections },
])

const filterOptions = computed(() => [
    { value: 'all', label: copy.value.all },
    { value: 'visible', label: copy.value.visible },
    { value: 'hidden', label: copy.value.hidden },
])

const normalizedSearch = computed(() => search.value.trim().toLocaleLowerCase())

function matches(value) {
    return String(value ?? '').toLocaleLowerCase().includes(normalizedSearch.value)
}

const filteredPages = computed(() => props.pages.map((page) => {
    const pageMatches = ! normalizedSearch.value
        || matches(page.name)
        || matches(page.key)
        || matches(page.path)
    const sourceSections = page.sections || []
    const searchedSections = normalizedSearch.value && ! pageMatches
        ? sourceSections.filter((section) => matches(section.name) || matches(section.key) || matches(section.content))
        : sourceSections
    const sections = searchedSections.filter((section) => visibility.value === 'all'
        || (visibility.value === 'visible' ? section.enabled : ! section.enabled))
    const includePage = (! normalizedSearch.value || pageMatches || searchedSections.length > 0)
        && (visibility.value === 'all' || page.enabled === (visibility.value === 'visible') || sections.length > 0)

    return { ...page, sections, includePage }
}).filter((page) => page.includePage))

const visibleSectionCount = computed(() => filteredPages.value.reduce(
    (total, page) => total + page.sections.length,
    0,
))

async function setVisibility(url, current, enabled, event) {
    if (! canUpdate.value) {
        return
    }

    if (! enabled && await confirm({
        title: copy.value.hideTitle,
        message: copy.value.hideMessage,
        confirmLabel: copy.value.confirmHide,
        cancelLabel: copy.value.cancel,
        danger: true,
    }) === false) {
        if (event?.target) {
            event.target.checked = current
        }

        return
    }

    router.patch(url, { enabled }, { preserveScroll: true })
}

function moveSection(page, section, direction) {
    if (! canUpdate.value) {
        return
    }

    const reordered = [...(page.sections || [])]
    const from = reordered.findIndex((entry) => entry.id === section.id)
    const to = from + direction

    if (from < 0 || to < 0 || to >= reordered.length) {
        return
    }

    const [moved] = reordered.splice(from, 1)
    reordered.splice(to, 0, moved)

    router.post(localPath('cms/content-management/sections/reorder'), {
        order: reordered.map((entry, position) => ({
            id: entry.id,
            display_order: position,
        })),
    }, { preserveScroll: true })
}
</script>

<template>
    <CmsDashboardLayout
        :title="copy.title"
        :subtitle="copy.subtitle"
        :hint="copy.hint"
    >
        <div class="pc-stack cms-workspace">
            <section class="cms-summary" :aria-label="copy.title">
                <article v-for="card in summaryCards" :key="card.key" class="cms-summary__card">
                    <span class="pc-hint">{{ card.label }}</span>
                    <strong>{{ card.value }}</strong>
                </article>
            </section>

            <section class="pc-card">
                <header class="pc-card__head cms-toolbar">
                    <label class="pc-field cms-search">
                        <span>{{ copy.search }}</span>
                        <input v-model="search" type="search" maxlength="120">
                    </label>
                    <div class="cms-filter" role="group" :aria-label="copy.filterLabel">
                        <button
                            v-for="option in filterOptions"
                            :key="option.value"
                            type="button"
                            class="pc-btn pc-btn--sm"
                            :class="visibility === option.value ? 'pc-btn--primary' : 'pc-btn--ghost'"
                            :aria-pressed="visibility === option.value"
                            @click="visibility = option.value"
                        >{{ option.label }}</button>
                    </div>
                </header>
                <p class="pc-hint cms-result-count">{{ copy.showing }} {{ visibleSectionCount }} {{ copy.sections }}</p>
            </section>

            <p v-if="!filteredPages.length" class="pc-empty">{{ copy.noResults }}</p>

            <section v-for="page in filteredPages" :key="page.key" class="pc-card cms-page">
                <header class="pc-card__head">
                    <div class="cms-page__heading">
                        <h2 class="pc-card__title">{{ page.name }}</h2>
                        <p class="pc-hint"><code>{{ page.path }}</code></p>
                    </div>
                    <label class="pc-toggle">
                        <input
                            type="checkbox"
                            :checked="page.enabled"
                            :disabled="!canUpdate"
                            :aria-label="`${page.name} — ${copy.pageVisibility}`"
                            @change="setVisibility(
                                localPath(`cms/content-management/pages/${page.key}/toggle`),
                                page.enabled,
                                $event.target.checked,
                                $event,
                            )"
                        >
                        <span>{{ page.enabled ? copy.visible : copy.hidden }}</span>
                    </label>
                </header>

                <p v-if="!page.sections.length" class="pc-empty">{{ copy.noSections }}</p>
                <ul v-else class="cms-section-list">
                    <li v-for="section in page.sections" :key="section.id" class="cms-section-row">
                        <div class="cms-section-row__name">
                            <span class="cms-cell-label">{{ copy.sections }}</span>
                            <strong>{{ section.name }}</strong>
                            <span class="pc-hint"><code>{{ section.key }}</code></span>
                        </div>
                        <div class="cms-section-row__records">
                            <span class="cms-cell-label">{{ copy.records }}</span>
                            <span>{{ section.recordCount }}</span>
                        </div>
                        <div class="cms-section-row__visibility">
                            <span class="cms-cell-label">{{ copy.sectionVisibility }}</span>
                            <label class="pc-toggle">
                                <input
                                    type="checkbox"
                                    :checked="section.enabled"
                                    :disabled="!canUpdate"
                                    :aria-label="`${section.name} — ${copy.sectionVisibility}`"
                                    @change="setVisibility(
                                        localPath(`cms/content-management/sections/${section.key}/toggle`),
                                        section.enabled,
                                        $event.target.checked,
                                        $event,
                                    )"
                                >
                                <span>{{ section.enabled ? copy.visible : copy.hidden }}</span>
                            </label>
                            <div class="pc-row-actions">
                                <button
                                    type="button"
                                    class="pc-btn pc-btn--ghost pc-btn--xs"
                                    :disabled="!canUpdate || section.order === 0"
                                    :aria-label="`${copy.moveUp} — ${section.name}`"
                                    @click="moveSection(page, section, -1)"
                                >↑</button>
                                <button
                                    type="button"
                                    class="pc-btn pc-btn--ghost pc-btn--xs"
                                    :disabled="!canUpdate || section.order === (page.sections.length - 1)"
                                    :aria-label="`${copy.moveDown} — ${section.name}`"
                                    @click="moveSection(page, section, 1)"
                                >↓</button>
                            </div>
                        </div>
                        <div class="cms-section-row__manage">
                            <Link
                                v-if="canManageContent"
                                class="pc-btn pc-btn--secondary pc-btn--sm"
                                :href="section.manageUrl || localPath(`cms/content/${section.key}`)"
                            >{{ copy.manage }}</Link>
                            <span v-else class="pc-hint">—</span>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </CmsDashboardLayout>
</template>

<style scoped>
.cms-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.cms-summary__card {
    display: grid;
    gap: 4px;
    min-inline-size: 0;
    padding: 14px 16px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius);
    background: var(--pc-surface);
}

.cms-summary__card strong {
    font-size: 1.35rem;
    line-height: 1.2;
}

.cms-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    justify-content: space-between;
    gap: 14px;
}

.cms-search {
    flex: 1 1 260px;
    max-inline-size: 420px;
}

.cms-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.cms-result-count {
    margin: 0;
    padding-inline: 20px;
}

.cms-page__heading {
    min-inline-size: 0;
}

.cms-page__heading h2,
.cms-page__heading p {
    margin: 0;
    overflow-wrap: anywhere;
}

.cms-section-list {
    display: grid;
    gap: 10px;
    margin: 0;
    padding: 20px;
    list-style: none;
}

.cms-section-row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(72px, 0.5fr) minmax(0, 1.5fr) auto;
    align-items: center;
    gap: 14px;
    min-inline-size: 0;
    padding: 12px 14px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm);
    background: var(--pc-surface-2);
}

.cms-section-row > div {
    display: grid;
    gap: 3px;
    min-inline-size: 0;
}

.cms-section-row strong,
.cms-section-row code {
    overflow-wrap: anywhere;
}

.cms-cell-label {
    display: none;
    color: var(--pc-muted);
    font-size: 0.72rem;
    font-weight: 650;
}

.cms-section-row__visibility {
    align-items: start;
}

.cms-section-row__manage {
    justify-items: end;
}

@media (max-width: 960px) {
    .cms-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cms-section-row {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }

    .cms-section-row__manage {
        justify-items: start;
    }
}

@media (max-width: 640px) {
    .cms-summary,
    .cms-section-row {
        grid-template-columns: minmax(0, 1fr);
    }

    .cms-search {
        max-inline-size: none;
    }

    .cms-result-count,
    .cms-section-list {
        padding-inline: 16px;
    }

    .cms-cell-label {
        display: block;
    }

    .cms-section-row__manage {
        justify-items: stretch;
    }
}
</style>
