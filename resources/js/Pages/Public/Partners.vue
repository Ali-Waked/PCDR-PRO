<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import ProPillHeader from '../../Components/RedesignHome/ProPillHeader.vue'
import ProPrototypeFooter from '../../Components/RedesignHome/ProPrototypeFooter.vue'
import ProPrototypeSprite from '../../Components/RedesignHome/ProPrototypeSprite.vue'
import ProTopStrip from '../../Components/RedesignHome/ProTopStrip.vue'
import { useI18n } from '../../composables/useI18n'
import '../../../css/pcdr-prototype.css'

const props = defineProps({
    directory: { type: Object, default: () => ({}) },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, localPath, site, t } = useI18n()

const homeUrl = computed(() => localPath(''))
const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim())
    ? props.meta.title
    : t('ui.partner_directory.seo_title', 'PCDR | Partners'))
const description = computed(() => props.meta?.description || site.value?.description || t('ui.partner_directory.seo_description', ''))
const skipLabel = computed(() => t('ui.skip_to_content', locale.value === 'ar' ? 'تخطَّ إلى المحتوى' : 'Skip to content'))
const categories = computed(() => Array.isArray(props.directory?.categories) ? props.directory.categories : [])
const groups = computed(() => Array.isArray(props.directory?.groups) ? props.directory.groups : [])
const hasPartners = computed(() => Boolean(props.directory?.hasPartners) && groups.value.length > 0)
const activeCategory = computed(() => props.directory?.activeCategory || 'all')

const countLabel = computed(() => translate('ui.partner_directory.partners_count', {
    count: Number(props.directory?.total ?? 0),
}))

function translate(key, replacements = {}, fallback = '') {
    let value = String(t(key) ?? '')
    if (value === '') {
        value = String(fallback ?? '')
    }

    for (const [name, replacement] of Object.entries(replacements)) {
        value = value.replaceAll(`:${name}`, String(replacement ?? ''))
    }

    return value
}

function filterHref(slug) {
    const base = localPath('partners')
    return slug === 'all' ? base : `${base}?category=${encodeURIComponent(slug)}`
}
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
        <ProPillHeader :brand-url="homeUrl" :anchor-base="homeUrl" :partners-url="localPath('partners')" />

        <main id="top">
            <section class="sec partner-hero" aria-labelledby="partners-title">
                <div class="wrap">
                    <p class="eyebrow"><b>01</b> {{ t('ui.partner_directory.public_eyebrow') }}</p>
                    <h1 id="partners-title" class="h2">{{ t('ui.partner_directory.public_title') }}</h1>
                    <div class="deco" aria-hidden="true" />
                    <p class="partner-lede">{{ t('ui.partner_directory.public_intro') }}</p>
                    <p class="partner-count">{{ countLabel }}</p>
                </div>
            </section>

            <section class="sec partner-directory" aria-labelledby="directory-title">
                <div class="wrap">
                    <h2 id="directory-title" class="sr-only">{{ t('ui.partner_directory.filter_label') }}</h2>

                    <nav
                        v-if="categories.length"
                        class="partner-filters"
                        :aria-label="t('ui.partner_directory.filter_label')"
                    >
                        <Link
                            :href="filterHref('all')"
                            class="partner-filter"
                            :class="{ 'is-active': activeCategory === 'all' }"
                            :aria-current="activeCategory === 'all' ? 'page' : undefined"
                            preserve-scroll
                        >
                            {{ t('ui.partner_directory.filter_all') }}
                            <span>{{ Number(directory.total ?? 0) }}</span>
                        </Link>
                        <Link
                            v-for="category in categories"
                            :key="category.slug"
                            :href="filterHref(category.slug)"
                            class="partner-filter"
                            :class="{ 'is-active': activeCategory === category.slug }"
                            :aria-current="activeCategory === category.slug ? 'page' : undefined"
                            preserve-scroll
                        >
                            {{ category.name }}
                            <span>{{ Number(category.count ?? 0) }}</span>
                        </Link>
                    </nav>

                    <template v-if="hasPartners">
                        <section
                            v-for="group in groups"
                            :key="group.slug"
                            class="partner-group"
                            :aria-labelledby="`partner-group-${group.slug}`"
                        >
                            <header class="partner-group__head">
                                <h3 :id="`partner-group-${group.slug}`">{{ group.name }}</h3>
                                <p v-if="group.description">{{ group.description }}</p>
                            </header>
                            <ul class="partner-grid">
                                <li v-for="(partner, index) in group.partners" :key="`${group.slug}-${index}`" class="partner-card">
                                    <span class="partner-logo">
                                        <img v-if="partner.logo?.url" :src="partner.logo.url" :alt="partner.logo.alt || partner.name" loading="lazy">
                                        <b v-else aria-hidden="true">{{ String(partner.name || '?').trim().charAt(0) }}</b>
                                    </span>
                                    <div class="partner-card__body">
                                        <h4>{{ partner.name }}</h4>
                                        <p v-if="partner.description">{{ partner.description }}</p>
                                        <p class="partner-meta">
                                            <span v-if="partner.year">{{ translate('ui.partner_directory.partnership_year', { year: partner.year }) }}</span>
                                            <a
                                                v-if="partner.website"
                                                :href="partner.website"
                                                :aria-label="translate('ui.partner_directory.visit_website_label', { name: partner.name })"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                dir="ltr"
                                            >{{ partner.website }}</a>
                                        </p>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </template>
                    <div v-else class="partner-empty" role="status">
                        <h3>{{ t('ui.partner_directory.empty_title') }}</h3>
                        <p>{{ t('ui.partner_directory.empty_body') }}</p>
                    </div>

                    <aside class="partner-cta" :aria-label="t('ui.partner_directory.cta_title')">
                        <div>
                            <h3>{{ t('ui.partner_directory.cta_title') }}</h3>
                            <p>{{ t('ui.partner_directory.cta_body') }}</p>
                        </div>
                        <Link class="partner-cta__link" :href="localPath('contact')">{{ t('ui.partner_directory.cta_label') }}</Link>
                    </aside>
                </div>
            </section>
        </main>

        <ProPrototypeFooter :anchor-base="homeUrl" />
    </div>
</template>
