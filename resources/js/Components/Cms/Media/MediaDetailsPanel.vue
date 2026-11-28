<script setup>
import { computed } from 'vue'
import UiIcon from '../../Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'
import { detectKind, previewMode } from '../../../Support/mediaKind'
import MediaVisual from './MediaVisual.vue'

const props = defineProps({
    item: { type: Object, required: true },
    url: { type: String, default: '' },
    can: { type: Object, default: () => ({ update: false, delete: false }) },
    editing: { type: Boolean, default: false },
    editForm: { type: Object, required: true },
})

const emit = defineEmits([
    'close', 'preview', 'download', 'copy', 'open-editor', 'save', 'cancel-edit', 'remove',
])
const { t, formatDate } = useI18n()

const kind = computed(() => detectKind(props.item))
const usage = computed(() => props.item.usage || { count: 0, locations: [] })
const previewable = computed(() => previewMode(props.item) !== '' && props.url !== '')
const downloadable = computed(() => props.url !== '')
</script>

<template>
    <section class="pc-card pc-media-details" :aria-label="t('cms_media_details')">
        <div class="pc-card__head">
            <div>
                <h2 class="pc-card__title">{{ item.name }}</h2>
                <p class="pc-card__subtitle" dir="ltr">{{ item.mime }}</p>
            </div>
            <button class="pc-icon-button" type="button" :aria-label="t('cms_close')" @click="emit('close')">
                <UiIcon name="close" :size="16" />
            </button>
        </div>

        <div class="pc-media-details__body">
            <div class="pc-media-details__preview">
                <MediaVisual :item="item" :src="url" variant="detail" />
                <p v-if="previewMode(item) === ''" class="pc-hint">{{ t('cms_media_no_preview') }}</p>
            </div>

            <div class="pc-media-details__info">
                <dl class="pc-detail-list">
                    <div class="pc-detail-list__item">
                        <dt>{{ t('cms_type', t('ui.status')) }}</dt>
                        <dd>{{ t(`cms_media_kind_${kind}`, kind) }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('cms_media_visibility') }}</dt>
                        <dd>{{ item.restricted ? t('cms_media_visibility_restricted') : t('cms_media_visibility_public') }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('cms_media_file_size') }}</dt>
                        <dd dir="ltr">{{ item.size }} ({{ item.sizeBytes }} B)</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('cms_media_mime_type') }}</dt>
                        <dd dir="ltr">{{ item.mime }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('cms_media_extension') }}</dt>
                        <dd dir="ltr">{{ item.extension || '—' }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('cms_media_uploaded_at') }}</dt>
                        <dd>{{ formatDate(item.createdAt) }}</dd>
                    </div>
                    <div v-if="item.width && item.height" class="pc-detail-list__item">
                        <dt>{{ t('cms_media_dimensions') }}</dt>
                        <dd dir="ltr">{{ item.width }} × {{ item.height }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('ui.alt_text') }}</dt>
                        <dd>{{ item.alt || '—' }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('ui.cms_description') }}</dt>
                        <dd>{{ item.caption || '—' }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('cms_media_used_in') }}</dt>
                        <dd>
                            <span v-if="usage.count === 0">{{ t('cms_media_no_usage') }}</span>
                            <span v-else>
                                {{ usage.count }}
                                <span v-for="location in usage.locations" :key="location.label" class="pc-media-usage">
                                    {{ location.label }} ({{ location.count }})
                                </span>
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="pc-table__actions">
                    <button v-if="previewable" class="pc-btn pc-btn--secondary pc-btn--sm" type="button" @click="emit('preview')">
                        <UiIcon name="eye" :size="14" />
                        {{ t('cms_preview') }}
                    </button>
                    <button v-if="downloadable" class="pc-btn pc-btn--secondary pc-btn--sm" type="button" @click="emit('download')">
                        <UiIcon name="download" :size="14" />
                        {{ t('download') }}
                    </button>
                    <button class="pc-btn pc-btn--ghost pc-btn--sm" type="button" @click="emit('copy')">
                        <UiIcon name="copy" :size="14" />
                        {{ t('cms_media_copy_url') }}
                    </button>
                    <button v-if="can.update" class="pc-btn pc-btn--secondary pc-btn--sm" type="button" @click="emit('open-editor')">
                        <UiIcon name="edit" :size="14" />
                        {{ t('ui.edit') }}
                    </button>
                    <button v-if="can.delete" class="pc-btn pc-btn--danger pc-btn--sm" type="button" @click="emit('remove')">
                        <UiIcon name="trash" :size="14" />
                        {{ t('ui.delete') }}
                    </button>
                </div>

                <input
                    v-if="url"
                    class="pc-input"
                    type="text"
                    dir="ltr"
                    readonly
                    :value="url"
                    :aria-label="t('cms_signed_link')"
                    @focus="$event.target.select()"
                >

                <form v-if="can.update && editing" class="pc-form-grid pc-form-grid--2" @submit.prevent="emit('save')">
                    <label v-for="code in ['ar', 'en']" :key="`edit-alt-${code}`" class="pc-field">
                        <span class="pc-label">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }} — {{ t('ui.alt_text') }}</span>
                        <input v-model="editForm.translations[code].alt_text" class="pc-input" type="text" maxlength="255">
                    </label>
                    <label v-for="code in ['ar', 'en']" :key="`edit-caption-${code}`" class="pc-field">
                        <span class="pc-label">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }} — {{ t('ui.cms_description') }}</span>
                        <input v-model="editForm.translations[code].caption" class="pc-input" type="text" maxlength="500">
                    </label>
                    <div class="pc-form-actions">
                        <button class="pc-btn pc-btn--primary pc-btn--sm" type="submit">
                            <UiIcon name="check" :size="14" />
                            {{ t('cms_save') }}
                        </button>
                        <button class="pc-btn pc-btn--ghost pc-btn--sm" type="button" @click="emit('cancel-edit')">
                            {{ t('cms_close') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
