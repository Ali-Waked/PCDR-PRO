<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { LANGUAGE_OPTIONS } from '../../Support/languageSwitcher'
import { useI18n } from '../../composables/useI18n'

// Kept for compatibility with inner pages that still pass a page-derived
// anchor base. All menu links come from the shared navigation payload.
defineProps({
    brandUrl: { type: String, default: '' },
    anchorBase: { type: String, default: '' },
    partnersUrl: { type: String, default: '' },
})

const page = usePage()
const { locale, alternateUrl, site } = useI18n()

const primary = computed(() => Array.isArray(page.props.navigation?.primary)
    ? page.props.navigation.primary.filter((item) => item && typeof item.url === 'string' && item.url !== '')
    : [])

const copy = computed(() => (locale.value === 'ar' ? {
    brandLabel: 'الصفحة الرئيسية',
    menu: 'القائمة الرئيسية',
    arabic: 'العربية',
    english: 'English',
} : {
    brandLabel: 'home page',
    menu: 'Main menu',
    arabic: 'العربية',
    english: 'English',
}))

const logo = computed(() => site.value?.logo || '/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg')
const brand = computed(() => String(site.value?.name || '').trim())
const brandSub = computed(() => site.value?.region || site.value?.slogan || '')
const brandLabel = computed(() => `${brand.value} — ${copy.value.brandLabel}`)

const menuOpen = ref(false)
const openGroups = ref({})
const activeSub = ref(null)
const burger = ref(null)
const menu = ref(null)

function keyFor(item, fallback = '') {
    const raw = String(item?.key || item?.url || fallback)
    const normalized = raw.replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '')

    return normalized || 'item'
}

const groups = computed(() => primary.value.filter(
    (item) => Array.isArray(item.children) && item.children.length > 0,
))
const defaultGroupKey = computed(() => (groups.value.length ? keyFor(groups.value[0]) : ''))
const defaultActiveKey = computed(() => {
    const group = groups.value[0]
    return group ? `${keyFor(group)}-${keyFor(group.children[0])}` : ''
})

function hasChildren(item) {
    return Array.isArray(item.children) && item.children.length > 0
}

function isGroupOpen(key) {
    if (Object.prototype.hasOwnProperty.call(openGroups.value, key)) {
        return openGroups.value[key] === true
    }

    return key === defaultGroupKey.value
}

function isActive(key) {
    return (activeSub.value || defaultActiveKey.value) === key
}

function toggleMenu() {
    menuOpen.value = !menuOpen.value
}

function closeMenu() {
    menuOpen.value = false
}

function toggleGroup(group) {
    openGroups.value[group] = !isGroupOpen(group)
}

function activate(key) {
    activeSub.value = key
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
                    <Link class="brand" :href="primary[0]?.url || '/'" :aria-label="brandLabel" @click="closeMenu">
                        <span class="logo">
                            <img :src="logo" alt="" width="56" height="56">
                        </span>
                        <span class="brand-t">
                            <b>{{ brand }}</b>
                            <small>{{ brandSub }}</small>
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
                    <div
                        v-for="(item, index) in primary"
                        :key="keyFor(item, String(index))"
                        class="mi"
                        :class="{ open: hasChildren(item) && isGroupOpen(keyFor(item, String(index))) }"
                    >
                        <button
                            v-if="hasChildren(item)"
                            class="mi-h"
                            type="button"
                            :aria-expanded="isGroupOpen(keyFor(item, String(index))) ? 'true' : 'false'"
                            :aria-controls="`sub-${keyFor(item, String(index))}`"
                            @click="toggleGroup(keyFor(item, String(index)))"
                        >
                            <span>{{ item.label }}</span>
                            <svg class="ico" aria-hidden="true"><use href="#i-chev" /></svg>
                        </button>
                        <a
                            v-else-if="item.external"
                            class="mi-h"
                            :href="item.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            @click="closeMenu"
                        >{{ item.label }}</a>
                        <Link v-else class="mi-h" :href="item.url" @click="closeMenu">{{ item.label }}</Link>

                        <ul v-if="hasChildren(item)" class="sub" :id="`sub-${keyFor(item, String(index))}`">
                            <li v-for="(child, childIndex) in item.children" :key="keyFor(child, `${index}-${childIndex}`)">
                                <a
                                    v-if="child.external"
                                    :href="child.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    @click="closeMenu"
                                >{{ child.label }}</a>
                                <Link
                                    v-else
                                    :href="child.url"
                                    :class="{ on: isActive(`${keyFor(item, String(index))}-${keyFor(child, String(childIndex))}`) }"
                                    @click="activate(`${keyFor(item, String(index))}-${keyFor(child, String(childIndex))}`)"
                                >{{ child.label }}</Link>
                            </li>
                        </ul>
                    </div>
                    <div class="menu-foot">
                        <div class="mi">
                            <div class="mi-h lang-row" role="group" aria-label="Language / اللغة">
                                <a
                                    v-for="option in LANGUAGE_OPTIONS"
                                    :key="option.code"
                                    :href="alternateUrl(option.code)"
                                    :lang="option.lang"
                                    :hreflang="option.lang"
                                    :aria-current="locale === option.code ? 'page' : undefined"
                                    @click="closeMenu"
                                >{{ option.code === 'ar' ? copy.arabic : copy.english }}</a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>
</template>
