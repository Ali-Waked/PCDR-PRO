<script setup>
defineProps({
    albums: { type: Array, default: () => [] },
    heading: { type: String, default: '' },
})
</script>

<template>
    <div v-if="albums.length" class="pcdr-pro-home pro-media">
        <h3 v-if="heading" class="pro-media__heading">{{ heading }}</h3>
        <ul class="pro-albums">
            <li v-for="(album, index) in albums" :key="album.key || `album-${index}`" :class="{ big: index === 0 }">
                <img v-if="album.mediaUrl" :src="album.mediaUrl" :alt="album.mediaAlt || album.title" loading="lazy">
                <span v-else class="pro-placeholder" aria-hidden="true" />
                <span class="pro-caption">{{ album.title }}</span>
            </li>
        </ul>
    </div>
</template>
