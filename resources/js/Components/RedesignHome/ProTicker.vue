<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)

const FALLBACK = [
    {
        ar: 'الخطة الاستراتيجية والتشغيلية 2027–2029: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة',
        en: 'Strategic and operational plan 2027–2029: from humanitarian response to recovery and sustainable development',
        url: '#news',
    },
    {
        ar: 'المرحلة الأولى 2027: التأسيس المؤسسي وبناء الجاهزية الإدارية والفنية',
        en: 'Phase one 2027: institutional foundation and building administrative and technical readiness',
        url: '#news',
    },
    {
        ar: 'إطلاق البرامج الميدانية: المساعدات الغذائية والدعم النفسي والاجتماعي',
        en: 'Launch of field programmes: food assistance and psychosocial support',
        url: '#news',
    },
    {
        ar: 'مستهدفنا لعام 2027: 3,000 أسرة مستفيدة من التدخلات الإنسانية',
        en: 'Our 2027 target: 3,000 families benefiting from humanitarian interventions',
        url: '#targets',
    },
]

const label = computed(() => {
    const fromCms = content.text(content.section('ticker'), 'tickerLabel')
    return fromCms || (locale.value === 'ar' ? 'أهم الأخبار' : 'Latest news')
})

const items = computed(() => {
    const arabic = locale.value === 'ar'
    const fromCms = content.items('ticker').map((item) => ({
        key: content.text(item, 'key'),
        title: content.text(item, 'title'),
        url: content.safeUrl(content.text(item, 'url')),
    })).filter((item) => item.title)

    if (fromCms.length > 0) {
        return fromCms
    }

    return FALLBACK.map((item, index) => ({
        key: `ticker-${index + 1}`,
        title: arabic ? item.ar : item.en,
        url: content.safeUrl(item.url),
    }))
})

const current = ref(0)
let timer = null

onMounted(() => {
    if (items.value.length > 1 && typeof window !== 'undefined'
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        timer = setInterval(() => {
            current.value = (current.value + 1) % items.value.length
        }, 4800)
    }
})
onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
})
</script>

<template>
    <div v-if="items.length" class="ticker">
        <div class="wrap">
            <span class="lbl">{{ label }}</span>
            <span class="txt" aria-live="polite">
                <a
                    v-if="items[current % items.length]?.url"
                    :key="`a-${current}`"
                    class="tk-link"
                    :href="items[current % items.length].url"
                >{{ items[current % items.length].title }}</a>
                <span
                    v-else
                    :key="`s-${current}`"
                    class="tk-link"
                >{{ items[current % items.length]?.title }}</span>
            </span>
        </div>
    </div>
</template>
