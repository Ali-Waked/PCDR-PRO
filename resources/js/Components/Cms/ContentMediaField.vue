<script setup>
import { computed, ref, watch } from 'vue'
import UiIcon from '../Dashboard/UiIcon.vue'
import MediaVisual from './Media/MediaVisual.vue'
import { useI18n } from '../../composables/useI18n'
import { previewMode } from '../../Support/mediaKind'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    labels: { type: Object, default: () => ({}) },
    accept: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const open = ref(false)

const selected = computed(() => props.options.find(
    (item) => String(item.id) === String(props.modelValue),
) || null)

const filteredOptions = computed(() => {
    if (props.accept === '') {
        return props.options
    }

    return props.options.filter((item) => item.type === props.accept)
})

const labels = computed(() => ({
    select: props.labels.select || t('cms_select_media'),
    change: props.labels.change || t('ui.edit'),
    remove: props.labels.remove || t('cms_remove_item'),
    title: props.labels.title || t('cms_media_picker'),
    insert: props.labels.insert || t('cms_insert_media'),
    empty: props.labels.empty || t('cms_select_media'),
    close: props.labels.close || t('cms_close'),
}))

watch(() => props.modelValue, () => {
    open.value = false
})

function isImage(item) {
    return item && previewMode(item) === 'image'
}

function choose(item) {
    emit('update:modelValue', item.id)
    open.value = false
}

function clear() {
    emit('update:modelValue', '')
    open.value = false
}
</script>

<template>
    <div class="pc-content-media">
        <div class="pc-content-media__head">
            <span class="pc-label">{{ label }}</span>
            <button
                type="button"
                class="pc-btn pc-btn--ghost pc-btn--sm"
                :aria-expanded="open ? 'true' : 'false'"
                @click="open = !open"
            >
                <UiIcon :name="accept === 'document' ? 'file' : 'image'" :size="14" />
                {{ open ? labels.close : (selected ? labels.change : labels.select) }}
            </button>
        </div>

        <div class="pc-content-media__current">
            <span v-if="selected" class="pc-content-media__visual">
                <MediaVisual :item="selected" :src="selected.url" variant="list" />
            </span>
            <span v-else class="pc-content-media__placeholder" aria-hidden="true">
                <UiIcon :name="accept === 'document' ? 'file' : 'image'" :size="18" />
            </span>
            <span class="pc-content-media__name">{{ selected?.name || labels.select }}</span>
            <span v-if="selected?.restricted" class="pc-badge pc-badge--sand">
                <UiIcon name="lock" :size="12" />
                {{ t('cms_restricted_file') }}
            </span>
            <button v-if="selected" type="button" class="pc-btn pc-btn--danger pc-btn--sm" @click="clear">
                {{ labels.remove }}
            </button>
        </div>

        <div v-if="open" class="pc-content-media__picker">
            <span class="pc-eyebrow">{{ labels.title }}</span>
            <div v-if="filteredOptions.length" class="pc-content-media__options">
                <button
                    v-for="item in filteredOptions"
                    :key="item.id"
                    type="button"
                    class="pc-content-media__option"
                    :class="{ 'is-selected': String(item.id) === String(modelValue) }"
                    :aria-pressed="String(item.id) === String(modelValue) ? 'true' : 'false'"
                    @click="choose(item)"
                >
                    <span class="pc-media-cover pc-content-media__thumbnail">
                        <MediaVisual :item="item" :src="item.url" variant="list" />
                    </span>
                    <span class="pc-content-media__name">{{ item.name }}</span>
                    <span class="pc-content-media__insert">{{ labels.insert }}</span>
                </button>
            </div>
            <p v-else class="pc-hint">{{ labels.empty }}</p>
        </div>
    </div>
</template>
