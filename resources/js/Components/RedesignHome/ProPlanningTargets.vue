<script setup>
defineProps({
    stats: { type: Array, default: () => [] },
    caveat: { type: String, default: '' },
    heading: { type: String, default: '' },
})
</script>

<template>
    <div v-if="stats.length" class="pcdr-pro-home pro-impact">
        <h3 v-if="heading" class="pro-impact__heading">{{ heading }}</h3>
        <dl class="pro-impact__grid">
            <div v-for="stat in stats" :key="stat.key || stat.label" class="pro-impact__item">
                <dt>{{ stat.display || stat.value || '—' }}</dt>
                <dd>{{ stat.label }}</dd>
            </div>
        </dl>
        <p v-if="caveat" class="pro-impact__caveat">{{ caveat }}</p>
    </div>
</template>
