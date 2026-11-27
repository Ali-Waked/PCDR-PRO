<script setup>
import { Link } from '@inertiajs/vue3'
import UiIcon from './UiIcon.vue'

defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    breadcrumb: { type: Array, default: () => [] },
})
</script>

<template>
    <header class="pc-page-head">
        <div class="pc-page-head__copy">
            <nav v-if="breadcrumb.length" class="pc-breadcrumb" aria-label="Breadcrumb">
                <template v-for="(item, index) in breadcrumb" :key="`${item.label}-${index}`">
                    <UiIcon v-if="index > 0" name="chevronForward" :size="12" />
                    <Link v-if="item.href" :href="item.href">{{ item.label }}</Link>
                    <span v-else aria-current="page">{{ item.label }}</span>
                </template>
            </nav>
            <span v-if="eyebrow" class="pc-eyebrow">{{ eyebrow }}</span>
            <h1 class="pc-page-title">{{ title }}</h1>
            <p v-if="subtitle" class="pc-page-subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="pc-page-actions">
            <slot name="actions" />
        </div>
    </header>
</template>
