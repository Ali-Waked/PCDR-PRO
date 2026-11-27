<script setup>
import { computed } from 'vue'
import DefaultSection from './DefaultSection.vue'
import HeroSection from './HeroSection.vue'
import TickerSection from './TickerSection.vue'

const props = defineProps({
    locale: { type: String, default: 'ar' },
    section: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    media: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    organization: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
})

const editor = computed(() => {
    if (props.section?.content === 'slides') {
        return HeroSection
    }

    if (props.section?.content === 'announcements') {
        return TickerSection
    }

    return DefaultSection
})
</script>

<template>
    <component :is="editor" v-bind="$props" />
</template>
