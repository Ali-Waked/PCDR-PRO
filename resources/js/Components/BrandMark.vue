<script setup>
import { computed } from 'vue'
import { useLocale } from '../Composables/useLocale'
import { useI18n } from '../composables/useI18n'

const props = defineProps({
    compact: { type: Boolean, default: false },
    inverse: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
})

const { t } = useLocale()
const { site } = useI18n()

// The approved static PNG remains the fallback; administrators can replace it
// through the canonical CMS logo setting.
const logoSource = computed(() => site.value?.logo || '/assets/pcdr-homepage/logo/pcdr-logo.png')

const sizeClass = computed(() => `pcdr-brand--${props.size}`)
</script>

<template>
    <span class="pcdr-brand" :class="[sizeClass, { 'pcdr-brand--inverse': inverse }]">
        <img
            class="pcdr-brand__mark"
            :src="logoSource"
            :alt="compact ? t('app_name') : ''"
            :aria-hidden="compact ? undefined : 'true'"
            width="48"
            height="48"
        >
        <span v-if="!compact" class="pcdr-brand__wordmark">
            <strong>{{ t('app_name') }}</strong>
            <small>{{ t('admin_brand.tagline') }}</small>
        </span>
    </span>
</template>
