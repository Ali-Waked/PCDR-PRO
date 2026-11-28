<script setup>
import { computed, ref, watch } from 'vue'
import UiIcon from '../../Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'
import { acceptForFormats } from '../../../Support/mediaKind'

const props = defineProps({
    form: { type: Object, required: true },
    formats: { type: Object, default: () => ({ image: [], document: [], maxKb: 10240 }) },
    canUpload: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])
const { t } = useI18n()
const input = ref(null)
const dragging = ref(false)

const accept = computed(() => acceptForFormats(
    props.form.is_restricted ? props.formats?.document : props.formats?.image,
))

const formatsLabel = computed(() => (
    props.form.is_restricted ? props.formats?.document : props.formats?.image
)?.map((extension) => String(extension).toUpperCase()).join(' · ') || '')

const progress = computed(() => Math.max(0, Math.min(100, Number(props.form.progress?.percentage ?? 0))))

const selectedName = computed(() => props.form.file?.name || '')

watch(() => props.form.is_restricted, () => {
    props.form.file = null
    if (input.value) {
        input.value.value = ''
    }
})

function setFile(file) {
    if (file) {
        props.form.file = file
    }
}

function onDrop(event) {
    dragging.value = false
    setFile(event.dataTransfer?.files?.[0])
}
</script>

<template>
    <form class="pc-media-upload" @submit.prevent="emit('submit')">
        <label
            class="pc-media-drop"
            :class="{ 'is-dragging': dragging, 'has-file': selectedName }"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="input"
                class="pc-media-drop__input"
                type="file"
                :accept="accept"
                :disabled="!canUpload || form.processing"
                @change="setFile($event.target.files?.[0])"
            >
            <span class="pc-media-drop__icon"><UiIcon name="upload" :size="22" /></span>
            <span class="pc-media-drop__title">{{ selectedName || t('cms_media_drop_hint') }}</span>
            <span v-if="formatsLabel" class="pc-media-drop__formats" dir="ltr">
                {{ t('cms_media_supported_formats') }}: {{ formatsLabel }}
            </span>
        </label>

        <div class="pc-media-upload__options">
            <label class="pc-checkbox">
                <input v-model="form.is_restricted" type="checkbox" :disabled="form.processing">
                <span>{{ t('cms_restricted_file') }}</span>
            </label>

            <label v-for="code in ['ar', 'en']" :key="`upload-alt-${code}`" class="pc-field">
                <span class="pc-label">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }} — {{ t('ui.alt_text') }}</span>
                <input v-model="form.translations[code].alt_text" class="pc-input" type="text" maxlength="255">
            </label>

            <label v-for="code in ['ar', 'en']" :key="`upload-caption-${code}`" class="pc-field">
                <span class="pc-label">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }} — {{ t('ui.cms_description') }}</span>
                <input v-model="form.translations[code].caption" class="pc-input" type="text" maxlength="500">
            </label>
        </div>

        <div class="pc-media-upload__actions">
            <button
                class="pc-btn pc-btn--primary"
                type="submit"
                :disabled="!canUpload || form.processing || !form.file"
                :aria-busy="form.processing ? 'true' : 'false'"
            >
                <UiIcon name="upload" :size="16" />
                {{ form.processing ? t('cms_media_uploading') : t('cms_upload') }}
            </button>
            <span v-if="form.processing" class="pc-media-upload__progress" role="status">
                <span class="pc-media-upload__bar"><span :style="{ inlineSize: `${Math.max(progress, 8)}%` }" /></span>
                <span dir="ltr">{{ Math.round(progress) }}%</span>
            </span>
        </div>

        <p v-if="form.errors.file" class="pc-error" role="alert">{{ form.errors.file }}</p>
    </form>
</template>
