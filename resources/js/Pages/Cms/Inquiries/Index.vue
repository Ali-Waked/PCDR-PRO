<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import CmsPagination from '../../../Components/Dashboard/CmsPagination.vue'
import CmsStatusBadge from '../../../Components/Dashboard/CmsStatusBadge.vue'
import DataTable from '../../../Components/Dashboard/DataTable.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import MetricCard from '../../../Components/Dashboard/MetricCard.vue'
import PcSelect from '../../../Components/Dashboard/PcSelect.vue'
import StatusSelect from '../../../Components/Selection/StatusSelect.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import InquiryStatusControl from '../../../Components/Cms/InquiryStatusControl.vue'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    inquiries: { type: Object, default: () => ({}) },
    pagination: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ status: '', search: '', assigned: '' }) },
    statuses: { type: Array, default: () => ['new', 'in_progress', 'resolved', 'closed'] },
    canonicalStatuses: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
})

const { t, localPath, formatDate, page } = useI18n()
const inquiriesPath = localPath('cms/inquiries')

const breadcrumb = computed(() => [
    { label: t('ui.dashboard_content_studio'), href: localPath('cms') },
    { label: t('ui.cms_inquiries') },
])

const groups = computed(() => [
    {
        key: 'contact',
        label: t('cms_inquiries_contact'),
        icon: 'mail',
        total: Number(props.pagination?.contact?.total ?? 0),
    },
    {
        key: 'volunteer',
        label: t('cms_inquiries_volunteer'),
        icon: 'users',
        total: Number(props.pagination?.volunteer?.total ?? 0),
    },
])

const activeType = ref('contact')
const rows = computed(() => props.inquiries?.[activeType.value] || [])
const activePagination = computed(() => props.pagination?.[activeType.value] || {
    currentPage: 1,
    lastPage: 1,
    total: 0,
    pageName: `${activeType.value}Page`,
})
const totalResults = computed(() => groups.value.reduce((sum, group) => sum + group.total, 0))

const searchTerm = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const assignedFilter = ref(props.filters?.assigned || '')
const processing = ref(false)
let debounceTimer = null

const hasFilters = computed(() => Boolean(searchTerm.value.trim())
    || statusFilter.value !== ''
    || assignedFilter.value !== '')

const availableStatuses = computed(() => props.canonicalStatuses.length
    ? props.canonicalStatuses
    : props.statuses)

const statusOptions = computed(() => [
    { value: '', label: t('ui.all'), tone: 'slate' },
    ...availableStatuses.value.map((status) => ({ value: status, label: statusLabel(status) })),
])

const assignedOptions = computed(() => [
    { value: '', label: t('ui.all') },
    { value: 'mine', label: t('cms_assigned_to_me') },
    { value: 'unassigned', label: t('cms_unassigned') },
])

const metrics = computed(() => [
    { key: 'total', label: t('ui.cms_inquiries_page.metrics_total'), value: props.counts?.total ?? 0, icon: 'inbox', tone: '' },
    { key: 'new', label: t('cms_status_new'), value: props.counts?.new ?? 0, icon: 'mail', tone: 'teal' },
    { key: 'in_progress', label: t('cms_status_in_progress'), value: props.counts?.in_progress ?? 0, icon: 'clock', tone: 'sand' },
    { key: 'unassigned', label: t('ui.cms_inquiries_page.metrics_unassigned'), value: props.counts?.unassigned ?? 0, icon: 'users', tone: 'navy' },
])

const columns = computed(() => [
    { key: 'reference', label: t('cms_reference') },
    { key: 'sender', label: t('cms_name') },
    { key: 'subject', label: t('subject') },
    { key: 'assigned', label: t('cms_assigned_to') },
    { key: 'status', label: t('cms_status') },
    { key: 'created', label: t('cms_created_at') },
    { key: 'actions', label: t('cms_actions'), align: 'end' },
])

const summary = computed(() => translate('ui.cms_inquiries_page.results_count', { count: totalResults.value }))
const emptyTitle = computed(() => hasFilters.value
    ? t('ui.cms_inquiries_page.empty_filtered_title')
    : t('ui.cms_inquiries_page.empty_title'))
const emptyMessage = computed(() => hasFilters.value
    ? t('ui.cms_inquiries_page.empty_filtered_message')
    : t('ui.cms_inquiries_page.empty_message'))

const statusLabels = {
    new: 'cms_status_new',
    in_progress: 'cms_status_in_progress',
    resolved: 'cms_status_resolved',
    closed: 'cms_status_closed',
}

function statusLabel(status) {
    return statusLabels[status] ? t(statusLabels[status], status) : status
}

function translate(key, replacements = {}) {
    let value = String(t(key) ?? '')
    for (const [name, replacement] of Object.entries(replacements)) {
        value = value.replaceAll(`:${name}`, String(replacement ?? ''))
    }

    return value
}

function initials(name) {
    return String(name || '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || '?'
}

function applyFilters() {
    window.clearTimeout(debounceTimer)

    const query = {}
    const search = searchTerm.value.trim()

    if (search !== '') {
        query.search = search
    }
    if (statusFilter.value !== '') {
        query.status = statusFilter.value
    }
    if (assignedFilter.value !== '') {
        query.assigned = assignedFilter.value
    }

    router.get(inquiriesPath, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { processing.value = true },
        onFinish: () => { processing.value = false },
    })
}

function queueSearch() {
    window.clearTimeout(debounceTimer)
    debounceTimer = window.setTimeout(() => applyFilters(), 350)
}

function changeStatusFilter(value) {
    statusFilter.value = value || ''
    applyFilters()
}

function changeAssignedFilter(value) {
    assignedFilter.value = value || ''
    applyFilters()
}

function clearFilters() {
    searchTerm.value = ''
    statusFilter.value = ''
    assignedFilter.value = ''
    applyFilters()
}

watch(() => props.filters, (filters) => {
    searchTerm.value = filters?.search || ''
    statusFilter.value = filters?.status || ''
    assignedFilter.value = filters?.assigned || ''
}, { deep: true })

onBeforeUnmount(() => window.clearTimeout(debounceTimer))
</script>

<template>
    <CmsDashboardLayout
        :title="t('ui.cms_inquiries')"
        :subtitle="t('ui.cms_inquiries_page.description')"
        :eyebrow="t('ui.dashboard_content_studio')"
        :breadcrumb="breadcrumb"
    >
        <div class="pc-stack iq-page pcdr-enter">
            <section class="pc-metric-grid iq-metrics" :aria-label="t('ui.cms_inquiries')">
                <MetricCard
                    v-for="metric in metrics"
                    :key="metric.key"
                    :label="metric.label"
                    :value="metric.value"
                    :icon="metric.icon"
                    :tone="metric.tone"
                />
            </section>

            <section class="pc-card iq-queue">
                <div class="pc-card__head iq-queue__head">
                    <div>
                        <h2 class="pc-card__title">{{ t('ui.cms_inquiries') }}</h2>
                        <p class="pc-card__subtitle">{{ summary }}</p>
                    </div>
                    <span class="pc-badge pc-badge--teal">{{ totalResults }}</span>
                </div>

                <form class="pc-toolbar iq-toolbar" @submit.prevent="applyFilters()">
                    <label class="pc-search iq-search">
                        <span class="sr-only">{{ t('ui.cms_inquiries_page.search') }}</span>
                        <UiIcon name="search" :size="16" />
                        <input
                            v-model="searchTerm"
                            type="search"
                            autocomplete="off"
                            :placeholder="t('ui.cms_inquiries_page.search')"
                            :disabled="processing || page.processing"
                            @input="queueSearch"
                        >
                    </label>

                    <StatusSelect
                        :model-value="statusFilter"
                        :label="t('ui.status')"
                        :options="statusOptions"
                        :placeholder="t('ui.all')"
                        :empty-text="t('ui.no_results')"
                        :searchable="false"
                        :clearable="false"
                        :loading="processing"
                        :disabled="page.processing"
                        @update:model-value="changeStatusFilter"
                    />

                    <PcSelect
                        :model-value="assignedFilter"
                        :label="t('cms_assignment')"
                        :options="assignedOptions"
                        :placeholder="t('ui.all')"
                        :empty-text="t('ui.no_results')"
                        :searchable="false"
                        :clearable="false"
                        :loading="processing"
                        :disabled="page.processing"
                        @update:model-value="changeAssignedFilter"
                    />

                    <button class="pc-btn pc-btn--secondary pc-btn--sm" type="submit" :disabled="processing || page.processing">
                        <UiIcon name="search" :size="14" />
                        {{ t('ui.filter') }}
                    </button>
                    <button
                        v-if="hasFilters"
                        class="pc-btn pc-btn--ghost pc-btn--sm"
                        type="button"
                        :disabled="processing || page.processing"
                        @click="clearFilters"
                    >
                        <UiIcon name="refresh" :size="14" />
                        {{ t('ui.cms_inquiries_page.clear_filters') }}
                    </button>
                </form>

                <div class="iq-tabs" role="tablist" :aria-label="t('cms_type')">
                    <button
                        v-for="group in groups"
                        :key="group.key"
                        class="iq-tab"
                        :class="{ 'is-active': activeType === group.key }"
                        type="button"
                        role="tab"
                        :aria-selected="activeType === group.key ? 'true' : 'false'"
                        aria-controls="iq-panel"
                        @click="activeType = group.key"
                    >
                        <UiIcon :name="group.icon" :size="15" />
                        <span>{{ group.label }}</span>
                        <span class="iq-tabs__count">{{ group.total }}</span>
                    </button>
                </div>

                <div v-if="processing" class="iq-loading" role="status">
                    <span class="pc-btn__spinner" aria-hidden="true" />
                    {{ t('ui.loading') }}
                </div>

                <div id="iq-panel" role="tabpanel">
                    <template v-if="rows.length">
                        <div class="iq-desktop-list">
                            <DataTable :columns="columns">
                                <tr v-for="row in rows" :key="row.id">
                                    <td class="pc-directional" dir="ltr">
                                        <span class="iq-reference">{{ row.reference }}</span>
                                    </td>
                                    <td>
                                        <div class="iq-person">
                                            <span class="iq-avatar" aria-hidden="true">{{ initials(row.senderName || row.label) }}</span>
                                            <span class="iq-person__text">
                                                <span class="pc-table__primary">{{ row.senderName || row.label }}</span>
                                                <span class="pc-table__secondary pc-directional" dir="ltr">{{ row.email }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="pc-table__primary">{{ row.subject || row.label }}</span>
                                        <span v-if="row.context" class="pc-table__secondary">{{ row.context }}</span>
                                    </td>
                                    <td>{{ row.assignedTo || t('cms_unassigned') }}</td>
                                    <td>
                                        <InquiryStatusControl
                                            v-if="can.update"
                                            :inquiry="row"
                                            :statuses="availableStatuses"
                                            :inquiries-path="inquiriesPath"
                                            compact
                                        />
                                        <CmsStatusBadge v-else :status="row.status" :label="statusLabel(row.status)" />
                                    </td>
                                    <td class="pc-table__secondary">{{ formatDate(row.createdAt) }}</td>
                                    <td>
                                        <div class="pc-table__actions">
                                            <Link :href="`${inquiriesPath}/${row.type}/${row.id}`" class="pc-btn pc-btn--secondary pc-btn--sm">
                                                <UiIcon name="eye" :size="14" />
                                                <span class="iq-action-label">{{ t('ui.view') }}</span>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </DataTable>
                        </div>

                        <div class="iq-mobile-list">
                            <article v-for="row in rows" :key="row.id" class="iq-card">
                                <div class="iq-card__head">
                                    <div class="iq-card__identity">
                                        <span class="iq-avatar" aria-hidden="true">{{ initials(row.senderName || row.label) }}</span>
                                        <div>
                                            <strong>{{ row.subject || row.label }}</strong>
                                            <span class="pc-table__secondary pc-directional" dir="ltr">{{ row.reference }}</span>
                                        </div>
                                    </div>
                                    <CmsStatusBadge :status="row.status" :label="statusLabel(row.status)" />
                                </div>

                                <dl class="iq-card__meta">
                                    <div>
                                        <dt>{{ t('cms_name') }}</dt>
                                        <dd>{{ row.senderName || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ t('ui.email') }}</dt>
                                        <dd class="pc-directional" dir="ltr">{{ row.email }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ t('cms_assigned_to') }}</dt>
                                        <dd>{{ row.assignedTo || t('cms_unassigned') }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ t('cms_created_at') }}</dt>
                                        <dd>{{ formatDate(row.createdAt) }}</dd>
                                    </div>
                                </dl>

                                <div class="iq-card__actions">
                                    <InquiryStatusControl
                                        v-if="can.update"
                                        :inquiry="row"
                                        :statuses="availableStatuses"
                                        :inquiries-path="inquiriesPath"
                                    />
                                    <Link :href="`${inquiriesPath}/${row.type}/${row.id}`" class="pc-btn pc-btn--secondary pc-btn--sm">
                                        <UiIcon name="eye" :size="14" />
                                        {{ t('ui.view') }}
                                    </Link>
                                </div>
                            </article>
                        </div>

                        <CmsPagination
                            :pagination="activePagination"
                            :summary="summary"
                            :page-key="activePagination.pageName || `${activeType}Page`"
                        />
                    </template>

                    <EmptyState v-else :title="emptyTitle" :message="emptyMessage" icon="mail">
                        <button v-if="hasFilters" class="pc-btn pc-btn--secondary" type="button" @click="clearFilters">
                            <UiIcon name="refresh" :size="15" />
                            {{ t('ui.cms_inquiries_page.clear_filters') }}
                        </button>
                    </EmptyState>
                </div>
            </section>

            <p class="iq-note">
                <UiIcon name="info" :size="15" />
                <span>{{ t('cms_inquiry_handling_note') }}</span>
            </p>
        </div>
    </CmsDashboardLayout>
</template>

<style scoped>
.iq-page {
    min-inline-size: 0;
}

.iq-metrics {
    gap: 14px;
}

.iq-queue__head {
    padding-block-end: 16px;
}

.iq-toolbar {
    align-items: flex-end;
}

.iq-toolbar .pc-selection {
    flex: 0 1 190px;
    min-inline-size: 170px;
}

.iq-search {
    flex: 1 1 260px;
    max-inline-size: 420px;
}

.iq-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 14px 18px;
    border-block-end: 1px solid var(--pc-border);
    background: var(--pc-surface);
}

.iq-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 13px;
    border: 1px solid var(--pc-border);
    border-radius: 10px;
    background: var(--pc-surface-2);
    color: var(--pc-text-soft);
    font: inherit;
    font-size: 0.78rem;
    cursor: pointer;
    transition: background var(--motion-duration-fast), border-color var(--motion-duration-fast), color var(--motion-duration-fast);
}

.iq-tab:hover {
    border-color: var(--pc-teal-bright);
    color: var(--pc-teal);
}

.iq-tab.is-active {
    border-color: var(--pc-teal);
    background: var(--pc-teal-soft);
    color: var(--pc-teal);
    font-weight: 700;
}

.iq-tabs__count {
    min-inline-size: 22px;
    padding-inline: 6px;
    border-radius: 999px;
    background: var(--pc-surface);
    color: var(--pc-muted);
    font-size: 0.7rem;
    font-variant-numeric: tabular-nums;
}

.iq-tab.is-active .iq-tabs__count {
    background: var(--pc-teal);
    color: var(--pc-on-secondary);
}

.iq-loading {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    color: var(--pc-muted);
    font-size: 0.74rem;
}

.iq-reference {
    color: var(--pc-muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.73rem;
}

.iq-person {
    display: flex;
    align-items: center;
    gap: 10px;
    min-inline-size: 0;
}

.iq-avatar {
    display: grid;
    place-items: center;
    inline-size: 32px;
    block-size: 32px;
    flex: none;
    border-radius: 10px;
    background: var(--pc-teal-soft);
    color: var(--pc-teal);
    font-size: 0.7rem;
    font-weight: 800;
}

.iq-person__text {
    display: grid;
    gap: 2px;
    min-inline-size: 0;
}

.iq-desktop-list {
    display: block;
}

.iq-mobile-list {
    display: none;
}

.iq-card {
    display: grid;
    gap: 12px;
    padding: 16px;
    border-block-end: 1px solid var(--pc-border);
}

.iq-card:last-child {
    border-block-end: 0;
}

.iq-card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.iq-card__identity {
    display: flex;
    align-items: center;
    gap: 10px;
    min-inline-size: 0;
}

.iq-card__identity strong {
    display: block;
    overflow-wrap: anywhere;
    font-size: 0.86rem;
}

.iq-card__meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 14px;
    margin: 0;
}

.iq-card__meta div {
    min-inline-size: 0;
}

.iq-card__meta dt {
    color: var(--pc-muted);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.iq-card__meta dd {
    margin: 2px 0 0;
    overflow-wrap: anywhere;
    font-size: 0.78rem;
}

.iq-card__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.iq-card__actions .iq-status-control {
    flex: 1 1 220px;
}

.iq-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 0;
    color: var(--pc-muted);
    font-size: 0.74rem;
}

.iq-note .pc-icon {
    flex: none;
    margin-block-start: 1px;
}

@media (max-width: 1180px) {
    .iq-action-label {
        display: none;
    }
}

@media (max-width: 900px) {
    .iq-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .iq-toolbar {
        align-items: stretch;
    }

    .iq-search {
        flex-basis: 100%;
        max-inline-size: none;
    }

    .iq-desktop-list {
        display: none;
    }

    .iq-mobile-list {
        display: block;
    }
}

@media (max-width: 560px) {
    .iq-metrics {
        grid-template-columns: minmax(0, 1fr);
    }

    .iq-toolbar .pc-selection {
        flex: 1 1 140px;
        min-inline-size: 0;
    }

    .iq-tabs {
        padding-inline: 12px;
    }

    .iq-card__meta {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
