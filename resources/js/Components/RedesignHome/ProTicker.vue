<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
    items: { type: Array, default: () => [] },
    label: { type: String, default: '' },
})

const current = ref(0)
let timer = null

const active = computed(() => props.items[current.value] || null)

function stopTimer() {
    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
}

onMounted(() => {
    if (props.items.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        timer = setInterval(() => {
            current.value = (current.value + 1) % props.items.length
        }, 4800)
    }
})
onBeforeUnmount(stopTimer)
</script>

<template>
    <div v-if="active" class="pcdr-pro-home pro-ticker">
        <span class="pro-ticker__label">{{ label }}</span>
        <span class="pro-ticker__text" aria-live="polite">
            <a v-if="active.url" :href="active.url">{{ active.title }}</a>
            <template v-else>{{ active.title }}</template>
        </span>
    </div>
</template>
