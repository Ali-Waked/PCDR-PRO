<script setup>
import { computed, ref, watch } from 'vue'
import UiIcon from '../../Dashboard/UiIcon.vue'
import { detectKind, displayExtension, previewMode } from '../../../Support/mediaKind'
import MediaFileTypeIcon from './MediaFileTypeIcon.vue'

const props = defineProps({
    item: { type: Object, required: true },
    src: { type: String, default: '' },
    variant: { type: String, default: 'tile' },
})

const imageFailed = ref(false)

const kind = computed(() => detectKind(props.item))
const mode = computed(() => previewMode(props.item))
const extension = computed(() => displayExtension(props.item))
const resolvedSrc = computed(() => props.src || props.item?.accessUrl || '')
const badge = computed(() => (extension.value || (kind.value === 'image' ? 'IMG' : 'FILE')).toUpperCase())

watch(() => [props.src, props.item?.id], () => {
    imageFailed.value = false
})

const showImage = computed(() => (
    (mode.value === 'image' || mode.value === 'video') && resolvedSrc.value !== '' && ! imageFailed.value
))
</script>

<template>
    <div
        class="pc-media-visual"
        :class="[
            `pc-media-visual--${kind}`,
            `pc-media-visual--${variant}`,
            { 'pc-media-visual--restricted': item.restricted },
        ]"
    >
        <img
            v-if="showImage && mode === 'image'"
            class="pc-media-visual__image"
            :src="resolvedSrc"
            :alt="item.alt || item.name"
            loading="lazy"
            decoding="async"
            @error="imageFailed = true"
        >
        <div v-else class="pc-media-visual__fallback">
            <MediaFileTypeIcon :kind="kind" :size="variant === 'detail' ? 48 : 30" />
            <span class="pc-media-visual__extension" dir="ltr">{{ badge }}</span>
        </div>

        <span class="pc-media-visual__badge" dir="ltr">{{ badge }}</span>
        <span v-if="item.restricted" class="pc-media-visual__lock" aria-hidden="true">
            <UiIcon name="lock" :size="12" />
        </span>
    </div>
</template>
