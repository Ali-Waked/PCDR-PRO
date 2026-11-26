<script setup>
defineProps({
    message: { type: String, default: '' },
    tone: { type: String, default: 'info' },
})
</script>

<template>
    <p
        v-if="message"
        class="auth-alert"
        :class="`auth-alert--${tone}`"
        :role="tone === 'error' ? 'alert' : 'status'"
    >
        {{ message }}
    </p>
</template>
