<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from '../../composables/useI18n'

// Kept for compatibility with inner pages that still pass a page-derived
// anchor base; destinations below are always resolved from the real routes.
defineProps({
    brandUrl: { type: String, default: '' },
    anchorBase: { type: String, default: '' },
    partnersUrl: { type: String, default: '' },
})

const { locale, alternateUrl, localPath } = useI18n()

const logo = '/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg'

const homeHref = computed(() => localPath())
const aboutHref = computed(() => localPath('about'))
const programsHref = computed(() => localPath('programs'))
const mediaHref = computed(() => localPath('media'))
const partnersHref = computed(() => localPath('partners'))
const contactHref = computed(() => localPath('contact'))
const complaintsHref = computed(() => localPath('complaints'))
const anchored = (base, id) => `${base}#${id}`

const copy = computed(() => (locale.value === 'ar' ? {
    brand: 'جمعية العمل التشاركي',
    brandSub: 'في الأزمات والكوارث – قطاع غزة',
    brandLabel: 'جمعية العمل التشاركي في الأزمات والكوارث، الصفحة الرئيسية',
    menu: 'القائمة الرئيسية',
    about: 'من نحن',
    vision: 'رؤيتنا',
    goals: 'أهدافنا الاستراتيجية',
    achievements: 'إنجازاتنا بالأرقام',
    board: 'مجلس الإدارة',
    programs: 'برامجنا',
    media: 'المركز الإعلامي',
    news: 'الأخبار والأنشطة',
    albums: 'ألبوم الصور',
    stories: 'قصص نجاح',
    partners: 'شركاؤنا',
    contact: 'اتصل بنا',
    complaints: 'صندوق الشكاوى',
    arabic: 'العربية',
    english: 'English',
} : {
    brand: 'Participatory Action Association',
    brandSub: 'in Crises and Disasters – Gaza Strip',
    brandLabel: 'Participatory Action in Crises and Disasters, home page',
    menu: 'Main menu',
    about: 'About us',
    vision: 'Our vision',
    goals: 'Our strategic objectives',
    achievements: 'Our achievements in numbers',
    board: 'Board of directors',
    programs: 'Our programmes',
    media: 'Media centre',
    news: 'News and activities',
    albums: 'Photo album',
    stories: 'Success stories',
    partners: 'Our partners',
    contact: 'Contact us',
    complaints: 'Complaints',
    arabic: 'العربية',
    english: 'English',
}))

const menuOpen = ref(false)
const openGroups = ref({ about: true, media: false })
const activeSub = ref('about')
const burger = ref(null)
const menu = ref(null)

function toggleMenu() {
    menuOpen.value = !menuOpen.value
}

function closeMenu() {
    menuOpen.value = false
}

function toggleGroup(group) {
    openGroups.value[group] = !openGroups.value[group]
}

function activate(sub) {
    activeSub.value = sub
    closeMenu()
}

function onDocumentClick(event) {
    if (!menuOpen.value) {
        return
    }
    if (menu.value?.contains(event.target) || burger.value?.contains(event.target)) {
        return
    }
    closeMenu()
}

function onKeydown(event) {
    if (event.key === 'Escape' && menuOpen.value) {
        closeMenu()
        burger.value?.focus()
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <header class="head">
        <div class="wrap">
            <div class="head-in">
                <div class="pill">
                    <Link class="brand" :href="homeHref" :aria-label="copy.brandLabel" @click="closeMenu">
                        <span class="logo">
                            <img :src="logo" alt="" width="76" height="76">
                        </span>
                        <span class="brand-t">
                            <b>{{ copy.brand }}</b>
                            <small>{{ copy.brandSub }}</small>
                        </span>
                    </Link>
                    <button
                        ref="burger"
                        class="burger"
                        type="button"
                        :aria-expanded="menuOpen ? 'true' : 'false'"
                        aria-controls="menu"
                        :aria-label="copy.menu"
                        @click.stop="toggleMenu"
                    >
                        <svg class="m" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                        <svg class="x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>

                <nav
                    ref="menu"
                    class="menu"
                    id="menu"
                    :hidden="!menuOpen"
                    :aria-label="copy.menu"
                >
                    <div class="mi" :class="{ open: openGroups.about }">
                        <button
                            class="mi-h"
                            type="button"
                            :aria-expanded="openGroups.about ? 'true' : 'false'"
                            aria-controls="sub-about"
                            @click="toggleGroup('about')"
                        >
                            <span>{{ copy.about }}</span>
                            <svg class="ico" aria-hidden="true"><use href="#i-chev" /></svg>
                        </button>
                        <ul class="sub" id="sub-about">
                            <li>
                                <Link
                                    :href="aboutHref"
                                    :class="{ on: activeSub === 'about' }"
                                    @click="activate('about')"
                                >{{ copy.about }}</Link>
                            </li>
                            <li>
                                <Link
                                    :href="anchored(aboutHref, 'vision')"
                                    :class="{ on: activeSub === 'vision' }"
                                    @click="activate('vision')"
                                >{{ copy.vision }}</Link>
                            </li>
                            <li>
                                <Link
                                    :href="anchored(aboutHref, 'objectives')"
                                    :class="{ on: activeSub === 'objectives' }"
                                    @click="activate('objectives')"
                                >{{ copy.goals }}</Link>
                            </li>
                            <li>
                                <Link
                                    :href="anchored(aboutHref, 'achievements')"
                                    @click="closeMenu"
                                >{{ copy.achievements }}</Link>
                            </li>
                            <li>
                                <Link
                                    :href="anchored(aboutHref, 'board')"
                                    @click="closeMenu"
                                >{{ copy.board }}</Link>
                            </li>
                        </ul>
                    </div>
                    <div class="mi"><Link class="mi-h" :href="programsHref" @click="closeMenu">{{ copy.programs }}</Link></div>
                    <div class="mi" :class="{ open: openGroups.media }">
                        <button
                            class="mi-h"
                            type="button"
                            :aria-expanded="openGroups.media ? 'true' : 'false'"
                            aria-controls="sub-media"
                            @click="toggleGroup('media')"
                        >
                            <span>{{ copy.media }}</span>
                            <svg class="ico" aria-hidden="true"><use href="#i-chev" /></svg>
                        </button>
                        <ul class="sub" id="sub-media">
                            <li><Link :href="mediaHref" @click="closeMenu">{{ copy.media }}</Link></li>
                            <li><Link :href="anchored(mediaHref, 'news')" @click="closeMenu">{{ copy.news }}</Link></li>
                            <li><Link :href="anchored(mediaHref, 'albums')" @click="closeMenu">{{ copy.albums }}</Link></li>
                            <li><Link :href="anchored(mediaHref, 'stories')" @click="closeMenu">{{ copy.stories }}</Link></li>
                        </ul>
                    </div>
                    <div class="mi"><Link class="mi-h" :href="partnersHref" @click="closeMenu">{{ copy.partners }}</Link></div>
                    <div class="mi"><Link class="mi-h" :href="contactHref" @click="closeMenu">{{ copy.contact }}</Link></div>
                    <div class="mi"><Link class="mi-h" :href="complaintsHref" @click="closeMenu">{{ copy.complaints }}</Link></div>
                    <div class="menu-foot">
                        <div class="mi">
                            <div class="mi-h lang-row" role="group" aria-label="Language / اللغة">
                                <a
                                    :href="alternateUrl('ar')"
                                    lang="ar"
                                    hreflang="ar"
                                    :aria-current="locale === 'ar' ? 'page' : undefined"
                                    @click="closeMenu"
                                >{{ copy.arabic }}</a>
                                <span class="sep" aria-hidden="true">·</span>
                                <a
                                    :href="alternateUrl('en')"
                                    lang="en"
                                    hreflang="en"
                                    :aria-current="locale === 'en' ? 'page' : undefined"
                                    @click="closeMenu"
                                >{{ copy.english }}</a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>
</template>
