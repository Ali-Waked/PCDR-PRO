<script setup>
import EmptyState from '../../Dashboard/EmptyState.vue'
import UiIcon from '../../Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'

defineProps({
    state: { type: Object, required: true },
})

const emit = defineEmits(['clear'])
const { t } = useI18n()
</script>

<template>
    <EmptyState :title="state.title" :message="state.message" :icon="state.icon || 'image'">
        <button v-if="state.reset" class="pc-btn pc-btn--secondary pc-btn--sm" type="button" @click="emit('clear')">
            <UiIcon name="refresh" :size="14" />
            {{ t('ui.cms_content_page.clear_filters', '') }}
        </button>
    </EmptyState>
</template>
