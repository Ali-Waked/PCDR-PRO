<script setup>
import DashboardChrome from '../Components/Dashboard/DashboardChrome.vue'

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    titleKey: { type: String, default: '' },
    subtitleKey: { type: String, default: '' },
    eyebrowKey: { type: String, default: '' },
    showHeader: { type: Boolean, default: true },
    breadcrumb: { type: Array, default: () => [] },
})
</script>

<template>
    <DashboardChrome
        mode="admin"
        :title="title"
        :subtitle="subtitle"
        :eyebrow="eyebrow"
        :title-key="titleKey"
        :subtitle-key="subtitleKey"
        :eyebrow-key="eyebrowKey"
        :show-header="showHeader"
        :breadcrumb="breadcrumb"
    >
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
        <slot />
    </DashboardChrome>
</template>
