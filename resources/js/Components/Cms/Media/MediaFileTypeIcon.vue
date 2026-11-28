<script setup>
import { computed } from 'vue'
import UiIcon from '../../Dashboard/UiIcon.vue'
import { kindMeta } from '../../../Support/mediaKind'

const props = defineProps({
    kind: { type: String, default: 'other' },
    size: { type: [Number, String], default: 28 },
})

const meta = computed(() => kindMeta(props.kind))
</script>

<template>
    <span class="pc-file-icon" :class="`pc-file-icon--${meta.tone}`" aria-hidden="true">
        <UiIcon :name="meta.icon" :size="size" />
    </span>
</template>
