<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import UiIcon from '../../Components/Dashboard/UiIcon.vue'
import RichTextEditor from '../../Components/Cms/RichTextEditor.vue'
import SelectionControl from '../../Components/Selection/SelectionControl.vue'
import StatusSelect from '../../Components/Selection/StatusSelect.vue'
import { useI18n } from '../../composables/useI18n'
import { fieldInput, humanize, operationRoute } from '../../Components/Operations/operations'

const props = defineProps({
    mode: { type: String, default: 'create' },
    resource: { type: String, required: true },
    definition: { type: Object, default: () => ({}) },
    record: { type: Object, default: null },
    fields: { type: Array, default: () => [] },
    validationRules: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    transitions: { type: Object, default: () => ({}) },
    locales: { type: Array, default: () => [] },
    translationFields: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    transitionsHistory: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({}) },
})

const { t, locale, direction, flash, formatDate: localizedDate } = useI18n()
const richTranslationFields = [
    'description', 'objectives', 'methodology', 'findings', 'recommendations',
    'lessons_learned', 'conditions', 'deliverables', 'reporting_requirements',
]

function isRichTranslation(field) {
    return richTranslationFields.includes(field)
}

const isEdit = computed(() => props.mode === 'edit')
const recordId = computed(() => props.record?.id ?? null)
const fieldNames = computed(() => props.fields?.length ? props.fields : Object.keys(props.definition?.fields ?? {}))
const locales = computed(() => (Array.isArray(props.locales) ? props.locales : []))
const translationFields = computed(() => (Array.isArray(props.translationFields) ? props.translationFields : []))
const documents = computed(() => (Array.isArray(props.documents) ? props.documents : []))
const history = computed(() => (Array.isArray(props.transitionsHistory) ? props.transitionsHistory : []))
const canSave = computed(() => !isEdit.value || props.permissions?.update !== false)
const canTransition = computed(() => isEdit.value && props.permissions?.transition === true)

const indexUrl = computed(() => operationRoute(`operations.${props.resource}.index`, { resource: props.resource }, locale.value))
const submitUrl = computed(() => operationRoute(
    `operations.${props.resource}.${isEdit.value ? 'update' : 'store'}`,
    { resource: props.resource, record: recordId.value },
    locale.value,
))
const transitionUrl = computed(() => operationRoute(
    `operations.${props.resource}.transition`,
    { resource: props.resource, record: recordId.value },
    locale.value,
))

function buildDefaults() {
    const values = {}
    for (const field of fieldNames.value) {
        const rules = Array.isArray(props.validationRules?.[field])
            ? props.validationRules[field]
            : String(props.validationRules?.[field] ?? '').split('|')
        const input = fieldInput(field, rules)
        const value = props.record?.[field] ?? ''
        values[field] = typeof value === 'boolean' && input.options.includes('true') ? String(value) : value
    }

    const translations = {}
    for (const code of locales.value) {
        translations[code] = {}
        for (const field of translationFields.value) {
            translations[code][field] = ''
        }
    }

    const current = props.record?.translation
    if (current?.locale && translations[current.locale]) {
        for (const field of translationFields.value) {
            translations[current.locale][field] = current[field] ?? ''
        }
    }

    return { ...values, translations }
}

const form = useForm(buildDefaults())
const transitionForm = useForm({ to_status: '', reason: '', evidence_media_id: '' })

const renderedFields = computed(() => fieldNames.value.map((name) => {
    const rules = Array.isArray(props.validationRules?.[name])
        ? props.validationRules[name]
        : String(props.validationRules?.[name] ?? '').split('|')
    const input = fieldInput(name, rules)
    return {
        name,
        label: humanize(name),
        input,
        required: rules.includes('required'),
        error: form.errors[name],
    }
}))

const transitionTargets = computed(() => {
    const allowed = props.transitions?.[props.record?.status] ?? []
    return Array.isArray(allowed) ? allowed : []
})
const transitionStatusOptions = computed(() => transitionTargets.value.map((status) => ({
    value: status,
    label: humanize(status),
})))

function isStatusField(field) {
    return /status/i.test(field.name)
}

function fieldOptions(field) {
    return (field.input.options ?? []).map((option) => ({
        value: option,
        label: humanize(option),
    }))
}

function save() {
    if (!canSave.value) {
        return
    }
    if (isEdit.value) {
        form.put(submitUrl.value, { preserveScroll: true })
    } else {
        form.post(submitUrl.value, { preserveScroll: true })
    }
}

function submitTransition() {
    if (!transitionForm.to_status) {
        return
    }
    transitionForm.post(transitionUrl.value, {
        preserveScroll: true,
        onSuccess: () => transitionForm.reset(),
    })
}

function errorText(error) {
    return Array.isArray(error) ? error.join(', ') : String(error ?? '')
}
</script>

<template>
    <AppLayout :title="isEdit ? `${t('ui.cms_edit')} ${humanize(resource)}` : `${t('ui.cms_create')} ${humanize(resource)}`" :subtitle="definition?.label || ''">
        <div class="pc-stack">

            <div v-if="Object.keys(form.errors).length" class="pc-callout pc-callout--warning" role="alert">
                {{ errorText(Object.values(form.errors)[0]) }}
            </div>

            <form class="pc-form-grid pc-form-grid--2" @submit.prevent="save">
                <section class="pc-card pc-card--padded">
                    <h2 class="mb-4 text-base font-semibold">{{ humanize('record_details') }}</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="field in renderedFields" :key="field.name" class="pc-field">
                            <StatusSelect
                                v-if="field.input.type === 'select' && isStatusField(field)"
                                v-model="form[field.name]"
                                :label="field.label"
                                :name="field.name"
                                :options="fieldOptions(field)"
                                :placeholder="t('ui.filter')"
                                :required="field.required"
                                :error="errorText(field.error)"
                                :searchable="field.input.options.length > 8"
                            />
                            <SelectionControl
                                v-else-if="field.input.type === 'select'"
                                v-model="form[field.name]"
                                :label="field.label"
                                :name="field.name"
                                :options="fieldOptions(field)"
                                :placeholder="t('ui.filter')"
                                :required="field.required"
                                :error="errorText(field.error)"
                                :searchable="field.input.options.length > 6"
                            />
                            <label v-else class="pc-field">
                                <span class="pc-label">{{ field.label }}<span v-if="field.required" class="pc-required"> *</span></span>
                                <input
                                    v-model="form[field.name]"
                                    :name="field.name"
                                    :type="field.input.type"
                                    :step="field.input.step || undefined"
                                    class="pc-input"
                                    :dir="['code', 'reference', 'website', 'source_url'].includes(field.name) ? 'ltr' : undefined"
                                />
                            </label>
                            <span v-if="field.input.hint" class="pc-hint">{{ field.input.hint }}</span>
                            <span v-if="field.error" class="pc-error">{{ errorText(field.error) }}</span>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <button type="submit" class="pc-btn pc-btn--primary" :disabled="!canSave || form.processing">
                            {{ form.processing ? t('ui.loading', humanize('loading')) : t('ui.cms_save') }}
                        </button>
                        <Link :href="indexUrl" class="pc-btn pc-btn--secondary">
                            {{ t('ui.cancel', humanize('cancel')) }}
                        </Link>
                        <Link :href="indexUrl" class="pc-btn pc-btn--ghost">
                            <UiIcon name="arrowBack" :size="15" /> {{ t('ui.back_to_list') }}
                        </Link>
                    </div>
                </section>

                <div class="pc-stack">
                    <section v-if="locales.length && translationFields.length" class="pc-card pc-card--padded">
                        <h2 class="mb-4 text-base font-semibold">{{ t('ui.cms_translations') }}</h2>
                        <div v-for="code in locales" :key="code" class="pc-card__body">
                            <h3 class="mb-2 text-sm font-semibold">{{ code === 'ar' ? t('ui.cms_arabic') : t('ui.cms_english') }}</h3>
                            <div :dir="code === 'ar' ? 'rtl' : 'ltr'" class="pc-stack">
                                <label v-for="field in translationFields" :key="field" class="pc-field">
                                    <span class="font-medium">{{ humanize(field) }}</span>
                                    <RichTextEditor
                                        v-if="isRichTranslation(field)"
                                        v-model="form.translations[code][field]"
                                        :locale="code"
                                        :error="form.errors[`translations.${code}.${field}`] || ''"
                                        :features="{ image: false }"
                                    />
                                    <textarea
                                        v-else
                                        v-model="form.translations[code][field]"
                                        :name="`translations[${code}][${field}]`"
                                        rows="2"
                                        class="pc-textarea"
                                    ></textarea>
                                    <span v-if="form.errors[`translations.${code}.${field}`]" class="pc-error">
                                        {{ errorText(form.errors[`translations.${code}.${field}`]) }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section v-if="isEdit" class="pc-card pc-card--padded">
                        <h2 class="mb-4 text-base font-semibold">{{ humanize('documents') }}</h2>
                        <ul v-if="documents.length" class="pc-stack">
                            <li v-for="document in documents" :key="document.id" class="pc-list__item">
                                <span>{{ document.original_name || humanize(document.usage || 'document') }}</span>
                                <a v-if="document.signedUrl" :href="document.signedUrl" class="pc-link">
                                    {{ t('ui.download') }}
                                </a>
                                <span v-else class="pc-hint">{{ humanize('no_signed_link') }}</span>
                            </li>
                        </ul>
                        <p v-else class="pc-hint">{{ t('ui.no_documents') }}</p>
                    </section>
                </div>
            </form>

            <div v-if="isEdit" class="pc-form-grid pc-form-grid--2">
                <section v-if="canTransition" class="pc-card pc-card--padded">
                    <h2 class="mb-4 text-base font-semibold">{{ humanize('status_transition') }}</h2>
                    <p class="mb-3 text-xs text-muted">{{ humanize('current_status') }}: {{ humanize(record?.status || '') }}</p>
                    <form class="pc-stack" @submit.prevent="submitTransition">
                        <div class="pc-field">
                            <StatusSelect
                                v-model="transitionForm.to_status"
                                :label="humanize('to_status')"
                                name="to_status"
                                :options="transitionStatusOptions"
                                :placeholder="t('ui.filter')"
                                :error="errorText(transitionForm.errors.to_status)"
                                :searchable="transitionTargets.length > 8"
                            />
                        </div>
                        <label class="pc-field">
                            <span class="font-medium">{{ humanize('reason') }}</span>
                            <textarea v-model="transitionForm.reason" name="reason" rows="2" class="pc-textarea"></textarea>
                            <span v-if="transitionForm.errors.reason" class="pc-error">{{ errorText(transitionForm.errors.reason) }}</span>
                        </label>
                        <label class="pc-field">
                            <span class="font-medium">{{ humanize('evidence_media_id') }}</span>
                            <input v-model="transitionForm.evidence_media_id" type="number" step="1" min="1" name="evidence_media_id" class="pc-input" />
                            <span class="pc-hint">Select an existing media ID for supporting evidence.</span>
                            <span v-if="transitionForm.errors.evidence_media_id" class="pc-error">{{ errorText(transitionForm.errors.evidence_media_id) }}</span>
                        </label>
                        <button type="submit" class="pc-btn pc-btn--primary" :disabled="!transitionTargets.length || transitionForm.processing">
                            {{ humanize('apply_transition') }}
                        </button>
                    </form>
                </section>

                <section class="pc-card pc-card--padded">
                    <h2 class="mb-4 text-base font-semibold">{{ humanize('transition_history') }}</h2>
                    <ul v-if="history.length" class="pc-stack">
                        <li v-for="entry in history" :key="entry.id" class="pc-list__item">
                            <div class="pc-table__actions">
                                <span class="font-medium">{{ humanize(entry.from || '') }} → {{ humanize(entry.to || '') }}</span>
                                <span class="pc-hint">{{ localizedDate(entry.created_at) }}</span>
                            </div>
                            <p v-if="entry.reason" class="mt-1 text-xs text-muted">{{ entry.reason }}</p>
                            <p v-if="entry.actor" class="mt-1 text-xs text-muted">{{ entry.actor }}</p>
                        </li>
                    </ul>
                    <p v-else class="pc-hint">{{ t('ui.no_results') }}</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
