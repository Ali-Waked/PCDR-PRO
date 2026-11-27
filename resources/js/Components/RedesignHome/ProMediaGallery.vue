<script setup>
import { computed } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const content = useHomepageContent(() => props.sections)
const albumSection = computed(() => content.section('albums'))

const eyebrow = computed(() => content.text(albumSection.value, 'eyebrow'))
const heading = computed(() => content.text(albumSection.value, 'title'))

const GRADIENTS = ['g1', 'g2', 'g3', 'g4', 'g5']

const albums = computed(() => content.items('albums').map((item, index) => ({
    key: content.text(item, 'key', `album-${index + 1}`),
    caption: content.text(item, 'caption')
        || content.text(item, 'title')
        || content.text(item, 'label'),
    motifKey: content.text(item, 'motifKey') || 'm-people',
    gradient: GRADIENTS[index % GRADIENTS.length],
    media: content.mediaUrl(item),
    alt: content.mediaAlt(item),
})).filter((album) => album.caption || album.media))

function photoStyle(album) {
    return album.media ? { '--photo': `url("${album.media.replace(/"/g, '%22')}")` } : null
}
</script>

<template>
    <section class="sec" id="albums" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div v-if="eyebrow" class="eyebrow"><b>{{ eyebrow }}</b></div>
                <h2 v-if="heading" class="h2">{{ heading }}</h2>
            </div>
            <div v-if="albums.length" class="album">
                <figure
                    v-for="(album, index) in albums"
                    :key="album.key"
                    class="ph"
                    :class="[{ big: index === 0 }, album.gradient]"
                    :style="photoStyle(album)"
                    :aria-label="album.alt || undefined"
                >
                    <svg aria-hidden="true"><use :href="`#${album.motifKey}`" /></svg>
                    <figcaption v-if="album.caption">{{ album.caption }}</figcaption>
                </figure>
            </div>
        </div>
    </section>
</template>
