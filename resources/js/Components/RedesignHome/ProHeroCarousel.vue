<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
    slides: { type: Array, default: () => [] },
    labels: { type: Object, required: true },
})

const current = ref(0)
const playing = ref(true)
let timer = null

const hasSlides = computed(() => props.slides.length > 0)

function show(index) {
    if (!hasSlides.value) {
        return
    }
    current.value = (index + props.slides.length) % props.slides.length
}

function stopTimer() {
    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
}

function startTimer() {
    stopTimer()
    if (!playing.value || props.slides.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return
    }
    timer = setInterval(() => show(current.value + 1), 6500)
}

function toggle() {
    playing.value = !playing.value
    startTimer()
}

function onKeydown(event) {
    if (event.key === 'ArrowLeft') {
        show(current.value - 1)
    } else if (event.key === 'ArrowRight') {
        show(current.value + 1)
    }
}

let touchStart = null

function onTouchStart(event) {
    touchStart = event.touches[0]?.clientX ?? null
}

function onTouchEnd(event) {
    if (touchStart === null) {
        return
    }
    const distance = (event.changedTouches[0]?.clientX ?? touchStart) - touchStart
    touchStart = null
    if (Math.abs(distance) > 50) {
        show(current.value + (distance < 0 ? 1 : -1))
    }
}

onMounted(startTimer)
onBeforeUnmount(stopTimer)
</script>

<template>
    <div
        v-if="hasSlides"
        class="pcdr-pro-home pro-hero"
        role="region"
        :aria-label="labels.carousel"
        tabindex="0"
        @keydown="onKeydown"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <div
            v-for="(slide, index) in slides"
            :key="slide.key || `slide-${index}`"
            class="pro-slide"
            :class="`s${(index % 5) + 1}`"
            :aria-hidden="current === index ? undefined : 'true'"
            :inert="current === index ? undefined : true"
        >
            <div class="pro-slide__content">
                <span class="pro-tag">{{ slide.label }}</span>
                <component :is="index === 0 ? 'h1' : 'h2'" class="pro-title">{{ slide.title }}</component>
                <p v-if="slide.body" class="pro-body">{{ slide.body }}</p>
            </div>
        </div>

        <div class="pro-controls">
            <button
                v-if="slides.length > 1"
                type="button"
                class="pro-play"
                :aria-label="playing ? labels.pause : labels.play"
                @click="toggle"
            >
                <span aria-hidden="true">{{ playing ? '❚❚' : '▶' }}</span>
            </button>
            <div class="pro-bars" role="group" :aria-label="labels.selectSlide">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.key || `bar-${index}`"
                    type="button"
                    :class="{ on: current === index }"
                    :aria-label="`${labels.slide} ${index + 1}`"
                    :aria-current="current === index ? 'true' : 'false'"
                    @click="show(index)"
                />
            </div>
        </div>
    </div>
</template>
