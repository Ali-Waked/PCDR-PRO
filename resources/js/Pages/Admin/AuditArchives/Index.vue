<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
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
    statusFilter: { type: String, default: '' },
    statuses: { type: Array, default: () => [] },
    retention: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
})

const { t, locale, localePath } = useLocale()

const month = ref('')
const force = ref(false)
const queue = ref(false)
const status = ref(props.statusFilter)
const detailsOpen = ref(false)
const detailsLoading = ref(false)
const detailsError = ref('')
const details = ref(null)
const selected = ref(null)
const dialogRef = ref(null)
const detailsHttp = useHttp({})

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

function createArchive() {
    router.post(localePath('/admin/audit-archives'), {
        month: month.value,
        force: force.value,
        queue: queue.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            month.value = ''
            force.value = false
            queue.value = false
        },
    })
}

function applyStatus() {
    router.get(localePath('/admin/audit-archives'), status.value ? { status: status.value } : {}, {
        preserveState: true,
        preserveScroll: true,
    })
}

function verifyArchive(archive) {
    router.post(localePath(`/admin/audit-archives/${archive.id}/verify`), {}, {
        preserveScroll: true,
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
        await nextTick()
        dialogRef.value?.focus()
    } catch {
        detailsError.value = t('audit_archives_page.no_details')
    } finally {
        detailsLoading.value = false
    }
}

function closeDetails() {
    detailsOpen.value = false
    details.value = null
}

function handleKeydown(event) {
    if (event.key === 'Escape' && detailsOpen.value) {
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
            <div class="pc-page-actions">
                <Link :href="localePath('/admin/audit-logs')" class="pc-btn pc-btn--secondary">
                    {{ t('audit_page.title') }}
                </Link>
            </div>
        </header>

        <div class="pc-workflow-alert" :class="retention.enabled ? 'is-verified' : 'is-neutral'" role="status">
            <div class="pc-workflow-alert__body">
                <span class="pc-workflow-alert__icon" aria-hidden="true">
                    <UiIcon name="shield" :size="18" />
                </span>
                <div class="pc-workflow-alert__copy">
                    <strong class="pc-workflow-alert__title">{{ t('audit_archives_page.retention_title') }}</strong>
                    <p class="pc-workflow-alert__message">
                        {{ retention.enabled ? t('audit_archives_page.retention_enabled') : t('audit_archives_page.retention_disabled') }}
                    </p>
                    <p v-if="retention.enabled" class="pc-workflow-alert__reason pc-audit-secondary">{{ retentionNote }}</p>
                </div>
            </div>
        </div>

        <Card v-if="can.manage">
            <h2 class="pc-audit-section-title">{{ t('audit_archives_page.generate') }}</h2>
            <form class="pc-form-grid pc-audit-archive-form" @submit.prevent="createArchive">
                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_archives_page.month') }}</span>
                    <input v-model="month" class="pcdr-input" type="month" name="month" placeholder="YYYY-MM">
                </label>
                <label class="pc-audit-check">
                    <input v-model="force" type="checkbox">
                    <span>{{ t('audit_archives_page.retry') }}</span>
                </label>
                <label class="pc-audit-check">
                    <input v-model="queue" type="checkbox">
                    <span>{{ t('audit_archives_page.queue') }}</span>
                </label>
                <div class="pc-field pc-field--action">
                    <Button type="submit" variant="primary">{{ t('audit_archives_page.generate') }}</Button>
                </div>
            </form>
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
                <StatusSelect
                    v-model="status"
                    name="status"
                    :label="t('audit_archives_page.status')"
                    :options="statusOptions"
                    :placeholder="t('audit_archives_page.status_all')"
                    :searchable="statuses.length > 6"
                    clearable
                    @change="applyStatus"
                />
            </div>

            <div class="pc-audit-table pc-audit-table--desktop">
                <table class="pc-table pc-audit-table__grid pc-audit-table--archives">
                    <thead>
                        <tr>
                            <th>{{ t('audit_archives_page.period') }}</th>
                            <th>{{ t('audit_archives_page.status') }}</th>
                            <th>{{ t('audit_archives_page.records') }}</th>
                            <th>{{ t('audit_archives_page.size') }}</th>
                            <th>{{ t('audit_archives_page.verified_at') }}</th>
                            <th>{{ t('audit_archives_page.file') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="archive in archives.data ?? []" :key="archive.id">
                            <td>
                                <div class="pc-audit-primary">{{ archive.period_key }}</div>
                                <div class="pc-audit-secondary">{{ archive.attempts }} {{ t('audit_archives_page.attempts') }}</div>
                            </td>
                            <td>
                                <span class="pc-audit-chip" :class="statusClass(archive.status)">
                                    {{ statusLabels[archive.status] ?? archive.status }}
                                </span>
                            </td>
                            <td>{{ archive.record_count }}</td>
                            <td>{{ formatBytes(archive.file_size) }}</td>
                            <td class="pc-audit-secondary">{{ formatDate(archive.verified_at) }}</td>
                            <td>
                                <div class="pc-audit-row-actions">
                                    <button type="button" class="pc-btn pc-btn--ghost pc-audit-row-action" @click="openDetails(archive)">
                                        {{ t('audit_archives_page.view_details') }}
                                    </button>
                                    <a
                                        v-if="can.download"
                                        :href="localePath(`/admin/audit-archives/${archive.id}/download`)"
                                        class="pc-btn pc-btn--secondary pc-audit-row-action"
                                    >
                                        {{ t('audit_archives_page.download') }}
                                    </a>
                                    <button
                                        v-if="can.manage && archive.status !== 'pruned'"
                                        type="button"
                                        class="pc-btn pc-btn--ghost pc-audit-row-action"
                                        @click="verifyArchive(archive)"
                                    >
                                        {{ t('audit_archives_page.verify') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!archives.data?.length">
                            <td colspan="6">
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
                        <span class="pc-audit-chip" :class="statusClass(archive.status)">
                            {{ statusLabels[archive.status] ?? archive.status }}
                        </span>
                    </div>
                    <dl class="pc-audit-card__list">
                        <div>
                            <dt>{{ t('audit_archives_page.records') }}</dt>
                            <dd>{{ archive.record_count }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.size') }}</dt>
                            <dd>{{ formatBytes(archive.file_size) }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_archives_page.verified_at') }}</dt>
                            <dd>{{ formatDate(archive.verified_at) }}</dd>
                        </div>
                    </dl>
                    <div class="pc-audit-card__actions">
                        <button type="button" class="pc-btn pc-btn--secondary" @click="openDetails(archive)">
                            {{ t('audit_archives_page.view_details') }}
                        </button>
                        <a
                            v-if="can.download"
                            :href="localePath(`/admin/audit-archives/${archive.id}/download`)"
                            class="pc-btn pc-btn--primary"
                        >
                            {{ t('audit_archives_page.download') }}
                        </a>
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
                            <span class="pc-audit-chip" :class="statusClass(run.status)">{{ run.status }}</span>
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
                                <dd>{{ hold.id }}</dd>
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
                                <dd>{{ statusLabels[details.status] ?? details.status }}</dd>
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
                                <dt>{{ t('audit_archives_page.period_start') }}</dt>
                                <dd>{{ formatDate(details.period_start) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.period_end') }}</dt>
                                <dd>{{ formatDate(details.period_end) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.cutoff_at') }}</dt>
                                <dd>{{ formatDate(details.cutoff_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.schema_version') }}</dt>
                                <dd>{{ details.manifest?.schema_version }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_archives_page.integrity') }}</dt>
                                <dd>{{ details.verified_at ? statusLabels.verified : '—' }}</dd>
                            </div>
                        </dl>

                        <p v-if="details.error" class="pc-audit-note pc-audit-note--error">{{ details.error }}</p>

                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.checksums') }}</h3>
                        <pre class="pc-audit-pre" dir="auto">{{ pretty(details.checksums) }}</pre>

                        <h3 class="pc-audit-section-title">{{ t('audit_archives_page.manifest') }}</h3>
                        <pre class="pc-audit-pre" dir="auto">{{ pretty(details.manifest) }}</pre>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
