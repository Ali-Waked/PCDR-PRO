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
    about: { type: Object, default: () => ({}) },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, localPath, site } = useI18n()

const COPY = {
    ar: {
        skip: 'تخطَّ إلى المحتوى',
        eyebrow: 'تعارف',
        title: 'من نحن',
        lead: 'تعرفوا على مسار الجمعية: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة.',
        overview: 'نبذة عن الجمعية',
        vision: 'رؤيتنا',
        mission: 'رسالتنا',
        objectives: 'أهدافنا الاستراتيجية',
        achievements: 'إنجازاتنا ومحاور العمل',
        pillars: 'المحاور الاستراتيجية',
        pillarIntro: ' أحد عشر محوراً موجهاً للعمل المؤسسي في قطاع غزة.',
        board: 'مجلس الإدارة',
        empty: 'لا توجد محتويات منشورة بعد.',
        jump: 'انتقال سريع',
        visionAnchor: 'الرؤية',
        objectivesAnchor: 'الأهداف',
        achievementsAnchor: 'الإنجازات',
        boardAnchor: 'المجلس',
        contact: 'تواصل معنا',
        partners: 'شركاؤنا',
        programs: 'برامجنا',
    },
    en: {
        skip: 'Skip to content',
        eyebrow: 'Who we are',
        title: 'About us',
        lead: 'Discover our path from humanitarian response to recovery and sustainable development.',
        overview: 'Institutional overview',
        vision: 'Our vision',
        mission: 'Our mission',
        objectives: 'Strategic objectives',
        achievements: 'Achievements and focus areas',
        pillars: 'Strategic pillars',
        pillarIntro: 'Eleven pillars guiding our institutional work in the Gaza Strip.',
        board: 'Board of directors',
        empty: 'No published content yet.',
        jump: 'Quick links',
        visionAnchor: 'Vision',
        objectivesAnchor: 'Objectives',
        achievementsAnchor: 'Achievements',
        boardAnchor: 'Board',
        contact: 'Contact us',
        partners: 'Our partners',
        programs: 'Our programmes',
    },
}

const copy = computed(() => COPY[locale.value] ?? COPY.en)
const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim()) ? props.meta.title : copy.value.title)
const description = computed(() => props.meta?.description || site.value?.description || '')
const overview = computed(() => props.about?.overview ?? {})
const vision = computed(() => props.about?.vision ?? {})
const mission = computed(() => props.about?.mission ?? {})
const objectives = computed(() => Array.isArray(props.about?.objectives) ? props.about.objectives : [])
const board = computed(() => Array.isArray(props.about?.board) ? props.about.board : [])
const achievements = computed(() => Array.isArray(props.about?.achievements) ? props.about.achievements : [])
const pillars = computed(() => Array.isArray(props.about?.pillars) ? props.about.pillars : [])
const image = computed(() => props.about?.image ?? null)
const heroTitle = computed(() => props.about?.title || copy.value.title)
const heroBody = computed(() => props.about?.body || copy.value.lead)
const logo = computed(() => site.value?.logo || '/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg')

const anchors = computed(() => [
    { id: 'vision', label: copy.value.visionAnchor },
    { id: 'objectives', label: copy.value.objectivesAnchor },
    { id: 'achievements', label: copy.value.achievementsAnchor },
    { id: 'board', label: copy.value.boardAnchor },
])
</script>

<template>
    <div class="pcdr-prototype about-page" :lang="locale" :dir="direction">
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
        <a class="skip" href="#about-content">{{ copy.skip }}</a>
        <ProTopStrip />
        <ProPillHeader :brand-url="localPath('')" :anchor-base="localPath('')" />

        <main id="top">
            <section class="sec about-hero" aria-labelledby="about-title">
                <div class="wrap">
                    <p class="eyebrow"><b>01</b> {{ props.about?.eyebrow || copy.eyebrow }}</p>
                    <h1 id="about-title" class="h2">{{ heroTitle }}</h1>
                    <div class="deco" aria-hidden="true" />
                    <p class="about-lede">{{ heroBody }}</p>
                    <nav class="about-jump" :aria-label="copy.jump">
                        <a v-for="anchor in anchors" :key="anchor.id" :href="`#${anchor.id}`">{{ anchor.label }}</a>
                    </nav>
                </div>
            </section>

            <section id="overview" class="sec about-overview" aria-labelledby="about-overview-title">
                <div class="wrap about-overview__grid">
                    <figure class="about-figure">
                        <img :src="image?.url || logo" :alt="image?.alt || heroTitle" loading="lazy">
                        <figcaption v-if="image?.caption">{{ image.caption }}</figcaption>
                    </figure>
                    <div>
                        <p class="eyebrow"><b>02</b> {{ copy.overview }}</p>
                        <h2 id="about-overview-title" class="about-section-title">{{ overview.title || copy.overview }}</h2>
                        <p class="about-prose">{{ overview.body || heroBody }}</p>
                        <div class="about-actions">
                            <Link class="about-button" :href="localPath('programs')">{{ copy.programs }}</Link>
                            <Link class="about-button about-button--ghost" :href="localPath('partners')">{{ copy.partners }}</Link>
                        </div>
                    </div>
                </div>
            </section>

            <section id="vision" class="sec about-statement" aria-labelledby="about-vision-title">
                <div class="wrap about-statement__grid">
                    <article class="panel about-card">
                        <p class="eyebrow"><b>03</b> {{ copy.vision }}</p>
                        <h2 id="about-vision-title" class="about-section-title">{{ vision.title || copy.vision }}</h2>
                        <p class="about-prose">{{ vision.body || copy.empty }}</p>
                    </article>
                    <article class="panel about-card">
                        <p class="eyebrow"><b>04</b> {{ copy.mission }}</p>
                        <h2 class="about-section-title">{{ mission.title || copy.mission }}</h2>
                        <p class="about-prose">{{ mission.body || copy.empty }}</p>
                    </article>
                </div>
            </section>

            <section id="objectives" class="sec about-objectives" aria-labelledby="about-objectives-title">
                <div class="wrap">
                    <p class="eyebrow"><b>05</b> {{ copy.objectives }}</p>
                    <h2 id="about-objectives-title" class="about-section-title">{{ copy.objectives }}</h2>
                    <ol v-if="objectives.length" class="goals about-goals">
                        <li v-for="(objective, index) in objectives" :key="objective.key || objective.id || index">
                            <span>{{ objective.title || objective.body }}</span>
                        </li>
                    </ol>
                    <p v-else class="panel about-empty" role="status">{{ copy.empty }}</p>
                </div>
            </section>

            <section id="achievements" class="sec about-achievements" aria-labelledby="about-achievements-title">
                <div class="wrap">
                    <p class="eyebrow"><b>06</b> {{ copy.achievements }}</p>
                    <h2 id="about-achievements-title" class="about-section-title">{{ copy.achievements }}</h2>
                    <p class="fine">{{ copy.pillarIntro }}</p>
                    <div v-if="pillars.length" class="about-pillars">
                        <article v-for="pillar in pillars" :key="pillar.id" class="panel about-pillar">
                            <b>{{ pillar.code }}</b>
                            <h3>{{ pillar.title || pillar.shortTitle }}</h3>
                            <p v-if="pillar.description">{{ pillar.description }}</p>
                        </article>
                    </div>
                    <div v-else-if="achievements.length" class="about-pillars">
                        <article v-for="(item, index) in achievements" :key="item.key || index" class="panel about-pillar">
                            <h3>{{ item.title || item.body }}</h3>
                        </article>
                    </div>
                    <p v-else class="panel about-empty" role="status">{{ copy.empty }}</p>
                </div>
            </section>

            <section id="board" class="sec about-board" aria-labelledby="about-board-title">
                <div class="wrap">
                    <p class="eyebrow"><b>07</b> {{ copy.board }}</p>
                    <h2 id="about-board-title" class="about-section-title">{{ copy.board }}</h2>
                    <div v-if="board.length" class="about-board__grid">
                        <article v-for="(member, index) in board" :key="member.key || member.id || index" class="panel about-member">
                            <span class="about-member__avatar" aria-hidden="true">{{ String(member.title || '?').trim().charAt(0) }}</span>
                            <div>
                                <h3>{{ member.title }}</h3>
                                <p v-if="member.body">{{ member.body }}</p>
                            </div>
                        </article>
                    </div>
                    <p v-else class="panel about-empty" role="status">{{ copy.empty }}</p>
                    <div class="about-actions">
                        <Link class="about-button" :href="localPath('contact')">{{ copy.contact }}</Link>
                    </div>
                </div>
            </section>
        </main>

        <ProPrototypeFooter :anchor-base="localPath('')" />
    </div>
</template>

<style scoped>
.about-hero{background:linear-gradient(155deg,#16233E 0%,#2A3B62 52%,#3E8A84 100%);color:#fff}
.about-hero .eyebrow,.about-hero .h2{color:#fff}
.about-hero .eyebrow b{color:var(--sand)}
.about-lede{max-width:74ch;margin-top:18px;color:var(--on-deep-soft);font-size:clamp(16px,2.2vw,19px)}
.about-jump{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}
.about-jump a{padding:8px 16px;border-radius:999px;border:1px solid rgba(255,255,255,.35);color:#fff;font-weight:700;text-decoration:none;font-size:14px}
.about-jump a:hover{background:var(--sand);color:var(--deep);border-color:var(--sand)}
.about-overview{background:var(--bg)}
.about-overview__grid,.about-statement__grid{display:grid;grid-template-columns:minmax(0,1fr);gap:28px;align-items:start}
@media(min-width:860px){.about-overview__grid{grid-template-columns:minmax(260px,.85fr) minmax(0,1.4fr)}.about-statement__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.about-figure{margin:0;padding:12px;background:var(--surface);border:1px solid var(--line);box-shadow:var(--shadow)}
.about-figure img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover}
.about-figure figcaption{margin-top:8px;font-size:14px;color:var(--ink-soft)}
.about-section-title{font:800 clamp(24px,3.6vw,34px)/1.35 var(--f-head);color:var(--rule);margin-top:8px}
.about-prose{margin-top:14px;color:var(--ink-soft);font-size:clamp(16px,2.1vw,18px);line-height:1.9;text-align:justify}
.about-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
.about-button{display:inline-flex;align-items:center;padding:10px 22px;border-radius:999px;background:var(--accent);color:var(--accent-ink);font:700 15px var(--f-head);text-decoration:none}
.about-button--ghost{background:transparent;color:var(--accent);border:1px solid var(--accent)}
.about-statement{background:var(--surface)}
.about-card{border-top:0;box-shadow:var(--shadow)}
.about-objectives{background:var(--bg)}
.about-goals{margin-top:24px;gap:12px 36px}
.about-goals span{font-size:16px}
.about-achievements{background:var(--surface)}
.about-pillars{list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,250px),1fr));gap:14px;margin-top:24px}
.about-pillar{display:grid;gap:6px;align-content:start;border-top:0;box-shadow:var(--shadow);transition:transform .2s,border-color .2s}
.about-pillar:hover{transform:translateY(-3px);border-color:var(--sand)}
.about-pillar b{font:800 14px var(--f-head);color:var(--sand-ink)}
.about-pillar h3{font:700 18px/1.5 var(--f-head)}
.about-pillar p{font-size:14px;color:var(--ink-soft);line-height:1.7}
.about-board{background:var(--bg)}
.about-board__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:14px;margin-top:24px}
.about-member{display:flex;gap:14px;align-items:start;border-top:0;box-shadow:var(--shadow)}
.about-member__avatar{flex:none;display:grid;place-items:center;width:52px;height:52px;border-radius:50%;background:var(--accent);color:var(--accent-ink);font:800 24px var(--f-head)}
.about-member h3{font:700 18px/1.45 var(--f-head)}
.about-member p{font-size:14px;color:var(--ink-soft);margin-top:6px}
.about-empty{margin-top:20px;border-top:0;text-align:center;color:var(--ink-soft)}
</style>
