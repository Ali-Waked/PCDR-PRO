<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const content = useHomepageContent(() => props.sections)

const label = computed(() => content.text(content.section('ticker'), 'tickerLabel'))

const items = computed(() => content.items('ticker').map((item) => ({
    key: content.text(item, 'key'),
    title: content.text(item, 'title'),
    url: content.safeUrl(content.text(item, 'url')),
})).filter((item) => item.title))

const current = ref(0)
let timer = null

watch(items, (value) => {
    if (current.value >= value.length) {
        current.value = 0
    }
})

onMounted(() => {
    if (items.value.length > 1 && typeof window !== 'undefined'
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        timer = setInterval(() => {
            current.value = (current.value + 1) % items.value.length
        }, 4800)
    }
})
onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
})
</script>

<template>
    <div v-if="items.length" class="ticker">
        <div class="wrap">
            <span v-if="label" class="lbl">{{ label }}</span>
            <span class="txt" aria-live="polite">
                <a
                    v-if="items[current % items.length]?.url"
                    :key="`a-${current}`"
                    class="tk-link"
                    :href="items[current % items.length].url"
                >{{ items[current % items.length].title }}</a>
                <span
                    v-else
                    :key="`s-${current}`"
                    class="tk-link"
                >{{ items[current % items.length]?.title }}</span>
            </span>
        </div>
    </div>
</template>
