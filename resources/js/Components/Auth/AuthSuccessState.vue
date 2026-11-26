<script setup>
defineProps({
    title: { type: String, required: true },
    message: { type: String, required: true },
    actionLabel: { type: String, default: '' },
    href: { type: String, default: '' },
})
</script>

<template>
    <div class="auth-success" role="status">
        <span class="auth-success__mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5" />
            </svg>
        </span>
        <h2 class="auth-success__title">{{ title }}</h2>
        <p class="auth-success__message">{{ message }}</p>
        <a v-if="href" class="pc-link auth-success__action" :href="href">{{ actionLabel }}</a>
    </div>
</template>
