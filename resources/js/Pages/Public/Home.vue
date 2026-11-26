<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import ProAboutTabs from '../../Components/RedesignHome/ProAboutTabs.vue'
import ProHeroCarousel from '../../Components/RedesignHome/ProHeroCarousel.vue'
import ProImpact from '../../Components/RedesignHome/ProImpact.vue'
import ProMediaGallery from '../../Components/RedesignHome/ProMediaGallery.vue'
import ProNewsTabs from '../../Components/RedesignHome/ProNewsTabs.vue'
import ProPillHeader from '../../Components/RedesignHome/ProPillHeader.vue'
import ProProgramAxes from '../../Components/RedesignHome/ProProgramAxes.vue'
import ProPrototypeFooter from '../../Components/RedesignHome/ProPrototypeFooter.vue'
import ProPrototypeSprite from '../../Components/RedesignHome/ProPrototypeSprite.vue'
import ProStories from '../../Components/RedesignHome/ProStories.vue'
import ProTicker from '../../Components/RedesignHome/ProTicker.vue'
import ProTopStrip from '../../Components/RedesignHome/ProTopStrip.vue'
import { useI18n } from '../../composables/useI18n'
import '../../../css/pcdr-prototype.css'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, site, t } = useI18n()

const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim())
    ? props.meta.title
    : (locale.value === 'ar'
        ? 'PCDR | جمعية العمل التشاركي في الأزمات والكوارث'
        : 'PCDR | Participatory Action in Crises and Disasters'))

const description = computed(() => props.meta?.description || site.value?.slogan || '')
const skipLabel = computed(() => t('ui.skip_to_content', locale.value === 'ar' ? 'تخطَّ إلى المحتوى' : 'Skip to content'))

onMounted(() => {
    if (typeof window !== 'undefined'
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.style.scrollBehavior = 'smooth'
    }
})
onBeforeUnmount(() => {
    document.documentElement.style.removeProperty('scroll-behavior')
})
</script>

<template>
    <div class="pcdr-prototype" :lang="locale" :dir="direction">
        <Head>
            <title>{{ title }}</title>
            <meta name="description" :content="description">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
            <link v-if="props.meta?.canonical" rel="canonical" :href="props.meta.canonical">
            <link rel="alternate" hreflang="ar" :href="props.meta?.alternates?.ar || alternateUrl('ar')">
            <link rel="alternate" hreflang="en" :href="props.meta?.alternates?.en || alternateUrl('en')">
            <link v-if="props.meta?.alternates?.['x-default']" rel="alternate" hreflang="x-default" :href="props.meta.alternates['x-default']">
            <meta name="robots" :content="props.meta?.robots || 'index,follow'">
            <meta property="og:type" :content="props.meta?.og?.type || 'website'">
            <meta property="og:title" :content="props.meta?.og?.title || title">
            <meta property="og:description" :content="props.meta?.og?.description || description">
            <meta property="og:url" :content="props.meta?.og?.url || props.meta?.canonical">
            <meta property="og:locale" :content="props.meta?.og?.locale || locale">
            <meta property="og:site_name" :content="props.meta?.og?.site_name || site?.shortName || 'PCDR'">
            <meta v-if="props.meta?.og?.image" property="og:image" :content="props.meta.og.image">
            <meta v-if="props.meta?.twitter?.card" name="twitter:card" :content="props.meta.twitter.card">
            <meta v-if="props.meta?.twitter?.title" name="twitter:title" :content="props.meta.twitter.title">
            <meta v-if="props.meta?.twitter?.description" name="twitter:description" :content="props.meta.twitter.description">
            <meta v-if="props.meta?.twitter?.image" name="twitter:image" :content="props.meta.twitter.image">
        </Head>

        <ProPrototypeSprite />
        <a class="skip" href="#top">{{ skipLabel }}</a>
        <ProTopStrip />
        <ProPillHeader />

        <main id="top">
            <ProHeroCarousel :sections="props.sections" />
            <ProTicker :sections="props.sections" />
            <ProImpact :sections="props.sections" />
            <ProProgramAxes :sections="props.sections" />
            <ProNewsTabs :sections="props.sections" />
            <ProMediaGallery :sections="props.sections" />
            <ProAboutTabs :sections="props.sections" />
            <ProStories :sections="props.sections" />
        </main>

        <ProPrototypeFooter :sections="props.sections" />
    </div>
</template>
