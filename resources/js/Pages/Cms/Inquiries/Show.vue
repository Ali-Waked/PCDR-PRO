<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import CmsStatusBadge from '../../../Components/Dashboard/CmsStatusBadge.vue'
import DataTable from '../../../Components/Dashboard/DataTable.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import PcSelect from '../../../Components/Dashboard/PcSelect.vue'
import StatusBadge from '../../../Components/Dashboard/StatusBadge.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import StatusSelect from '../../../Components/Selection/StatusSelect.vue'
import { useI18n } from '../../../composables/useI18n'
import {
    COMPLAINT_PRIORITIES,
    COMPLAINT_STATUSES,
    complaintPriorityLabel,
    complaintPriorityTone,
    complaintStatusLabel,
} from '../../../Support/complaintLabels'

const props = defineProps({
    type: { type: String, required: true },
    inquiry: { type: Object, required: true },
    notes: { type: Array, default: () => [] },
    replies: { type: Array, default: () => [] },
    deliveryLog: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    variables: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    contact: { type: Object, default: null },
    history: { type: Array, default: () => [] },
    attachments: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    complaintStatuses: { type: Array, default: () => COMPLAINT_STATUSES },
    complaintPriorities: { type: Array, default: () => COMPLAINT_PRIORITIES },
    allowedTransitions: { type: Array, default: () => [] },
    urls: { type: Object, default: () => ({}) },
})

const { t, localPath, formatDate } = useI18n()
const inquiryPath = localPath('cms/inquiries')
const detailPath = `${inquiryPath}/${props.type}/${props.inquiry.id}`

const statusForm = useForm({ status: props.inquiry.status || 'new' })
const assignForm = useForm({ assigned_to: props.inquiry.assignedTo ?? '' })
const noteForm = useForm({ body: '' })
const replyForm = useForm({
    template_id: '',
    subject: `${t('cms_reply_prefix')} ${props.inquiry.reference}`,
    message: '',
    locale: props.inquiry.locale || 'ar',
})
const complaintStatusForm = useForm({ status: props.inquiry.status || '' })
const complaintPriorityForm = useForm({ priority: props.inquiry.priority || 'normal' })
const complaintAssignForm = useForm({ assigned_to: props.inquiry.assignedTo ?? '' })
const complaintNoteForm = useForm({ body: '' })
const complaintResolveForm = useForm({ resolution_details: props.inquiry.resolutionDetails || '' })

const isComplaint = computed(() => props.type === 'complaint')
const actionUrls = computed(() => props.urls || {})

function statusLabel(status) {
    return complaintStatusLabel(t, status)
}

const statusOptions = computed(() => props.statuses.map((status) => ({
    value: status,
    label: statusLabel(status),
})))

const assignOptions = computed(() => (props.inquiry.users || []).map((user) => ({
    value: user.id,
    label: user.name,
})))

const complaintStatusOptions = computed(() => {
    const statuses = props.complaintStatuses.length ? props.complaintStatuses : COMPLAINT_STATUSES
    const allowed = props.allowedTransitions || []

    return statuses
        .filter((status) => allowed.includes(status))
        .map((status) => ({ value: status, label: statusLabel(status) }))
})

const complaintPriorityOptions = computed(() => {
    const priorities = props.complaintPriorities.length ? props.complaintPriorities : COMPLAINT_PRIORITIES

    return priorities.map((priority) => ({
        value: priority,
        label: complaintPriorityLabel(t, priority),
    }))
})

const complaintAssignOptions = computed(() => {
    const users = props.users.length ? props.users : (props.inquiry.users || [])

    return users.map((user) => ({ value: user.id, label: user.name }))
})

const templateOptions = computed(() => props.templates.map((template) => ({
    value: template.id,
    label: template.name,
})))

const localeOptions = [
    { value: 'ar', label: 'ar' },
    { value: 'en', label: 'en' },
]

const fieldRows = computed(() => Object.entries(props.inquiry)
    .filter(([key, value]) => !['id', 'type', 'reference', 'status', 'locale', 'assignedTo', 'assignedToName', 'createdAt', 'users'].includes(key)
        && value !== null && value !== '')
    .map(([key, value]) => ({ key, value: String(value) })))

const logColumns = computed(() => [
    { key: 'purpose', label: t('cms_purpose') },
    { key: 'status', label: t('ui.status') },
    { key: 'locale', label: t('ui.language') },
    { key: 'sent', label: t('cms_sent_at') },
])

function chooseTemplate(value) {
    replyForm.template_id = value ?? ''
    const template = props.templates.find((item) => String(item.id) === String(value))
    if (!template) {
        return
    }
    replyForm.subject = template.subject || replyForm.subject
    if (!replyForm.message) {
        replyForm.message = ''
    }
}

function saveStatus() {
    statusForm.put(`${detailPath}/status`, { preserveScroll: true })
}

function saveAssignment() {
    assignForm.put(`${detailPath}/assign`, { preserveScroll: true })
}

function addNote() {
    noteForm.post(`${detailPath}/notes`, { preserveScroll: true, onSuccess: () => noteForm.reset() })
}

function sendReply() {
    replyForm.post(`${detailPath}/reply`, { preserveScroll: true, onSuccess: () => replyForm.reset('message') })
}

function saveComplaintStatus() {
    if (!actionUrls.value.status) {
        return
    }
    complaintStatusForm.put(actionUrls.value.status, { preserveScroll: true })
}

function saveComplaintPriority() {
    if (!actionUrls.value.priority) {
        return
    }
    complaintPriorityForm.put(actionUrls.value.priority, { preserveScroll: true })
}

function saveComplaintAssignment() {
    if (!actionUrls.value.assign) {
        return
    }
    complaintAssignForm.put(actionUrls.value.assign, { preserveScroll: true })
}

function addComplaintNote() {
    if (!actionUrls.value.note) {
        return
    }
    complaintNoteForm.post(actionUrls.value.note, {
        preserveScroll: true,
        onSuccess: () => complaintNoteForm.reset(),
    })
}

function resolveComplaint() {
    if (!actionUrls.value.resolve) {
        return
    }
    complaintResolveForm.post(actionUrls.value.resolve, { preserveScroll: true })
}

function formatFileSize(bytes) {
    const size = Number(bytes || 0)
    if (size < 1024) {
        return `${size} B`
    }
    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} KB`
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
    <CmsDashboardLayout :title="t('ui.cms_inquiries')" :subtitle="inquiry.reference">
        <div class="pc-stack pcdr-enter">
            <div class="pc-page-actions">
                <Link :href="inquiryPath" class="pc-btn pc-btn--secondary">
                    <UiIcon name="arrowForward" :size="16" />
                    {{ t('back') }}
                </Link>
                <CmsStatusBadge :status="inquiry.status" :label="statusLabel(inquiry.status)" />
            </div>

            <template v-if="isComplaint">
                <div v-if="can.complaintUpdate || can.complaintAssign || can.complaintResolve" class="pc-grid pc-grid--2">
                    <form v-if="can.complaintUpdate" class="pc-card" @submit.prevent="saveComplaintStatus">
                        <div class="pc-card__head">
                            <h2 class="pc-card__title">{{ t('ui.status') }}</h2>
                        </div>
                        <div class="pc-card__body pc-form-grid">
                            <div class="pc-field">
                                <StatusSelect
                                    v-model="complaintStatusForm.status"
                                    :label="t('cms_status')"
                                    :options="complaintStatusOptions"
                                    :loading="complaintStatusForm.processing"
                                    :disabled="!complaintStatusOptions.length || complaintStatusForm.processing"
                                />
                                <p v-if="!complaintStatusOptions.length" class="pc-table__secondary">
                                    {{ t('cms_complaint_no_transitions') }}
                                </p>
                            </div>
                            <div class="pc-field pc-field--action">
                                <button
                                    class="pc-btn pc-btn--primary"
                                    type="submit"
                                    :disabled="!complaintStatusOptions.length || complaintStatusForm.processing || !actionUrls.status"
                                >
                                    <UiIcon name="check" :size="16" />
                                    {{ t('cms_update_status') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <form v-if="can.complaintUpdate" class="pc-card" @submit.prevent="saveComplaintPriority">
                        <div class="pc-card__head">
                            <h2 class="pc-card__title">{{ t('cms_priority') }}</h2>
                        </div>
                        <div class="pc-card__body pc-form-grid">
                            <div class="pc-field">
                                <PcSelect
                                    v-model="complaintPriorityForm.priority"
                                    :label="t('cms_priority')"
                                    :options="complaintPriorityOptions"
                                    :searchable="false"
                                    :clearable="false"
                                    :loading="complaintPriorityForm.processing"
                                />
                            </div>
                            <div class="pc-field pc-field--action">
                                <button class="pc-btn pc-btn--primary" type="submit" :disabled="complaintPriorityForm.processing || !actionUrls.priority">
                                    <UiIcon name="check" :size="16" />
                                    {{ t('cms_save') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <form v-if="can.complaintAssign" class="pc-card" @submit.prevent="saveComplaintAssignment">
                        <div class="pc-card__head">
                            <h2 class="pc-card__title">{{ t('cms_assignment') }}</h2>
                        </div>
                        <div class="pc-card__body pc-form-grid">
                            <div class="pc-field">
                                <SelectionControl
                                    v-if="complaintAssignOptions.length"
                                    v-model="complaintAssignForm.assigned_to"
                                    :label="t('cms_assigned_to')"
                                    :options="complaintAssignOptions"
                                    :placeholder="t('cms_unassigned')"
                                    clearable
                                    :loading="complaintAssignForm.processing"
                                    :searchable="complaintAssignOptions.length > 6"
                                />
                                <label v-else class="pc-field">
                                    <span class="pc-label">{{ t('cms_assigned_to') }}</span>
                                    <input v-model="complaintAssignForm.assigned_to" class="pc-input" type="text" maxlength="255">
                                </label>
                            </div>
                            <div class="pc-field pc-field--action">
                                <button class="pc-btn pc-btn--primary" type="submit" :disabled="complaintAssignForm.processing || !actionUrls.assign">
                                    <UiIcon name="check" :size="16" />
                                    {{ t('cms_save') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <form v-if="can.complaintResolve" class="pc-card" @submit.prevent="resolveComplaint">
                        <div class="pc-card__head">
                            <h2 class="pc-card__title">{{ t('cms_complaint_resolve') }}</h2>
                        </div>
                        <div class="pc-card__body pc-form-grid">
                            <label class="pc-field pc-field--full">
                                <span class="pc-label">{{ t('cms_complaint_resolution_details') }}</span>
                                <textarea v-model="complaintResolveForm.resolution_details" class="pc-textarea" rows="4" maxlength="5000"></textarea>
                            </label>
                            <div class="pc-field pc-field--action">
                                <button class="pc-btn pc-btn--primary" type="submit" :disabled="complaintResolveForm.processing || !actionUrls.resolve">
                                    <UiIcon name="check" :size="16" />
                                    {{ t('cms_complaint_resolve') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <section class="pc-card">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('cms_complaint_details') }}</h2>
                        <span class="pc-badge pc-badge--slate" dir="ltr">{{ inquiry.reference }}</span>
                    </div>
                    <div class="pc-card__body pc-stack">
                        <p class="pc-table__secondary">
                            <UiIcon name="lock" :size="14" />
                            {{ t('cms_complaint_confidential_note') }}
                        </p>
                        <dl class="pc-detail-list">
                            <div class="pc-detail-list__item">
                                <dt>{{ t('cms_priority') }}</dt>
                                <dd><StatusBadge :label="complaintPriorityLabel(t, inquiry.priority)" :tone="complaintPriorityTone(inquiry.priority)" /></dd>
                            </div>
                            <div class="pc-detail-list__item">
                                <dt>{{ t('cms_complaint_category') }}</dt>
                                <dd>{{ inquiry.categoryLabel || inquiry.category || '—' }}</dd>
                            </div>
                            <div class="pc-detail-list__item">
                                <dt>{{ t('subject') }}</dt>
                                <dd>{{ inquiry.subject || '—' }}</dd>
                            </div>
                            <div class="pc-detail-list__item pc-detail-list__item--full">
                                <dt>{{ t('cms_complaint_description') }}</dt>
                                <dd class="pc-preline">{{ inquiry.description || '—' }}</dd>
                            </div>
                            <div v-if="inquiry.relatedProject" class="pc-detail-list__item">
                                <dt>{{ t('cms_complaint_related_project') }}</dt>
                                <dd>{{ inquiry.relatedProject }}</dd>
                            </div>
                            <div v-if="inquiry.location" class="pc-detail-list__item">
                                <dt>{{ t('cms_complaint_location') }}</dt>
                                <dd>{{ inquiry.location }}</dd>
                            </div>
                            <div class="pc-detail-list__item">
                                <dt>{{ t('cms_complaint_anonymous') }}</dt>
                                <dd>{{ inquiry.isAnonymous ? t('cms_yes') : t('cms_no') }}</dd>
                            </div>
                            <div class="pc-detail-list__item">
                                <dt>{{ t('cms_assigned_to') }}</dt>
                                <dd>{{ inquiry.assignedToName || inquiry.assignedTo || t('cms_unassigned') }}</dd>
                            </div>
                            <div class="pc-detail-list__item">
                                <dt>{{ t('cms_created_at') }}</dt>
                                <dd>{{ formatDate(inquiry.createdAt) }}</dd>
                            </div>
                            <div class="pc-detail-list__item">
                                <dt>{{ t('ui.language') }}</dt>
                                <dd dir="ltr">{{ inquiry.locale }}</dd>
                            </div>
                            <div v-if="inquiry.resolvedAt" class="pc-detail-list__item">
                                <dt>{{ t('cms_complaint_resolved_at') }}</dt>
                                <dd>{{ formatDate(inquiry.resolvedAt) }}</dd>
                            </div>
                            <div v-if="inquiry.resolutionDetails" class="pc-detail-list__item pc-detail-list__item--full">
                                <dt>{{ t('cms_complaint_resolution_details') }}</dt>
                                <dd class="pc-preline">{{ inquiry.resolutionDetails }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="pc-card">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('cms_complaint_contact') }}</h2>
                    </div>
                    <div class="pc-card__body">
                        <dl v-if="contact" class="pc-detail-list">
                            <div v-if="contact.name" class="pc-detail-list__item">
                                <dt>{{ t('cms_name') }}</dt>
                                <dd>{{ contact.name }}</dd>
                            </div>
                            <div v-if="contact.email" class="pc-detail-list__item">
                                <dt>{{ t('ui.email') }}</dt>
                                <dd class="pc-directional" dir="ltr">{{ contact.email }}</dd>
                            </div>
                            <div v-if="contact.phone" class="pc-detail-list__item">
                                <dt>{{ t('ui.phone') }}</dt>
                                <dd class="pc-directional" dir="ltr">{{ contact.phone }}</dd>
                            </div>
                            <div v-if="contact.preferredContactMethod" class="pc-detail-list__item">
                                <dt>{{ t('cms_complaint_preferred_contact_method') }}</dt>
                                <dd>{{ contact.preferredContactMethod }}</dd>
                            </div>
                            <div v-if="contact.preferredLocale" class="pc-detail-list__item">
                                <dt>{{ t('cms_complaint_preferred_locale') }}</dt>
                                <dd dir="ltr">{{ contact.preferredLocale }}</dd>
                            </div>
                        </dl>
                        <p v-else class="pc-table__secondary">{{ t('ui.complaints.anonymity_body') }}</p>
                    </div>
                </section>

                <section class="pc-card">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('cms_complaint_history') }}</h2>
                        <span class="pc-badge pc-badge--slate">{{ history.length }}</span>
                    </div>
                    <div class="pc-card__body pc-stack">
                        <div v-if="history.length" class="pc-timeline">
                            <article v-for="entry in history" :key="entry.id" class="pc-timeline__item">
                                <span class="pc-timeline__dot" aria-hidden="true"></span>
                                <p class="pc-timeline__title">
                                    {{ statusLabel(entry.fromStatus) }} → {{ statusLabel(entry.toStatus) }}
                                </p>
                                <p class="pc-timeline__meta">{{ entry.changedByName || '—' }} · {{ formatDate(entry.createdAt) }}</p>
                                <p v-if="entry.note" class="pc-timeline__body">{{ entry.note }}</p>
                            </article>
                        </div>
                        <EmptyState v-else :title="t('cms_no_records')" icon="history" />
                    </div>
                </section>

                <section class="pc-card">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('cms_internal_notes') }}</h2>
                        <span class="pc-badge pc-badge--teal">{{ notes.length }}</span>
                    </div>
                    <div class="pc-card__body pc-stack">
                        <form v-if="can.complaintNote" class="pc-form-grid" @submit.prevent="addComplaintNote">
                            <label class="pc-field pc-field--full">
                                <span class="pc-label">{{ t('cms_note') }}</span>
                                <textarea v-model="complaintNoteForm.body" class="pc-textarea" rows="3" maxlength="5000" required></textarea>
                            </label>
                            <div class="pc-form-actions">
                                <button class="pc-btn pc-btn--primary" type="submit" :disabled="complaintNoteForm.processing || !actionUrls.note">
                                    <UiIcon name="plus" :size="16" />
                                    {{ t('cms_add_note') }}
                                </button>
                            </div>
                        </form>
                        <div v-if="notes.length" class="pc-timeline">
                            <article v-for="note in notes" :key="note.id" class="pc-timeline__item">
                                <span class="pc-timeline__dot" aria-hidden="true"></span>
                                <p class="pc-timeline__title">{{ note.author || '—' }}</p>
                                <p class="pc-timeline__meta">{{ formatDate(note.createdAt) }}</p>
                                <p class="pc-timeline__body">{{ note.body }}</p>
                            </article>
                        </div>
                        <EmptyState v-else :title="t('cms_no_records')" icon="inbox" />
                    </div>
                </section>

                <section v-if="can.complaintViewAttachments" class="pc-card">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('cms_complaint_attachments') }}</h2>
                        <span class="pc-badge pc-badge--slate">{{ attachments.length }}</span>
                    </div>
                    <div class="pc-card__body pc-stack">
                        <ul v-if="attachments.length" class="pc-token-list">
                            <li v-for="attachment in attachments" :key="attachment.id" class="pc-token">
                                <a :href="attachment.url" target="_blank" rel="noopener noreferrer">
                                    {{ attachment.label || attachment.originalName }}
                                </a>
                                <small dir="ltr">{{ formatFileSize(attachment.sizeBytes) }}</small>
                            </li>
                        </ul>
                        <p v-else class="pc-table__secondary">{{ t('cms_complaint_no_attachments') }}</p>
                    </div>
                </section>
            </template>

            <template v-else>
            <div v-if="can.update || can.assign" class="pc-grid pc-grid--2">
                <form v-if="can.update" class="pc-card" @submit.prevent="saveStatus">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('ui.status') }}</h2>
                    </div>
                    <div class="pc-card__body pc-form-grid">
                        <div class="pc-field">
                            <StatusSelect
                                v-model="statusForm.status"
                                :label="t('cms_status')"
                                :options="statusOptions"
                                :loading="statusForm.processing"
                            />
                        </div>
                        <div class="pc-field pc-field--action">
                            <button class="pc-btn pc-btn--primary" type="submit" :disabled="statusForm.processing">
                                <UiIcon name="check" :size="16" />
                                {{ t('cms_update_status', t('cms_save')) }}
                            </button>
                        </div>
                    </div>
                </form>

                <form v-if="can.assign" class="pc-card" @submit.prevent="saveAssignment">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('cms_assignment') }}</h2>
                    </div>
                    <div class="pc-card__body pc-form-grid">
                        <div class="pc-field">
                            <SelectionControl
                                v-model="assignForm.assigned_to"
                                :label="t('cms_assigned_to')"
                                :options="assignOptions"
                                :placeholder="t('cms_unassigned')"
                                clearable
                                :loading="assignForm.processing"
                                :searchable="assignOptions.length > 6"
                            />
                        </div>
                        <div class="pc-field pc-field--action">
                            <button class="pc-btn pc-btn--primary" type="submit" :disabled="assignForm.processing">
                                <UiIcon name="check" :size="16" />
                                {{ t('cms_save') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <section class="pc-card">
                <div class="pc-card__head">
                    <h2 class="pc-card__title">{{ t('cms_inquiry_details') }}</h2>
                    <span class="pc-badge pc-badge--slate" dir="ltr">{{ inquiry.reference }}</span>
                </div>
                <div class="pc-card__body">
                    <dl class="pc-detail-list">
                        <div v-for="row in fieldRows" :key="row.key" class="pc-detail-list__item">
                            <dt>{{ row.key }}</dt>
                            <dd>{{ row.value }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="pc-card">
                <div class="pc-card__head">
                    <h2 class="pc-card__title">{{ t('cms_internal_notes') }}</h2>
                    <span class="pc-badge pc-badge--teal">{{ notes.length }}</span>
                </div>
                <div class="pc-card__body pc-stack">
                    <form v-if="can.note" class="pc-form-grid" @submit.prevent="addNote">
                        <label class="pc-field pc-field--full">
                            <span class="pc-label">{{ t('cms_note') }}</span>
                            <textarea v-model="noteForm.body" class="pc-textarea" rows="3" maxlength="5000" required></textarea>
                        </label>
                        <div class="pc-form-actions">
                            <button class="pc-btn pc-btn--primary" type="submit" :disabled="noteForm.processing">
                                <UiIcon name="plus" :size="16" />
                                {{ t('cms_add_note') }}
                            </button>
                        </div>
                    </form>

                    <div v-if="notes.length" class="pc-timeline">
                        <article v-for="note in notes" :key="note.id" class="pc-timeline__item">
                            <span class="pc-timeline__dot" aria-hidden="true"></span>
                            <p class="pc-timeline__title">{{ note.author || '—' }}</p>
                            <p class="pc-timeline__meta">{{ formatDate(note.createdAt) }}</p>
                            <p class="pc-timeline__body">{{ note.body }}</p>
                        </article>
                    </div>
                    <EmptyState v-else :title="t('cms_no_records')" icon="inbox" />
                </div>
            </section>

            <section class="pc-card">
                <div class="pc-card__head">
                    <h2 class="pc-card__title">{{ t('cms_email_reply') }}</h2>
                    <span class="pc-badge pc-badge--teal">{{ replies.length }}</span>
                </div>
                <div class="pc-card__body pc-stack">
                    <form v-if="can.reply" class="pc-form-grid" @submit.prevent="sendReply">
                        <div class="pc-field">
                            <SelectionControl
                                :model-value="replyForm.template_id"
                                :label="t('cms_email_template')"
                                :options="templateOptions"
                                :placeholder="t('cms_default_template')"
                                clearable
                                :searchable="templateOptions.length > 6"
                                @update:model-value="chooseTemplate"
                            />
                        </div>
                        <div class="pc-field">
                            <SelectionControl
                                v-model="replyForm.locale"
                                :label="t('ui.language')"
                                :options="localeOptions"
                                :searchable="false"
                            />
                        </div>
                        <label class="pc-field pc-field--full">
                            <span class="pc-label">{{ t('subject') }}</span>
                            <input v-model="replyForm.subject" class="pc-input" type="text" maxlength="255" required>
                        </label>
                        <label class="pc-field pc-field--full">
                            <span class="pc-label">{{ t('cms_reply_message') }}</span>
                            <textarea v-model="replyForm.message" class="pc-textarea" rows="6" maxlength="5000" required></textarea>
                        </label>
                        <div class="pc-field pc-field--full">
                            <span class="pc-label">{{ t('cms_allowed_variables') }}</span>
                            <div class="pc-token-list">
                                <span v-for="variable in Object.keys(variables)" :key="variable" class="pc-token">{{ variable }}</span>
                            </div>
                        </div>
                        <div class="pc-form-actions">
                            <button class="pc-btn pc-btn--primary" type="submit" :disabled="replyForm.processing">
                                <UiIcon name="mail" :size="16" />
                                {{ t('cms_send_reply') }}
                            </button>
                        </div>
                    </form>

                    <div v-if="replies.length" class="pc-timeline">
                        <article v-for="reply in replies" :key="reply.id" class="pc-timeline__item">
                            <span class="pc-timeline__dot" aria-hidden="true"></span>
                            <p class="pc-timeline__title">{{ reply.subject }}</p>
                            <p class="pc-timeline__meta">{{ reply.status }} · {{ reply.sender || '—' }} · {{ formatDate(reply.sentAt) }}</p>
                            <p class="pc-timeline__body">{{ reply.body }}</p>
                            <p v-if="reply.error" class="pc-error" role="alert">{{ reply.error }}</p>
                        </article>
                    </div>
                    <EmptyState v-else :title="t('cms_no_replies')" icon="mail" />
                </div>
            </section>

            <section class="pc-card">
                <div class="pc-card__head">
                    <h2 class="pc-card__title">{{ t('cms_delivery_log') }}</h2>
                    <span class="pc-badge pc-badge--slate">{{ deliveryLog.length }}</span>
                </div>
                <template v-if="deliveryLog.length">
                    <DataTable :columns="logColumns">
                        <tr v-for="log in deliveryLog" :key="log.id">
                            <td>{{ log.purpose }}</td>
                            <td><CmsStatusBadge :status="String(log.status || '').toLowerCase()" :label="log.status" /></td>
                            <td dir="ltr">{{ log.locale }}</td>
                            <td class="pc-table__secondary">{{ formatDate(log.sentAt || log.failedAt) }}</td>
                        </tr>
                    </DataTable>
                </template>
                <EmptyState v-else :title="t('cms_no_records')" icon="history" />
            </section>
            </template>
        </div>
    </CmsDashboardLayout>
</template>
