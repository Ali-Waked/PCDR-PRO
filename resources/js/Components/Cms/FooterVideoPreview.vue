<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    provider: { type: String, default: '' },
    url: { type: String, default: '' },
    title: { type: String, default: '' },
    path: { type: String, required: true },
})

const { t } = useI18n()
const previewHttp = useHttp({ provider: props.provider, url: props.url })
const preview = ref(null)
const busy = ref(false)
const failed = ref(false)
let timer = null

const hasInput = computed(() => props.provider !== '' && props.url.trim() !== '')

function clear() {
    preview.value = null
    failed.value = false
}

async function refresh() {
    window.clearTimeout(timer)
    clear()

    if (!hasInput.value) {
        return
    }

    busy.value = true
    Object.assign(previewHttp, { provider: props.provider, url: props.url })

    try {
        preview.value = await previewHttp.post(props.path)
    } catch {
        failed.value = true
    } finally {
        busy.value = false
    }
}

function schedule() {
    window.clearTimeout(timer)
    clear()
    timer = window.setTimeout(refresh, 450)
}

watch(() => [props.provider, props.url], schedule, { immediate: true })
onBeforeUnmount(() => window.clearTimeout(timer))
</script>

<template>
    <div class="pc-card pc-footer-video-preview" data-cms-footer-video-preview>
        <div class="pc-card__head">
            <div>
                <h3 class="pc-card__title">{{ t('cms_footer_video_preview') }}</h3>
                <p class="pc-card__subtitle">{{ t('cms_footer_video_preview_hint') }}</p>
            </div>
            <span v-if="busy" class="pc-badge pc-badge--slate">{{ t('cms_footer_video_preview_busy') }}</span>
        </div>
        <div class="pc-card__body">
            <div v-if="preview" class="pc-footer-video-preview__frame">
                <iframe
                    :src="preview.embedUrl"
                    :title="title || t('cms_footer_video_title')"
                    :sandbox="preview.sandbox"
                    :allow="preview.allow"
                    referrerpolicy="strict-origin-when-cross-origin"
                    loading="lazy"
                    allowfullscreen
                ></iframe>
            </div>
            <p v-else-if="failed" class="pc-error" role="alert">{{ t('cms_footer_video_preview_failed') }}</p>
            <p v-else class="pc-hint">{{ t('cms_footer_video_preview_empty') }}</p>
        </div>
    </div>
</template>

<style scoped>
.pc-footer-video-preview__frame {
    overflow: hidden;
    border: 1px solid rgba(62, 81, 114, 0.22);
    border-radius: 12px;
    background: #0b1220;
    box-shadow: 0 10px 26px rgba(8, 13, 26, 0.14);
}

.pc-footer-video-preview__frame iframe {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    border: 0;
}
</style>
