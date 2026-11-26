<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import SplitText from '../../Components/StaticHome/SplitText.vue'
import SiteHeader from '../../Components/Public/SiteHeader.vue'
import SiteFooter from '../../Components/Public/SiteFooter.vue'
import ProAboutTabs from '../../Components/RedesignHome/ProAboutTabs.vue'
import ProHeroCarousel from '../../Components/RedesignHome/ProHeroCarousel.vue'
import ProMediaGallery from '../../Components/RedesignHome/ProMediaGallery.vue'
import ProPlanningTargets from '../../Components/RedesignHome/ProPlanningTargets.vue'
import ProStories from '../../Components/RedesignHome/ProStories.vue'
import ProTicker from '../../Components/RedesignHome/ProTicker.vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'
import '../../../css/public-site.css'
import '../../../css/pcdr-home.css'
import '../../../css/pcdr-pro-home.css'

const props = defineProps({
    vision: { type: String, default: '' },
    mission: { type: String, default: '' },
    pillars: { type: Array, default: () => [] },
    framework: { type: Array, default: () => [] },
    articles: { type: Array, default: () => [] },
    programs: { type: Array, default: () => [] },
    publications: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
})

const root = ref(null)
const intro = ref(null)
const heroArt = ref(null)

const { locale, direction, alternateUrl, localPath, t, site } = useI18n()

function routeFor(anchor) {
    const routes = {
        '#top': localPath(),
        '#about': localPath('about'),
        '#areas': localPath('strategic-areas'),
        '#programs': localPath('programs'),
        '#targets': localPath('about/direction'),
        '#knowledge': localPath('publications'),
        '#contact': localPath('contact'),
        '#partners': localPath('partnerships'),
        '#footer': localPath('contact'),
        '#community': localPath('get-involved'),
    }

    return routes[anchor] || anchor
}

function filled(value) {
    return typeof value === 'string' ? value.trim() !== '' : value !== null && value !== undefined
}

const sectionsByKey = computed(() => Object.fromEntries(
    (props.sections || [])
        .filter((section) => section && section.key)
        .map((section) => [section.key, section]),
))

function sectionFor(key) {
    return sectionsByKey.value[key] || null
}

function sectionText(key, field, fallback = '') {
    const value = sectionFor(key)?.[field]
    return filled(value) ? value : fallback
}

function itemsFor(key) {
    return [...(sectionFor(key)?.items || [])]
        .filter((item) => item && item.published !== false)
        .sort((first, second) => (first.sortOrder ?? 0) - (second.sortOrder ?? 0))
}

function textOf(item, field, fallback = '') {
    const value = item?.[field]
    return filled(value) ? value : fallback
}

const publishedPillars = computed(() => (props.pillars || [])
    .filter((pillar) => pillar && filled(pillar.title))
    .map((pillar) => ({
        key: pillar.code || pillar.id || pillar.title,
        title: pillar.title,
        body: filled(pillar.description) ? pillar.description : (pillar.shortTitle || ''),
        url: pillar.url || routeFor('#areas'),
    })))

const publishedPrograms = computed(() => (props.programs || [])
    .filter((program) => program && filled(program.title))
    .map((program) => ({
        key: program.id || program.title,
        title: program.title,
        url: program.url || routeFor('#programs'),
    })))

const knowledgeItems = computed(() => {
    const news = (props.articles || []).filter((item) => item && filled(item.title)).map((item) => ({
        key: `article-${item.id || item.title}`,
        tag: item.category || '',
        title: item.title,
        url: item.url || routeFor('#knowledge'),
    }))
    const publications = (props.publications || []).filter((item) => item && filled(item.title)).map((item) => ({
        key: `publication-${item.id || item.title}`,
        tag: item.category || '',
        title: item.title,
        url: item.url || routeFor('#knowledge'),
    }))

    return [...news, ...publications].slice(0, 6)
})

const heroButtons = computed(() => {
    const buttons = itemsFor('hero').filter((item) => textOf(item, 'label')).slice(0, 2)

    if (buttons.length === 0 && sectionText('hero', 'ctaLabel')) {
        return [{
            key: 'primary',
            label: sectionText('hero', 'ctaLabel'),
            url: sectionText('hero', 'ctaLink') || routeFor('#about'),
        }]
    }

    return buttons.map((button, index) => ({
        key: textOf(button, 'key', `hero-${index}`),
        label: textOf(button, 'label'),
        url: textOf(button, 'url') || (index === 0 ? routeFor('#about') : routeFor('#partners')),
    }))
})

const aboutCards = computed(() => itemsFor('intro')
    .map((item) => ({
        key: textOf(item, 'key'),
        title: textOf(item, 'title'),
        body: textOf(item, 'body'),
    }))
    .filter((card) => filled(card.title) || filled(card.body)))

const frameworkSteps = computed(() => {
    const steps = itemsFor('framework')
        .map((step) => ({
            key: textOf(step, 'key'),
            title: textOf(step, 'title'),
            body: textOf(step, 'body'),
        }))
        .filter((step) => filled(step.title))

    if (steps.length > 0) {
        return steps
    }

    return (props.framework || [])
        .filter((step) => step && filled(step.label))
        .map((step) => ({ key: step.key, title: step.label, body: '' }))
})

const programTiles = computed(() => {
    if (publishedPrograms.value.length > 0) {
        return publishedPrograms.value
    }

    return itemsFor('programs')
        .map((tile) => ({
            key: textOf(tile, 'key'),
            title: textOf(tile, 'title') || textOf(tile, 'label'),
            url: textOf(tile, 'url') || routeFor('#programs'),
        }))
        .filter((tile) => filled(tile.title))
})

const targetStats = computed(() => itemsFor('targets')
    .map((stat) => ({
        key: textOf(stat, 'key'),
        value: Number(stat.value ?? 0),
        display: textOf(stat, 'display', stat.value != null ? String(stat.value) : '0'),
        label: textOf(stat, 'body') || textOf(stat, 'title'),
    }))
    .filter((stat) => filled(stat.label)))

const communityRoles = computed(() => itemsFor('community')
    .map((role) => ({
        key: textOf(role, 'key'),
        title: textOf(role, 'title'),
        body: textOf(role, 'body'),
    }))
    .filter((role) => filled(role.title)))

const partnerCategories = computed(() => itemsFor('partners')
    .map((category) => ({
        key: textOf(category, 'key'),
        label: textOf(category, 'title') || textOf(category, 'label') || textOf(category, 'body'),
    }))
    .filter((category) => filled(category.label)))

const ctaButtons = computed(() => {
    const buttons = itemsFor('cta').filter((item) => textOf(item, 'label'))

    if (buttons.length === 0 && sectionText('cta', 'ctaLabel')) {
        return [{
            key: 'contact',
            label: sectionText('cta', 'ctaLabel'),
            url: sectionText('cta', 'ctaLink') || routeFor('#footer'),
        }]
    }

    return buttons.map((button, index) => ({
        key: textOf(button, 'key', `cta-${index}`),
        label: textOf(button, 'label'),
        url: textOf(button, 'url') || (
            textOf(button, 'key') === 'contact'
                ? routeFor('#footer')
                : (textOf(button, 'key') === 'volunteer' ? routeFor('#community') : routeFor('#areas'))
        ),
    }))
})

const proContent = useHomepageContent(() => props.sections)

const proSlides = computed(() => proContent.items('hero', 'slide-').map((item) => ({
    key: item.key,
    label: textOf(item, 'label'),
    title: textOf(item, 'title'),
    body: textOf(item, 'body'),
})))

const proTicker = computed(() => proContent.items('hero', 'ticker-').map((item) => ({
    key: item.key,
    title: textOf(item, 'title'),
    url: textOf(item, 'url'),
})))

const proTargets = computed(() => proContent.items('targets').map((item) => ({
    key: item.key,
    display: textOf(item, 'display'),
    value: item.value ?? 0,
    label: textOf(item, 'body') || textOf(item, 'title'),
})))

const proIntroItems = computed(() => proContent.items('intro'))

const proObjectives = computed(() => proIntroItems.value
    .filter((item) => String(item.key || '').startsWith('objective-'))
    .map((item) => ({ key: item.key, title: textOf(item, 'title') }))
    .filter((item) => filled(item.title)))

const proValues = computed(() => proIntroItems.value
    .filter((item) => String(item.key || '').startsWith('value-'))
    .map((item) => ({ key: item.key, title: textOf(item, 'title') }))
    .filter((item) => filled(item.title)))

const proIntroCard = (key) => {
    const item = proIntroItems.value.find((entry) => entry.key === key)
    return item ? { title: textOf(item, 'title'), body: textOf(item, 'body') } : null
}

const proMission = computed(() => proIntroCard('mission'))
const proVision = computed(() => proIntroCard('vision'))

const proAlbums = computed(() => proContent.items('community', 'album-').map((item) => ({
    key: item.key,
    title: textOf(item, 'title'),
    mediaUrl: proContent.mediaUrl(item),
    mediaAlt: proContent.mediaAlt(item),
})))

const proStories = computed(() => proContent.items('community', 'story-').map((item) => ({
    key: item.key,
    title: textOf(item, 'title'),
    body: textOf(item, 'body'),
})))

const proLabels = computed(() => locale.value === 'ar' ? {
    carousel: 'أبرز برامج الجمعية',
    pause: 'إيقاف مؤقت للعرض',
    play: 'تشغيل العرض',
    selectSlide: 'اختيار الشريحة',
    slide: 'الشريحة',
    ticker: 'أهم الأخبار',
    impact: 'بصمتنا في المجتمع',
    group: 'رسالتنا وأهدافنا ورؤيتنا',
    mission: 'رسالتنا',
    objectives: 'أهدافنا',
    vision: 'رؤيتنا',
    values: 'قيمنا المؤسسية',
    albums: 'ألبوم الصور',
    stories: 'قصص نجاح',
} : {
    carousel: 'Featured programmes',
    pause: 'Pause slideshow',
    play: 'Play slideshow',
    selectSlide: 'Choose a slide',
    slide: 'Slide',
    ticker: 'Latest news',
    impact: 'Our community imprint',
    group: 'Mission, objectives and vision',
    mission: 'Mission',
    objectives: 'Objectives',
    vision: 'Vision',
    values: 'Institutional values',
    albums: 'Photo album',
    stories: 'Success stories',
})

const copy = computed(() => {
    const visionCard = aboutCards.value.find((card) => card.key === 'vision')
    const missionCard = aboutCards.value.find((card) => card.key === 'mission')

    return {
        skip: t('ui.skip_to_content', 'تخطَّ إلى المحتوى'),
        hero: {
            eyebrow: sectionText('hero', 'eyebrow', site.value?.region || ''),
            title: sectionText('hero', 'title', site.value?.slogan || ''),
            lead: sectionText('hero', 'body', site.value?.slogan || ''),
            buttons: heroButtons.value,
        },
        about: {
            eyebrow: sectionText('intro', 'eyebrow'),
            title: sectionText('intro', 'title'),
            visionTitle: visionCard?.title || '',
            vision: visionCard?.body || props.vision || '',
            missionTitle: missionCard?.title || '',
            mission: missionCard?.body || props.mission || '',
        },
        areas: {
            eyebrow: sectionText('areas', 'eyebrow'),
            title: sectionText('areas', 'title'),
            pillars: publishedPillars.value,
        },
        approach: {
            eyebrow: sectionText('framework', 'eyebrow'),
            title: sectionText('framework', 'title'),
            steps: frameworkSteps.value,
        },
        programs: {
            eyebrow: sectionText('programs', 'eyebrow'),
            title: sectionText('programs', 'title'),
            note: sectionText('programs', 'body', t('strategic_programs_intro')),
            tiles: programTiles.value,
        },
        targets: {
            badge: sectionText('targets', 'eyebrow'),
            title: sectionText('targets', 'title'),
            note: sectionText('targets', 'body', t('strategic_targets_intro')),
            stats: targetStats.value,
        },
        community: {
            eyebrow: sectionText('community', 'eyebrow'),
            title: sectionText('community', 'title'),
            body: sectionText('community', 'body'),
            roles: communityRoles.value,
        },
        partners: {
            eyebrow: sectionText('partners', 'eyebrow'),
            title: sectionText('partners', 'title'),
            lead: sectionText('partners', 'body', t('strategic_partnerships_intro')),
            categories: partnerCategories.value,
        },
        knowledge: {
            eyebrow: sectionText('knowledge', 'eyebrow'),
            title: sectionText('knowledge', 'title'),
            note: sectionText('knowledge', 'body'),
            empty: sectionText('knowledge', 'ctaLabel', locale.value === 'ar' ? 'لا توجد مواد منشورة بعد.' : 'No published items yet.'),
            articles: knowledgeItems.value,
        },
        cta: {
            eyebrow: sectionText('cta', 'eyebrow'),
            title: sectionText('cta', 'title'),
            lead: sectionText('cta', 'body'),
            buttons: ctaButtons.value,
        },
    }
})

const otherLocale = computed(() => (locale.value === 'ar' ? 'en' : 'ar'))
const title = computed(() => filled(props.meta?.title)
    ? String(props.meta.title)
    : (locale.value === 'ar'
        ? 'PCDR | جمعية العمل التشاركي في الأزمات والكوارث'
        : 'PCDR | Participatory Action in Crises and Disasters'))
const description = computed(() => props.meta?.description || site.value?.slogan || '')
const logoSmall = computed(() => site.value?.logo || '/assets/pcdr-homepage/logo/pcdr-logo.png')
const logoAnimated = '/assets/pcdr-homepage/logo/pcdr-logo-animated.svg'

const observers = []
const timers = []
const listenerRemovers = []

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function cleanupMotion() {
    observers.splice(0).forEach((observer) => observer.disconnect())
    timers.splice(0).forEach(clearTimeout)
    listenerRemovers.splice(0).forEach((remove) => remove())
    document.documentElement.classList.remove('js', 'ready')
    document.documentElement.style.removeProperty('scroll-behavior')
    document.documentElement.style.removeProperty('scroll-padding-top')
    document.body.classList.remove('pcdr-menu-open')
}

function setupMotion() {
    const shell = root.value
    if (! shell) {
        return
    }

    document.documentElement.classList.add('js')

    shell.querySelectorAll('[data-stagger]').forEach((group) => {
        [...group.children].forEach((child, index) => child.style.setProperty('--i', String(index)))
    })

    const reduced = prefersReducedMotion()
    if (reduced) {
        document.documentElement.classList.add('ready')
        intro.value?.remove()
        return
    }

    document.documentElement.style.scrollBehavior = 'smooth'
    document.documentElement.style.scrollPaddingTop = '76px'

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (! entry.isIntersecting) {
                    return
                }
                entry.target.classList.add('in')
                observer.unobserve(entry.target)
            })
        }, { threshold: 0.15, rootMargin: '0px 0px -6% 0px' })
        shell.querySelectorAll('.rv, .steps').forEach((element) => revealObserver.observe(element))
        observers.push(revealObserver)

        const vm = shell.querySelector('.vm')
        if (vm) {
            const vmObserver = new IntersectionObserver(([entry], observer) => {
                if (entry.isIntersecting) {
                    vm.classList.add('in')
                    observer.disconnect()
                }
            }, { threshold: 0.15 })
            vmObserver.observe(vm)
            observers.push(vmObserver)
        }

        const format = (value) => value.toLocaleString('en-US')
        const countObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (! entry.isIntersecting) {
                    return
                }
                observer.unobserve(entry.target)
                const element = entry.target
                const end = Number(element.dataset.count || 0)
                const started = performance.now()
                const duration = 1600

                const tick = (now) => {
                    const progress = Math.min((now - started) / duration, 1)
                    element.textContent = format(Math.round(end * (1 - Math.pow(1 - progress, 3))))
                    if (progress < 1) {
                        requestAnimationFrame(tick)
                    }
                }
                requestAnimationFrame(tick)
            })
        }, { threshold: 0.6 })
        shell.querySelectorAll('[data-count]').forEach((element) => countObserver.observe(element))
        observers.push(countObserver)
    }

    const introElement = intro.value
    timers.push(setTimeout(() => {
        introElement?.classList.add('out')
        document.documentElement.classList.add('ready')
        timers.push(setTimeout(() => introElement?.remove(), 1000))
    }, 1250))

    if (! window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return
    }

    const hero = shell.querySelector('.hero')
    if (hero && heroArt.value) {
        const onPointerMove = (event) => {
            const rect = hero.getBoundingClientRect()
            heroArt.value.style.setProperty('--px', (((event.clientX - rect.left) / rect.width) - 0.5) * 2)
            heroArt.value.style.setProperty('--py', (((event.clientY - rect.top) / rect.height) - 0.5) * 2)
        }
        hero.addEventListener('pointermove', onPointerMove)
        listenerRemovers.push(() => hero.removeEventListener('pointermove', onPointerMove))
    }

    shell.querySelectorAll('.tile').forEach((tile) => {
        const onMove = (event) => {
            const rect = tile.getBoundingClientRect()
            const x = (event.clientX - rect.left) / rect.width
            const y = (event.clientY - rect.top) / rect.height
            tile.style.setProperty('--mx', `${x * 100}%`)
            tile.style.setProperty('--my', `${y * 100}%`)
            tile.style.setProperty('--rx', `${(0.5 - y) * 6}deg`)
            tile.style.setProperty('--ry', `${(x - 0.5) * 8}deg`)
        }
        const onLeave = () => {
            tile.style.removeProperty('--rx')
            tile.style.removeProperty('--ry')
        }
        tile.addEventListener('pointermove', onMove)
        tile.addEventListener('pointerleave', onLeave)
        listenerRemovers.push(() => {
            tile.removeEventListener('pointermove', onMove)
            tile.removeEventListener('pointerleave', onLeave)
        })
    })

    shell.querySelectorAll('.btn').forEach((button) => {
        const onMove = (event) => {
            const rect = button.getBoundingClientRect()
            const x = (event.clientX - rect.left - rect.width / 2) * 0.18
            const y = (event.clientY - rect.top - rect.height / 2) * 0.28
            button.style.transform = `translate(${x}px, ${y}px)`
        }
        const onLeave = () => { button.style.transform = '' }
        button.addEventListener('pointermove', onMove)
        button.addEventListener('pointerleave', onLeave)
        listenerRemovers.push(() => {
            button.removeEventListener('pointermove', onMove)
            button.removeEventListener('pointerleave', onLeave)
        })
    })
}

onMounted(() => {
    setupMotion()
})
onBeforeUnmount(() => {
    cleanupMotion()
})
</script>

<template>
    <div ref="root" class="pcdr-home public-shell" :lang="locale" :dir="direction">
        <Head>
            <title>{{ title }}</title>
            <meta name="description" :content="description">
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

        <div id="intro" ref="intro" aria-hidden="true">
            <img :src="logoSmall" alt="" width="132" height="132">
            <i></i>
        </div>
        <div class="progress" aria-hidden="true"></div>
        <a class="skip" href="#main">{{ copy.skip }}</a>
        <SiteHeader :transparent="true" />

        <main id="main">
            <section class="hero" id="top">
                <div v-if="!proSlides.length" class="container hero-grid">
                    <div class="hero-copy">
                        <p class="eyebrow fx">{{ copy.hero.eyebrow }}</p>
                        <h1 data-split><SplitText :text="copy.hero.title" /></h1>
                        <p class="lead fx">{{ copy.hero.lead }}</p>
                        <div class="cta-row fx">
                            <a
                                v-for="(button, index) in copy.hero.buttons"
                                :key="button.key"
                                class="btn"
                                :class="index === 0 ? 'btn-primary' : 'btn-ghost'"
                                :href="button.url"
                            >{{ button.label }}</a>
                        </div>
                    </div>

                    <div ref="heroArt" class="hero-art" aria-hidden="true">
                        <i class="rg"></i><i class="rg"></i><i class="rg"></i>
                        <div class="orbit"><b></b><b></b><b></b></div>
                        <svg class="drawring" viewBox="0 0 100 100"><circle cx="50" cy="50" r="49" /></svg>
                        <div class="badge-wrap">
                            <div class="badge-logo">
                                <img :src="logoAnimated" width="1130" height="1130" alt="">
                                <span class="sheen"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <ProHeroCarousel v-else :slides="proSlides" :labels="proLabels" />
                <div class="wave" aria-hidden="true"><i></i><i class="w2"></i></div>
            </section>
            <ProTicker v-if="proTicker.length" :items="proTicker" :label="proLabels.ticker" />

            <section class="sec about" id="about">
                <div class="container">
                    <p class="eyebrow rv">{{ copy.about.eyebrow }}</p>
                    <h2 data-split class="rv"><SplitText :text="copy.about.title" /></h2>
                    <div v-if="!proMission && !proVision && !proObjectives.length && !proValues.length" class="vm" data-stagger>
                        <article class="vision rv">
                            <h3>{{ copy.about.visionTitle }}</h3>
                            <p>{{ copy.about.vision }}</p>
                        </article>
                        <article class="mission rv">
                            <h3>{{ copy.about.missionTitle }}</h3>
                            <p>{{ copy.about.mission }}</p>
                        </article>
                    </div>
                    <ProAboutTabs
                        v-else
                        :mission="proMission"
                        :vision="proVision"
                        :objectives="proObjectives"
                        :values="proValues"
                        :labels="proLabels"
                    />
                </div>
            </section>

            <section class="sec areas" id="areas">
                <div class="container">
                    <p class="eyebrow rv">{{ copy.areas.eyebrow }}</p>
                    <h2 data-split class="rv"><SplitText :text="copy.areas.title" /></h2>
                    <ol class="pillars" data-stagger>
                        <li v-for="(pillar, index) in copy.areas.pillars" :key="pillar.key || pillar.title" class="rv">
                            <span class="n">{{ String(index + 1).padStart(2, '0') }}</span>
                            <h3>
                                <a v-if="pillar.url && pillar.url !== routeFor('#areas')" :href="pillar.url">{{ pillar.title }}</a>
                                <template v-else>{{ pillar.title }}</template>
                            </h3>
                            <p>{{ pillar.body }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="sec approach" id="approach">
                <div class="container">
                    <p class="eyebrow rv">{{ copy.approach.eyebrow }}</p>
                    <h2 data-split class="rv"><SplitText :text="copy.approach.title" /></h2>
                    <ol class="steps" data-stagger>
                        <li v-for="(step, index) in copy.approach.steps" :key="step.key || step.title">
                            <span class="dot">{{ index + 1 }}</span>
                            <h3>{{ step.title }}</h3>
                            <p>{{ step.body }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="sec programs" id="programs">
                <div class="container">
                    <p class="eyebrow rv">{{ copy.programs.eyebrow }}</p>
                    <h2 data-split class="rv"><SplitText :text="copy.programs.title" /></h2>
                    <p class="note rv">{{ copy.programs.note }}</p>
                    <div class="bento" data-stagger>
                        <a
                            v-for="(tile, index) in copy.programs.tiles"
                            :key="tile.key || tile.url || tile.title"
                            class="tile rv"
                            :class="`t${index + 1}`"
                            :href="tile.url || routeFor('#programs')"
                        >
                            <small>{{ String(index + 1).padStart(2, '0') }}</small>
                            <h3>{{ tile.title }}</h3>
                        </a>
                    </div>
                </div>
            </section>

            <section class="sec targets" id="targets">
                <div class="container">
                    <span class="badge rv">{{ copy.targets.badge }}</span>
                    <h2 data-split class="rv"><SplitText :text="copy.targets.title" /></h2>
                    <p class="note rv">{{ copy.targets.note }}</p>
                    <dl v-if="!proTargets.length" class="stats" data-stagger>
                        <div v-for="stat in copy.targets.stats" :key="stat.key || stat.label" class="rv">
                            <dt class="num" :data-count="stat.value">{{ stat.display }}</dt>
                            <dd>{{ stat.label }}</dd>
                        </div>
                    </dl>
                    <ProPlanningTargets
                        v-else
                        :stats="proTargets"
                        :caveat="copy.targets.note"
                        :heading="proLabels.impact"
                    />
                </div>
            </section>

            <section class="sec community" id="community">
                <div class="container split">
                    <div>
                        <p class="eyebrow rv">{{ copy.community.eyebrow }}</p>
                        <h2 data-split class="rv"><SplitText :text="copy.community.title" /></h2>
                        <p class="rv">{{ copy.community.body }}</p>
                    </div>
                    <ul class="roles" data-stagger>
                        <li v-for="role in copy.community.roles" :key="role.key || role.title" class="rv">
                            <b>{{ role.title }}</b>
                            <span>{{ role.body }}</span>
                        </li>
                    </ul>
                </div>
                <ProMediaGallery v-if="proAlbums.length" :albums="proAlbums" :heading="proLabels.albums" />
                <ProStories v-if="proStories.length" :stories="proStories" :heading="proLabels.stories" />
            </section>

            <section class="sec partners" id="partners">
                <div class="container">
                    <p class="eyebrow rv">{{ copy.partners.eyebrow }}</p>
                    <h2 data-split class="rv"><SplitText :text="copy.partners.title" /></h2>
                    <p class="rv lead">{{ copy.partners.lead }}</p>
                    <ul class="cats" data-stagger>
                        <li v-for="category in copy.partners.categories" :key="category.key || category.label" class="rv">
                            {{ category.label }}
                        </li>
                    </ul>
                </div>
            </section>

            <section class="sec knowledge" id="knowledge">
                <div class="container">
                    <p class="eyebrow rv">{{ copy.knowledge.eyebrow }}</p>
                    <h2 data-split class="rv"><SplitText :text="copy.knowledge.title" /></h2>
                    <p class="note rv">{{ copy.knowledge.note }}</p>
                    <p v-if="!copy.knowledge.articles.length" class="note rv">{{ copy.knowledge.empty }}</p>
                    <div v-else class="news" data-stagger>
                        <article v-for="article in copy.knowledge.articles" :key="article.key || article.url" class="rv">
                            <span v-if="article.tag" class="tag">{{ article.tag }}</span>
                            <h3>
                                <a v-if="article.url" :href="article.url">{{ article.title }}</a>
                                <template v-else>{{ article.title }}</template>
                            </h3>
                        </article>
                    </div>
                </div>
            </section>

            <section class="sec cta" id="contact">
                <div class="container">
                    <h2 data-split class="rv"><SplitText :text="copy.cta.title" /></h2>
                    <p class="rv lead">{{ copy.cta.lead }}</p>
                    <div class="cta-row rv">
                        <a
                            v-for="(button, index) in copy.cta.buttons"
                            :key="button.key"
                            class="btn"
                            :class="index === 0 ? 'btn-primary' : 'btn-ghost'"
                            :href="button.url"
                        >{{ button.label }}</a>
                    </div>
                </div>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>
