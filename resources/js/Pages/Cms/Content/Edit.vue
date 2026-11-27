<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import CmsStatusBadge from '../../../Components/Dashboard/CmsStatusBadge.vue'
import ContentFormSection from '../../../Components/Cms/ContentFormSection.vue'
import ContentMediaField from '../../../Components/Cms/ContentMediaField.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import MultiSelect from '../../../Components/Selection/MultiSelect.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import StatusSelect from '../../../Components/Selection/StatusSelect.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import RichTextEditor from '../../../Components/Cms/RichTextEditor.vue'
import { useI18n } from '../../../composables/useI18n'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'

const props = defineProps({
    resource: { type: String, required: true },
    label: { type: String, required: true },
    routeBase: { type: String, required: true },
    pageTitle: { type: String, default: '' },
    pageDescription: { type: String, default: '' },
    record: { type: Object, default: null },
    translations: { type: Object, required: true },
    locales: { type: Array, default: () => ['ar', 'en'] },
    activeLocale: { type: String, default: '' },
    statuses: { type: Array, default: () => [] },
    revisions: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
    transitions: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
})

const { t, localPath, formatDate, locale } = useI18n()
const {
    confirmStatus,
    confirmArchive,
    confirmReverse,
    finishConfirmation,
    setConfirmationBusy,
} = useAdminNotifications()

const richFields = ['body', 'description', 'objectives']

const segments = {
    'cms.pillars': 'pillars',
    'cms.programs': 'programs',
    'cms.projects': 'projects',
    'cms.news': 'news',
    'cms.publications': 'publications',
}

const basePath = computed(() => localPath(`cms/${segments[props.routeBase] || props.resource}`))
const isCreate = computed(() => props.record === null)
const availableLocales = computed(() => props.locales?.length ? props.locales : ['ar', 'en'])
const activeLocale = ref(props.activeLocale || locale.value || availableLocales.value[0] || 'ar')
const title = computed(() => props.pageTitle || props.label || t('ui.cms_new_item'))
const description = computed(() => props.pageDescription || t('ui.cms_content_page.breadcrumb'))
const breadcrumb = computed(() => [
    { label: t('ui.cms_content_page.breadcrumb'), href: localPath('cms') },
    { label: title.value, href: basePath.value },
    ...(props.record ? [{ label: t('ui.cms_edit') }] : []),
])
const previewPath = computed(() => props.record
    ? localPath(`cms/preview/${segments[props.routeBase] || props.resource}/${props.record.id}`)
    : '')

function initialExtras() {
    const attrs = props.record?.attributes || {}

    switch (props.resource) {
        case 'pillars':
            return {
                code: attrs.code ?? '',
                featured_image_id: attrs.featured_image_id ?? '',
                is_featured: Boolean(attrs.is_featured),
            }
        case 'programs':
            return {
                strategic_pillar_id: attrs.strategic_pillar_id ?? '',
                start_date: attrs.start_date ?? '',
                end_date: attrs.end_date ?? '',
                featured_image_id: attrs.featured_image_id ?? '',
            }
        case 'projects':
            return {
                program_id: attrs.program_id ?? '',
                strategic_pillar_id: attrs.strategic_pillar_id ?? '',
                project_status: attrs.project_status ?? props.options.projectStatuses?.[0]?.value ?? 'planned',
                start_date: attrs.start_date ?? '',
                end_date: attrs.end_date ?? '',
                featured_image_id: attrs.featured_image_id ?? '',
            }
        case 'news':
            return {
                article_category_id: attrs.article_category_id ?? '',
                strategic_pillar_id: attrs.strategic_pillar_id ?? '',
                cover_media_id: attrs.cover_media_id ?? '',
                event_date: attrs.event_date ?? '',
                is_featured: Boolean(attrs.is_featured),
                tags: Array.isArray(attrs.tags) ? [...attrs.tags] : [],
            }
        case 'publications':
            return {
                category: attrs.category ?? 'institutional',
                strategic_pillar_id: attrs.strategic_pillar_id ?? '',
                document_media_id: attrs.document_media_id ?? '',
                cover_media_id: attrs.cover_media_id ?? '',
                publication_date: attrs.publication_date ?? '',
                version: attrs.version ?? '',
                is_restricted: Boolean(attrs.is_restricted),
            }
        default:
            return {}
    }
}

const hasExtras = computed(() => Object.keys(initialExtras()).length > 0)

const form = useForm({
    status: props.record?.status || 'draft',
    published_at: props.record?.publishedAt || '',
    display_order: props.record?.displayOrder ?? 0,
    translations: JSON.parse(JSON.stringify(props.translations)),
    ...initialExtras(),
})

const errorCount = computed(() => Object.keys(form.errors).length)

function fieldLabel(field) {
    const map = {
        title: 'cms_title',
        slug: 'cms_slug',
        summary: 'cms_summary',
        excerpt: 'cms_summary',
        body: 'cms_body',
        description: 'cms_description',
        objectives: 'ui.approach',
        target_groups: 'ui.target_groups',
        geographic_coverage: 'governance',
        seo_title: 'cms_seo',
        seo_description: 'cms_seo',
        short_title: 'cms_title',
        canonical_url: 'cms_seo',
        og_image: 'cms_seo',
        robots: 'cms_seo',
    }

    return t(map[field] || field)
}

function isLongField(field) {
    return ['body', 'description', 'objectives', 'target_groups'].includes(field)
}

function isRichField(field) {
    return richFields.includes(field)
}

const mediaOptions = computed(() => Array.isArray(props.options?.media) ? props.options.media : [])
const inlineMedia = computed(() => mediaOptions.value.filter((item) => !item.type || item.type === 'image'))

const mediaLabels = computed(() => ({
    select: t('cms_select_media'),
    change: t('ui.edit'),
    remove: t('cms_remove_item'),
    title: t('cms_media_picker'),
    insert: t('cms_insert_media'),
    empty: t('cms_no_records'),
    close: t('cms_close'),
}))

const mediaFields = computed(() => {
    if (props.resource === 'publications') {
        return ['document_media_id', 'cover_media_id']
    }
    if (props.resource === 'news') {
        return ['cover_media_id']
    }
    if (['pillars', 'programs', 'projects'].includes(props.resource)) {
        return ['featured_image_id']
    }

    return []
})

function mediaFieldLabel(field) {
    return t(field === 'document_media_id' ? 'documents' : 'ui.cms_media')
}

function mediaFieldAccept(field) {
    return field === 'document_media_id' ? 'document' : 'image'
}

const relationOptions = computed(() => ({
    pillars: (props.options?.pillars ?? []).map((item) => ({ value: item.id, label: item.label })),
    programs: (props.options?.programs ?? []).map((item) => ({ value: item.id, label: item.label })),
    newsCategories: (props.options?.categories ?? []).map((item) => ({ value: item.id, label: item.label })),
    publicationCategories: props.options?.categories ?? [],
    projectStatuses: props.options?.projectStatuses ?? [],
    tags: (props.options?.tags ?? []).map((item) => ({ value: item.id, label: item.label })),
}))

const workflowActions = computed(() => props.record ? (props.transitions?.[props.record.status] ?? []) : [])

const allowedStatuses = computed(() => {
    if (isCreate.value || workflowActions.value.length === 0) {
        return props.statuses
    }

    const current = props.statuses.find((status) => status.value === props.record.status)
    return [
        current ? { ...current, disabled: true } : null,
        ...workflowActions.value.map((action) => ({
            value: action.value,
            label: action.label,
            description: action.description,
        })),
    ].filter(Boolean)
})

function transitionMessage(action, subject) {
    const description = action?.description || action?.label || ''
    return description ? `${subject} — ${description}` : subject
}

async function applyTransition(status) {
    if (!props.record) {
        form.status = status
        return
    }

    if (status === props.record.status) {
        return
    }

    const action = workflowActions.value.find((entry) => entry.value === status)
    if (!action) {
        return
    }

    if (form.isDirty && ! window.confirm(t('ui.cms_content_page.dirty_status_confirm'))) {
        return
    }

    const subject = props.record.title || `#${props.record.id}`
    if (action.confirmation === 'archive'
        && ! await confirmArchive(subject, { message: transitionMessage(action, subject) })) {
        return
    }
    if (action.confirmation === 'publish'
        && ! await confirmStatus(subject, { message: transitionMessage(action, subject) })) {
        return
    }
    if (action.confirmation === 'reverse'
        && ! await confirmReverse(subject, { message: transitionMessage(action, subject) })) {
        return
    }

    setConfirmationBusy(true)
    router.post(`${basePath.value}/${props.record.id}/status`, { status }, {
        preserveScroll: true,
        onSuccess: () => { form.status = status },
        onFinish: () => {
            setConfirmationBusy(false)
            finishConfirmation()
        },
    })
}

async function backToIndex() {
    if (form.isDirty && ! window.confirm(t('ui.cms_content_page.unsaved_confirm'))) {
        return
    }

    router.visit(basePath.value)
}

function focusFirstError() {
    window.requestAnimationFrame(() => {
        document.querySelector('[aria-invalid="true"]')?.focus()
    })
}

function submit() {
    const url = isCreate.value ? basePath.value : `${basePath.value}/${props.record.id}`
    const options = {
        preserveScroll: true,
        onError: focusFirstError,
    }

    if (isCreate.value) {
        form.post(url, options)
        return
    }

    form.put(url, options)
}

function beforeUnload(event) {
    if (!form.isDirty) {
        return
    }

    event.preventDefault()
    event.returnValue = ''
}

watch(() => props.activeLocale, (value) => {
    if (value) {
        activeLocale.value = value
    }
})

onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
    <CmsDashboardLayout
        :title="title"
        :subtitle="description"
        :breadcrumb="breadcrumb"
        :eyebrow="t('ui.cms_content_page.breadcrumb')"
    >
        <div class="pc-stack pc-content-editor pcdr-enter">
            <div class="pc-toolbar pc-content-editor__toolbar">
                <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="backToIndex">
                    <UiIcon name="arrowForward" :size="15" />
                    {{ t('ui.back_to_list') }}
                </button>
                <CmsStatusBadge v-if="record" :status="record.status" />
                <div class="pc-toolbar__spacer" />
                <Link v-if="previewPath" :href="previewPath" class="pc-btn pc-btn--secondary pc-btn--sm">
                    <UiIcon name="eye" :size="15" />
                    {{ t('ui.cms_content_page.preview') }}
                </Link>
            </div>

            <div v-if="errorCount" class="pc-content-editor__errors" role="alert">
                <UiIcon name="alert" :size="17" />
                <span>{{ t('ui.cms_content_page.validation_title') }} ({{ errorCount }})</span>
            </div>

            <form class="pc-stack" @submit.prevent="submit">
                <div class="pc-grid pc-grid--content">
                    <div class="pc-stack">
                        <ContentFormSection
                            :title="t('ui.cms_translations')"
                            :description="t('ui.dashboard_editorial')"
                            :badge="availableLocales.length"
                        >
                            <div class="pc-tabs" role="tablist" :aria-label="t('ui.cms_translations')">
                                <button
                                    v-for="code in availableLocales"
                                    :key="`tab-${code}`"
                                    type="button"
                                    class="pc-tab"
                                    :class="{ 'is-active': activeLocale === code }"
                                    :aria-selected="activeLocale === code ? 'true' : 'false'"
                                    @click="activeLocale = code"
                                >
                                    {{ code === 'ar' ? t('cms_arabic') : t('cms_english') }}
                                </button>
                            </div>

                            <div
                                v-for="code in availableLocales"
                                v-show="activeLocale === code"
                                :key="code"
                                class="pc-translation-pane"
                            >
                                <template v-if="activeLocale === code">
                                    <div
                                        v-for="(value, field) in form.translations[code]"
                                        :key="`${code}-${field}`"
                                        class="pc-field"
                                    >
                                        <label class="pc-label" :for="`translation-${code}-${field}`">
                                            {{ fieldLabel(field) }}
                                            <span class="pc-label__optional">
                                                {{ ['title', 'slug'].includes(field) ? t('cms_required') : t('cms_optional') }}
                                            </span>
                                        </label>

                                        <RichTextEditor
                                            v-if="isRichField(field) && activeLocale === code"
                                            :id="`translation-${code}-${field}`"
                                            v-model="form.translations[code][field]"
                                            :locale="code"
                                            :media="inlineMedia"
                                            :error="form.errors[`translations.${code}.${field}`] || ''"
                                        />
                                        <textarea
                                            v-else-if="isLongField(field)"
                                            :id="`translation-${code}-${field}`"
                                            v-model="form.translations[code][field]"
                                            class="pc-textarea"
                                            :rows="field === 'body' ? 8 : 4"
                                            :aria-invalid="!!form.errors[`translations.${code}.${field}`]"
                                        ></textarea>
                                        <input
                                            v-else
                                            :id="`translation-${code}-${field}`"
                                            v-model="form.translations[code][field]"
                                            class="pc-input"
                                            type="text"
                                            :dir="['slug', 'canonical_url', 'og_image'].includes(field) ? 'ltr' : undefined"
                                            :aria-invalid="!!form.errors[`translations.${code}.${field}`]"
                                        >
                                        <span
                                            v-if="form.errors[`translations.${code}.${field}`]"
                                            class="pc-error"
                                            role="alert"
                                        >{{ form.errors[`translations.${code}.${field}`] }}</span>
                                    </div>
                                </template>
                            </div>
                        </ContentFormSection>

                        <ContentFormSection
                            v-if="previewPath"
                            :title="t('ui.cms_content_page.preview')"
                            :description="t('ui.cms_preview_notice')"
                        >
                            <Link :href="previewPath" class="pc-btn pc-btn--secondary">
                                <UiIcon name="eye" :size="15" />
                                {{ t('ui.cms_preview_title') }}
                            </Link>
                        </ContentFormSection>
                    </div>

                    <div class="pc-stack">
                        <ContentFormSection
                            :title="t('ui.cms_basic_data')"
                            :description="t('ui.cms_publish_status')"
                        >
                            <div class="pc-form-grid">
                                <div class="pc-field">
                                    <StatusSelect
                                        :model-value="form.status"
                                        name="status"
                                        :label="t('cms_publish_status')"
                                        :options="allowedStatuses"
                                        :loading="form.processing"
                                        :error="form.errors.status || ''"
                                        :searchable="allowedStatuses.length > 8"
                                        @update:model-value="applyTransition"
                                    />
                                </div>
                                <label class="pc-field">
                                    <span class="pc-label">{{ t('published_on') }}</span>
                                    <input v-model="form.published_at" class="pc-input" type="date" dir="ltr">
                                </label>
                                <label class="pc-field">
                                    <span class="pc-label">{{ t('cms_order') }}</span>
                                    <input v-model.number="form.display_order" class="pc-input" type="number" min="0" dir="ltr">
                                </label>
                            </div>
                        </ContentFormSection>

                        <ContentFormSection
                            v-if="hasExtras"
                            :title="t('ui.cms_relations')"
                            :description="t('ui.dashboard_editorial')"
                        >
                            <div class="pc-form-grid">
                                <template v-if="resource === 'pillars'">
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('ui.cms_content_page.code') }}</span>
                                        <input v-model="form.code" class="pc-input" type="text" maxlength="2" dir="ltr">
                                    </label>
                                    <label class="pc-checkbox">
                                        <input v-model="form.is_featured" type="checkbox">
                                        <span>{{ t('cms_section_enabled') }}</span>
                                    </label>
                                </template>

                                <template v-if="resource === 'programs'">
                                    <div class="pc-field">
                                        <SelectionControl
                                            v-model="form.strategic_pillar_id"
                                            :label="t('nav.pillars')"
                                            :options="relationOptions.pillars"
                                            :placeholder="t('cms_optional')"
                                            :error="form.errors.strategic_pillar_id || ''"
                                            clearable
                                            :searchable="relationOptions.pillars.length > 6"
                                        />
                                    </div>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('start') }}</span>
                                        <input v-model="form.start_date" class="pc-input" type="date" dir="ltr">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('end') }}</span>
                                        <input v-model="form.end_date" class="pc-input" type="date" dir="ltr">
                                    </label>
                                </template>

                                <template v-if="resource === 'projects'">
                                    <div class="pc-field">
                                        <SelectionControl
                                            v-model="form.program_id"
                                            :label="t('ui.cms_programs')"
                                            :options="relationOptions.programs"
                                            :placeholder="t('cms_optional')"
                                            :error="form.errors.program_id || ''"
                                            clearable
                                            :searchable="relationOptions.programs.length > 6"
                                        />
                                    </div>
                                    <div class="pc-field">
                                        <SelectionControl
                                            v-model="form.strategic_pillar_id"
                                            :label="t('nav.pillars')"
                                            :options="relationOptions.pillars"
                                            :placeholder="t('cms_optional')"
                                            :error="form.errors.strategic_pillar_id || ''"
                                            clearable
                                            :searchable="relationOptions.pillars.length > 6"
                                        />
                                    </div>
                                    <div class="pc-field">
                                        <StatusSelect
                                            v-model="form.project_status"
                                            :label="t('ui.cms_content_page.filters.project_status')"
                                            :options="relationOptions.projectStatuses"
                                            :error="form.errors.project_status || ''"
                                            :searchable="relationOptions.projectStatuses.length > 8"
                                        />
                                    </div>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('start') }}</span>
                                        <input v-model="form.start_date" class="pc-input" type="date" dir="ltr">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('end') }}</span>
                                        <input v-model="form.end_date" class="pc-input" type="date" dir="ltr">
                                    </label>
                                </template>

                                <template v-if="resource === 'news'">
                                    <div class="pc-field">
                                        <SelectionControl
                                            v-model="form.article_category_id"
                                            :label="t('ui.category')"
                                            :options="relationOptions.newsCategories"
                                            :placeholder="t('cms_optional')"
                                            :error="form.errors.article_category_id || ''"
                                            clearable
                                            :searchable="relationOptions.newsCategories.length > 6"
                                        />
                                    </div>
                                    <div class="pc-field">
                                        <SelectionControl
                                            v-model="form.strategic_pillar_id"
                                            :label="t('nav.pillars')"
                                            :options="relationOptions.pillars"
                                            :placeholder="t('cms_optional')"
                                            :error="form.errors.strategic_pillar_id || ''"
                                            clearable
                                            :searchable="relationOptions.pillars.length > 6"
                                        />
                                    </div>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('activity') }}</span>
                                        <input v-model="form.event_date" class="pc-input" type="date" dir="ltr">
                                    </label>
                                    <label class="pc-checkbox">
                                        <input v-model="form.is_featured" type="checkbox">
                                        <span>{{ t('cms_section_enabled') }}</span>
                                    </label>
                                    <div v-if="relationOptions.tags.length" class="pc-field pc-field--full">
                                        <MultiSelect
                                            v-model="form.tags"
                                            :label="t('available_options')"
                                            :options="relationOptions.tags"
                                            :error="form.errors.tags || ''"
                                            clearable
                                            :searchable="relationOptions.tags.length > 6"
                                        />
                                    </div>
                                </template>

                                <template v-if="resource === 'publications'">
                                    <div class="pc-field">
                                        <SelectionControl
                                            v-model="form.category"
                                            :label="t('ui.category')"
                                            :options="relationOptions.publicationCategories"
                                            :error="form.errors.category || ''"
                                            :searchable="relationOptions.publicationCategories.length > 6"
                                        />
                                    </div>
                                    <div class="pc-field">
                                        <SelectionControl
                                            v-model="form.strategic_pillar_id"
                                            :label="t('nav.pillars')"
                                            :options="relationOptions.pillars"
                                            :placeholder="t('cms_optional')"
                                            :error="form.errors.strategic_pillar_id || ''"
                                            clearable
                                            :searchable="relationOptions.pillars.length > 6"
                                        />
                                    </div>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('published_on') }}</span>
                                        <input v-model="form.publication_date" class="pc-input" type="date" dir="ltr">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('version') }}</span>
                                        <input v-model="form.version" class="pc-input" type="text" maxlength="32" dir="ltr">
                                    </label>
                                    <label class="pc-checkbox">
                                        <input v-model="form.is_restricted" type="checkbox">
                                        <span>{{ t('cms_restricted_file') }}</span>
                                    </label>
                                </template>

                                <div
                                    v-for="field in mediaFields"
                                    :key="`media-${field}`"
                                    class="pc-field pc-field--full"
                                >
                                    <ContentMediaField
                                        v-model="form[field]"
                                        :label="mediaFieldLabel(field)"
                                        :options="mediaOptions"
                                        :labels="mediaLabels"
                                        :accept="mediaFieldAccept(field)"
                                    />
                                </div>
                            </div>
                        </ContentFormSection>

                        <ContentFormSection
                            v-if="revisions.length"
                            :title="t('ui.cms_revisions')"
                            :description="t('ui.cms_created_at')"
                            :badge="revisions.length"
                        >
                            <div class="pc-timeline">
                                <article v-for="revision in revisions" :key="revision.id" class="pc-timeline__item">
                                    <span class="pc-timeline__dot" aria-hidden="true"></span>
                                    <div class="pc-table__actions">
                                        <CmsStatusBadge :status="revision.status" />
                                        <span class="pc-badge pc-badge--slate" dir="ltr">{{ String(revision.locale).toUpperCase() }}</span>
                                    </div>
                                    <p class="pc-timeline__body">{{ revision.note }}</p>
                                    <p class="pc-timeline__meta">{{ revision.actor || t('ui.cms_unknown_actor') }} · {{ formatDate(revision.created_at) }}</p>
                                </article>
                            </div>
                        </ContentFormSection>
                        <EmptyState v-else :title="t('cms_no_revisions')" icon="history" />
                    </div>
                </div>

                <div class="pc-form-actions">
                    <button
                        class="pc-btn pc-btn--primary"
                        type="submit"
                        :disabled="form.processing"
                        :aria-busy="form.processing ? 'true' : 'false'"
                    >
                        <UiIcon name="check" :size="16" />
                        {{ t('cms_save') }}
                    </button>
                    <span v-if="form.errors.status" class="pc-error" role="alert">{{ form.errors.status }}</span>
                </div>
            </form>
        </div>
    </CmsDashboardLayout>
</template>
