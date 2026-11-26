<script setup>
defineProps({
    stories: { type: Array, default: () => [] },
    heading: { type: String, default: '' },
})
</script>

<template>
    <div v-if="stories.length" class="pcdr-pro-home pro-stories">
        <h3 v-if="heading" class="pro-stories__heading">{{ heading }}</h3>
        <ul class="pro-stories__list">
            <li v-for="story in stories" :key="story.key || story.title">
                <article>
                    <h4>{{ story.title }}</h4>
                    <p>{{ story.body }}</p>
                </article>
            </li>
        </ul>
    </div>
</template>
