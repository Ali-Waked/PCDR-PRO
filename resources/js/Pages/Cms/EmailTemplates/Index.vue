<script setup>
import { computed, ref } from 'vue'
import { router, useHttp } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import CmsStatusBadge from '../../../Components/Dashboard/CmsStatusBadge.vue'
import ConfirmDialog from '../../../Components/Dashboard/ConfirmDialog.vue'
import DataTable from '../../../Components/Dashboard/DataTable.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import Pagination from '../../../Components/Pagination.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import StatusSelect from '../../../Components/Selection/StatusSelect.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import EmailPreviewDialog from '../../../Components/EmailTemplates/EmailPreviewDialog.vue'
import TemplateGuideDialog from '../../../Components/EmailTemplates/TemplateGuideDialog.vue'
import TemplateWizard from '../../../Components/EmailTemplates/TemplateWizard.vue'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    templates: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ q: '', status: '', locale: '' }) },
    locales: { type: Array, default: () => ['ar', 'en'] },
    variableCatalog: { type: Object, default: () => ({}) },
    starters: { type: Array, default: () => [] },
    guideExample: { type: String, default: '' },
    strings: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
})

const { locale, localPath, formatDate } = useI18n()

const templatesPath = localPath('cms/email-templates')
const previewPath = `${templatesPath}/preview`
const sendTestPath = `${templatesPath}/send-test`

const search = ref(props.filters?.q || '')
const statusFilter = ref(props.filters?.status || '')
const localeFilter = ref(props.filters?.locale || '')

const wizardOpen = ref(false)
const wizardTemplate = ref(null)
const guideOpen = ref(false)
const previewOpen = ref(false)
const previewData = ref(null)
const previewBusy = ref(false)
const previewError = ref('')
const previewLocale = ref('')
const previewDraft = ref(null)
const previewTemplateId = ref(null)
const imagesBlocked = ref(false)
const testBusy = ref(false)
const testNotice = ref('')
const deleting = ref(null)
const busyAction = ref('')

const savedPreviewHttp = useHttp({ template_id: null, locale: '', images_blocked: false })
const draftPreviewHttp = useHttp({ subject: '', body: '', locale: '', images_blocked: false })
const testHttp = useHttp({ template_id: null, subject: '', body: '', locale: '' })

function s(key, fallback = '') {
    return props.strings?.[key] ?? fallback
}

const templateRows = computed(() => props.templates?.data || [])
const meta = computed(() => props.templates?.meta || {})
const defaultLocale = computed(() => props.locales.includes(locale.value) ? locale.value : (props.locales[0] || 'ar'))

const statusFilterOptions = computed(() => [
    { value: 'active', label: s('active') },
    { value: 'inactive', label: s('inactive') },
])
const localeFilterOptions = computed(() => props.locales.map((code) => ({
    value: code,
    label: code.toUpperCase(),
})))

if (! previewLocale.value) {
    previewLocale.value = defaultLocale.value
}

const hasFilters = computed(() => search.value.trim() !== '' || statusFilter.value !== '' || localeFilter.value !== '')

const metrics = computed(() => [
    { key: 'total', label: s('stats_total'), value: props.stats?.total ?? 0, icon: 'template', tone: '' },
    { key: 'active', label: s('stats_active'), value: props.stats?.active ?? 0, icon: 'check', tone: 'pc-metric--teal' },
    { key: 'inactive', label: s('stats_inactive'), value: props.stats?.inactive ?? 0, icon: 'eyeOff', tone: 'pc-metric--sand' },
    { key: 'recent', label: s('stats_recent'), value: props.stats?.recent ?? 0, icon: 'clock', tone: 'pc-metric--navy' },
])

const columns = computed(() => [
    { key: 'template', label: s('table_template') },
    { key: 'locales', label: s('table_locale') },
    { key: 'status', label: s('table_status') },
    { key: 'updated', label: s('table_updated') },
    { key: 'actions', label: s('table_actions'), align: 'end' },
])

function countLabel(count) {
    return s('results_count', ':count templates').replace(':count', String(count || 0))
}

function translationFor(template) {
    return template?.translations?.[locale.value]
        || template?.translations?.[defaultLocale.value]
        || template?.translations?.en
        || template?.translations?.ar
        || {}
}

function displayName(template) {
    return translationFor(template).name || template.key
}

function descriptionFor(template) {
    return translationFor(template).description || ''
}

function formatUpdated(value) {
    return value ? formatDate(value) : '—'
}

function isBusy(action, id) {
    return busyAction.value === `${action}:${id}`
}

function applyFilters(page = 1) {
    const params = {
        q: search.value.trim(),
        status: statusFilter.value,
        locale: localeFilter.value,
    }

    if (page > 1) {
        params.page = page
    }

    router.get(templatesPath, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function clearFilters() {
    search.value = ''
    statusFilter.value = ''
    localeFilter.value = ''
    applyFilters()
}

function openCreate() {
    wizardTemplate.value = null
    wizardOpen.value = true
}

function openEdit(template) {
    wizardTemplate.value = template
    wizardOpen.value = true
}

function closeWizard() {
    wizardOpen.value = false
    wizardTemplate.value = null
}

function handleSaved() {
    closeWizard()
    router.reload({ only: ['templates', 'stats'], preserveScroll: true })
}

function duplicate(template) {
    if (isBusy('duplicate', template.id)) {
        return
    }

    busyAction.value = `duplicate:${template.id}`
    router.post(`${templatesPath}/${template.id}/duplicate`, {}, {
        preserveScroll: true,
        onFinish: () => { busyAction.value = '' },
        onSuccess: () => router.reload({ only: ['templates', 'stats'] }),
    })
}

function toggleStatus(template) {
    if (isBusy('status', template.id)) {
        return
    }

    busyAction.value = `status:${template.id}`
    router.patch(`${templatesPath}/${template.id}/status`, { is_active: ! template.active }, {
        preserveScroll: true,
        onFinish: () => { busyAction.value = '' },
        onSuccess: () => router.reload({ only: ['templates', 'stats'] }),
    })
}

function confirmDelete() {
    const target = deleting.value
    if (! target) {
        return
    }

    busyAction.value = `delete:${target.id}`
    router.delete(`${templatesPath}/${target.id}`, {
        preserveScroll: true,
        onFinish: () => {
            busyAction.value = ''
            deleting.value = null
        },
        onSuccess: () => router.reload({ only: ['templates', 'stats'] }),
    })
}

async function loadSavedPreview() {
    if (! previewTemplateId.value) {
        return
    }

    previewBusy.value = true
    previewError.value = ''

    Object.assign(savedPreviewHttp, {
        template_id: previewTemplateId.value,
        locale: previewLocale.value || defaultLocale.value,
        images_blocked: imagesBlocked.value,
    })

    try {
        previewData.value = await savedPreviewHttp.post(previewPath)
    } catch {
        previewError.value = s('preview_failed')
    } finally {
        previewBusy.value = false
    }
}

async function loadDraftPreview() {
    if (! previewDraft.value) {
        return
    }

    previewBusy.value = true
    previewError.value = ''

    Object.assign(draftPreviewHttp, {
        subject: previewDraft.value.subject,
        body: previewDraft.value.body,
        locale: previewLocale.value || defaultLocale.value,
        images_blocked: imagesBlocked.value,
    })

    try {
        previewData.value = await draftPreviewHttp.post(previewPath)
    } catch {
        previewError.value = s('preview_failed')
    } finally {
        previewBusy.value = false
    }
}

function refreshPreview() {
    if (previewDraft.value) {
        return loadDraftPreview()
    }

    return loadSavedPreview()
}

function openTemplatePreview(template) {
    previewDraft.value = null
    previewTemplateId.value = template.id
    previewLocale.value = props.locales.includes(locale.value) ? locale.value : defaultLocale.value
    previewData.value = null
    previewError.value = ''
    testNotice.value = ''
    previewOpen.value = true
    loadSavedPreview()
}

function openWizardPreview(payload) {
    previewDraft.value = {
        subject: payload?.subject || '',
        body: payload?.body || '',
    }
    previewTemplateId.value = null
    previewLocale.value = payload?.locale || defaultLocale.value
    previewData.value = payload?.preview || null
    previewError.value = ''
    testNotice.value = ''
    previewOpen.value = true
}

function changePreviewLocale(value) {
    previewLocale.value = value
    refreshPreview()
}

function toggleImagesBlocked(value) {
    imagesBlocked.value = value
    refreshPreview()
}

async function sendTestEmail() {
    const payload = {
        locale: previewLocale.value || defaultLocale.value,
        template_id: previewDraft.value ? null : (previewTemplateId.value || null),
        subject: previewDraft.value?.subject || '',
        body: previewDraft.value?.body || '',
    }

    testBusy.value = true
    testNotice.value = ''

    Object.assign(testHttp, payload)

    try {
        await testHttp.post(sendTestPath)
        testNotice.value = s('preview_test_sent')
    } catch {
        testNotice.value = s('preview_test_failed')
    } finally {
        testBusy.value = false
    }
}
</script>

<template>
    <CmsDashboardLayout
        :title="s('page_title')"
        :subtitle="s('page_description')"
        :eyebrow="s('page_eyebrow')"
    >
        <template #actions>
            <button type="button" class="pc-btn pc-btn--secondary" data-testid="email-templates-guide" @click="guideOpen = true">
                <UiIcon name="book" :size="16" />
                <span>{{ s('guide') }}</span>
            </button>
            <button v-if="can.create" type="button" class="pc-btn pc-btn--primary" data-testid="email-templates-create" @click="openCreate">
                <UiIcon name="plus" :size="16" />
                <span>{{ s('create') }}</span>
            </button>
        </template>

        <div class="pc-stack pcdr-enter et-page">
            <section class="pc-metric-grid" :aria-label="s('stats_total')">
                <article v-for="metric in metrics" :key="metric.key" class="pc-metric" :class="metric.tone">
                    <div class="pc-metric__head">
                        <span class="pc-metric__label">{{ metric.label }}</span>
                        <span class="pc-metric__icon"><UiIcon :name="metric.icon" :size="17" /></span>
                    </div>
                    <strong class="pc-metric__value">{{ metric.value }}</strong>
                    <span v-if="metric.key === 'recent'" class="pc-metric__hint">{{ s('stats_recent_note') }}</span>
                    <span v-else class="pc-metric__hint">&nbsp;</span>
                </article>
            </section>

            <section class="pc-card">
                <div class="pc-card__head">
                    <div>
                        <h2 class="pc-card__title">{{ s('page_title') }}</h2>
                        <p class="pc-card__subtitle">{{ countLabel(meta.total) }}</p>
                    </div>
                    <button v-if="can.create" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="openCreate">
                        <UiIcon name="plus" :size="14" />
                        {{ s('create') }}
                    </button>
                </div>

                <form class="pc-toolbar et-filter-bar" @submit.prevent="applyFilters()">
                    <div class="pc-search">
                        <UiIcon name="search" :size="16" />
                        <input v-model="search" type="search" :placeholder="s('search_placeholder')" :aria-label="s('search')">
                    </div>
                    <div class="et-filter-field">
                        <StatusSelect
                            v-model="statusFilter"
                            :aria-label="s('table_status')"
                            :options="statusFilterOptions"
                            :placeholder="s('all_statuses')"
                            clearable
                            size="sm"
                        />
                    </div>
                    <div class="et-filter-field">
                        <SelectionControl
                            v-model="localeFilter"
                            :aria-label="s('locale_label')"
                            :options="localeFilterOptions"
                            :placeholder="s('all_locales')"
                            clearable
                            :searchable="false"
                            size="sm"
                        />
                    </div>
                    <button type="submit" class="pc-btn pc-btn--secondary pc-btn--sm">
                        <UiIcon name="search" :size="14" />
                        {{ s('search') }}
                    </button>
                    <button v-if="hasFilters" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="clearFilters">
                        <UiIcon name="close" :size="14" />
                        {{ s('clear_filters') }}
                    </button>
                </form>

                <div class="pc-filter-summary">{{ countLabel(meta.total) }}</div>

                <template v-if="templateRows.length">
                    <div class="et-desktop-list">
                        <DataTable :columns="columns">
                            <tr v-for="template in templateRows" :key="template.id">
                                <td>
                                    <div class="et-template-cell">
                                        <span class="et-template-icon"><UiIcon name="mail" :size="16" /></span>
                                        <div class="et-template-cell__text">
                                            <span class="pc-table__primary">{{ displayName(template) }}</span>
                                            <span class="pc-table__secondary pc-directional" dir="ltr">{{ template.key }}</span>
                                            <small v-if="descriptionFor(template)" class="et-template-description">{{ descriptionFor(template) }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="et-locale-badges">
                                        <span v-for="code in template.locales" :key="code" class="pc-badge pc-badge--slate">{{ code.toUpperCase() }}</span>
                                    </div>
                                </td>
                                <td>
                                    <CmsStatusBadge :label="template.active ? s('active') : s('inactive')" :status="template.active ? 'active' : 'inactive'" />
                                </td>
                                <td dir="auto">{{ formatUpdated(template.updatedAt) }}</td>
                                <td>
                                    <div class="pc-table__actions">
                                        <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" data-testid="email-templates-preview" :disabled="previewBusy && previewOpen" @click="openTemplatePreview(template)">
                                            <UiIcon name="eye" :size="14" />
                                            <span class="et-action-label">{{ s('preview') }}</span>
                                        </button>
                                        <button v-if="can.update" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="openEdit(template)">
                                            <UiIcon name="edit" :size="14" />
                                            <span class="et-action-label">{{ s('edit') }}</span>
                                        </button>
                                        <button v-if="can.create" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" :disabled="isBusy('duplicate', template.id)" @click="duplicate(template)">
                                            <UiIcon name="copy" :size="14" />
                                            <span class="et-action-label">{{ s('duplicate') }}</span>
                                        </button>
                                        <button v-if="can.update" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" :disabled="isBusy('status', template.id)" @click="toggleStatus(template)">
                                            <UiIcon :name="template.active ? 'eyeOff' : 'check'" :size="14" />
                                            <span class="et-action-label">{{ template.active ? s('deactivate') : s('activate') }}</span>
                                        </button>
                                        <button v-if="can.delete" type="button" class="pc-btn pc-btn--danger pc-btn--sm" @click="deleting = template">
                                            <UiIcon name="trash" :size="14" />
                                            <span class="et-action-label">{{ s('delete') }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </DataTable>
                    </div>

                    <div class="et-mobile-list">
                        <article v-for="template in templateRows" :key="template.id" class="et-mobile-card">
                            <div class="et-mobile-card__head">
                                <div>
                                    <strong>{{ displayName(template) }}</strong>
                                    <span class="pc-table__secondary pc-directional" dir="ltr">{{ template.key }}</span>
                                </div>
                                <CmsStatusBadge :label="template.active ? s('active') : s('inactive')" :status="template.active ? 'active' : 'inactive'" />
                            </div>
                            <p v-if="descriptionFor(template)" class="et-mobile-card__description">{{ descriptionFor(template) }}</p>
                            <div class="et-mobile-card__meta">
                                <div class="et-locale-badges">
                                    <span v-for="code in template.locales" :key="code" class="pc-badge pc-badge--slate">{{ code.toUpperCase() }}</span>
                                </div>
                                <span>{{ formatUpdated(template.updatedAt) }}</span>
                            </div>
                            <div class="et-mobile-card__actions">
                                <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" data-testid="email-templates-preview-mobile" @click="openTemplatePreview(template)">
                                    <UiIcon name="eye" :size="14" /> {{ s('preview') }}
                                </button>
                                <button v-if="can.update" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="openEdit(template)">
                                    <UiIcon name="edit" :size="14" /> {{ s('edit') }}
                                </button>
                                <button v-if="can.create" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" :disabled="isBusy('duplicate', template.id)" @click="duplicate(template)">
                                    <UiIcon name="copy" :size="14" /> {{ s('duplicate') }}
                                </button>
                                <button v-if="can.update" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" :disabled="isBusy('status', template.id)" @click="toggleStatus(template)">
                                    <UiIcon :name="template.active ? 'eyeOff' : 'check'" :size="14" />
                                    {{ template.active ? s('deactivate') : s('activate') }}
                                </button>
                                <button v-if="can.delete" type="button" class="pc-btn pc-btn--danger pc-btn--sm" @click="deleting = template">
                                    <UiIcon name="trash" :size="14" /> {{ s('delete') }}
                                </button>
                            </div>
                        </article>
                    </div>
                </template>

                <EmptyState v-else :title="s('empty_title')" :message="s('empty_description')" icon="mail">
                    <button v-if="can.create" type="button" class="pc-btn pc-btn--primary" @click="openCreate">
                        <UiIcon name="plus" :size="16" />
                        {{ s('create') }}
                    </button>
                </EmptyState>

                <Pagination :pagination="templates" replace />
            </section>
        </div>

        <TemplateWizard
            :open="wizardOpen"
            :template="wizardTemplate"
            :locales="locales"
            :variable-catalog="variableCatalog"
            :starters="starters"
            :strings="strings"
            :can="can"
            :templates-path="templatesPath"
            :preview-path="previewPath"
            :initial-locale="defaultLocale"
            @close="closeWizard"
            @saved="handleSaved"
            @open-guide="guideOpen = true"
            @open-preview="openWizardPreview"
        />

        <EmailPreviewDialog
            :open="previewOpen"
            :preview="previewData"
            :strings="strings"
            :busy="previewBusy"
            :error="previewError"
            :locales="locales"
            :active-locale="previewLocale"
            :images-blocked="imagesBlocked"
            :test-busy="testBusy"
            :test-notice="testNotice"
            @close="previewOpen = false"
            @refresh="refreshPreview"
            @change-locale="changePreviewLocale"
            @toggle-images-blocked="toggleImagesBlocked"
            @send-test="sendTestEmail"
        />

        <TemplateGuideDialog :open="guideOpen" :strings="strings" :example="guideExample" @close="guideOpen = false" />

        <ConfirmDialog
            :open="Boolean(deleting)"
            :title="s('delete_title')"
            :message="s('delete_message')"
            :confirm-label="s('delete')"
            :cancel-label="s('cancel')"
            :busy="Boolean(deleting && isBusy('delete', deleting.id))"
            danger
            @close="deleting = null"
            @confirm="confirmDelete"
        />
    </CmsDashboardLayout>
</template>

<style scoped>
.et-page { min-inline-size: 0; }
.et-filter-bar { align-items: flex-end; }
.et-filter-field { display: grid; gap: 4px; min-inline-size: 155px; }
.pc-sr-text {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.et-template-cell { display: flex; align-items: flex-start; gap: 10px; min-inline-size: 0; }
.et-template-icon {
    display: grid;
    place-items: center;
    inline-size: 32px;
    block-size: 32px;
    flex-shrink: 0;
    border-radius: 9px;
    background: var(--pc-teal-soft);
    color: var(--pc-teal);
}
.et-template-cell__text { display: grid; gap: 2px; min-inline-size: 0; }
.et-template-description { color: var(--pc-muted); font-size: 0.72rem; line-height: 1.5; overflow-wrap: anywhere; }
.et-locale-badges { display: flex; flex-wrap: wrap; gap: 5px; }
.et-desktop-list { display: block; }
.et-mobile-list { display: none; }
.et-mobile-card { display: grid; gap: 10px; padding: 16px; border-block-end: 1px solid var(--pc-border); }
.et-mobile-card:last-child { border-block-end: 0; }
.et-mobile-card__head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 8px; }
.et-mobile-card__head strong { display: block; font-size: 0.9rem; }
.et-mobile-card__description { margin: 0; color: var(--pc-muted); font-size: 0.78rem; line-height: 1.6; }
.et-mobile-card__meta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; color: var(--pc-muted); font-size: 0.72rem; }
.et-mobile-card__actions { display: flex; flex-wrap: wrap; gap: 6px; }
.et-pagination { gap: 10px; }
.et-pagination__pages { display: flex; flex-wrap: wrap; gap: 5px; }
.et-page-link {
    min-inline-size: 32px;
    block-size: 32px;
    padding-inline: 9px;
    border: 1px solid var(--pc-border);
    border-radius: 8px;
    background: var(--pc-surface);
    color: var(--pc-text-soft);
    font: inherit;
    font-size: 0.76rem;
    cursor: pointer;
}
.et-page-link.is-active { background: var(--pc-teal); border-color: var(--pc-teal); color: var(--pc-on-secondary); font-weight: 700; }

@media (max-width: 1180px) {
    .et-action-label { display: none; }
}

@media (max-width: 900px) {
    .pc-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .et-desktop-list { display: none; }
    .et-mobile-list { display: block; }
}

@media (max-width: 560px) {
    .pc-metric-grid { grid-template-columns: minmax(0, 1fr); }
    .et-filter-bar { align-items: stretch; }
    .et-filter-bar .pc-search { max-inline-size: none; flex-basis: 100%; }
    .et-filter-field { flex: 1 1 140px; min-inline-size: 0; }
    .et-pagination__label { flex-basis: 100%; text-align: center; }
}
</style>
