<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import ProPillHeader from '../../Components/RedesignHome/ProPillHeader.vue'
import ProPrototypeFooter from '../../Components/RedesignHome/ProPrototypeFooter.vue'
import ProPrototypeSprite from '../../Components/RedesignHome/ProPrototypeSprite.vue'
import ProTopStrip from '../../Components/RedesignHome/ProTopStrip.vue'
import { useI18n } from '../../composables/useI18n'
import '../../../css/pcdr-prototype.css'

const props = defineProps({
    media: { type: Object, default: null },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, localPath, formatDate, site } = useI18n()

const COPY = {
    ar: {
        skip: 'تخطَّ إلى المحتوى',
        eyebrow: 'المركز الإعلامي',
        title: 'الأخبار والأنشطة والقصص',
        lead: 'تابعوا آخر المستجدات والأنشطة الميدانية وألبومة الصور وقصص التغيير التي شاركتم في صناعتها.',
        news: 'الأخبار',
        activities: 'الأنشطة',
        albums: 'ألبوم الصور',
        stories: 'قصص نجاح',
        emptyNews: 'لا توجد أخبار منشورة بعد.',
        emptyActivities: 'لا توجد أنشطة منشورة بعد.',
        emptyAlbums: 'لا توجد صور منشورة بعد.',
        emptyStories: 'لا توجد قصص منشورة بعد.',
        loading: 'جارٍ تحميل المحتوى الإعلامي…',
        published: 'تاريخ النشر',
        contact: 'أرسل لنا قصتك',
    },
    en: {
        skip: 'Skip to content',
        eyebrow: 'Media centre',
        title: 'News, activities and stories',
        lead: 'Follow the latest updates, field activities, photo albums and stories of change from our work.',
        news: 'News',
        activities: 'Activities',
        albums: 'Photo album',
        stories: 'Success stories',
        emptyNews: 'No published news yet.',
        emptyActivities: 'No published activities yet.',
        emptyAlbums: 'No published photos yet.',
        emptyStories: 'No published stories yet.',
        loading: 'Loading media content…',
        published: 'Published',
        contact: 'Share your story',
    },
}

const copy = computed(() => COPY[locale.value] ?? COPY.en)
const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim()) ? props.meta.title : copy.value.title)
const description = computed(() => props.meta?.description || site.value?.description || '')
const loading = computed(() => props.media === null)
const news = computed(() => Array.isArray(props.media?.news) ? props.media.news : [])
const activities = computed(() => Array.isArray(props.media?.activities) ? props.media.activities : [])
const albums = computed(() => Array.isArray(props.media?.albums?.items) ? props.media.albums.items : [])
const stories = computed(() => Array.isArray(props.media?.stories?.items) ? props.media.stories.items : [])

const tabs = computed(() => [
    { id: 'news', label: copy.value.news },
    { id: 'activities', label: copy.value.activities },
    { id: 'albums', label: copy.value.albums },
    { id: 'stories', label: copy.value.stories },
])
const activeTab = ref('news')

function selectTab(tab) {
    activeTab.value = tab.id
    document.getElementById(tab.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function date(value) {
    return value ? formatDate(value) : null
}
</script>

<template>
    <div class="pcdr-prototype media-page" :lang="locale" :dir="direction">
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
        <a class="skip" href="#media-content">{{ copy.skip }}</a>
        <ProTopStrip />
        <ProPillHeader :brand-url="localPath('')" :anchor-base="localPath('')" />

        <main id="top">
            <section class="sec media-hero" aria-labelledby="media-title">
                <div class="wrap">
                    <p class="eyebrow"><b>01</b> {{ copy.eyebrow }}</p>
                    <h1 id="media-title" class="h2">{{ copy.title }}</h1>
                    <div class="deco" aria-hidden="true" />
                    <p class="media-lede">{{ copy.lead }}</p>
                </div>
            </section>

            <section id="media-content" class="sec media-body" aria-label="">
                <div class="wrap">
                    <div v-if="loading" class="media-grid" aria-busy="true" aria-live="polite">
                        <div v-for="index in 3" :key="index" class="panel media-skeleton" />
                        <p class="sr-only">{{ copy.loading }}</p>
                    </div>

                    <template v-else>
                        <div class="tabs media-tabs" role="tablist" :aria-label="copy.eyebrow">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                class="tab"
                                type="button"
                                role="tab"
                                :aria-selected="activeTab === tab.id ? 'true' : 'false'"
                                :aria-controls="tab.id"
                                @click="selectTab(tab)"
                            >{{ tab.label }}</button>
                        </div>

                        <section id="news" class="media-section" :aria-labelledby="news-title">
                            <header class="media-section__head">
                                <h2 id="news-title">{{ copy.news }}</h2>
                            </header>
                            <div v-if="news.length" class="media-grid">
                                <article v-for="item in news" :key="item.id" class="panel media-card">
                                    <div class="media-card__media" :style="item.image?.url ? { '--photo': `url('${item.image.url.replace(/'/g, '%27')}')` } : undefined" />
                                    <div class="media-card__body">
                                        <h3>{{ item.title }}</h3>
                                        <p v-if="item.category?.name" class="media-category">{{ item.category.name }}</p>
                                        <p v-if="item.summary">{{ item.summary }}</p>
                                        <p v-if="date(item.publishedAt)" class="date">
                                            <span>{{ copy.published }}</span>
                                            <time :datetime="item.publishedAt">{{ date(item.publishedAt) }}</time>
                                        </p>
                                    </div>
                                </article>
                            </div>
                            <p v-else class="panel media-empty" role="status">{{ copy.emptyNews }}</p>
                        </section>

                        <section id="activities" class="media-section" :aria-labelledby="activities-title">
                            <header class="media-section__head">
                                <h2 id="activities-title">{{ copy.activities }}</h2>
                            </header>
                            <div v-if="activities.length" class="media-grid">
                                <article v-for="item in activities" :key="item.id" class="panel media-card">
                                    <div class="media-card__media" :style="item.image?.url ? { '--photo': `url('${item.image.url.replace(/'/g, '%27')}')` } : undefined" />
                                    <div class="media-card__body">
                                        <h3>{{ item.title }}</h3>
                                        <p v-if="item.summary">{{ item.summary }}</p>
                                        <p class="media-meta">
                                            <time v-if="date(item.eventDate)" :datetime="item.eventDate">{{ date(item.eventDate) }}</time>
                                            <time v-else-if="date(item.publishedAt)" :datetime="item.publishedAt">{{ date(item.publishedAt) }}</time>
                                        </p>
                                    </div>
                                </article>
                            </div>
                            <p v-else class="panel media-empty" role="status">{{ copy.emptyActivities }}</p>
                        </section>

                        <section id="albums" class="media-section" :aria-labelledby="albums-title">
                            <header class="media-section__head">
                                <h2 id="albums-title">{{ props.media?.albums?.title || copy.albums }}</h2>
                            </header>
                            <div v-if="albums.length" class="album media-album">
                                <figure v-for="(item, index) in albums" :key="item.id || item.key || index" class="ph" :class="{ big: index === 0 }" :style="item.media?.url ? { '--photo': `url('${item.media.url.replace(/'/g, '%27')}')` } : undefined">
                                    <figcaption>{{ item.title || item.caption || item.label }}</figcaption>
                                </figure>
                            </div>
                            <p v-else class="panel media-empty" role="status">{{ copy.emptyAlbums }}</p>
                        </section>

                        <section id="stories" class="media-section" :aria-labelledby="stories-title">
                            <header class="media-section__head">
                                <h2 id="stories-title">{{ props.media?.stories?.title || copy.stories }}</h2>
                            </header>
                            <ul v-if="stories.length" class="stories">
                                <li v-for="(item, index) in stories" :key="item.id || item.key || index" class="story">
                                    <div class="thumb g2" :style="item.media?.url ? { '--photo': `url('${item.media.url.replace(/'/g, '%27')}')` } : undefined" aria-hidden="true" />
                                    <div>
                                        <h3>{{ item.title || item.label }}</h3>
                                        <p>{{ item.body || item.caption || item.display }}</p>
                                    </div>
                                </li>
                            </ul>
                            <p v-else class="panel media-empty" role="status">{{ copy.emptyStories }}</p>
                        </section>

                        <div class="media-cta">
                            <Link class="programs-button" :href="localPath('contact')">{{ copy.contact }}</Link>
                        </div>
                    </template>
                </div>
            </section>
        </main>

        <ProPrototypeFooter :anchor-base="localPath('')" />
    </div>
</template>

<style scoped>
.media-hero{background:var(--brand-hero);color:var(--on-deep)}
.media-hero .eyebrow,.media-hero .h2{color:var(--on-deep)}
.media-hero .eyebrow b{color:var(--sand)}
.media-lede{max-width:72ch;margin-top:18px;color:var(--on-deep-soft);font-size:clamp(16px,2.2vw,19px)}
.media-body{background:var(--bg)}
.media-tabs{margin-block-start:0;position:sticky;inset-block-start:96px;z-index:5;background:var(--surface)}
.media-section{padding-block-start:44px}
.media-section + .media-section{margin-block-start:36px;border-block-start:1px solid var(--line)}
.media-section__head{display:flex;align-items:center;gap:14px;margin-block-end:20px}
.media-section__head h2{font:800 clamp(24px,3.5vw,32px)/1.3 var(--f-head);color:var(--rule)}
.media-section__head::after{content:"";height:3px;flex:1;background:var(--sand)}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,320px),1fr));gap:16px}
.media-card{border-top:0;box-shadow:var(--shadow);overflow:hidden}
.media-card__media{aspect-ratio:16/9;background:var(--photo,none) center/cover no-repeat,linear-gradient(135deg,#1B2B4B,#3E8A84)}
.media-card__body{padding:18px}
.media-card h3{font:700 19px/1.5 var(--f-head)}
.media-category{margin-block-start:6px;font-size:13px;font-weight:700;color:var(--accent)}
.media-card__body>p:not(.date):not(.media-category){margin-block-start:8px;color:var(--ink-soft);font-size:14px;line-height:1.7}
.media-card .date{margin-block-start:12px;font-size:13px;flex-wrap:wrap}
.media-meta{margin-block-start:10px;color:var(--ink-soft);font-size:13px}
.media-album{margin-block-start:0}
.media-empty{margin-block-start:0;border-top:0;text-align:center;color:var(--ink-soft)}
.media-skeleton{min-height:240px;animation:pcdr-pulse 1.4s ease-in-out infinite}
@keyframes pcdr-pulse{50%{opacity:.45}}
.media-cta{margin-block-start:36px;display:flex;justify-content:flex-start}
.programs-button{display:inline-flex;align-items:center;padding:10px 22px;border-radius:999px;background:var(--accent);color:var(--accent-ink);font:700 15px var(--f-head);text-decoration:none}
</style>
