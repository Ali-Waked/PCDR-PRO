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
    programs: { type: Object, default: null },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, localPath, formatDate, site } = useI18n()

const COPY = {
    ar: {
        skip: 'تخطَّ إلى المحتوى',
        eyebrow: 'خطتنا التشغيلية',
        title: 'برامجنا ومشاريعنا',
        lead: 'برامج ومشاريع منشورة، مصنّفة وفق المحاور الاستراتيجية للخطة 2027–2029.',
        filterLabel: 'تصفية حسب المجال',
        filterAll: 'كل المجالات',
        empty: 'لا توجد برامج منشورة في هذا المجال حتى الآن.',
        emptyAreas: 'لا توجد محاور استراتيجية منشورة بعد.',
        loading: 'جارٍ تحميل البرامج…',
        noProjects: 'لا توجد مشاريع منشورة ضمن هذا البرنامج.',
        projects: 'المشاريع',
        start: 'البداية',
        end: 'النهاية',
        contact: 'اقترح تعاوناً معنا',
        count: 'برنامج',
    },
    en: {
        skip: 'Skip to content',
        eyebrow: 'Our operational plan',
        title: 'Programmes and projects',
        lead: 'Published programmes and projects organised under the strategic pillars of the 2027–2029 plan.',
        filterLabel: 'Filter by area',
        filterAll: 'All areas',
        empty: 'No published programmes are available in this area yet.',
        emptyAreas: 'No strategic areas have been published yet.',
        loading: 'Loading programmes…',
        noProjects: 'No published projects in this programme.',
        projects: 'Projects',
        start: 'Start',
        end: 'End',
        contact: 'Propose a partnership',
        count: 'programmes',
    },
}

const copy = computed(() => COPY[locale.value] ?? COPY.en)
const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim()) ? props.meta.title : copy.value.title)
const description = computed(() => props.meta?.description || site.value?.description || '')
const loading = computed(() => props.programs === null)
const areas = computed(() => Array.isArray(props.programs?.areas) ? props.programs.areas : [])
const programs = computed(() => Array.isArray(props.programs?.programs) ? props.programs.programs : [])
const activeArea = computed(() => props.programs?.activeArea ?? null)
const programCount = computed(() => String(programs.value.length))

function areaHref(code) {
    const base = localPath('programs')
    return code ? `${base}?area=${encodeURIComponent(code)}` : base
}

function date(value) {
    return value ? formatDate(value) : null
}
</script>

<template>
    <div class="pcdr-prototype programs-page" :lang="locale" :dir="direction">
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
        <a class="skip" href="#programs-content">{{ copy.skip }}</a>
        <ProTopStrip />
        <ProPillHeader :brand-url="localPath('')" :anchor-base="localPath('')" />

        <main id="top">
            <section class="sec programs-hero" aria-labelledby="programs-title">
                <div class="wrap">
                    <p class="eyebrow"><b>01</b> {{ copy.eyebrow }}</p>
                    <h1 id="programs-title" class="h2">{{ copy.title }}</h1>
                    <div class="deco" aria-hidden="true" />
                    <p class="programs-lede">{{ copy.lead }}</p>
                </div>
            </section>

            <section id="programs-content" class="sec programs-body" aria-labelledby="programs-list-title">
                <div class="wrap">
                    <h2 id="programs-list-title" class="sr-only">{{ copy.title }}</h2>

                    <div v-if="loading" class="programs-grid" aria-busy="true" aria-live="polite">
                        <div v-for="index in 3" :key="index" class="panel programs-skeleton" />
                        <p class="sr-only">{{ copy.loading }}</p>
                    </div>

                    <template v-else>
                        <nav class="programs-filters" :aria-label="copy.filterLabel">
                            <Link
                                :href="areaHref(null)"
                                class="programs-filter"
                                :class="{ 'is-active': !activeArea }"
                                :aria-current="!activeArea ? 'page' : undefined"
                                preserve-scroll
                            >{{ copy.filterAll }}</Link>
                            <Link
                                v-for="area in areas"
                                :key="area.id"
                                :href="areaHref(area.code)"
                                class="programs-filter"
                                :class="{ 'is-active': activeArea === area.code || activeArea === String(area.id) }"
                                :aria-current="activeArea === area.code ? 'page' : undefined"
                                preserve-scroll
                            >{{ area.shortTitle || area.title }}</Link>
                        </nav>

                        <p v-if="!areas.length" class="panel programs-empty" role="status">{{ copy.emptyAreas }}</p>

                        <div v-else-if="programs.length" class="programs-grid">
                            <article v-for="program in programs" :key="program.id" class="panel programs-card">
                                <div v-if="program.image?.url" class="programs-card__media">
                                    <img :src="program.image.url" :alt="program.image.alt || program.title || ''" loading="lazy">
                                </div>
                                <div class="programs-card__body">
                                    <span v-if="program.area" class="programs-badge">{{ program.area.title }} · {{ program.area.code }}</span>
                                    <h3>{{ program.title }}</h3>
                                    <p v-if="program.summary">{{ program.summary }}</p>

                                    <dl v-if="date(program.startDate) || date(program.endDate)" class="programs-dates">
                                        <div v-if="date(program.startDate)">
                                            <dt>{{ copy.start }}</dt>
                                            <dd><time :datetime="program.startDate">{{ date(program.startDate) }}</time></dd>
                                        </div>
                                        <div v-if="date(program.endDate)">
                                            <dt>{{ copy.end }}</dt>
                                            <dd><time :datetime="program.endDate">{{ date(program.endDate) }}</time></dd>
                                        </div>
                                    </dl>

                                    <h4 class="programs-projects-title">{{ copy.projects }}</h4>
                                    <ul v-if="program.projects?.length" class="programs-projects">
                                        <li v-for="project in program.projects" :key="project.id" class="programs-project">
                                            <img v-if="project.image?.url" :src="project.image.url" :alt="project.image.alt || project.title || ''" loading="lazy">
                                            <div>
                                                <h5>{{ project.title }}</h5>
                                                <p v-if="project.summary">{{ project.summary }}</p>
                                                <p class="programs-meta">
                                                    <span v-if="project.statusLabel" class="programs-status">{{ project.statusLabel }}</span>
                                                    <time v-if="date(project.startDate)" :datetime="project.startDate">{{ date(project.startDate) }}</time>
                                                    <time v-if="date(project.endDate)" :datetime="project.endDate">{{ date(project.endDate) }}</time>
                                                </p>
                                            </div>
                                        </li>
                                    </ul>
                                    <p v-else class="fine">{{ copy.noProjects }}</p>
                                </div>
                            </article>
                        </div>

                        <p v-else class="panel programs-empty" role="status">{{ copy.empty }}</p>

                        <div class="programs-cta">
                            <p>{{ programCount }} {{ copy.count }}</p>
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
.programs-hero{background:var(--brand-hero);color:var(--on-deep)}
.programs-hero .eyebrow,.programs-hero .h2{color:#fff}
.programs-hero .eyebrow b{color:var(--sand)}
.programs-lede{max-width:72ch;margin-top:18px;color:var(--on-deep-soft);font-size:clamp(16px,2.2vw,19px)}
.programs-body{background:var(--bg)}
.programs-filters{display:flex;flex-wrap:wrap;gap:10px;margin-block-end:28px}
.programs-filter{padding:8px 16px;border:1px solid var(--line);border-radius:999px;background:var(--surface);color:var(--ink-soft);font:700 14px/1.5 var(--f-head);text-decoration:none}
.programs-filter:hover{border-color:var(--sand);color:var(--ink)}
.programs-filter.is-active{background:var(--accent);border-color:var(--accent);color:var(--accent-ink)}
.programs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,360px),1fr));gap:18px;align-items:start}
.programs-card{border-top:0;box-shadow:var(--shadow);display:grid;gap:0;overflow:hidden}
.programs-card__media{background:var(--g,linear-gradient(135deg,#2A3B62,#3E8A84));aspect-ratio:16/8}
.programs-card__media img{width:100%;height:100%;object-fit:cover}
.programs-card__body{padding:20px}
.programs-badge{display:inline-block;padding:4px 12px;border-radius:999px;background:var(--sand);color:#1B2B4B;font:700 12px var(--f-head)}
.programs-card h3{font:800 clamp(20px,3vw,25px)/1.4 var(--f-head);margin-block-start:12px;color:var(--rule)}
.programs-card__body>p{margin-block-start:8px;color:var(--ink-soft);font-size:15px;line-height:1.75}
.programs-dates{display:flex;flex-wrap:wrap;gap:10px 24px;margin:16px 0 0;padding-top:12px;border-top:1px solid var(--line)}
.programs-dates dt{font:700 12px var(--f-head);color:var(--ink-soft)}
.programs-dates dd{margin:0;font-size:14px;font-weight:700}
.programs-projects-title{font:700 16px var(--f-head);margin-block:18px 10px;color:var(--accent)}
.programs-projects{list-style:none;padding:0;display:grid;gap:12px}
.programs-project{display:grid;grid-template-columns:72px minmax(0,1fr);gap:12px;padding:12px;border:1px solid var(--line);border-radius:12px;background:var(--bg)}
.programs-project img{width:72px;height:72px;object-fit:cover;border-radius:8px}
.programs-project h5{font:700 16px/1.45 var(--f-head)}
.programs-project p{font-size:13px;color:var(--ink-soft);line-height:1.6;margin-block-start:4px}
.programs-meta{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-block-start:8px;font-size:12px}
.programs-status{padding:2px 10px;border-radius:999px;background:var(--accent);color:var(--accent-ink);font-weight:700}
.programs-empty{margin-block-start:20px;border-top:0;text-align:center;color:var(--ink-soft)}
.programs-skeleton{min-height:280px;animation:pcdr-pulse 1.4s ease-in-out infinite}
@keyframes pcdr-pulse{50%{opacity:.45}}
.programs-cta{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin-block-start:36px;padding:20px 24px;background:var(--surface);border:1px solid var(--line);border-inline-start:6px solid var(--sand)}
.programs-cta p{font:700 16px var(--f-head);color:var(--ink-soft)}
.programs-button{display:inline-flex;align-items:center;padding:10px 22px;border-radius:999px;background:var(--accent);color:var(--accent-ink);font:700 15px var(--f-head);text-decoration:none}
@media(max-width:560px){.programs-project{grid-template-columns:56px minmax(0,1fr)}.programs-project img{width:56px;height:56px}}
</style>
