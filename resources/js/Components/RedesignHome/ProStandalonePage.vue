<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import ProPillHeader from './ProPillHeader.vue'
import ProPrototypeFooter from './ProPrototypeFooter.vue'
import ProPrototypeSprite from './ProPrototypeSprite.vue'
import ProTopStrip from './ProTopStrip.vue'
import { useI18n } from '../../composables/useI18n'
import '../../../css/pcdr-prototype.css'

const props = defineProps({
    page: { type: Object, required: true },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, localPath, formatDate, site } = useI18n()

const copy = computed(() => (locale.value === 'ar' ? {
    skip: 'تخطَّ إلى المحتوى',
    empty: 'لا توجد محتويات منشورة بعد.',
    published: 'تاريخ النشر',
} : {
    skip: 'Skip to content',
    empty: 'No published content yet.',
    published: 'Published',
}))

const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim())
    ? props.meta.title
    : (props.page?.title || ''))
const description = computed(() => props.meta?.description || site.value?.description || '')
const blocks = computed(() => Array.isArray(props.page?.blocks) ? props.page.blocks : [])

function pad(value) {
    return String(value).padStart(2, '0')
}

function items(block) {
    return Array.isArray(block?.items) ? block.items : []
}

function hasItems(block) {
    return items(block).length > 0
}

function keyFor(item, index) {
    return String(item?.key || item?.id || index)
}

function photoStyle(url) {
    return typeof url === 'string' && url !== ''
        ? { '--photo': `url('${url.replace(/'/g, '%27')}')` }
        : undefined
}

function date(value) {
    return value ? formatDate(value) : null
}
</script>

<template>
    <div class="pcdr-prototype standalone-page" :lang="locale" :dir="direction">
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
        <a class="skip" href="#standalone-content">{{ copy.skip }}</a>
        <ProTopStrip />
        <ProPillHeader :brand-url="localPath('')" :anchor-base="localPath('')" />

        <main id="top">
            <section class="sec standalone-hero" aria-labelledby="standalone-title">
                <div class="wrap">
                    <p class="eyebrow"><b>01</b> {{ props.page?.eyebrow }}</p>
                    <h1 id="standalone-title" class="h2">{{ props.page?.title }}</h1>
                    <div class="deco" aria-hidden="true" />
                    <p v-if="props.page?.lead" class="standalone-lede">{{ props.page.lead }}</p>
                </div>
            </section>

            <div id="standalone-content" class="standalone-body">
                <section
                    v-for="(block, index) in blocks"
                    :id="block.id"
                    :key="block.id || index"
                    class="sec standalone-section"
                    :aria-labelledby="`standalone-block-${index}`"
                >
                    <div class="wrap">
                        <p v-if="block.kicker" class="eyebrow"><b>{{ pad(index + 2) }}</b> {{ block.kicker }}</p>
                        <h2 :id="`standalone-block-${index}`" class="standalone-title">{{ block.title }}</h2>
                        <p v-if="block.intro" class="standalone-intro">{{ block.intro }}</p>

                        <div v-if="block.type === 'statements' && hasItems(block)" class="standalone-grid">
                            <article v-for="(item, itemIndex) in items(block)" :key="keyFor(item, itemIndex)" class="panel standalone-card">
                                <h3>{{ item.title || item.label }}</h3>
                                <p v-if="item.body || item.display">{{ item.body || item.display }}</p>
                            </article>
                        </div>

                        <ol v-else-if="block.type === 'items' && hasItems(block)" class="goals standalone-list">
                            <li v-for="(item, itemIndex) in items(block)" :key="keyFor(item, itemIndex)">
                                <span>{{ item.title || item.body || item.display }}</span>
                                <p v-if="item.title && (item.body || item.display)">{{ item.body || item.display }}</p>
                            </li>
                        </ol>

                        <div v-else-if="block.type === 'articles' && hasItems(block)" class="standalone-grid">
                            <article v-for="(item, itemIndex) in items(block)" :key="keyFor(item, itemIndex)" class="panel standalone-article">
                                <div class="standalone-article__media" :style="photoStyle(item.image?.url)" aria-hidden="true" />
                                <div class="standalone-article__body">
                                    <p v-if="item.category?.name" class="standalone-category">{{ item.category.name }}</p>
                                    <h3>{{ item.title }}</h3>
                                    <p v-if="item.summary">{{ item.summary }}</p>
                                    <p v-if="date(item.publishedAt)" class="date">
                                        <span>{{ copy.published }}</span>
                                        <time :datetime="item.publishedAt">{{ date(item.publishedAt) }}</time>
                                    </p>
                                </div>
                            </article>
                        </div>

                        <div v-else-if="block.type === 'gallery' && hasItems(block)" class="album standalone-gallery">
                            <figure
                                v-for="(item, itemIndex) in items(block)"
                                :key="keyFor(item, itemIndex)"
                                class="ph"
                                :class="{ big: itemIndex === 0 }"
                                :style="photoStyle(item.media?.url)"
                            >
                                <figcaption>{{ item.title || item.caption || item.label }}</figcaption>
                            </figure>
                        </div>

                        <ul v-else-if="block.type === 'stories' && hasItems(block)" class="stories">
                            <li v-for="(item, itemIndex) in items(block)" :key="keyFor(item, itemIndex)" class="story">
                                <div class="thumb g2" :style="photoStyle(item.media?.url)" aria-hidden="true" />
                                <div>
                                    <h3>{{ item.title || item.label }}</h3>
                                    <p>{{ item.body || item.caption || item.display }}</p>
                                </div>
                            </li>
                        </ul>

                        <div v-else-if="block.type === 'members' && hasItems(block)" class="standalone-grid">
                            <article v-for="(item, itemIndex) in items(block)" :key="keyFor(item, itemIndex)" class="panel standalone-member">
                                <span class="standalone-avatar" aria-hidden="true">{{ String(item.title || '?').trim().charAt(0) }}</span>
                                <div>
                                    <h3>{{ item.title }}</h3>
                                    <p v-if="item.body">{{ item.body }}</p>
                                </div>
                            </article>
                        </div>

                        <div v-else-if="block.type === 'pillars' && hasItems(block)" class="standalone-grid">
                            <article v-for="(item, itemIndex) in items(block)" :key="keyFor(item, itemIndex)" class="panel standalone-card">
                                <b v-if="item.code">{{ item.code }}</b>
                                <h3>{{ item.title || item.shortTitle }}</h3>
                                <p v-if="item.description">{{ item.description }}</p>
                            </article>
                        </div>

                        <p v-else class="panel standalone-empty" role="status">{{ block.empty || copy.empty }}</p>
                    </div>
                </section>
            </div>
        </main>

        <ProPrototypeFooter :anchor-base="localPath('')" />
    </div>
</template>

<style scoped>
.standalone-lede{max-inline-size:74ch;margin-block-start:18px;color:var(--on-deep-soft);font-size:clamp(16px,2.2vw,19px)}
.standalone-body{background:var(--bg)}
.standalone-section + .standalone-section{border-block-start:1px solid var(--line)}
.standalone-section:nth-child(even){background:var(--surface)}
.standalone-title{font:800 clamp(24px,3.6vw,34px)/1.35 var(--f-head);color:var(--rule);margin-block-start:10px}
.standalone-intro{max-inline-size:72ch;margin-block-start:12px;color:var(--ink-soft);font-size:16px;line-height:1.85}
.standalone-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:16px;margin-block-start:24px}
.standalone-card,.standalone-article,.standalone-member{border-block-start:0;box-shadow:var(--shadow);overflow:hidden}
.standalone-card{display:grid;gap:8px;align-content:start}
.standalone-card b{font:800 14px var(--f-head);color:var(--sand-ink)}
.standalone-card h3,.standalone-article h3,.standalone-member h3{font:700 18px/1.5 var(--f-head)}
.standalone-card p,.standalone-article p,.standalone-member p{color:var(--ink-soft);font-size:14px;line-height:1.75}
.standalone-list{margin-block-start:24px;gap:12px 36px}
.standalone-list li{display:grid;gap:4px}
.standalone-list p{color:var(--ink-soft);font-size:14px;line-height:1.7}
.standalone-article__media{aspect-ratio:16/9;background:var(--photo,none) center/cover no-repeat,var(--brand-hero)}
.standalone-article__body{padding:18px}
.standalone-category{margin-block-end:6px;font-size:13px;font-weight:700;color:var(--accent)}
.standalone-gallery{margin-block-start:24px}
.standalone-member{display:flex;gap:14px;align-items:start;padding:18px}
.standalone-avatar{flex:none;display:grid;place-items:center;inline-size:52px;block-size:52px;border-radius:50%;background:var(--accent);color:var(--accent-ink);font:800 24px var(--f-head)}
.standalone-empty{margin-block-start:24px;border-block-start:0;text-align:center;color:var(--ink-soft)}
</style>
