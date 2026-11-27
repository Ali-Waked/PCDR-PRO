<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Head, Link, router, useHttp } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import Button from '../../../Components/Ui/Button.vue'
import Pagination from '../../../Components/Ui/Pagination.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import MetricCard from '../../../Components/Dashboard/MetricCard.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import StatusSelect from '../../../Components/Selection/StatusSelect.vue'
import { statusTone } from '../../../Support/selectionStatuses'
import { useLocale } from '../../../Composables/useLocale'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'audit_archives_page.title',
        subtitleKey: 'audit_archives_page.subtitle',
        showHeader: false,
    }, () => page),
})

const props = defineProps({
    archives: { type: Object, required: true },
    cleanupRuns: { type: Array, default: () => [] },
    holds: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    retention: { type: Object, default: () => ({}) },
    automation: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
})

const { t, locale, localePath } = useLocale()
const { notify, confirm, finishConfirmation } = useAdminNotifications()

const filters = reactive({
    status: '',
    month: '',
    year: '',
    source: '',
    search: '',
    sort: 'period',
    direction: 'desc',
    per_page: 20,
    ...props.filters,
})

const generate = reactive({
    month: '',
    force: false,
    reason: '',
})

const action = reactive({
    open: false,
    type: '',
    archive: null,
    name: '',
    reason: '',
    toStatus: '',
})

const detailsOpen = ref(false)
const detailsLoading = ref(false)
const detailsError = ref('')
const details = ref(null)
const helpOpen = ref(false)
const selected = ref(null)
const dialogRef = ref(null)
const helpDialogRef = ref(null)
const actionDialogRef = ref(null)
const detailsHttp = useHttp({})

const sortableColumns = ['period', 'created', 'status', 'records', 'size']

const statusLabels = computed(() => ({
    pending: t('audit_archives_page.status_pending'),
    processing: t('audit_archives_page.status_processing'),
    verified: t('audit_archives_page.status_verified'),
    failed: t('audit_archives_page.status_failed'),
    pruned: t('audit_archives_page.status_pruned'),
}))

const statusOptions = computed(() => props.statuses.map((value) => ({
    value,
    label: statusLabels.value[value] ?? value,
})))

const sourceLabels = computed(() => ({
    manual: t('audit_archives_page.source_manual'),
    automatic: t('audit_archives_page.source_automatic'),
}))

const sourceOptions = computed(() => (props.sources.length ? props.sources : ['manual', 'automatic']).map((value) => ({
    value,
    label: sourceLabels.value[value] ?? value,
})))

const sortOptions = computed(() => sortableColumns.map((value) => ({
    value,
    label: t(`audit_archives_page.sort_${value}`),
})))

const scopeLabels = computed(() => ({
    global: t('audit_archives_page.global'),
    subject: t('audit_archives_page.subject'),
    actor: t('audit_archives_page.actor'),
    period: t('audit_archives_page.period_scope'),
}))

const metrics = computed(() => {
    const rows = props.archives.data ?? []

    return [
        { label: t('audit_archives_page.total_archives'), value: props.archives.total ?? rows.length, icon: 'file', tone: 'teal' },
        { label: t('audit_archives_page.verified_archives'), value: rows.filter((item) => item.status === 'verified').length, icon: 'shield', tone: 'navy' },
        { label: t('audit_archives_page.failed_archives'), value: rows.filter((item) => item.status === 'failed').length, icon: 'history', tone: 'sand' },
        { label: t('audit_archives_page.archived_records'), value: rows.reduce((sum, item) => sum + Number(item.record_count ?? 0), 0), icon: 'layers', tone: 'teal' },
    ]
})

const retentionNote = computed(() => t('audit_archives_page.retention_note')
    .replace(':months', String(props.retention.hot_months ?? '—'))
    .replace(':days', String(props.retention.min_archive_age_days ?? '—')))

const automationEnabled = computed(() => Boolean(props.automation?.enabled))
const automationHealth = computed(() => t(
    `audit_archives_page.automation_health_${props.automation?.health ?? 'not_started'}`,
    props.automation?.health ?? '—',
))
const automationCadence = computed(() => `${props.automation?.interval_months ?? '—'} ${t('audit_archives_page.month')}`)
const automationExecution = computed(() => {
    const day = props.automation?.execution_day ?? '—'
    const time = props.automation?.execution_time ?? '—'
    return `${day} · ${time} ${props.automation?.timezone ?? ''}`.trim()
})

const actionRequiresReason = computed(() => {
    if (action.type === 'retry') {
        return true
    }

    return action.type === 'transition' && action.archive?.status === 'failed'
})

const actionTitle = computed(() => {
    if (action.type === 'retry') {
        return t('audit_archives_page.confirm_retry_title')
    }
    if (action.type === 'transition') {
        return t('audit_archives_page.confirm_transition_title')
    }

    return t('audit_archives_page.confirm_edit_title')
})

const actionMessage = computed(() => {
    if (action.type === 'retry') {
        return t('audit_archives_page.confirm_retry_message')
    }
    if (action.type === 'transition') {
        return t('audit_archives_page.confirm_verify_message')
    }

    return t('audit_archives_page.confirm_edit_message')
})

function formatDate(value) {
    if (!value) {
        return '—'
    }

    try {
        return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : 'en-GB', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value))
    } catch {
        return String(value)
    }
}

function formatBytes(value) {
    const bytes = Number(value ?? 0)
    if (!bytes) {
        return '—'
    }

    const units = ['B', 'KB', 'MB', 'GB']
    let size = bytes
    let unit = 0
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024
        unit++
    }

    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
}

function pretty(value) {
    if (value === null || value === undefined || value === '') {
        return '—'
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

function statusClass(value) {
    return `pc-audit-chip--${statusTone(value)}`
}

function statusLabel(value) {
    return statusLabels.value[value] ?? value ?? '—'
}

function sourceLabel(value) {
    return sourceLabels.value[value] ?? value ?? '—'
}

function sortIndicator(key) {
    if (filters.sort !== key) {
        return '↕'
    }

    return filters.direction === 'asc' ? '↑' : '↓'
}

function applyFilters() {
    router.get(localePath('/admin/audit-archives'), { ...filters }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function clearFilters() {
    Object.assign(filters, {
        status: '',
        month: '',
        year: '',
        source: '',
        search: '',
        sort: 'period',
        direction: 'desc',
        per_page: 20,
    })
    applyFilters()
}

function changeSort(key) {
    if (filters.sort === key) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'
    } else {
        filters.sort = key
        filters.direction = 'desc'
    }

    applyFilters()
}

function refreshStatus() {
    router.reload({ preserveScroll: true })
}

async function queueGenerate() {
    const month = String(generate.month ?? '').trim()
    if (generate.force && String(generate.reason ?? '').trim() === '') {
        notify.warning(t('audit_archives_page.reason_required'))
        return
    }

    const subject = month || t('audit_archives_page.month_all')
    const accepted = await confirm({
        title: t('audit_archives_page.confirm_generate_title'),
        message: `${t('audit_archives_page.confirm_generate_message')} ${t('audit_archives_page.month')}: ${subject}`,
        confirmLabel: t('audit_archives_page.confirm_action'),
        cancelLabel: t('audit_archives_page.cancel_action'),
    })

    if (!accepted) {
        return
    }

    router.post(localePath('/admin/audit-archives'), {
        month: month || null,
        force: generate.force,
        reason: String(generate.reason ?? '').trim() || null,
    }, {
        preserveScroll: true,
        onFinish: finishConfirmation,
        onSuccess: () => {
            generate.month = ''
            generate.force = false
            generate.reason = ''
        },
    })
}

function openAction(type, archive, toStatus = '') {
    action.type = type
    action.archive = archive
    action.name = archive?.name ?? ''
    action.reason = ''
    action.toStatus = toStatus
    action.open = true

    nextTick(() => actionDialogRef.value?.focus())
}

function closeAction() {
    action.open = false
    action.archive = null
    action.reason = ''
    action.name = ''
    action.toStatus = ''
}

async function submitAction() {
    const archive = action.archive
    const type = action.type
    if (!archive) {
        return
    }

    const name = String(action.name ?? '').trim()
    const reason = String(action.reason ?? '').trim()
    if (type === 'edit' && name === '') {
        notify.warning(t('audit_archives_page.reason_required'))
        return
    }
    if (actionRequiresReason.value && reason === '') {
        notify.warning(t('audit_archives_page.reason_required'))
        return
    }

    const accepted = await confirm({
        title: actionTitle.value,
        message: actionMessage.value,
        confirmLabel: t('audit_archives_page.confirm_action'),
        cancelLabel: t('audit_archives_page.cancel_action'),
    })

    if (!accepted) {
        return
    }

    const base = localePath(`/admin/audit-archives/${archive.id}`)
    action.open = false

    if (type === 'edit') {
        router.put(base, { name }, { preserveScroll: true, onFinish: finishConfirmation })
        return
    }

    if (type === 'retry') {
        router.post(localePath('/admin/audit-archives'), {
            month: archive.period_key,
            force: true,
            reason,
        }, { preserveScroll: true, onFinish: finishConfirmation })
        return
    }

    router.post(`${base}/transition`, {
        to_status: action.toStatus,
        reason: reason || null,
    }, { preserveScroll: true, onFinish: finishConfirmation })
}

async function verifyArchive(archive) {
    if (archive.status === 'failed') {
        openAction('transition', archive, 'verified')
        return
    }

    const accepted = await confirm({
        title: t('audit_archives_page.confirm_verify_title'),
        message: t('audit_archives_page.confirm_verify_message'),
        confirmLabel: t('audit_archives_page.confirm_action'),
        cancelLabel: t('audit_archives_page.cancel_action'),
    })

    if (!accepted) {
        return
    }

    router.post(localePath(`/admin/audit-archives/${archive.id}/verify`), {}, {
        preserveScroll: true,
        onFinish: finishConfirmation,
    })
}

async function openDetails(archive) {
    selected.value = archive
    detailsOpen.value = true
    detailsLoading.value = true
    detailsError.value = ''
    details.value = null

    try {
        const payload = await detailsHttp.get(localePath(`/admin/audit-archives/${archive.id}`))
        details.value = payload.archive
        transitions.value = payload.transitions ?? []
        await nextTick()
        dialogRef.value?.focus()
    } catch {
        detailsError.value = t('audit_archives_page.no_details')
    } finally {
        detailsLoading.value = false
    }
}

const transitions = ref([])

function closeDetails() {
    detailsOpen.value = false
    details.value = null
    transitions.value = []
}

function openHelp() {
    helpOpen.value = true
    nextTick(() => helpDialogRef.value?.focus())
}

function handleKeydown(event) {
    if (event.key !== 'Escape') {
        return
    }

    if (helpOpen.value) {
        helpOpen.value = false
    } else if (action.open) {
        closeAction()
    } else if (detailsOpen.value) {
        closeDetails()
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <div class="pc-stack pc-audit-page pcdr-enter">
        <Head :title="t('audit_archives_page.title')" />

        <header class="pc-page-head">
            <div class="pc-page-head__copy">
                <span class="pc-audit-eyebrow">{{ t('audit_archives_page.append_only_note') }}</span>
                <h1>{{ t('audit_archives_page.title') }}</h1>
                <p>{{ t('audit_archives_page.subtitle') }}</p>
            </div>
            <div class="pc-page-actions pc-audit-actions">
                <Button variant="secondary" @click="openHelp">
                    <UiIcon name="book" :size="16" />
                    {{ t('audit_archives_page.help_button') }}
                </Button>
                <Button variant="ghost" @click="refreshStatus">
                    <UiIcon name="refresh" :size="16" />
                    {{ t('audit_archives_page.refresh') }}
                </Button>
                <Link :href="localePath('/admin/audit-logs')" class="pc-btn pc-btn--secondary">
                    {{ t('audit_page.title') }}
                </Link>
            </div>
        </header>

        <div class="pc-workflow-alert pc-workflow-alert--info" role="note">
            <div class="pc-workflow-alert__body">
                <span class="pc-workflow-alert__icon" aria-hidden="true">
                    <UiIcon name="info" :size="18" />
                </span>
                <div class="pc-workflow-alert__copy">
                    <strong class="pc-workflow-alert__title">{{ t('audit_archives_page.info_title') }}</strong>
                    <p class="pc-workflow-alert__message">{{ t('audit_archives_page.info_message') }}</p>
                </div>
            </div>
            <div class="pc-workflow-alert__actions">
                <Button variant="secondary" @click="openHelp">
                    {{ t('audit_archives_page.help_button') }}
                </Button>
            </div>
        </div>

        <Card v-if="can.manage">
            <h2 class="pc-audit-section-title">{{ t('audit_archives_page.generate') }}</h2>
            <p class="pc-audit-section-note">{{ t('audit_archives_page.help_queue') }}</p>
            <form class="pc-form-grid pc-audit-archive-form" @submit.prevent="queueGenerate">
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.month') }}</span>
                    <input v-model="generate.month" class="pcdr-input" type="month" name="month" placeholder="YYYY-MM">
                </label>
                <label class="pc-audit-check">
                    <input v-model="generate.force" type="checkbox">
                    <span>{{ t('audit_archives_page.retry') }}</span>
                </label>
                <label v-if="generate.force" class="pc-field pc-field--full">
                    <span class="pcdr-label">{{ t('audit_archives_page.reason') }}</span>
                    <input v-model="generate.reason" class="pcdr-input" type="text" maxlength="500" name="reason">
                </label>
                <div class="pc-field pc-field--action">
                    <Button type="submit" variant="primary">
                        <UiIcon name="clock" :size="16" />
                        {{ t('audit_archives_page.generate') }}
                    </Button>
                </div>
            </form>
        </Card>

        <Card>
            <div class="pc-audit-filter-head">
                <h2 class="pc-audit-section-title">{{ t('audit_archives_page.automation_title') }}</h2>
                <span class="pc-badge pc-badge--small" :class="automationEnabled ? 'pc-badge--green' : 'pc-badge--slate'">
                    {{ automationEnabled ? t('audit_archives_page.automation_enabled') : t('audit_archives_page.automation_disabled') }}
                </span>
                <Link :href="localePath('/cms/settings')" class="pc-btn pc-btn--ghost pc-audit-automation__link">
                    <UiIcon name="settings" :size="15" />
                    {{ t('audit_archives_page.automation_settings') }}
                </Link>
            </div>
            <div class="pc-audit-automation__grid">
                <div>
                    <dt>{{ t('audit_archives_page.automation_health') }}</dt>
                    <dd>{{ automationHealth }}</dd>
                </div>
                <div>
                    <dt>{{ t('audit_archives_page.automation_cadence') }}</dt>
                    <dd>{{ automationCadence }}</dd>
                </div>
                <div>
                    <dt>{{ t('audit_archives_page.automation_execution') }}</dt>
                    <dd>{{ automationExecution }}</dd>
                </div>
                <div>
                    <dt>{{ t('audit_archives_page.automation_next') }}</dt>
                    <dd>{{ formatDate(automation.next_expected_at) }}</dd>
                </div>
                <div>
                    <dt>{{ t('audit_archives_page.automation_last_success') }}</dt>
                    <dd>{{ automation.last_successful_archive ?? '—' }} {{ formatDate(automation.last_successful_at) }}</dd>
                </div>
                <div>
                    <dt>{{ t('audit_archives_page.automation_last_check') }}</dt>
                    <dd>{{ formatDate(automation.last_execution_at) }}</dd>
                </div>
            </div>
            <p v-if="automation.last_failure" class="pc-audit-automation__failure">
                {{ t('audit_archives_page.automation_last_failure') }}: {{ automation.last_failure }}
            </p>
        </Card>

        <div class="pc-metric-grid pc-audit-metrics">
            <MetricCard
                v-for="metric in metrics"
                :key="metric.label"
                :label="metric.label"
                :value="metric.value"
                :icon="metric.icon"
                :tone="metric.tone"
            />
        </div>

        <Card>
            <div class="pc-audit-filter-head">
                <h2 class="pc-audit-section-title">{{ t('audit_archives_page.title') }}</h2>
                <Button variant="ghost" @click="refreshStatus">
                    <UiIcon name="refresh" :size="15" />
                    {{ t('audit_archives_page.refresh') }}
                </Button>
            </div>

            <form class="pc-audit-filter-form pc-form-grid" @submit.prevent="applyFilters">
                <label class="pc-field pc-field--full">
                    <span class="pcdr-label">{{ t('audit_archives_page.search') }}</span>
                    <input
                        v-model="filters.search"
                        class="pcdr-input"
                        type="search"
                        name="search"
                        :placeholder="t('audit_archives_page.search_placeholder')"
                    >
                </label>
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.status') }}</span>
                    <StatusSelect
                        v-model="filters.status"
                        name="status"
                        :label="t('audit_archives_page.status')"
                        :options="statusOptions"
                        :placeholder="t('audit_archives_page.status_all')"
                        clearable
                    />
                </label>
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.source') }}</span>
                    <select v-model="filters.source" class="pcdr-input" name="source">
                        <option value="">{{ t('audit_archives_page.source_all') }}</option>
                        <option v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.year') }}</span>
                    <select v-model="filters.year" class="pcdr-input" name="year">
                        <option value="">{{ t('audit_archives_page.year_all') }}</option>
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </label>
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.month') }}</span>
                    <input v-model="filters.month" class="pcdr-input" type="month" name="filter_month">
                </label>
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.sort') }}</span>
                    <select v-model="filters.sort" class="pcdr-input" name="sort">
                        <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.direction') }}</span>
                    <select v-model="filters.direction" class="pcdr-input" name="direction">
                        <option value="desc">{{ t('audit_archives_page.direction_desc') }}</option>
                        <option value="asc">{{ t('audit_archives_page.direction_asc') }}</option>
                    </select>
                </label>
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.per_page') }}</span>
                    <select v-model.number="filters.per_page" class="pcdr-input" name="per_page">
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                    </select>
                </label>
                <div class="pc-field pc-field--action pc-audit-filter-actions">
                    <Button type="submit" variant="primary">{{ t('audit_archives_page.apply_filters') }}</Button>
                    <Button type="button" variant="ghost" @click="clearFilters">{{ t('audit_archives_page.clear_filters') }}</Button>
                </div>
            </form>

            <div class="pc-audit-table pc-audit-table--desktop">
                <table class="pc-table pc-audit-table__grid pc-audit-table--archives">
                    <thead>
                        <tr>
                            <th>
                                <button type="button" class="pc-audit-sort" @click="changeSort('period')">
                                    {{ t('audit_archives_page.period') }} <span aria-hidden="true">{{ sortIndicator('period') }}</span>
                                </button>
                            </th>
                            <th>{{ t('audit_archives_page.name') }}</th>
                            <th>
                                <button type="button" class="pc-audit-sort" @click="changeSort('status')">
                                    {{ t('audit_archives_page.status') }} <span aria-hidden="true">{{ sortIndicator('status') }}</span>
                                </button>
                            </th>
                            <th>
                                <button type="button" class="pc-audit-sort" @click="changeSort('records')">
                                    {{ t('audit_archives_page.records') }} <span aria-hidden="true">{{ sortIndicator('records') }}</span>
                                </button>
                            </th>
                            <th>{{ t('audit_archives_page.queued_at') }}</th>
                            <th>{{ t('audit_archives_page.started_at') }}</th>
                            <th>{{ t('audit_archives_page.finished_at') }}</th>
                            <th>{{ t('audit_archives_page.file') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="archive in archives.data ?? []" :key="archive.id">
                            <td>
                                <div class="pc-audit-primary">{{ archive.period_key }}</div>
                                <div class="pc-audit-secondary">{{ formatDate(archive.created_at) }}</div>
                            </td>
                            <td>
                                <div class="pc-audit-primary pc-audit-truncate" dir="auto">{{ archive.name }}</div>
                                <div class="pc-audit-secondary">{{ sourceLabel(archive.source) }}</div>
                            </td>
                            <td>
                                <span class="pc-audit-chip" :class="statusClass(archive.status)">{{ statusLabel(archive.status) }}</span>
                                <div class="pc-audit-secondary">{{ archive.attempts }} {{ t('audit_archives_page.attempts') }}</div>
                                <div v-if="archive.status === 'processing'" class="pc-audit-secondary">{{ t('audit_archives_page.processing_hint') }}</div>
                            </td>
                            <td>
                                <div>{{ archive.record_count }}</div>
                                <div class="pc-audit-secondary">
                                    <button type="button" class="pc-audit-sort" @click="changeSort('size')">
                                        {{ formatBytes(archive.file_size) }} <span aria-hidden="true">{{ sortIndicator('size') }}</span>
                                    </button>
                                </div>
                            </td>
                            <td class="pc-audit-secondary">{{ formatDate(archive.queued_at) }}</td>
                            <td class="pc-audit-secondary">{{ formatDate(archive.started_at) }}</td>
                            <td>
                                <div class="pc-audit-secondary">{{ formatDate(archive.finished_at) }}</div>
                                <div v-if="archive.failed_at" class="pc-audit-note--error">{{ formatDate(archive.failed_at) }}</div>
                                <div v-else class="pc-audit-secondary">{{ formatDate(archive.verified_at) }}</div>
                            </td>
                            <td>
                                <div class="pc-audit-row-actions">
                                    <button type="button" class="pc-btn pc-btn--ghost pc-audit-row-action" @click="openDetails(archive)">
                                        {{ t('audit_archives_page.view_details') }}
                                    </button>
                                    <a
                                        v-if="can.download && archive.status === 'verified'"
                                        :href="localePath(`/admin/audit-archives/${archive.id}/download`)"
                                        class="pc-btn pc-btn--secondary pc-audit-row-action"
                                    >
                                        {{ t('audit_archives_page.download') }}
                                    </a>
                                    <button
                                        v-if="can.manage && archive.status !== 'pruned'"
                                        type="button"
                                        class="pc-btn pc-btn--ghost pc-audit-row-action"
                                        @click="openAction('edit', archive)"
                                    >
                                        {{ t('audit_archives_page.edit') }}
                                    </button>
                                    <button
                                        v-if="can.manage && archive.status !== 'pruned'"
                                        type="button"
                                        class="pc-btn pc-btn--ghost pc-audit-row-action"
                                        @click="verifyArchive(archive)"
                                    >
                                        {{ t('audit_archives_page.verify') }}
                                    </button>
                                    <button
                                        v-if="can.manage && archive.status === 'failed'"
                                        type="button"
                                        class="pc-btn pc-btn--secondary pc-audit-row-action"
                                        @click="openAction('retry', archive)"
                                    >
                                        {{ t('audit_archives_page.retry') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!archives.data?.length">
                            <td colspan="8">
                                <EmptyState :title="t('audit_archives_page.no_archives')" icon="folder" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pc-audit-cards pc-audit-table--mobile">
                <article v-for="archive in archives.data ?? []" :key="archive.id" class="pc-audit-card">
                    <div class="pc-audit-card__top">
                        <span class="pc-audit-primary">{{ archive.period_key }}</span>
                        <span class="pc-audit-chip" :class="statusClass(archive.status)">{{ statusLabel(archive.status) }}</span>
                    </div>
                    <p class="pc-audit-secondary pc-audit-truncate" dir="auto">{{ archive.name }}</p>
                    <dl class="pc-audit-card__list">
                        <div>
                            <dt>{{ t('audit_archives_page.source') }}</dt>
                            <dd>{{ sourceLabel(archive.source) }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.records') }}</dt>
                            <dd>{{ archive.record_count }} · {{ formatBytes(archive.file_size) }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.attempts') }}</dt>
                            <dd>{{ archive.attempts }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.queued_at') }}</dt>
                            <dd>{{ formatDate(archive.queued_at) }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.started_at') }}</dt>
                            <dd>{{ formatDate(archive.started_at) }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.finished_at') }}</dt>
                            <dd>{{ formatDate(archive.finished_at) }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.verified_at') }}</dt>
                            <dd>{{ formatDate(archive.verified_at) }}</dd>
                        </div>
                        <div v-if="archive.failed_at">
                            <dt>{{ t('audit_archives_page.failed_at') }}</dt>
                            <dd>{{ formatDate(archive.failed_at) }}</dd>
                        </div>
                    </dl>
                    <div class="pc-audit-card__actions">
                        <button type="button" class="pc-btn pc-btn--secondary" @click="openDetails(archive)">
                            {{ t('audit_archives_page.view_details') }}
                        </button>
                        <a
                            v-if="can.download && archive.status === 'verified'"
                            :href="localePath(`/admin/audit-archives/${archive.id}/download`)"
                            class="pc-btn pc-btn--primary"
                        >
                            {{ t('audit_archives_page.download') }}
                        </a>
                        <button
                            v-if="can.manage && archive.status !== 'pruned'"
                            type="button"
                            class="pc-btn pc-btn--ghost"
                            @click="openAction('edit', archive)"
                        >
                            {{ t('audit_archives_page.edit') }}
                        </button>
                        <button
                            v-if="can.manage && archive.status !== 'pruned'"
                            type="button"
                            class="pc-btn pc-btn--ghost"
                            @click="verifyArchive(archive)"
                        >
                            {{ t('audit_archives_page.verify') }}
                        </button>
                        <button
                            v-if="can.manage && archive.status === 'failed'"
                            type="button"
                            class="pc-btn pc-btn--secondary"
                            @click="openAction('retry', archive)"
                        >
                            {{ t('audit_archives_page.retry') }}
                        </button>
                    </div>
                </article>
                <EmptyState v-if="!archives.data?.length" :title="t('audit_archives_page.no_archives')" icon="folder" />
            </div>

            <Pagination :data="archives" />
        </Card>

        <div class="pc-audit-support-grid">
            <Card>
                <h2 class="pc-audit-section-title">{{ t('audit_archives_page.cleanup_reports') }}</h2>
                <div v-if="!cleanupRuns.length" class="pc-audit-note">{{ t('audit_archives_page.no_reports') }}</div>
                <div v-else class="pc-audit-report-list">
                    <article v-for="run in cleanupRuns" :key="run.id" class="pc-audit-report">
                        <div class="pc-audit-card__top">
                            <span class="pc-audit-primary">#{{ run.id }} · {{ run.archive_period ?? '—' }}</span>
                            <span class="pc-audit-chip" :class="statusClass(run.status)">{{ statusLabel(run.status) }}</span>
                        </div>
                        <p class="pc-audit-secondary">{{ run.mode }} · {{ formatDate(run.finished_at) }}</p>
                        <dl class="pc-audit-card__list">
                            <div>
                                <dt>{{ t('audit_archives_page.eligible') }}</dt>
                                <dd>{{ run.eligible_count }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.deleted') }}</dt>
                                <dd>{{ run.deleted_count }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.held') }}</dt>
                                <dd>{{ run.skipped_hold_count }}</dd>
                            </div>
                        </dl>
                        <p v-if="run.error" class="pc-audit-note pc-audit-note--error">{{ run.error }}</p>
                    </article>
                </div>
            </Card>

            <Card>
                <h2 class="pc-audit-section-title">{{ t('audit_archives_page.legal_holds') }}</h2>
                <div class="pc-audit-note">{{ retention.enabled ? t('audit_archives_page.retention_enabled') : t('audit_archives_page.retention_disabled') }}</div>
                <p v-if="retention.enabled" class="pc-audit-secondary">{{ retentionNote }}</p>
                <div v-if="!holds.length" class="pc-audit-note">{{ t('audit_archives_page.no_holds') }}</div>
                <div v-else class="pc-audit-report-list">
                    <article v-for="hold in holds" :key="hold.id" class="pc-audit-report">
                        <div class="pc-audit-card__top">
                            <span class="pc-audit-primary" dir="auto">{{ hold.label }}</span>
                            <span class="pc-audit-chip pc-audit-chip--teal">{{ scopeLabels[hold.scope_type] ?? hold.scope_type }}</span>
                        </div>
                        <p class="pc-audit-secondary" dir="auto">{{ hold.reason }}</p>
                        <dl class="pc-audit-card__list">
                            <div>
                                <dt>{{ t('audit_archives_page.expires_at') }}</dt>
                                <dd>{{ formatDate(hold.expires_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.created_by') }}</dt>
                                <dd>{{ hold.requested_by ?? '—' }}</dd>
                            </div>
                        </dl>
                    </article>
                </div>
            </Card>
        </div>

        <div v-if="detailsOpen" class="pc-modal-backdrop" @click.self="closeDetails">
            <div ref="dialogRef" class="pc-modal pc-audit-dialog" role="dialog" aria-modal="true" tabindex="-1" :aria-label="t('audit_archives_page.view_details')">
                <header class="pc-modal__head">
                    <div>
                        <h2 class="pc-modal__title">{{ details?.period_key ?? selected?.period_key }}</h2>
                        <p class="pc-audit-secondary">{{ details?.name ?? selected?.name }}</p>
                    </div>
                    <button type="button" class="pc-btn pc-btn--ghost" @click="closeDetails">{{ t('audit_archives_page.close') }}</button>
                </header>

                <div class="pc-modal__body">
                    <p v-if="detailsLoading" class="pc-audit-note">{{ t('audit_page.loading') }}</p>
                    <p v-else-if="detailsError" class="pc-audit-note pc-audit-note--error">{{ detailsError }}</p>

                    <template v-if="details">
                        <dl class="pc-audit-detail-grid">
                            <div>
                                <dt>{{ t('audit_archives_page.status') }}</dt>
                                <dd>{{ statusLabel(details.status) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.source') }}</dt>
                                <dd>{{ sourceLabel(details.source) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.records') }}</dt>
                                <dd>{{ details.record_count }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.size') }}</dt>
                                <dd>{{ formatBytes(details.file_size) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.attempts') }}</dt>
                                <dd>{{ details.attempts }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.period_start') }}</dt>
                                <dd>{{ formatDate(details.period_start) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.period_end_inclusive') }}</dt>
                                <dd>{{ formatDate(details.period_end_inclusive) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.cutoff_at') }}</dt>
                                <dd>{{ formatDate(details.cutoff_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.queued_at') }}</dt>
                                <dd>{{ formatDate(details.queued_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.started_at') }}</dt>
                                <dd>{{ formatDate(details.started_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.finished_at') }}</dt>
                                <dd>{{ formatDate(details.finished_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.failed_at') }}</dt>
                                <dd>{{ formatDate(details.failed_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.initiated_by') }}</dt>
                                <dd>{{ details.initiated_by ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.schema_version') }}</dt>
                                <dd>{{ details.manifest?.schema_version ?? details.schema_version ?? '—' }}</dd>
                            </div>
                        </dl>

                        <p v-if="details.error" class="pc-audit-note pc-audit-note--error">{{ details.error }}</p>

                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.transitions') }}</h3>
                        <ul v-if="transitions.length" class="pc-audit-transition-list">
                            <li v-for="transition in transitions" :key="transition.id">
                                <span>{{ formatDate(transition.created_at) }}</span>
                                <span class="pc-badge pc-badge--small">{{ statusLabel(transition.from_status) }} → {{ statusLabel(transition.to_status) }}</span>
                                <span>{{ transition.actor }}</span>
                                <span v-if="transition.reason" dir="auto">{{ transition.reason }}</span>
                            </li>
                        </ul>
                        <p v-else class="pc-audit-note">{{ t('audit_archives_page.no_transitions') }}</p>

                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.checksums') }}</h3>
                        <pre class="pc-audit-pre" dir="auto">{{ pretty(details.checksums) }}</pre>

                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.manifest') }}</h3>
                        <pre class="pc-audit-pre" dir="auto">{{ pretty(details.manifest) }}</pre>
                    </template>
                </div>
            </div>
        </div>

        <div v-if="action.open" class="pc-modal-backdrop" @click.self="closeAction">
            <div ref="actionDialogRef" class="pc-modal pc-audit-dialog" role="dialog" aria-modal="true" tabindex="-1" :aria-label="actionTitle">
                <form @submit.prevent="submitAction">
                    <header class="pc-modal__head">
                        <h2 class="pc-modal__title">{{ actionTitle }}</h2>
                        <button type="button" class="pc-btn pc-btn--ghost" @click="closeAction">{{ t('audit_archives_page.cancel') }}</button>
                    </header>
                    <div class="pc-modal__body">
                        <p class="pc-audit-note">{{ actionMessage }}</p>
                        <p class="pc-audit-secondary">{{ action.archive?.period_key }} · {{ action.archive?.name }}</p>

                        <label v-if="action.type === 'edit'" class="pc-field">
                            <span class="pcdr-label">{{ t('audit_archives_page.name') }}</span>
                            <input v-model="action.name" class="pcdr-input" type="text" maxlength="120" required>
                        </label>
                        <label v-if="actionRequiresReason" class="pc-field">
                            <span class="pcdr-label">{{ t('audit_archives_page.reason') }}</span>
                            <input v-model="action.reason" class="pcdr-input" type="text" maxlength="500" required>
                        </label>
                    </div>
                    <footer class="pc-modal__foot pc-audit-filter-actions">
                        <Button type="button" variant="ghost" @click="closeAction">{{ t('audit_archives_page.cancel') }}</Button>
                        <Button type="submit" variant="primary">{{ t('audit_archives_page.confirm_action') }}</Button>
                    </footer>
                </form>
            </div>
        </div>

        <div v-if="helpOpen" class="pc-modal-backdrop" @click.self="helpOpen = false">
            <div ref="helpDialogRef" class="pc-modal pc-audit-dialog" role="dialog" aria-modal="true" tabindex="-1" :aria-label="t('audit_archives_page.help_title')">
                <header class="pc-modal__head">
                    <h2 class="pc-modal__title">{{ t('audit_archives_page.help_title') }}</h2>
                    <button type="button" class="pc-btn pc-btn--ghost" @click="helpOpen = false">{{ t('audit_archives_page.help_close') }}</button>
                </header>
                <div class="pc-modal__body pc-audit-help">
                    <p>{{ t('audit_archives_page.help_intro') }}</p>
                    <section>
                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.info_title') }}</h3>
                        <p>{{ t('audit_archives_page.info_message') }}</p>
                    </section>
                    <section>
                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.status') }}</h3>
                        <p>{{ t('audit_archives_page.help_lifecycle') }}</p>
                    </section>
                    <section>
                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.filters') }}</h3>
                        <p>{{ t('audit_archives_page.help_filters') }}</p>
                    </section>
                    <section>
                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.queued_at') }}</h3>
                        <p>{{ t('audit_archives_page.help_queue') }}</p>
                    </section>
                    <section>
                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.download') }}</h3>
                        <p>{{ t('audit_archives_page.help_download') }}</p>
                    </section>
                    <section>
                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.automation_title') }}</h3>
                        <p>{{ t('audit_archives_page.help_automation') }}</p>
                        <Link :href="localePath('/cms/settings')" class="pc-btn pc-btn--secondary">
                            {{ t('audit_archives_page.automation_settings') }}
                        </Link>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
