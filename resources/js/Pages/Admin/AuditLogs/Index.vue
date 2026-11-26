<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { Head, Link, router, useHttp } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import Button from '../../../Components/Ui/Button.vue'
import Pagination from '../../../Components/Ui/Pagination.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import MetricCard from '../../../Components/Dashboard/MetricCard.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import { statusTone } from '../../../Support/selectionStatuses'
import { useLocale } from '../../../Composables/useLocale'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'audit_page.title',
        subtitleKey: 'audit_page.subtitle',
        showHeader: false,
    }, () => page),
})

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
})

const { t, locale, localePath } = useLocale()

const defaults = {
    search: '',
    actor: '',
    action: '',
    subject: '',
    from: '',
    to: '',
    event_class: '',
    severity: '',
}

const form = reactive({ ...defaults, ...props.filters })
const perPage = ref(props.logs.per_page ?? 25)
const filterPanelOpen = ref(false)
const detailsOpen = ref(false)
const detailsLoading = ref(false)
const detailsError = ref('')
const details = ref(null)
const selected = ref(null)
const dialogRef = ref(null)
const detailsHttp = useHttp({})

const severityLabels = computed(() => ({
    critical: t('audit_page.critical'),
    warning: t('audit_page.warning'),
    info: t('audit_page.info'),
}))

const classLabels = computed(() => ({
    security: t('audit_page.security'),
    content: t('audit_page.content'),
    people: t('audit_page.people'),
    system: t('audit_page.system'),
}))

const eventClassOptions = computed(() => (props.meta.eventClasses ?? []).map((value) => ({
    value,
    label: classLabels.value[value] ?? value,
})))
const severityOptions = computed(() => (props.meta.severities ?? []).map((value) => ({
    value,
    label: severityLabels.value[value] ?? value,
})))
const actorOptions = computed(() => (props.meta.actors ?? []).map((actor) => ({
    value: actor.id,
    label: actor.name,
})))
const actionOptions = computed(() => (props.meta.actions ?? []).map((value) => ({ value, label: value })))
const subjectOptions = computed(() => (props.meta.subjects ?? []).map((value) => ({ value, label: value })))

const activeChips = computed(() => {
    const labels = {
        search: t('audit_page.search'),
        actor: t('audit_page.actor'),
        action: t('audit_page.action'),
        subject: t('audit_page.subject'),
        from: t('audit_page.from'),
        to: t('audit_page.to'),
        event_class: t('audit_page.event_class'),
        severity: t('audit_page.severity'),
    }

    return Object.entries(labels)
        .filter(([key]) => String(form[key] ?? '') !== '')
        .map(([key, label]) => ({ key, label, value: String(form[key]) }))
})

const queryString = computed(() => {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(form)) {
        if (value !== '' && value !== null && value !== undefined) {
            params.set(key, String(value))
        }
    }

    return params.toString()
})

const exportUrl = (format) => {
    const query = queryString.value
    const suffix = query ? `&${query}` : ''
    return `${localePath('/admin/audit-logs/export')}?format=${format}${suffix}`
}

const metrics = computed(() => [
    { label: t('audit_page.total_records'), value: props.stats.total ?? 0, hint: t('audit_page.append_only'), icon: 'file', tone: 'teal' },
    { label: t('audit_page.today'), value: props.stats.today ?? 0, hint: '', icon: 'clock', tone: 'navy' },
    { label: t('audit_page.security_events'), value: props.stats.security ?? 0, hint: '', icon: 'shield', tone: 'sand' },
    { label: t('audit_page.deleted'), value: props.stats.deleted ?? 0, hint: '', icon: 'trash', tone: 'teal' },
])

watch(() => props.filters, (value) => {
    Object.assign(form, defaults, value)
}, { deep: true })

function applyFilters() {
    const query = {}
    for (const [key, value] of Object.entries(form)) {
        if (value !== '' && value !== null && value !== undefined) {
            query[key] = value
        }
    }

    router.get(localePath('/admin/audit-logs'), { ...query, per_page: perPage.value }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function clearFilter(key) {
    form[key] = ''
    applyFilters()
}

function resetFilters() {
    Object.assign(form, defaults)
    perPage.value = 25
    applyFilters()
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    const localeCode = locale.value === 'ar' ? 'ar' : 'en-GB'

    try {
        return new Intl.DateTimeFormat(localeCode, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    } catch {
        return String(value)
    }
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

function severityClass(value) {
    return `pc-audit-chip--${statusTone(value)}`
}

function classClass(value) {
    return `pc-audit-chip--${value ?? 'slate'}`
}

async function openDetails(log) {
    selected.value = log
    detailsOpen.value = true
    detailsLoading.value = true
    detailsError.value = ''
    details.value = null

    try {
        const payload = await detailsHttp.get(localePath(`/admin/audit-logs/${log.id}`))
        details.value = payload.log
        await nextTick()
        dialogRef.value?.focus()
    } catch {
        detailsError.value = t('audit_page.details_unavailable')
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
        <Head :title="t('audit_page.title')" />

        <header class="pc-page-head">
            <div class="pc-page-head__copy">
                <span class="pc-audit-eyebrow">{{ t('audit_page.append_only') }}</span>
                <h1>{{ t('audit_page.title') }}</h1>
                <p>{{ t('audit_page.subtitle') }}</p>
            </div>
            <div class="pc-page-actions pc-audit-actions">
                <Link v-if="can.archives" :href="localePath('/admin/audit-archives')" class="pc-btn pc-btn--secondary">
                    {{ t('audit_page.archives') }}
                </Link>
                <a v-if="can.export" :href="exportUrl('csv')" class="pc-btn pc-btn--secondary pc-audit-export">
                    {{ t('audit_page.export_csv') }}
                </a>
                <a v-if="can.export" :href="exportUrl('jsonl')" class="pc-btn pc-btn--primary pc-audit-export">
                    {{ t('audit_page.export_jsonl') }}
                </a>
            </div>
        </header>

        <div class="pc-metric-grid pc-audit-metrics">
            <MetricCard
                v-for="metric in metrics"
                :key="metric.label"
                :label="metric.label"
                :value="metric.value"
                :hint="metric.hint"
                :icon="metric.icon"
                :tone="metric.tone"
            />
        </div>

        <Card>
            <div class="pc-audit-filter-head">
                <div>
                    <h2 class="pc-audit-section-title">{{ t('audit_page.filters') }}</h2>
                    <p v-if="activeChips.length" class="pc-audit-section-note">
                        {{ t('audit_page.filters_active') }}: {{ activeChips.length }}
                    </p>
                </div>
                <button type="button" class="pc-btn pc-btn--ghost pc-audit-filter-toggle" @click="filterPanelOpen = !filterPanelOpen">
                    {{ t('audit_page.filter_panel') }}
                </button>
            </div>

            <div v-if="activeChips.length" class="pc-audit-chips" :aria-label="t('audit_page.filters_active')">
                <button
                    v-for="chip in activeChips"
                    :key="chip.key"
                    type="button"
                    class="pc-audit-chip pc-audit-chip--removable"
                    @click="clearFilter(chip.key)"
                >
                    <span>{{ chip.label }}: {{ chip.value }}</span>
                    <span aria-hidden="true">×</span>
                </button>
            </div>

            <form class="pc-form-grid pc-audit-filter-form" :class="{ 'is-open': filterPanelOpen }" @submit.prevent="applyFilters">
                <label class="pc-field pc-field--full">
                    <span class="pcdr-label">{{ t('audit_page.search') }}</span>
                    <input v-model="form.search" class="pcdr-input" type="search" name="search" autocomplete="off">
                </label>

                <SelectionControl
                    v-model="form.event_class"
                    name="event_class"
                    :label="t('audit_page.event_class')"
                    :options="eventClassOptions"
                    :placeholder="t('audit_page.all_events')"
                    :searchable="eventClassOptions.length > 6"
                    clearable
                />

                <SelectionControl
                    v-model="form.severity"
                    name="severity"
                    :label="t('audit_page.severity')"
                    :options="severityOptions"
                    :placeholder="t('audit_page.all_severities')"
                    :searchable="severityOptions.length > 6"
                    clearable
                />

                <SelectionControl
                    v-model="form.actor"
                    name="actor"
                    :label="t('audit_page.actor')"
                    :options="actorOptions"
                    :placeholder="t('audit_page.all_actors')"
                    :searchable="actorOptions.length > 6"
                    clearable
                />

                <SelectionControl
                    v-model="form.action"
                    name="action"
                    :label="t('audit_page.action')"
                    :options="actionOptions"
                    :placeholder="t('audit_page.all_actions')"
                    :searchable="actionOptions.length > 6"
                    clearable
                />

                <SelectionControl
                    v-model="form.subject"
                    name="subject"
                    :label="t('audit_page.subject')"
                    :options="subjectOptions"
                    :placeholder="t('audit_page.all_subjects')"
                    :searchable="subjectOptions.length > 6"
                    clearable
                />

                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_page.from') }}</span>
                    <input v-model="form.from" class="pcdr-input" type="date" name="from">
                </label>

                <label class="pc-field">
                    <span class="pcdr-label">{{ t('audit_page.to') }}</span>
                    <input v-model="form.to" class="pcdr-input" type="date" name="to">
                </label>

                <div class="pc-field pc-field--action pc-audit-filter-actions">
                    <Button type="submit" variant="primary">{{ t('audit_page.apply') }}</Button>
                    <Button type="button" variant="secondary" @click="resetFilters">{{ t('audit_page.reset') }}</Button>
                </div>
            </form>
        </Card>

        <Card>
            <div class="pc-audit-table pc-audit-table--desktop">
                <table class="pc-table pc-audit-table__grid">
                    <thead>
                        <tr>
                            <th>{{ t('audit_page.time') }}</th>
                            <th>{{ t('audit_page.event') }}</th>
                            <th>{{ t('audit_page.actor') }}</th>
                            <th>{{ t('audit_page.subject') }}</th>
                            <th>{{ t('audit_page.severity') }}</th>
                            <th>{{ t('audit_page.request') }}</th>
                            <th><span class="pc-sr-only">{{ t('audit_page.details') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data ?? []" :key="log.id">
                            <td class="pc-audit-time">{{ formatDate(log.created_at) }}</td>
                            <td>
                                <div class="pc-audit-primary" dir="auto">{{ log.event_label ?? log.action }}</div>
                                <div class="pc-audit-secondary" dir="auto">{{ log.action }}</div>
                            </td>
                            <td>
                                <div class="pc-audit-primary">{{ log.actor ?? 'system' }}</div>
                                <div class="pc-audit-secondary">{{ log.is_archived ? t('audit_page.archived') : t('audit_page.live') }}</div>
                            </td>
                            <td class="pc-audit-truncate" dir="auto">
                                {{ log.subject_type }}<span v-if="log.subject_id">:{{ log.subject_id }}</span>
                            </td>
                            <td>
                                <span class="pc-audit-chip" :class="severityClass(log.severity)">
                                    {{ severityLabels[log.severity] ?? log.severity }}
                                </span>
                                <span class="pc-audit-chip pc-audit-chip--subtle" :class="classClass(log.event_class)">
                                    {{ classLabels[log.event_class] ?? log.event_class }}
                                </span>
                            </td>
                            <td class="pc-audit-secondary pc-audit-truncate" dir="auto">{{ log.request_id || '—' }}</td>
                            <td>
                                <button type="button" class="pc-btn pc-btn--ghost pc-audit-row-action" @click="openDetails(log)">
                                    {{ t('audit_page.details') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="7">
                                <EmptyState :title="t('audit_page.no_results')" icon="history" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pc-audit-cards pc-audit-table--mobile">
                <article v-for="log in logs.data ?? []" :key="log.id" class="pc-audit-card">
                    <div class="pc-audit-card__top">
                        <span class="pc-audit-time">{{ formatDate(log.created_at) }}</span>
                        <span class="pc-audit-chip" :class="severityClass(log.severity)">
                            {{ severityLabels[log.severity] ?? log.severity }}
                        </span>
                    </div>
                    <h3 class="pc-audit-card__title" dir="auto">{{ log.event_label ?? log.action }}</h3>
                    <p class="pc-audit-card__meta" dir="auto">{{ log.action }}</p>
                    <dl class="pc-audit-card__list">
                        <div>
                            <dt>{{ t('audit_page.actor') }}</dt>
                            <dd>{{ log.actor ?? 'system' }}</dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_page.subject') }}</dt>
                            <dd dir="auto">{{ log.subject_type }}<span v-if="log.subject_id">:{{ log.subject_id }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ t('audit_page.request') }}</dt>
                            <dd dir="auto">{{ log.request_id || '—' }}</dd>
                        </div>
                    </dl>
                    <button type="button" class="pc-btn pc-btn--secondary pc-audit-card__action" @click="openDetails(log)">
                        {{ t('audit_page.details') }}
                    </button>
                </article>
                <EmptyState v-if="!logs.data?.length" :title="t('audit_page.no_results')" icon="history" />
            </div>

            <Pagination :data="logs" />
        </Card>

        <div v-if="detailsOpen" class="pc-modal-backdrop" @click.self="closeDetails">
            <div ref="dialogRef" class="pc-modal pc-audit-dialog" role="dialog" aria-modal="true" tabindex="-1" :aria-label="t('audit_page.details')">
                <header class="pc-modal__head">
                    <div>
                        <h2 class="pc-modal__title">{{ t('audit_page.details') }}</h2>
                        <p class="pc-audit-secondary">#{{ details?.id ?? selected?.id }}</p>
                    </div>
                    <button type="button" class="pc-btn pc-btn--ghost" @click="closeDetails">{{ t('audit_page.close') }}</button>
                </header>

                <div class="pc-modal__body">
                    <p v-if="detailsLoading" class="pc-audit-note">{{ t('audit_page.loading') }}</p>
                    <p v-else-if="detailsError" class="pc-audit-note pc-audit-note--error">{{ detailsError }}</p>

                    <template v-if="details">
                        <dl class="pc-audit-detail-grid">
                            <div>
                                <dt>{{ t('audit_page.time') }}</dt>
                                <dd>{{ formatDate(details.created_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_page.actor') }}</dt>
                                <dd>{{ details.actor }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_page.event') }}</dt>
                                <dd dir="auto">{{ details.event_label }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_page.severity') }}</dt>
                                <dd>{{ severityLabels[details.severity] ?? details.severity }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_page.subject') }}</dt>
                                <dd dir="auto">{{ details.subject_type }}:{{ details.subject_id || '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('audit_page.request') }}</dt>
                                <dd dir="auto">{{ details.request_id || '—' }}</dd>
                            </div>
                        </dl>

                        <p v-if="details.description" class="pc-audit-note" dir="auto">{{ details.description }}</p>

                        <h3 class="pc-audit-section-title">{{ t('audit_page.changed_fields') }}</h3>
                        <p v-if="!details.changed_fields?.length" class="pc-audit-note">{{ t('audit_page.no_changes') }}</p>
                        <div v-else class="pc-audit-change-list">
                            <div v-for="change in details.changed_fields" :key="change.field" class="pc-audit-change">
                                <strong dir="auto">{{ change.field }}</strong>
                                <span>{{ t('audit_page.before') }}: <code>{{ pretty(change.before) }}</code></span>
                                <span>{{ t('audit_page.after') }}: <code>{{ pretty(change.after) }}</code></span>
                            </div>
                        </div>

                        <h3 class="pc-audit-section-title">{{ t('audit_page.context') }}</h3>
                        <pre class="pc-audit-pre" dir="auto">{{ pretty(details.context) }}</pre>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
