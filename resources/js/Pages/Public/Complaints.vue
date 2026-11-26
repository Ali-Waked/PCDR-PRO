<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import ProPillHeader from '../../Components/RedesignHome/ProPillHeader.vue'
import ProPrototypeFooter from '../../Components/RedesignHome/ProPrototypeFooter.vue'
import ProPrototypeSprite from '../../Components/RedesignHome/ProPrototypeSprite.vue'
import ProTopStrip from '../../Components/RedesignHome/ProTopStrip.vue'
import SelectionControl from '../../Components/Selection/SelectionControl.vue'
import { useI18n } from '../../composables/useI18n'
import {
    attachmentAccept,
    clearComplaintIdentity,
    complaintPayload,
    pickComplaintAttachments,
} from '../../Support/complaintForm'
import '../../../css/pcdr-prototype.css'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    contactMethods: { type: Array, default: () => [] },
    attachmentLimits: { type: Object, default: () => ({ maxFiles: 5, maxFileSizeKb: 10240, allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'] }) },
    storeUrl: { type: String, default: '' },
    complaintsUrl: { type: String, default: '' },
    submittedReference: { type: String, default: null },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, site, t } = useI18n()

const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim())
    ? props.meta.title
    : t('ui.complaints.title', t('nav.complaints')))
const description = computed(() => props.meta?.description || site.value?.slogan || '')
const skipLabel = computed(() => t('ui.skip_to_content', locale.value === 'ar' ? 'تخطَّ إلى المحتوى' : 'Skip to content'))

const form = useForm({
    category: props.categories[0]?.value || '',
    subject: '',
    description: '',
    related_project: '',
    location: '',
    is_anonymous: false,
    name: '',
    preferred_contact_method: props.contactMethods[0]?.value || '',
    email: '',
    phone: '',
    preferred_locale: locale.value,
    attachments: [],
})

const attachmentInput = ref(null)

watch(locale, (value) => {
    form.preferred_locale = value
})

watch(() => form.is_anonymous, (anonymous) => {
    if (anonymous) {
        clearComplaintIdentity(form)
        return
    }

    if (!form.preferred_contact_method) {
        form.preferred_contact_method = props.contactMethods[0]?.value || ''
    }
})

const localeOptions = computed(() => [
    { value: 'ar', label: 'العربية' },
    { value: 'en', label: 'English' },
])

const guidance = computed(() => [
    t('ui.complaints.guidance_subject'),
    t('ui.complaints.guidance_description'),
    t('ui.complaints.guidance_location'),
    t('ui.complaints.guidance_evidence'),
    t('ui.complaints.guidance_contact'),
])

const acceptedFiles = computed(() => attachmentAccept(props.attachmentLimits))
const attachmentHint = computed(() => format('ui.complaints.attachments_hint', {
    max: props.attachmentLimits?.maxFiles ?? 5,
    size: props.attachmentLimits?.maxFileSizeKb ?? 10240,
    types: (props.attachmentLimits?.allowedExtensions || []).join(', '),
}))

function format(key, replacements = {}) {
    let value = String(t(key) ?? '')
    for (const [name, replacement] of Object.entries(replacements)) {
        value = value.replaceAll(`:${name}`, String(replacement ?? ''))
    }

    return value
}

function attachmentErrorMessage(code) {
    if (code === 'too_many') {
        return format('ui.complaints.attachments_too_many', { max: props.attachmentLimits?.maxFiles ?? 5 })
    }
    if (code === 'too_large') {
        return format('ui.complaints.attachments_too_large', { size: props.attachmentLimits?.maxFileSizeKb ?? 10240 })
    }

    return t('ui.complaints.attachments_extension')
}

function onAttachmentsChange(event) {
    const result = pickComplaintAttachments(event.target.files, props.attachmentLimits)
    if (result.error) {
        form.attachments = []
        event.target.value = ''
        form.setError('attachments', attachmentErrorMessage(result.error))
        return
    }

    form.attachments = result.files
    form.clearErrors('attachments')
}

function removeAttachment(index) {
    const remaining = form.attachments.filter((_, position) => position !== index)
    form.attachments = remaining

    if (attachmentInput.value && typeof DataTransfer !== 'undefined') {
        const transfer = new DataTransfer()
        remaining.forEach((file) => transfer.items.add(file))
        attachmentInput.value.files = transfer.files
    }
}

function formatSize(bytes) {
    const size = Number(bytes || 0)
    if (size < 1024) {
        return `${size} B`
    }
    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} KB`
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

function submit() {
    if (!props.storeUrl || form.processing) {
        return
    }

    form
        .transform((data) => complaintPayload(data))
        .post(props.storeUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset()
                if (attachmentInput.value) {
                    attachmentInput.value.value = ''
                }
            },
        })
}
</script>

<template>
    <div class="pcdr-prototype complaints-page" :lang="locale" :dir="direction">
        <Head>
            <title>{{ title }}</title>
            <meta name="description" :content="description">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
            <link v-if="props.meta?.canonical" rel="canonical" :href="props.meta.canonical">
            <link rel="alternate" hreflang="ar" :href="props.meta?.alternates?.ar || alternateUrl('ar')">
            <link rel="alternate" hreflang="en" :href="props.meta?.alternates?.en || alternateUrl('en')">
            <link v-if="props.meta?.alternates?.['x-default']" rel="alternate" hreflang="x-default" :href="props.meta.alternates['x-default']">
            <meta name="robots" :content="props.meta?.robots || 'index,follow'">
            <meta property="og:type" :content="props.meta?.og?.type || 'website'">
            <meta property="og:title" :content="props.meta?.og?.title || title">
            <meta property="og:description" :content="props.meta?.og?.description || description">
            <meta property="og:url" :content="props.meta?.og?.url || props.meta?.canonical">
            <meta property="og:locale" :content="props.meta?.og?.locale || locale">
            <meta property="og:site_name" :content="props.meta?.og?.site_name || site?.shortName || 'PCDR'">
            <meta v-if="props.meta?.og?.image" property="og:image" :content="props.meta.og.image">
            <meta v-if="props.meta?.twitter?.card" name="twitter:card" :content="props.meta.twitter.card">
            <meta v-if="props.meta?.twitter?.title" name="twitter:title" :content="props.meta.twitter.title">
            <meta v-if="props.meta?.twitter?.description" name="twitter:description" :content="props.meta.twitter.description">
            <meta v-if="props.meta?.twitter?.image" name="twitter:image" :content="props.meta.twitter.image">
        </Head>

        <ProPrototypeSprite />
        <a class="skip" href="#complaint-form">{{ skipLabel }}</a>
        <ProTopStrip />
        <ProPillHeader />

        <main id="top">
            <section class="sec complaints-hero">
                <div class="wrap">
                    <div class="eyebrow"><b>01</b><span>{{ t('ui.complaints.title') }}</span></div>
                    <h1 class="h2">{{ t('ui.complaints.title') }}</h1>
                    <div class="deco" aria-hidden="true"></div>
                    <p class="complaints-hero__lead">{{ t('ui.complaints.intro') }}</p>
                </div>
            </section>

            <section class="sec complaints-body">
                <div class="wrap complaints-layout">
                    <div class="complaints-main">
                        <div v-if="props.submittedReference" class="panel complaints-confirm" role="status" aria-live="polite">
                            <span class="tag">{{ t('ui.complaints.submitted_title') }}</span>
                            <h2 class="complaints-confirm__title">{{ t('ui.complaints.submitted_title') }}</h2>
                            <p>{{ t('ui.complaints.submitted_body') }}</p>
                            <p class="complaints-confirm__ref">
                                <span>{{ t('ui.complaints.reference') }}</span>
                                <strong dir="ltr">{{ props.submittedReference }}</strong>
                            </p>
                            <Link class="complaints-btn" :href="props.complaintsUrl">{{ t('ui.complaints.submit_another') }}</Link>
                        </div>

                        <form v-else id="complaint-form" class="panel complaints-form" @submit.prevent="submit">
                            <div class="complaints-form__head">
                                <h2>{{ t('ui.complaints.form_title') }}</h2>
                                <p class="fine">{{ t('ui.complaints.form_note') }}</p>
                            </div>

                            <p v-if="Object.keys(form.errors).length" class="complaints-error" role="alert">
                                {{ t('ui.complaints.error_summary') }}
                            </p>

                            <div class="complaints-grid">
                                <div class="complaints-field">
                                    <SelectionControl
                                        v-model="form.category"
                                        name="category"
                                        :label="t('ui.complaints.category')"
                                        :options="props.categories"
                                        :error="form.errors.category"
                                        :searchable="false"
                                        required
                                        variant="public"
                                    />
                                </div>

                                <label class="complaints-field">
                                    <span>{{ t('ui.complaints.subject') }} <b aria-hidden="true">*</b></span>
                                    <input v-model="form.subject" class="complaints-input" type="text" maxlength="255" required>
                                    <small v-if="form.errors.subject" class="complaints-error" role="alert">{{ form.errors.subject }}</small>
                                </label>

                                <label class="complaints-field complaints-field--full">
                                    <span>{{ t('ui.complaints.description') }} <b aria-hidden="true">*</b></span>
                                    <textarea v-model="form.description" class="complaints-input" rows="7" required></textarea>
                                    <small class="fine">{{ t('ui.complaints.description_hint') }}</small>
                                    <small v-if="form.errors.description" class="complaints-error" role="alert">{{ form.errors.description }}</small>
                                </label>

                                <label class="complaints-field">
                                    <span>{{ t('ui.complaints.related_project') }}</span>
                                    <input v-model="form.related_project" class="complaints-input" type="text" maxlength="255">
                                    <small v-if="form.errors.related_project" class="complaints-error" role="alert">{{ form.errors.related_project }}</small>
                                </label>

                                <label class="complaints-field">
                                    <span>{{ t('ui.complaints.location') }}</span>
                                    <input v-model="form.location" class="complaints-input" type="text" maxlength="255">
                                    <small v-if="form.errors.location" class="complaints-error" role="alert">{{ form.errors.location }}</small>
                                </label>
                            </div>

                            <label class="complaints-anonymous">
                                <input v-model="form.is_anonymous" type="checkbox">
                                <span>
                                    <b>{{ t('ui.complaints.anonymous') }}</b>
                                    <small>{{ t('ui.complaints.anonymous_hint') }}</small>
                                </span>
                            </label>

                            <fieldset v-show="!form.is_anonymous" class="complaints-identity" :disabled="form.is_anonymous">
                                <legend>{{ t('ui.complaints.identity_title') }}</legend>
                                <div class="complaints-grid">
                                    <label class="complaints-field">
                                        <span>{{ t('ui.complaints.name') }}</span>
                                        <input v-model="form.name" class="complaints-input" type="text" maxlength="255" autocomplete="name">
                                        <small v-if="form.errors.name" class="complaints-error" role="alert">{{ form.errors.name }}</small>
                                    </label>

                                    <div class="complaints-field">
                                        <SelectionControl
                                            v-model="form.preferred_contact_method"
                                            name="preferred_contact_method"
                                            :label="t('ui.complaints.preferred_contact_method')"
                                            :options="props.contactMethods"
                                            :placeholder="t('ui.select')"
                                            :error="form.errors.preferred_contact_method"
                                            :disabled="form.is_anonymous"
                                            :searchable="false"
                                            clearable
                                            variant="public"
                                        />
                                    </div>

                                    <label class="complaints-field">
                                        <span>{{ t('ui.complaints.email') }}</span>
                                        <input v-model="form.email" class="complaints-input" type="email" autocomplete="email">
                                        <small v-if="form.errors.email" class="complaints-error" role="alert">{{ form.errors.email }}</small>
                                    </label>

                                    <label class="complaints-field">
                                        <span>{{ t('ui.complaints.phone') }}</span>
                                        <input v-model="form.phone" class="complaints-input" type="tel" autocomplete="tel">
                                        <small v-if="form.errors.phone" class="complaints-error" role="alert">{{ form.errors.phone }}</small>
                                    </label>

                                    <div class="complaints-field complaints-field--full">
                                        <SelectionControl
                                            v-model="form.preferred_locale"
                                            name="preferred_locale"
                                            :label="t('ui.complaints.preferred_locale')"
                                            :options="localeOptions"
                                            :error="form.errors.preferred_locale"
                                            :disabled="form.is_anonymous"
                                            :searchable="false"
                                            variant="public"
                                        />
                                    </div>
                                </div>
                            </fieldset>

                            <div class="complaints-field complaints-field--full">
                                <span>{{ t('ui.complaints.attachments') }}</span>
                                <input
                                    ref="attachmentInput"
                                    class="complaints-input"
                                    type="file"
                                    name="attachments[]"
                                    multiple
                                    :accept="acceptedFiles"
                                    @change="onAttachmentsChange"
                                >
                                <small class="fine">{{ attachmentHint }}</small>
                                <small v-if="form.errors.attachments" class="complaints-error" role="alert">{{ form.errors.attachments }}</small>
                                <ul v-if="form.attachments.length" class="complaints-files">
                                    <li v-for="(file, index) in form.attachments" :key="`${file.name}-${index}`">
                                        <span dir="ltr">{{ file.name }}</span>
                                        <small>{{ formatSize(file.size) }}</small>
                                        <button type="button" @click="removeAttachment(index)">{{ t('ui.complaints.remove_attachment') }}</button>
                                    </li>
                                </ul>
                            </div>

                            <div class="complaints-form__actions">
                                <button class="complaints-btn" type="submit" :disabled="form.processing || !props.storeUrl">
                                    {{ form.processing ? t('ui.complaints.submitting') : t('ui.complaints.submit') }}
                                </button>
                                <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="complaints-progress"></progress>
                            </div>
                        </form>
                    </div>

                    <aside class="complaints-aside">
                        <section class="axis">
                            <span class="tag">{{ t('ui.complaints.guidance_title') }}</span>
                            <ul class="complaints-list">
                                <li v-for="(item, index) in guidance" :key="item">
                                    <span aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span>
                                    {{ item }}
                                </li>
                            </ul>
                        </section>
                        <section class="axis">
                            <h3>{{ t('ui.complaints.confidentiality_title') }}</h3>
                            <p>{{ t('ui.complaints.confidentiality_body') }}</p>
                        </section>
                        <section class="axis">
                            <h3>{{ t('ui.complaints.anonymity_title') }}</h3>
                            <p>{{ t('ui.complaints.anonymity_body') }}</p>
                        </section>
                    </aside>
                </div>
            </section>
        </main>

        <ProPrototypeFooter />
    </div>
</template>
