<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import CmsStatusBadge from '../Dashboard/CmsStatusBadge.vue'
import UiIcon from '../Dashboard/UiIcon.vue'
import StatusSelect from '../Selection/StatusSelect.vue'
import { complaintStatusLabel } from '../../Support/complaintLabels'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    inquiry: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    inquiriesPath: { type: String, required: true },
    compact: { type: Boolean, default: false },
})

const { t } = useI18n()

function statusLabel(status) {
    return complaintStatusLabel(t, status)
}

const statusOptions = computed(() => props.statuses.map((status) => ({
    value: status,
    label: statusLabel(status),
})))

const draft = ref(props.inquiry.status || 'new')
const busy = ref(false)
const hasChange = computed(() => draft.value !== props.inquiry.status)

watch(() => props.inquiry.status, (status) => {
    draft.value = status || 'new'
})

function save() {
    if (!hasChange.value || busy.value) {
        return
    }

    busy.value = true
    router.put(`${props.inquiriesPath}/${props.inquiry.type}/${props.inquiry.id}/status`, { status: draft.value }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value = false },
    })
}
</script>

<template>
    <div class="iq-status-control" :class="{ 'is-compact': compact }">
        <CmsStatusBadge :status="inquiry.status" :label="statusLabel(inquiry.status)" />

        <div class="iq-status-control__edit">
            <div class="iq-status-control__field">
                <StatusSelect
                    v-model="draft"
                    :aria-label="`${t('cms_status')}: ${inquiry.reference}`"
                    :options="statusOptions"
                    :disabled="busy"
                    :loading="busy"
                    size="sm"
                />
            </div>
            <button
                class="pc-btn pc-btn--secondary pc-btn--sm"
                type="button"
                :disabled="!hasChange || busy"
                :title="t('cms_update_status')"
                :aria-label="t('cms_update_status')"
                @click="save"
            >
                <span v-if="busy" class="pc-btn__spinner" aria-hidden="true" />
                <UiIcon v-else name="check" :size="14" />
                <span class="iq-status-control__label">{{ t('cms_update_status') }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.iq-status-control {
    display: grid;
    gap: 8px;
    justify-items: start;
    min-inline-size: 0;
}

.iq-status-control__edit {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    min-inline-size: 0;
}

.iq-status-control__field {
    min-inline-size: 122px;
    max-inline-size: 100%;
}

.iq-status-control__label {
    display: inline;
}

.iq-status-control.is-compact .iq-status-control__label {
    display: none;
}

@media (max-width: 1180px) {
    .iq-status-control__label {
        display: none;
    }
}
</style>
