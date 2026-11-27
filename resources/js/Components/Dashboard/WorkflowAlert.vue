<script setup>
import { computed, ref, watch } from 'vue'
import UiIcon from './UiIcon.vue'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    workflow: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false },
})

const emit = defineEmits(['dismiss'])

const { t } = useI18n()

const visible = ref(true)
const detailsOpen = ref(false)

const feedback = computed(() => props.workflow ?? {})
const sequence = computed(() => (Array.isArray(feedback.value.sequence) ? feedback.value.sequence : []))
const current = computed(() => feedback.value.current ?? null)
const requested = computed(() => feedback.value.requested ?? null)
const nextAction = computed(() => feedback.value.nextAction ?? null)

const hasDetails = computed(() => Boolean(
    feedback.value.type
    || feedback.value.message
    || feedback.value.reason
    || current.value
    || requested.value,
))

watch(() => props.workflow, () => {
    visible.value = true
    detailsOpen.value = false
})

function dismiss() {
    visible.value = false
    emit('dismiss')
}
</script>

<template>
    <div v-if="visible && hasDetails" class="pc-workflow-alert" role="alert">
        <div class="pc-workflow-alert__body">
            <span class="pc-workflow-alert__icon" aria-hidden="true">
                <UiIcon name="alert" :size="18" />
            </span>

            <div class="pc-workflow-alert__copy">
                <strong v-if="!compact" class="pc-workflow-alert__title">{{ feedback.title }}</strong>
                <p v-if="feedback.message" class="pc-workflow-alert__message">{{ feedback.message }}</p>
                <p v-if="feedback.reason" class="pc-workflow-alert__reason">{{ feedback.reason }}</p>

                <div v-if="current || requested" class="pc-workflow-alert__states">
                    <div v-if="current" class="pc-workflow-alert__state">
                        <span>{{ feedback.currentLabel || t('ui.cms_pillars_page.alert.current_status') }}</span>
                        <b>{{ current.label }}</b>
                    </div>
                    <UiIcon class="pc-workflow-alert__arrow" name="arrowForward" :size="15" />
                    <div v-if="requested" class="pc-workflow-alert__state">
                        <span>{{ feedback.requestedLabel || t('ui.cms_pillars_page.alert.requested_status') }}</span>
                        <b>{{ requested.label }}</b>
                    </div>
                </div>

                <div v-if="detailsOpen && sequence.length" class="pc-workflow-alert__sequence">
                    <span class="pc-workflow-alert__sequence-label">
                        {{ feedback.sequenceLabel || t('ui.cms_pillars_page.workflow_sequence') }}
                    </span>
                    <ol>
                        <li v-for="(status, index) in sequence" :key="`${status}-${index}`">
                            <span>{{ status }}</span>
                        </li>
                    </ol>
                </div>

                <div v-if="detailsOpen && nextAction" class="pc-workflow-alert__next">
                    <span>{{ feedback.nextLabel || t('ui.cms_pillars_page.alert.next_action_label') }}</span>
                    <b>{{ nextAction.label }}</b>
                </div>
                <p v-else-if="detailsOpen && feedback.noNextLabel" class="pc-workflow-alert__next is-empty">
                    {{ feedback.noNextLabel }}
                </p>
            </div>
        </div>

        <div class="pc-workflow-alert__actions">
            <button
                v-if="sequence.length || nextAction"
                type="button"
                class="pc-btn pc-btn--ghost pc-btn--sm"
                @click="detailsOpen = !detailsOpen"
            >
                {{ detailsOpen ? t('ui.cms_pillars_page.hide_details') : (feedback.detailsLabel || t('ui.cms_pillars_page.view_details')) }}
            </button>
            <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="dismiss">
                {{ feedback.understoodLabel || t('ui.cms_pillars_page.alert.understood') }}
            </button>
        </div>
    </div>
</template>
