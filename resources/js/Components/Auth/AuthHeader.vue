<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
})
</script>

<template>
    <header class="pc-auth__heading">
        <h1>{{ title }}</h1>
        <p v-if="subtitle">{{ subtitle }}</p>
    </header>
</template>
