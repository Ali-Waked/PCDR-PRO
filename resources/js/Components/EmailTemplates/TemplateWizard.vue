<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useForm, useHttp } from '@inertiajs/vue3'
import EmailTemplateDialog from './EmailTemplateDialog.vue'
import TemplateEditor from './TemplateEditor.vue'
import ConfirmDialog from '../Dashboard/ConfirmDialog.vue'
import UiIcon from '../Dashboard/UiIcon.vue'
import StatusSelect from '../Selection/StatusSelect.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    template: { type: Object, default: null },
    locales: { type: Array, default: () => ['ar', 'en'] },
    variableCatalog: { type: Object, default: () => ({}) },
    starters: { type: Array, default: () => [] },
    strings: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
    templatesPath: { type: String, required: true },
    previewPath: { type: String, required: true },
    initialLocale: { type: String, default: 'ar' },
})

const emit = defineEmits(['close', 'saved', 'open-guide', 'open-preview'])

const step = ref(0)
const highestStep = ref(0)
const selectedLocale = ref(props.initialLocale || props.locales[0] || 'ar')
const clientErrors = ref({})
const copiedToken = ref('')
const starterNotice = ref('')
const preview = ref(null)
const previewBusy = ref(false)
const previewError = ref('')
const previewMode = ref('desktop')
const previewTab = ref('visual')
const imagesBlocked = ref(false)
const cancelDialog = ref(false)
const editorRef = ref(null)

const previewHttp = useHttp({ subject: '', body: '', locale: '', template_id: null })

function blankForm() {
    return {
        key: '',
        is_active: true,
        sort_order: 0,
        translations: Object.fromEntries(props.locales.map((locale) => [locale, {
            name: '', description: '', subject: '', body: '',
        }])),
    }
}

const form = useForm(blankForm())
const initialSnapshot = ref(JSON.stringify(form.data()))
const editingId = computed(() => props.template?.id || null)
const isDirty = computed(() => JSON.stringify(form.data()) !== initialSnapshot.value)
const activeTranslation = computed(() => form.translations[selectedLocale.value] || { name: '', description: '', subject: '', body: '' })
const activeDirection = computed(() => selectedLocale.value === 'ar' ? 'rtl' : 'ltr')
const activeStatusOptions = computed(() => [
    { value: true, label: props.strings.active, tone: 'green' },
    { value: false, label: props.strings.inactive, tone: 'slate' },
])

const steps = computed(() => Array.from({ length: 6 }, (_, index) => ({
    title: props.strings[`step_${index + 1}_title`] || '',
    short: props.strings[`step_${index + 1}_short`] || '',
    hint: props.strings[`step_${index + 1}_hint`] || '',
})))

const variableList = computed(() => Object.entries(props.variableCatalog || {}).map(([key, meta]) => ({
    key,
    label: meta.label || key,
    description: meta.description || '',
    example: meta.example || '',
    token: meta.token || `{{ ${key} }}`,
})))

const usedVariables = computed(() => {
    const keys = new Set()
    const pattern = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/g

    Object.values(form.translations || {}).forEach((translation) => {
        const content = `${translation.subject || ''}\n${translation.body || ''}`
        for (const match of content.matchAll(pattern)) {
            if (props.variableCatalog?.[match[1]]) {
                keys.add(match[1])
            }
        }
    })

    return variableList.value.filter((variable) => keys.has(variable.key))
})

function errorFor(path) {
    return clientErrors.value[path] || form.errors[path] || ''
}

function translationFor(locale) {
    return form.translations[locale] || { name: '', description: '', subject: '', body: '' }
}

function initialize() {
    step.value = 0
    highestStep.value = 0
    clientErrors.value = {}
    starterNotice.value = ''
    preview.value = null
    previewError.value = ''
    imagesBlocked.value = false
    selectedLocale.value = props.initialLocale || props.locales[0] || 'ar'

    const data = props.template
        ? {
            key: props.template.key,
            is_active: props.template.active,
            sort_order: props.template.sortOrder ?? 0,
            translations: Object.fromEntries(props.locales.map((locale) => [locale, {
                name: props.template.translations?.[locale]?.name || '',
                description: props.template.translations?.[locale]?.description || '',
                subject: props.template.translations?.[locale]?.subject || '',
                body: props.template.translations?.[locale]?.body || '',
            }])),
        }
        : blankForm()

    form.defaults(data)
    form.reset()

    form.clearErrors()
    initialSnapshot.value = JSON.stringify(form.data())
}

watch(() => props.open, (open) => {
    if (open) {
        initialize()
        window.addEventListener('beforeunload', warnBeforeUnload)
    } else {
        window.removeEventListener('beforeunload', warnBeforeUnload)
    }
})

watch(() => props.template, () => {
    if (props.open) {
        initialize()
    }
})

onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeUnload))

function warnBeforeUnload(event) {
    if (!props.open || !isDirty.value) {
        return
    }

    event.preventDefault()
    event.returnValue = ''
}

function requestClose() {
    if (isDirty.value) {
        cancelDialog.value = true

        return
    }

    emit('close')
}

function applyStarter(starter) {
    starterNotice.value = props.strings.starter_selected || ''
    form.key = `${starter.key}_custom`

    props.locales.forEach((locale) => {
        const source = starter.translations?.[locale]
        if (!source) {
            return
        }

        form.translations[locale].name = source.name || ''
        form.translations[locale].description = source.description || ''
        form.translations[locale].subject = source.subject || ''
        form.translations[locale].body = source.body || ''
    })
}

function validateStep(index) {
    const errors = {}

    if (index === 0) {
        if (!editingId.value && !form.key.trim()) {
            errors.key = props.strings.key_required || 'Template key is required.'
        } else if (!editingId.value && !/^[a-z][a-z0-9_]*$/.test(form.key.trim())) {
            errors.key = props.strings.key_pattern || 'Use lowercase letters, numbers and underscores.'
        }

        props.locales.forEach((locale) => {
            if (!translationFor(locale).name.trim()) {
                errors[`translations.${locale}.name`] = props.strings.name_required || 'Template name is required.'
            }
        })
    }

    if (index === 1) {
        props.locales.forEach((locale) => {
            if (!translationFor(locale).subject.trim()) {
                errors[`translations.${locale}.subject`] = props.strings.subject_required || 'Email subject is required.'
            }
        })
    }

    if (index === 2) {
        props.locales.forEach((locale) => {
            if (!translationFor(locale).body.trim()) {
                errors[`translations.${locale}.body`] = props.strings.body_required || 'Message body is required.'
            }
        })
    }

    if (index === 4 && !preview.value) {
        errors.preview = props.strings.preview_failed || 'Generate a preview before continuing.'
    }

    clientErrors.value = errors

    return Object.keys(errors).length === 0
}

function goToStep(index) {
    if (index < 0 || index > 5 || index > highestStep.value + 1) {
        return
    }

    step.value = index
    clientErrors.value = {}
}

function nextStep() {
    if (!validateStep(step.value)) {
        return
    }

    highestStep.value = Math.max(highestStep.value, step.value + 1)
    step.value += 1
    clientErrors.value = {}
}

async function renderPreview() {
    previewBusy.value = true
    previewError.value = ''

    Object.assign(previewHttp, {
        subject: activeTranslation.value.subject || '',
        body: activeTranslation.value.body || '',
        locale: selectedLocale.value,
        template_id: editingId.value || null,
        images_blocked: imagesBlocked.value,
    })

    try {
        preview.value = await previewHttp.post(props.previewPath)
        clientErrors.value = { ...clientErrors.value, preview: '' }
    } catch {
        previewError.value = props.strings.preview_failed || 'The preview could not be generated.'
    } finally {
        previewBusy.value = false
    }
}

async function copyToken(token) {
    try {
        await navigator.clipboard.writeText(token)
        copiedToken.value = token
        window.setTimeout(() => { copiedToken.value = '' }, 1600)
    } catch {
        copiedToken.value = ''
    }
}

async function insertToken(token) {
    step.value = 2
    await nextTick()
    editorRef.value?.insertText(token)
}

function save() {
    if (form.processing) {
        return
    }

    for (let index = 0; index <= 4; index++) {
        if (!validateStep(index)) {
            step.value = index

            return
        }
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            initialSnapshot.value = JSON.stringify(form.data())
            emit('saved')
        },
        onError: (errors) => {
            const keys = Object.keys(errors || {})
            if (keys.some((key) => key.startsWith('key'))) {
                step.value = 0
            } else if (keys.some((key) => key.includes('.subject'))) {
                step.value = 1
            } else {
                step.value = 2
            }
        },
    }

    if (editingId.value) {
        form.put(`${props.templatesPath}/${editingId.value}`, options)
    } else {
        form.post(props.templatesPath, options)
    }
}
</script>

<template>
    <EmailTemplateDialog
        :open="open"
        :title="editingId ? strings.wizard_edit_title : strings.wizard_create_title"
        :subtitle="strings.wizard_subtitle"
        :close-label="strings.close"
        size="wizard"
        @close="requestClose"
    >
        <template #toolbar>
            <span class="wizard-dirty" :class="{ 'is-dirty': isDirty }">
                <UiIcon :name="isDirty ? 'alert' : 'check'" :size="13" />
                {{ isDirty ? strings.unsaved : strings.saved }}
            </span>
            <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="emit('open-guide')">
                <UiIcon name="book" :size="14" />
                {{ strings.open_guide }}
            </button>
        </template>

        <div class="wizard" data-testid="template-wizard">
            <nav class="wizard-steps" :aria-label="strings.wizard_step">
                <button
                    v-for="(item, index) in steps"
                    :key="item.short"
                    type="button"
                    class="wizard-step"
                    :class="{ 'is-active': step === index, 'is-complete': index < highestStep }"
                    :aria-current="step === index ? 'step' : undefined"
                    :disabled="index > highestStep + 1"
                    @click="goToStep(index)"
                >
                    <span class="wizard-step__index">
                        <UiIcon v-if="index < highestStep" name="check" :size="13" />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span class="wizard-step__label">{{ item.short }}</span>
                </button>
            </nav>

            <div class="wizard-progress" role="status">
                <span>{{ strings.wizard_step.replace(':current', String(step + 1)).replace(':total', '6') }}</span>
                <span class="wizard-progress__bar"><span :style="{ inlineSize: `${((step + 1) / 6) * 100}%` }" /></span>
            </div>

            <section class="wizard-panel">
                <header class="wizard-panel__head">
                    <h3>{{ steps[step].title }}</h3>
                    <p>{{ steps[step].hint }}</p>
                </header>

                <div v-if="step === 0" class="wizard-form">
                    <div v-if="!editingId && starters.length" class="starter-grid">
                        <div class="starter-intro">
                            <h4>{{ strings.starters_title }}</h4>
                            <p>{{ strings.starters_description }}</p>
                        </div>
                        <button
                            v-for="starter in starters"
                            :key="starter.key"
                            type="button"
                            class="starter-card"
                            @click="applyStarter(starter)"
                        >
                            <UiIcon name="template" :size="16" />
                            <span dir="ltr">{{ starter.key }}</span>
                            <small>{{ translationFor(selectedLocale).name || starter.translations?.[selectedLocale]?.name }}</small>
                        </button>
                    </div>
                    <p v-if="starterNotice" class="pc-callout" role="status">{{ starterNotice }}</p>

                    <div class="pc-form-grid pc-form-grid--2">
                        <label v-if="!editingId" class="pc-field">
                            <span class="pc-label">{{ strings.key_label }}</span>
                            <input v-model="form.key" class="pc-input" type="text" maxlength="96" dir="ltr" required>
                            <span class="pc-help">{{ strings.key_help }}</span>
                            <span v-if="errorFor('key')" class="pc-error" role="alert">{{ errorFor('key') }}</span>
                        </label>
                        <label class="pc-field">
                            <span class="pc-label">{{ strings.sort_label }}</span>
                            <input v-model.number="form.sort_order" class="pc-input" type="number" min="0" max="999">
                            <span class="pc-help">{{ strings.sort_help }}</span>
                        </label>
                        <div class="pc-field">
                            <StatusSelect
                                v-model="form.is_active"
                                :label="strings.status_label"
                                :options="activeStatusOptions"
                                :loading="form.processing"
                            />
                            <span class="pc-help">{{ strings.status_help }}</span>
                        </div>
                    </div>

                    <div class="locale-tabs" role="tablist" :aria-label="strings.locale_label">
                        <button
                            v-for="locale in locales"
                            :key="locale"
                            type="button"
                            role="tab"
                            :aria-selected="selectedLocale === locale"
                            :class="{ 'is-active': selectedLocale === locale }"
                            @click="selectedLocale = locale"
                        >
                            {{ locale.toUpperCase() }}
                        </button>
                    </div>

                    <label class="pc-field">
                        <span class="pc-label">{{ strings.name_label }}</span>
                        <input v-model="activeTranslation.name" class="pc-input" type="text" maxlength="160" required>
                        <span class="pc-help">{{ strings.name_help }}</span>
                        <span v-if="errorFor(`translations.${selectedLocale}.name`)" class="pc-error" role="alert">{{ errorFor(`translations.${selectedLocale}.name`) }}</span>
                    </label>
                    <label class="pc-field">
                        <span class="pc-label">{{ strings.description_label }}</span>
                        <input v-model="activeTranslation.description" class="pc-input" type="text" maxlength="500">
                        <span class="pc-help">{{ strings.description_help }}</span>
                    </label>
                </div>

                <div v-else-if="step === 1" class="wizard-form">
                    <div class="pc-callout pc-callout--info">{{ strings.sender_note }}</div>
                    <div class="locale-tabs" role="tablist" :aria-label="strings.locale_label">
                        <button
                            v-for="locale in locales"
                            :key="locale"
                            type="button"
                            role="tab"
                            :aria-selected="selectedLocale === locale"
                            :class="{ 'is-active': selectedLocale === locale }"
                            @click="selectedLocale = locale"
                        >
                            {{ locale.toUpperCase() }}
                        </button>
                    </div>
                    <label class="pc-field">
                        <span class="pc-label">{{ strings.subject_label }}</span>
                        <input v-model="activeTranslation.subject" class="pc-input" type="text" maxlength="255" required>
                        <span class="pc-help">{{ strings.subject_help }}</span>
                        <span v-if="errorFor(`translations.${selectedLocale}.subject`)" class="pc-error" role="alert">{{ errorFor(`translations.${selectedLocale}.subject`) }}</span>
                    </label>
                    <div class="variable-shortcuts">
                        <span class="pc-eyebrow">{{ strings.variables_intro }}</span>
                        <button
                            v-for="variable in usedVariables.slice(0, 4)"
                            :key="variable.key"
                            type="button"
                            class="pc-token"
                            @click="activeTranslation.subject += ` ${variable.token}`"
                        >
                            {{ variable.token }}
                        </button>
                    </div>
                </div>

                <div v-else-if="step === 2" class="wizard-form">
                    <div class="locale-tabs" role="tablist" :aria-label="strings.locale_label">
                        <button
                            v-for="locale in locales"
                            :key="locale"
                            type="button"
                            role="tab"
                            :aria-selected="selectedLocale === locale"
                            :class="{ 'is-active': selectedLocale === locale }"
                            @click="selectedLocale = locale"
                        >
                            {{ locale.toUpperCase() }}
                        </button>
                    </div>
                    <TemplateEditor
                        ref="editorRef"
                        v-model="activeTranslation.body"
                        :direction="activeDirection"
                        :label="strings.body_label"
                        :help="strings.body_help"
                        :strings="strings"
                    />
                    <span v-if="errorFor(`translations.${selectedLocale}.body`)" class="pc-error" role="alert">{{ errorFor(`translations.${selectedLocale}.body`) }}</span>
                </div>

                <div v-else-if="step === 3" class="wizard-form">
                    <div class="pc-callout pc-callout--info">{{ strings.variables_intro }}</div>
                    <p class="pc-page-subtitle">{{ strings.variables_note }}</p>
                    <div class="variable-grid">
                        <article v-for="variable in variableList" :key="variable.key" class="variable-card">
                            <div class="variable-card__head">
                                <h4>{{ variable.label }}</h4>
                                <code dir="ltr">{{ variable.token }}</code>
                            </div>
                            <p>{{ variable.description }}</p>
                            <p class="variable-card__example">
                                <span>{{ strings.variable_example }}:</span>
                                <span dir="auto">{{ variable.example }}</span>
                            </p>
                            <div class="variable-card__actions">
                                <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="copyToken(variable.token)">
                                    <UiIcon :name="copiedToken === variable.token ? 'check' : 'copy'" :size="14" />
                                    {{ copiedToken === variable.token ? strings.copied : strings.copy }}
                                </button>
                                <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="insertToken(variable.token)">
                                    <UiIcon name="plus" :size="14" />
                                    {{ strings.insert }}
                                </button>
                            </div>
                        </article>
                    </div>
                    <div class="pc-callout">
                        <strong>{{ strings.used_variables }}</strong>
                        <div v-if="usedVariables.length" class="pc-token-list">
                            <span v-for="variable in usedVariables" :key="variable.key" class="pc-token" dir="ltr">{{ variable.token }}</span>
                        </div>
                        <p v-else>{{ strings.no_variables_used }}</p>
                    </div>
                </div>

                <div v-else-if="step === 4" class="wizard-form">
                    <div class="preview-controls">
                        <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" :disabled="previewBusy" @click="renderPreview">
                            <UiIcon name="eye" :size="15" />
                            {{ strings.preview_refresh }}
                        </button>
                        <div class="et-segmented" role="group" :aria-label="strings.preview_title">
                            <button type="button" :class="{ 'is-active': previewMode === 'desktop' }" @click="previewMode = 'desktop'">
                                <UiIcon name="monitor" :size="14" /> {{ strings.preview_desktop }}
                            </button>
                            <button type="button" :class="{ 'is-active': previewMode === 'mobile' }" @click="previewMode = 'mobile'">
                                <UiIcon name="phone" :size="14" /> {{ strings.preview_mobile }}
                            </button>
                        </div>
                        <button
                            type="button"
                            class="pc-btn pc-btn--secondary pc-btn--sm"
                            :aria-pressed="imagesBlocked ? 'true' : 'false'"
                            @click="imagesBlocked = !imagesBlocked; renderPreview()"
                        >
                            <UiIcon name="eyeOff" :size="14" />
                            {{ imagesBlocked ? strings.preview_images_on : strings.preview_images_blocked }}
                        </button>
                        <button
                            v-if="preview"
                            type="button"
                            class="pc-btn pc-btn--secondary pc-btn--sm"
                            @click="emit('open-preview', {
                                preview,
                                subject: activeTranslation.subject || '',
                                body: activeTranslation.body || '',
                                locale: selectedLocale,
                            })"
                        >
                            <UiIcon name="eye" :size="14" /> {{ strings.preview }}
                        </button>
                    </div>

                    <div v-if="previewBusy" class="preview-placeholder" role="status">{{ strings.preview_loading }}</div>
                    <div v-else-if="previewError || errorFor('preview')" class="pc-error" role="alert">{{ previewError || errorFor('preview') }}</div>
                    <template v-else-if="preview">
                        <div class="preview-summary">
                            <div><span class="pc-eyebrow">{{ strings.review_subject }}</span><strong dir="auto">{{ preview.subject || '—' }}</strong></div>
                            <div><span class="pc-eyebrow">{{ strings.preview_from_label }}</span><span dir="auto">{{ preview.sender.name }} &lt;{{ preview.sender.address }}&gt;</span></div>
                            <div><span class="pc-eyebrow">{{ strings.preview_to_label }}</span><span dir="ltr">{{ preview.recipient }}</span></div>
                        </div>
                        <div class="preview-stage" :class="`preview-stage--${previewMode}`">
                            <iframe
                                v-if="previewTab === 'visual'"
                                class="preview-frame"
                                :srcdoc="preview.html"
                                sandbox=""
                                referrerpolicy="no-referrer"
                                :title="strings.preview_visual"
                            />
                            <pre v-else class="preview-text" :dir="preview.direction">{{ preview.plainBody }}</pre>
                        </div>
                        <div class="et-tabs" role="tablist" :aria-label="strings.preview_title">
                            <button type="button" role="tab" :aria-selected="previewTab === 'visual'" :class="{ 'is-active': previewTab === 'visual' }" @click="previewTab = 'visual'">{{ strings.preview_visual }}</button>
                            <button type="button" role="tab" :aria-selected="previewTab === 'text'" :class="{ 'is-active': previewTab === 'text' }" @click="previewTab = 'text'">{{ strings.preview_text }}</button>
                        </div>
                    </template>
                    <div v-else class="preview-placeholder">{{ strings.preview_failed }}</div>
                </div>

                <div v-else class="wizard-form">
                    <div class="review-grid">
                        <div><span class="pc-eyebrow">{{ strings.review_name }}</span><strong>{{ translationFor(selectedLocale).name || '—' }}</strong></div>
                        <div><span class="pc-eyebrow">{{ strings.review_key }}</span><strong dir="ltr">{{ form.key }}</strong></div>
                        <div><span class="pc-eyebrow">{{ strings.review_locales }}</span><strong dir="ltr">{{ locales.join(', ') }}</strong></div>
                        <div><span class="pc-eyebrow">{{ strings.review_status }}</span><strong>{{ form.is_active ? strings.active : strings.inactive }}</strong></div>
                        <div class="review-grid__wide"><span class="pc-eyebrow">{{ strings.review_subject }}</span><strong dir="auto">{{ translationFor(selectedLocale).subject || '—' }}</strong></div>
                        <div><span class="pc-eyebrow">{{ strings.review_content }}</span><strong>{{ translationFor(selectedLocale).body ? strings.review_ready : '—' }}</strong></div>
                        <div><span class="pc-eyebrow">{{ strings.review_variables }}</span><strong>{{ usedVariables.length }}</strong></div>
                    </div>
                    <div v-if="preview" class="preview-thumbnail">
                        <iframe
                            :srcdoc="preview.html"
                            sandbox=""
                            referrerpolicy="no-referrer"
                            :title="strings.preview_title"
                        />
                    </div>
                    <button v-else type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="step = 4">{{ strings.preview }}</button>
                </div>
            </section>
        </div>

        <template #footer>
            <div class="wizard-footer">
                <button type="button" class="pc-btn pc-btn--secondary" @click="requestClose">{{ strings.cancel }}</button>
                <div class="wizard-footer__nav">
                    <button v-if="step > 0" type="button" class="pc-btn pc-btn--secondary" @click="step -= 1">
                        <UiIcon name="chevronLeft" :size="15" />
                        {{ strings.previous }}
                    </button>
                    <button v-if="step < 5" type="button" class="pc-btn pc-btn--primary" @click="nextStep">
                        {{ strings.next }}
                        <UiIcon name="arrowRight" :size="15" />
                    </button>
                    <button v-else type="button" class="pc-btn pc-btn--primary" :disabled="form.processing" @click="save">
                        <UiIcon name="check" :size="15" />
                        {{ form.processing ? strings.saving : strings.save }}
                    </button>
                </div>
            </div>
        </template>
    </EmailTemplateDialog>

    <ConfirmDialog
        :open="cancelDialog"
        :title="strings.cancel_title"
        :message="strings.cancel_message"
        :confirm-label="strings.discard"
        :cancel-label="strings.keep_editing"
        danger
        @close="cancelDialog = false"
        @confirm="cancelDialog = false; emit('close')"
    />
</template>

<style scoped>
.wizard { display: grid; gap: 16px; }
.wizard-dirty {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    border-radius: 999px;
    background: var(--pc-teal-soft);
    color: var(--pc-teal);
    font-size: 0.72rem;
    white-space: nowrap;
}
.wizard-dirty.is-dirty { background: var(--pc-sand-soft); color: var(--pc-warning); }
.wizard-steps { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 6px; }
.wizard-step {
    display: flex;
    align-items: center;
    gap: 7px;
    min-inline-size: 0;
    padding: 8px 10px;
    border: 1px solid var(--pc-border);
    border-radius: 999px;
    background: var(--pc-surface);
    color: var(--pc-muted);
    font: inherit;
    font-size: 0.74rem;
    cursor: pointer;
}
.wizard-step:disabled { opacity: 0.45; cursor: not-allowed; }
.wizard-step.is-active { border-color: var(--pc-teal); color: var(--pc-teal); background: var(--pc-teal-soft); }
.wizard-step.is-complete { color: var(--pc-success); }
.wizard-step__index {
    display: grid;
    place-items: center;
    inline-size: 22px;
    block-size: 22px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--pc-surface-2);
    font-size: 0.7rem;
    font-weight: 700;
}
.wizard-step.is-active .wizard-step__index { background: var(--pc-teal); color: var(--pc-on-secondary); }
.wizard-step__label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wizard-progress { display: grid; gap: 6px; color: var(--pc-muted); font-size: 0.74rem; }
.wizard-progress__bar { block-size: 4px; border-radius: 999px; background: var(--pc-border); overflow: hidden; }
.wizard-progress__bar span { display: block; block-size: 100%; border-radius: inherit; background: var(--pc-teal); transition: inline-size 180ms ease; }
.wizard-panel {
    padding: 20px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius, 14px);
    background: var(--pc-surface);
}
.wizard-panel__head { margin-block-end: 18px; }
.wizard-panel__head h3 { margin: 0; font-size: 1.05rem; }
.wizard-panel__head p { margin: 5px 0 0; color: var(--pc-muted); font-size: 0.82rem; }
.wizard-form { display: grid; gap: 16px; }
.starter-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.starter-intro { grid-column: 1 / -1; }
.starter-intro h4 { margin: 0 0 4px; font-size: 0.92rem; }
.starter-intro p { margin: 0; color: var(--pc-muted); font-size: 0.8rem; }
.starter-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 6px 10px;
    padding: 12px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm, 9px);
    background: var(--pc-surface-2);
    color: var(--pc-text);
    text-align: start;
    font: inherit;
    cursor: pointer;
}
.starter-card:hover { border-color: var(--pc-teal); }
.starter-card span { color: var(--pc-teal); font-size: 0.78rem; font-weight: 700; }
.starter-card small { grid-column: 2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--pc-muted); }
.locale-tabs, .et-tabs, .et-segmented { display: inline-flex; flex-wrap: wrap; gap: 4px; }
.locale-tabs button, .et-tabs button, .et-segmented button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid var(--pc-border);
    border-radius: 999px;
    background: var(--pc-surface);
    color: var(--pc-muted);
    font: inherit;
    font-size: 0.76rem;
    cursor: pointer;
}
.locale-tabs button.is-active, .et-tabs button.is-active, .et-segmented button.is-active {
    border-color: var(--pc-teal);
    background: var(--pc-teal-soft);
    color: var(--pc-teal);
}
.variable-shortcuts { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.variable-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.variable-card {
    min-inline-size: 0;
    padding: 14px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm, 9px);
    background: var(--pc-surface-2);
}
.variable-card__head { display: grid; gap: 5px; }
.variable-card h4 { margin: 0; font-size: 0.86rem; }
.variable-card code {
    display: inline-block;
    inline-size: fit-content;
    max-inline-size: 100%;
    overflow-wrap: anywhere;
    padding: 3px 7px;
    border-radius: 5px;
    background: var(--pc-navy);
    color: var(--pc-on-primary);
    font-size: 0.76rem;
}
.variable-card p { margin: 8px 0 0; color: var(--pc-muted); font-size: 0.76rem; line-height: 1.6; }
.variable-card__example { display: flex; flex-wrap: wrap; gap: 6px; }
.variable-card__actions { display: flex; flex-wrap: wrap; gap: 6px; margin-block-start: 10px; }
.preview-controls { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.preview-summary { display: grid; gap: 6px; padding: 12px; border: 1px solid var(--pc-border); border-radius: var(--pc-radius-sm, 9px); }
.preview-summary > div { display: grid; gap: 2px; font-size: 0.82rem; overflow-wrap: anywhere; }
.preview-stage {
    display: flex;
    justify-content: center;
    padding: 10px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius, 14px);
    background: var(--pc-surface-2);
}
.preview-frame { display: block; inline-size: 100%; block-size: 380px; border: 0; border-radius: 8px; background: var(--pcdr-white); }
.preview-stage--mobile .preview-frame { inline-size: min(390px, 100%); border-radius: 18px; }
.preview-text {
    margin: 0;
    inline-size: 100%;
    max-block-size: 380px;
    overflow: auto;
    padding: 14px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    border-radius: 8px;
    background: var(--pc-surface);
    color: var(--pc-text);
    font: inherit;
    line-height: 1.8;
}
.preview-placeholder { padding: 48px 16px; text-align: center; color: var(--pc-muted); }
.review-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.review-grid > div { display: grid; gap: 4px; padding: 12px; border: 1px solid var(--pc-border); border-radius: var(--pc-radius-sm, 9px); overflow-wrap: anywhere; }
.review-grid__wide { grid-column: span 2; }
.review-grid strong { font-size: 0.86rem; }
.preview-thumbnail { padding: 8px; border: 1px solid var(--pc-border); border-radius: var(--pc-radius-sm, 9px); }
.preview-thumbnail iframe { display: block; inline-size: 100%; block-size: 220px; border: 0; border-radius: 6px; background: var(--pcdr-white); }
.wizard-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.wizard-footer__nav { display: flex; flex-wrap: wrap; gap: 8px; }

@media (max-width: 760px) {
    .wizard-steps { grid-template-columns: repeat(6, minmax(44px, 1fr)); overflow-x: auto; }
    .wizard-step { flex-direction: column; gap: 4px; padding: 7px 4px; text-align: center; }
    .wizard-step__label { max-inline-size: 100%; font-size: 0.62rem; }
    .starter-grid, .variable-grid { grid-template-columns: minmax(0, 1fr); }
    .review-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .review-grid__wide { grid-column: 1 / -1; }
    .preview-frame { block-size: 320px; }
    .wizard-footer { flex-direction: column-reverse; align-items: stretch; }
    .wizard-footer__nav { justify-content: space-between; }
}
</style>
