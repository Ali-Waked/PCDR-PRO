<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import CmsPagination from '../../../Components/Dashboard/CmsPagination.vue'
import MetricCard from '../../../Components/Dashboard/MetricCard.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import MediaDetailsPanel from '../../../Components/Cms/Media/MediaDetailsPanel.vue'
import MediaEmptyState from '../../../Components/Cms/Media/MediaEmptyState.vue'
import MediaFilters from '../../../Components/Cms/Media/MediaFilters.vue'
import MediaGrid from '../../../Components/Cms/Media/MediaGrid.vue'
import MediaUploadZone from '../../../Components/Cms/Media/MediaUploadZone.vue'
import { useI18n } from '../../../composables/useI18n'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'
import { previewMode } from '../../../Support/mediaKind'

const props = defineProps({
    items: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    filters: {
        type: Object,
        default: () => ({
            type: '', kind: '', visibility: '', q: '', date_from: '', date_to: '', sort: 'newest',
        }),
    },
    stats: { type: Object, default: () => ({ all: 0, images: 0, documents: 0, restricted: 0 }) },
    kindOptions: { type: Array, default: () => [] },
    sortOptions: { type: Array, default: () => [] },
    uploadFormats: { type: Object, default: () => ({ image: [], document: [], maxKb: 10240 }) },
    can: {
        type: Object,
        default: () => ({ view: true, upload: false, update: false, delete: false }),
    },
})

const { t, localPath } = useI18n()
const { confirmDelete, finishConfirmation, notify } = useAdminNotifications()
const basePath = localPath('cms/media')

const filters = ref({
    q: props.filters?.q || '',
    type: props.filters?.type || '',
    kind: props.filters?.kind || '',
    visibility: props.filters?.visibility || '',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
    sort: props.filters?.sort || 'newest',
    view: 'grid',
})

const typeOptions = computed(() => [
    { value: 'image', label: t('cms_media_type_image', 'Image') },
    { value: 'document', label: t('cms_media_type_document', 'Document') },
])

const visibilityOptions = computed(() => [
    { value: 'public', label: t('cms_media_visibility_public', 'Public') },
    { value: 'restricted', label: t('cms_media_visibility_restricted', 'Restricted') },
])

const hasFilters = computed(() => Boolean(
    filters.value.q
    || filters.value.type
    || filters.value.kind
    || filters.value.visibility
    || filters.value.date_from
    || filters.value.date_to
    || filters.value.sort !== 'newest',
))

const emptyState = computed(() => {
    const total = Number(props.pagination?.total ?? 0)
    const currentPage = Number(props.pagination?.currentPage ?? 1)
    const lastPage = Number(props.pagination?.lastPage ?? 1)

    if (total > 0 && currentPage > lastPage) {
        return {
            title: t('cms_media_invalid_page_title'),
            message: t('cms_media_invalid_page_message'),
            icon: 'refresh',
            reset: true,
        }
    }

    if (total === 0 && ! hasFilters.value) {
        return {
            title: t('cms_media_empty_library_title'),
            message: t('cms_media_empty_library_message'),
            icon: 'image',
            reset: false,
        }
    }

    return {
        title: t('cms_media_empty_title'),
        message: t('cms_media_empty_message'),
        icon: 'search',
        reset: true,
    }
})

watch(() => props.filters, (value) => {
    filters.value = {
        ...filters.value,
        q: value?.q || '',
        type: value?.type || '',
        kind: value?.kind || '',
        visibility: value?.visibility || '',
        date_from: value?.date_from || '',
        date_to: value?.date_to || '',
        sort: value?.sort || 'newest',
    }
}, { deep: true })

function visitOptions() {
    return { preserveState: true, preserveScroll: true }
}

function submitFilters() {
    router.get(basePath, {
        q: filters.value.q,
        type: filters.value.type,
        kind: filters.value.kind,
        visibility: filters.value.visibility,
        date_from: filters.value.date_from,
        date_to: filters.value.date_to,
        sort: filters.value.sort,
    }, visitOptions())
}

function clearFilters() {
    filters.value = { ...filters.value, q: '', type: '', kind: '', visibility: '', date_from: '', date_to: '', sort: 'newest' }
    router.get(basePath, {}, visitOptions())
}

const upload = useForm({
    file: null,
    is_restricted: false,
    translations: {
        ar: { alt_text: '', caption: '' },
        en: { alt_text: '', caption: '' },
    },
})

const editForm = useForm({
    translations: {
        ar: { alt_text: '', caption: '' },
        en: { alt_text: '', caption: '' },
    },
})

const detailsId = ref(null)
const editing = ref(null)
const signedLinks = ref({})
const copiedId = ref(null)
const previewItem = ref(null)
const previewUrl = ref('')
const previewDialog = ref(null)
let previousFocus = null

const activePreviewMode = computed(() => previewMode(previewItem.value || {}))

function submitUpload() {
    if (! props.can.upload) {
        return
    }

    upload.post(basePath, {
        ...visitOptions(),
        onSuccess: () => {
            upload.reset()
            upload.is_restricted = false
        },
    })
}

function detailItem() {
    return props.items.find((item) => item.id === detailsId.value) || null
}

function detailsUrl(item) {
    return item?.accessUrl || item?.url || signedLinks.value[item?.id] || ''
}

function toggleDetails(item) {
    detailsId.value = detailsId.value === item.id ? null : item.id
    if (detailsId.value !== item.id) {
        editing.value = null
    }
}

function translatedValue(item, code, field) {
    return item.translations?.[code]?.[field] ?? (field === 'alt_text' ? item.alt : item.caption) ?? ''
}

function openEditor(item) {
    detailsId.value = item.id
    editing.value = item.id
    editForm.translations = {
        ar: {
            alt_text: translatedValue(item, 'ar', 'alt_text'),
            caption: translatedValue(item, 'ar', 'caption'),
        },
        en: {
            alt_text: translatedValue(item, 'en', 'alt_text'),
            caption: translatedValue(item, 'en', 'caption'),
        },
    }
}

function saveEditor() {
    editForm.put(`${basePath}/${editing.value}`, {
        ...visitOptions(),
        onSuccess: () => { editing.value = null },
    })
}

async function remove(item) {
    if (! (await confirmDelete(item.name || `#${item.id}`))) {
        return
    }

    router.delete(`${basePath}/${item.id}`, {
        ...visitOptions(),
        onFinish: () => {
            finishConfirmation()
            if (detailsId.value === item.id) {
                detailsId.value = null
            }
        },
    })
}

function signedLink(item) {
    if (signedLinks.value[item.id]) {
        return Promise.resolve(signedLinks.value[item.id])
    }

    return fetch(`${basePath}/${item.id}/signed`, { headers: { Accept: 'application/json' } })
        .then((response) => response.ok ? response.json() : Promise.reject(new Error('failed')))
        .then((data) => {
            signedLinks.value[item.id] = data.url
            return data.url
        })
        .catch(() => {
            notify.error(t('ui.notification.server_message'), t('ui.notification.server_title'))
            return ''
        })
}

async function resolveUrl(item) {
    return detailsUrl(item) || await signedLink(item)
}

async function copyUrl(item) {
    const url = await resolveUrl(item)
    if (! url) {
        return
    }

    try {
        await navigator.clipboard.writeText(url)
        copiedId.value = item.id
        notify.success(t('cms_media_copied'))
    } catch {
        copiedId.value = null
        notify.error(t('cms_media_copy_failed'))
    }
}

async function download(item) {
    const url = await resolveUrl(item)
    if (! url) {
        return
    }

    const link = document.createElement('a')
    link.href = url
    link.download = item.name || 'file'
    link.rel = 'noopener'
    document.body.appendChild(link)
    link.click()
    link.remove()
}

function previewFocusables() {
    const root = previewDialog.value
    if (! root) {
        return []
    }

    return [...root.querySelectorAll('button:not([disabled]),[href],input:not([disabled]),[tabindex]:not([tabindex="-1"])')]
}

function onPreviewKeydown(event) {
    if (! previewUrl.value) {
        return
    }

    if (event.key === 'Escape') {
        event.preventDefault()
        closePreview()
        return
    }

    if (event.key !== 'Tab') {
        return
    }

    const items = previewFocusables()
    if (! items.length) {
        event.preventDefault()
        return
    }

    const first = items[0]
    const last = items[items.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (! event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

watch(previewUrl, (url) => {
    if (url) {
        previousFocus = document.activeElement
        document.addEventListener('keydown', onPreviewKeydown)
        requestAnimationFrame(() => (previewFocusables()[0] || previewDialog.value)?.focus?.())
        return
    }

    document.removeEventListener('keydown', onPreviewKeydown)
    previousFocus?.focus?.()
    previousFocus = null
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onPreviewKeydown)
    previousFocus?.focus?.()
})

async function openPreview(item) {
    if (previewMode(item) === '') {
        return
    }

    const url = await resolveUrl(item)
    if (! url) {
        return
    }

    previewItem.value = item
    previewUrl.value = url
}

function closePreview() {
    previewItem.value = null
    previewUrl.value = ''
}
</script>

<template>
    <CmsDashboardLayout
        :title="t('ui.cms_media')"
        :subtitle="t('cms_upload_hint')"
        :eyebrow="t('ui.dashboard_editorial')"
    >
        <div class="pc-stack pcdr-enter">
            <div class="pc-metric-grid">
                <MetricCard :label="t('ui.all')" :value="stats.all ?? 0" icon="folder" tone="sand" />
                <MetricCard :label="t('cms_media_type_image')" :value="stats.images ?? 0" icon="image" />
                <MetricCard :label="t('cms_media_type_document')" :value="stats.documents ?? 0" icon="file" />
                <MetricCard :label="t('cms_restricted_file')" :value="stats.restricted ?? 0" icon="lock" tone="navy" />
            </div>

            <section v-if="can.upload" class="pc-card" aria-labelledby="media-upload-title">
                <div class="pc-card__head">
                    <div>
                        <h2 id="media-upload-title" class="pc-card__title">{{ t('cms_upload') }}</h2>
                        <p class="pc-card__subtitle">{{ t('cms_upload_hint') }}</p>
                    </div>
                </div>
                <div class="pc-card__body">
                    <MediaUploadZone :form="upload" :formats="uploadFormats" :can-upload="can.upload" @submit="submitUpload" />
                </div>
            </section>

            <section class="pc-card" aria-labelledby="media-library-title">
                <div class="pc-card__head">
                    <div>
                        <h2 id="media-library-title" class="pc-card__title">{{ t('ui.cms_media') }}</h2>
                        <p class="pc-card__subtitle">{{ pagination.total ?? 0 }} {{ t('documents') }}</p>
                    </div>
                    <span class="pc-badge pc-badge--teal">{{ items.length }}</span>
                </div>

                <div class="pc-card__body pc-stack">
                    <MediaFilters
                        v-model="filters"
                        :type-options="typeOptions"
                        :kind-options="kindOptions"
                        :visibility-options="visibilityOptions"
                        :sort-options="sortOptions"
                        :has-filters="hasFilters"
                        @submit="submitFilters"
                        @clear="clearFilters"
                    />

                    <MediaEmptyState v-if="!items.length" :state="emptyState" @clear="clearFilters" />
                    <MediaGrid
                        v-else
                        :items="items"
                        :view="filters.view"
                        :links="signedLinks"
                        :can="can"
                        @details="toggleDetails"
                        @preview="openPreview"
                        @download="download"
                        @copy="copyUrl"
                        @remove="remove"
                    />

                    <span v-if="copiedId && items.some((item) => item.id === copiedId)" class="pc-hint" role="status">
                        {{ t('cms_media_copied') }}
                    </span>

                    <MediaDetailsPanel
                        v-if="detailItem()"
                        :key="detailItem().id"
                        :item="detailItem()"
                        :url="detailsUrl(detailItem())"
                        :can="can"
                        :editing="editing === detailItem().id"
                        :edit-form="editForm"
                        @close="detailsId = null"
                        @preview="openPreview(detailItem())"
                        @download="download(detailItem())"
                        @copy="copyUrl(detailItem())"
                        @open-editor="openEditor(detailItem())"
                        @save="saveEditor"
                        @cancel-edit="editing = null"
                        @remove="remove(detailItem())"
                    />

                    <CmsPagination :pagination="pagination" />
                </div>
            </section>
        </div>

        <div v-if="previewUrl" class="pc-modal-backdrop" @click.self="closePreview">
            <div ref="previewDialog" class="pc-modal pc-modal--wide" role="dialog" aria-modal="true" :aria-label="t('cms_preview')" tabindex="-1">
                <div class="pc-modal__head">
                    <h2 class="pc-modal__title">{{ previewItem?.name }}</h2>
                    <button class="pc-icon-button" type="button" :aria-label="t('cms_close')" @click="closePreview">
                        <UiIcon name="close" :size="16" />
                    </button>
                </div>
                <div class="pc-modal__body pc-media-preview">
                    <img
                        v-if="activePreviewMode === 'image'"
                        class="pc-media-preview-image"
                        :src="previewUrl"
                        :alt="previewItem?.alt || previewItem?.name"
                    >
                    <iframe
                        v-else-if="activePreviewMode === 'pdf'"
                        class="pc-media-preview-frame"
                        :src="previewUrl"
                        :title="previewItem?.name || t('cms_media_preview_pdf')"
                    />
                    <video
                        v-else-if="activePreviewMode === 'video'"
                        class="pc-media-preview-media"
                        :src="previewUrl"
                        controls
                        playsinline
                    />
                    <audio
                        v-else-if="activePreviewMode === 'audio'"
                        class="pc-media-preview-audio"
                        :src="previewUrl"
                        controls
                    />
                    <a v-else class="pc-btn pc-btn--secondary" :href="previewUrl" target="_blank" rel="noopener noreferrer">
                        <UiIcon name="download" :size="16" />
                        {{ t('open_original') }}
                    </a>
                </div>
            </div>
        </div>
    </CmsDashboardLayout>
</template>
