<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)

const labels = computed(() => (locale.value === 'ar' ? {
    carousel: 'أبرز برامج الجمعية',
    pause: 'إيقاف مؤقت للعرض',
    play: 'تشغيل العرض',
    selectSlide: 'اختيار الشريحة',
    slide: 'الشريحة',
} : {
    carousel: 'Featured programmes',
    pause: 'Pause slideshow',
    play: 'Play slideshow',
    selectSlide: 'Choose a slide',
    slide: 'Slide',
}))

// The backend decides slide membership from the structure registry. Legacy
// `slide-` keys, CTA controls and duplicated ticker rows are never inferred
// from the public key alone.
const slides = computed(() => content.items('hero').filter((item) => item.slide === true).map((item, index) => ({
    key: content.text(item, 'key', `slide-${index + 1}`),
    label: content.text(item, 'label'),
    title: content.text(item, 'title'),
    body: content.text(item, 'body'),
    motifKey: content.text(item, 'motifKey') || 'm-sun',
    media: content.mediaUrl(item),
})))

function photoStyle(slide) {
    return slide.media ? { '--photo': `url("${slide.media.replace(/"/g, '%22')}")` } : null
}

const current = ref(0)
const playing = ref(true)
const reduced = ref(false)
let timer = null
let touchStart = null

watch(slides, (value) => {
    if (current.value >= value.length) {
        current.value = 0
    }
})

function show(index) {
    if (!slides.value.length) {
        return
    }
    current.value = (index + slides.value.length) % slides.value.length
}

function stopTimer() {
    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
}

function startTimer() {
    stopTimer()
    if (!playing.value || reduced.value || slides.value.length < 2) {
        return
    }
    timer = setInterval(() => show(current.value + 1), 6500)
}

function toggle() {
    playing.value = !playing.value
    startTimer()
}

function select(index) {
    show(index)
    if (playing.value) {
        startTimer()
    }
}

function onKeydown(event) {
    if (event.key === 'ArrowLeft') {
        show(current.value + 1)
    } else if (event.key === 'ArrowRight') {
        show(current.value - 1)
    }
}

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
        if (playing.value) {
            startTimer()
        }
    }
}

onMounted(() => {
    reduced.value = typeof window !== 'undefined'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    playing.value = !reduced.value
    startTimer()
})
onBeforeUnmount(stopTimer)
</script>

<template>
    <section
        v-if="slides.length"
        class="hero"
        aria-roledescription="carousel"
        :aria-label="labels.carousel"
        tabindex="0"
        @keydown="onKeydown"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <div
            v-for="(slide, index) in slides"
            :key="slide.key || `slide-${index}`"
            class="slide"
            :class="[{ on: current === index }, `s${(index % 5) + 1}`]"
            :style="photoStyle(slide)"
            aria-roledescription="slide"
            :aria-hidden="current === index ? 'false' : 'true'"
        >
            <svg class="motif" aria-hidden="true"><use :href="`#${slide.motifKey}`" /></svg>
            <div class="wrap cap">
                <span v-if="slide.label" class="tag">{{ slide.label }}</span>
                <component :is="index === 0 ? 'h1' : 'h2'" v-if="slide.title">{{ slide.title }}</component>
                <p v-if="slide.body">{{ slide.body }}</p>
            </div>
        </div>

        <div class="ctl">
            <div class="wrap">
                <button
                    class="pp"
                    type="button"
                    :class="{ paused: !playing }"
                    :aria-label="playing ? labels.pause : labels.play"
                    @click="toggle"
                >
                    <svg class="ps" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1" /><rect x="14" y="5" width="4" height="14" rx="1" /></svg>
                    <svg class="pl" aria-hidden="true"><use href="#i-play" /></svg>
                </button>
                <div class="bars" role="group" :aria-label="labels.selectSlide">
                    <button
                        v-for="(slide, index) in slides"
                        :key="slide.key || `bar-${index}`"
                        type="button"
                        :aria-label="`${labels.slide} ${index + 1}`"
                        :aria-current="current === index ? 'true' : 'false'"
                        @click="select(index)"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
