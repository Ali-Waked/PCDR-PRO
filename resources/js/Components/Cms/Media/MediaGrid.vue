<script setup>
import UiIcon from '../../Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'
import { detectKind, previewMode } from '../../../Support/mediaKind'
import MediaVisual from './MediaVisual.vue'

const props = defineProps({
    items: { type: Array, required: true },
    view: { type: String, default: 'grid' },
    links: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({ update: false, delete: false }) },
})

const emit = defineEmits(['details', 'preview', 'download', 'copy', 'remove'])
const { t, formatDate } = useI18n()

function accessUrl(item) {
    return item.accessUrl || item.url || props.links[item.id] || ''
}

function kindLabel(item) {
    return t(`cms_media_kind_${detectKind(item)}`, detectKind(item))
}

function canPreview(item) {
    return previewMode(item) !== '' && accessUrl(item) !== ''
}

function canDownload(item) {
    return accessUrl(item) !== ''
}
</script>

<template>
    <div class="pc-media-results" :class="`pc-media-results--${view}`">
        <div v-if="view === 'grid'" class="pc-media-grid">
            <article v-for="item in items" :key="item.id" class="pc-media-tile">
                <MediaVisual :item="item" variant="tile" />

                <div class="pc-media-tile__body">
                    <span class="pc-media-tile__name" :title="item.name">{{ item.name }}</span>
                    <span class="pc-list__meta" dir="ltr">{{ item.size }} · {{ item.extension || kindLabel(item) }}</span>
                    <div class="pc-table__actions">
                        <span class="pc-badge pc-badge--slate">{{ kindLabel(item) }}</span>
                        <span
                            class="pc-badge"
                            :class="item.restricted ? 'pc-badge--sand' : 'pc-badge--teal'"
                        >
                            <UiIcon v-if="item.restricted" name="lock" :size="12" />
                            {{ item.restricted ? t('cms_media_visibility_restricted') : t('cms_media_visibility_public') }}
                        </span>
                    </div>

                    <div class="pc-media-tile__actions">
                        <button
                            class="pc-btn pc-btn--secondary pc-btn--sm"
                            type="button"
                            @click="emit('details', item)"
                        >
                            <UiIcon name="info" :size="14" />
                            {{ t('cms_media_details') }}
                        </button>
                        <button
                            v-if="canPreview(item)"
                            class="pc-icon-button"
                            type="button"
                            :title="t('cms_preview')"
                            :aria-label="`${t('cms_preview')} — ${item.name}`"
                            @click="emit('preview', item)"
                        >
                            <UiIcon name="eye" :size="15" />
                        </button>
                        <button
                            v-if="canDownload(item)"
                            class="pc-icon-button"
                            type="button"
                            :title="t('download')"
                            :aria-label="`${t('download')} — ${item.name}`"
                            @click="emit('download', item)"
                        >
                            <UiIcon name="download" :size="15" />
                        </button>
                        <button
                            class="pc-icon-button"
                            type="button"
                            :title="t('cms_media_copy_url')"
                            :aria-label="`${t('cms_media_copy_url')} — ${item.name}`"
                            @click="emit('copy', item)"
                        >
                            <UiIcon name="copy" :size="15" />
                        </button>
                        <button
                            v-if="can.delete"
                            class="pc-icon-button pc-icon-button--danger"
                            type="button"
                            :title="t('ui.delete')"
                            :aria-label="`${t('ui.delete')} — ${item.name}`"
                            @click="emit('remove', item)"
                        >
                            <UiIcon name="trash" :size="15" />
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="pc-media-list" role="table">
            <div class="pc-media-list__head" role="row">
                <span role="columnheader">{{ t('file', t('cms_type')) }}</span>
                <span role="columnheader">{{ t('cms_type', t('ui.status')) }}</span>
                <span role="columnheader">{{ t('cms_media_file_size') }}</span>
                <span role="columnheader">{{ t('cms_media_uploaded_at') }}</span>
                <span role="columnheader" class="is-end">{{ t('ui.actions', '') }}</span>
            </div>

            <div v-for="item in items" :key="item.id" class="pc-media-list__row" role="row">
                <div class="pc-media-list__file" role="cell">
                    <MediaVisual :item="item" variant="list" />
                    <div class="pc-media-list__name">
                        <button type="button" class="pc-link-button" @click="emit('details', item)">{{ item.name }}</button>
                        <span class="pc-list__meta" dir="ltr">{{ item.mime }}</span>
                    </div>
                </div>
                <div role="cell">
                    <span class="pc-badge pc-badge--slate">{{ kindLabel(item) }}</span>
                    <span v-if="item.restricted" class="pc-badge pc-badge--sand">
                        <UiIcon name="lock" :size="12" />
                        {{ t('cms_media_visibility_restricted') }}
                    </span>
                </div>
                <div role="cell" dir="ltr">{{ item.size }}</div>
                <div role="cell">{{ formatDate(item.createdAt) }}</div>
                <div class="pc-media-list__actions" role="cell">
                    <button
                        v-if="canPreview(item)"
                        class="pc-icon-button"
                        type="button"
                        :title="t('cms_preview')"
                        :aria-label="`${t('cms_preview')} — ${item.name}`"
                        @click="emit('preview', item)"
                    >
                        <UiIcon name="eye" :size="15" />
                    </button>
                    <button
                        v-if="canDownload(item)"
                        class="pc-icon-button"
                        type="button"
                        :title="t('download')"
                        :aria-label="`${t('download')} — ${item.name}`"
                        @click="emit('download', item)"
                    >
                        <UiIcon name="download" :size="15" />
                    </button>
                    <button
                        class="pc-icon-button"
                        type="button"
                        :title="t('cms_media_copy_url')"
                        :aria-label="`${t('cms_media_copy_url')} — ${item.name}`"
                        @click="emit('copy', item)"
                    >
                        <UiIcon name="copy" :size="15" />
                    </button>
                    <button
                        v-if="can.delete"
                        class="pc-icon-button pc-icon-button--danger"
                        type="button"
                        :title="t('ui.delete')"
                        :aria-label="`${t('ui.delete')} — ${item.name}`"
                        @click="emit('remove', item)"
                    >
                        <UiIcon name="trash" :size="15" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
