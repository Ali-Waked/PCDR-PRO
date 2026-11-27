<script setup>
import { computed } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const content = useHomepageContent(() => props.sections)
const storySection = computed(() => content.section('stories'))

const eyebrow = computed(() => content.text(storySection.value, 'eyebrow'))
const heading = computed(() => content.text(storySection.value, 'title'))

const GRADIENTS = ['g3', 'g2', 'g4', 'g1', 'g5']

const stories = computed(() => content.items('stories').map((item, index) => ({
    key: content.text(item, 'key', `story-${index + 1}`),
    title: content.text(item, 'title'),
    body: content.text(item, 'body'),
    motifKey: content.text(item, 'motifKey') || 'm-bars',
    gradient: GRADIENTS[index % GRADIENTS.length],
    media: content.mediaUrl(item),
    alt: content.mediaAlt(item),
})).filter((story) => story.title || story.body))

function photoStyle(story) {
    return story.media ? { '--photo': `url("${story.media.replace(/"/g, '%22')}")` } : null
}
</script>

<template>
    <section class="sec" id="stories" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div v-if="eyebrow" class="eyebrow"><b>{{ eyebrow }}</b></div>
                <h2 v-if="heading" class="h2">{{ heading }}</h2>
            </div>
            <ul v-if="stories.length" class="stories">
                <li v-for="story in stories" :key="story.key" class="story">
                    <div
                        class="thumb"
                        :class="story.gradient"
                        :style="photoStyle(story)"
                        :aria-label="story.alt || undefined"
                    >
                        <svg aria-hidden="true"><use :href="`#${story.motifKey}`" /></svg>
                    </div>
                    <div>
                        <h3 v-if="story.title">{{ story.title }}</h3>
                        <p v-if="story.body">{{ story.body }}</p>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
