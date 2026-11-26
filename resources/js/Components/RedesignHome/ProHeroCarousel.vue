<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)

// Exact prototype copy, with faithful English translations. Used when the CMS
// has not supplied a slide; CMS values always take precedence.
const FALLBACK = [
    {
        key: 'slide-1',
        motifKey: 'm-sun',
        ar: {
            label: 'الشعار الاستراتيجي 2027 – 2029',
            title: 'من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة',
            body: 'نحوّل الاحتياجات الميدانية في قطاع غزة إلى برامج ومشاريع قابلة للتنفيذ والقياس والاستدامة، بالشراكة مع المجتمع.',
        },
        en: {
            label: 'Strategic slogan 2027 – 2029',
            title: 'From humanitarian response to recovery and sustainable development',
            body: 'We translate field needs in the Gaza Strip into programmes and projects that can be implemented, measured and sustained, in partnership with the community.',
        },
    },
    {
        key: 'slide-2',
        motifKey: 'm-wheat',
        ar: {
            label: 'الأمن الغذائي والتغذية',
            title: 'غذاء كريم وعادل ومنتظم للأسر الأكثر احتياجاً',
            body: 'طرود غذائية وكوبونات ومطابخ مجتمعية وحدائق منزلية، وفق معايير استحقاق معلنة وشفافة.',
        },
        en: {
            label: 'Food security and nutrition',
            title: 'Dignified, fair and regular food for the most needy families',
            body: 'Food parcels, vouchers, community kitchens and home gardens, based on published and transparent eligibility criteria.',
        },
    },
    {
        key: 'slide-3',
        motifKey: 'm-bars',
        ar: {
            label: 'التمكين الاقتصادي وسبل العيش',
            title: 'من الاعتماد على المساعدات إلى القدرة على الإنتاج',
            body: 'منح صغيرة للمشروعات المنزلية، وتدريب مهني، ودعم للتعاونيات والمبادرات الإنتاجية.',
        },
        en: {
            label: 'Economic empowerment and livelihoods',
            title: 'From dependence on aid to the ability to produce',
            body: 'Small grants for home-based projects, vocational training, and support for cooperatives and productive initiatives.',
        },
    },
    {
        key: 'slide-4',
        motifKey: 'm-home',
        ar: {
            label: 'الحماية والدعم النفسي والاجتماعي',
            title: 'رعاية متكاملة للفئات الأكثر هشاشة',
            body: 'الأيتام والأشخاص ذوو الإعاقة والنساء المعيلات وكبار السن، مع دعم نفسي واجتماعي للأطفال والأسر.',
        },
        en: {
            label: 'Protection and psychosocial support',
            title: 'Integrated care for the most vulnerable groups',
            body: 'Orphans, persons with disabilities, female-headed households and older people, with psychosocial support for children and families.',
        },
    },
    {
        key: 'slide-5',
        motifKey: 'm-drop',
        ar: {
            label: 'الصحة والمياه والإصحاح البيئي',
            title: 'بيئة أكثر أماناً في مراكز الإيواء والمناطق المتضررة',
            body: 'حملات نظافة وتوعية صحية، ودعم مياه الشرب الآمنة، وتحسين مرافق الصرف الصحي.',
        },
        en: {
            label: 'Health, water and environmental sanitation',
            title: 'A safer environment in shelters and affected areas',
            body: 'Cleanliness and health-awareness campaigns, support for safe drinking water, and improved sanitation facilities.',
        },
    },
]

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

const slides = computed(() => {
    const fromCms = content.items('hero', 'slide-')
    const arabic = locale.value === 'ar'

    if (fromCms.length > 0) {
        return fromCms.map((item, index) => ({
            key: content.text(item, 'key', `slide-${index + 1}`),
            label: content.text(item, 'label'),
            title: content.text(item, 'title'),
            body: content.text(item, 'body'),
            motifKey: content.text(item, 'motifKey', FALLBACK[index % FALLBACK.length].motifKey),
            media: content.mediaUrl(item),
        }))
    }

    return FALLBACK.map((slide) => ({
        key: slide.key,
        label: arabic ? slide.ar.label : slide.en.label,
        title: arabic ? slide.ar.title : slide.en.title,
        body: arabic ? slide.ar.body : slide.en.body,
        motifKey: slide.motifKey,
        media: '',
    }))
})

function photoStyle(slide) {
    return slide.media ? { '--photo': `url("${slide.media.replace(/"/g, '%22')}")` } : null
}

const current = ref(0)
const playing = ref(true)
const reduced = ref(false)
let timer = null
let touchStart = null

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
                <span class="tag">{{ slide.label }}</span>
                <component :is="index === 0 ? 'h1' : 'h2'">{{ slide.title }}</component>
                <p>{{ slide.body }}</p>
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
