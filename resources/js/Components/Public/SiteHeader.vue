<script setup>
import { computed, nextTick, onBeforeMount, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import LanguageSwitcher from '../LanguageSwitcher.vue'
import NavDropdown from './NavDropdown.vue'
import { useI18n } from '../../composables/useI18n'
import { usePublicNavigation } from '../../composables/usePublicNavigation'
import { openDropdownId } from '../../Support/navDropdownState'

const props = defineProps({
    transparent: { type: Boolean, default: false },
})

const { page, t, site, locale, localPath } = useI18n()
const { links, cta, barEntries } = usePublicNavigation()

const NAV_BP_FALLBACK = 1280
const MIN_FREE_SPACE = 24

const headerRef = ref(null)
const barListRef = ref(null)
const open = ref(false)
const scrolled = ref(false)
const collapsed = ref(false)
const requiredWidth = ref(0)
const availableWidth = ref(0)
const freeSpace = ref(0)
const ink = ref({ x: 0, w: 0, visible: false, instant: false })
const hoveredKey = ref(null)
const reducedMotion = ref(false)

const currentPath = computed(() => (page.url || '/').split('?')[0])
const origin = typeof window === 'undefined' ? '' : window.location.origin
const isTransparent = computed(() => props.transparent && !scrolled.value && !open.value)

function pathOf(url) {
    try { return new URL(url, origin).pathname } catch { return String(url) }
}

function isActive(url) {
    if (!url) return false
    const path = pathOf(url)
    const home = localPath()
    if (path === home) return currentPath.value === path

    return currentPath.value === path || currentPath.value.startsWith(path + '/')
}

const activeLinkKey = computed(() => links.value.find((item) => isActive(item.url))?.key || null)

const activeBarKey = computed(() => {
    for (const entry of barEntries.value) {
        if (entry.kind === 'dropdown') {
            if (entry.items.some((item) => isActive(item.url))) {
                return entry.key
            }
        } else if (isActive(entry.url)) {
            return entry.key
        }
    }

    return activeLinkKey.value
})

const inkTargetKey = computed(() => hoveredKey.value || activeBarKey.value)

function closeMenu() { open.value = false }

function onScroll() { scrolled.value = window.scrollY > 8 }

function cssBreakpoint() {
    if (!headerRef.value) return NAV_BP_FALLBACK
    const raw = getComputedStyle(headerRef.value).getPropertyValue('--ps-nav-bp')

    return Number.parseFloat(raw) || NAV_BP_FALLBACK
}

let measuring = false

function measureNav() {
    const header = headerRef.value
    if (!header || measuring || typeof window === 'undefined') {
        return
    }

    measuring = true
    try {
        // Force the desktop layout while measuring; the data attribute is
        // applied synchronously so no intermediate frame is painted.
        header.dataset.nav = 'expanded'
        const inner = header.querySelector('.ps-header__inner')
        const brand = header.querySelector('.ps-brand')
        const actions = header.querySelector('.ps-header__actions')
        const list = barListRef.value
        if (!inner || !brand || !actions || !list) {
            return
        }

        const innerStyle = getComputedStyle(inner)
        const paddingStart = Number.parseFloat(innerStyle.paddingInlineStart) || 0
        const paddingEnd = Number.parseFloat(innerStyle.paddingInlineEnd) || 0
        const available = inner.clientWidth - paddingStart - paddingEnd
        const gap = Number.parseFloat(innerStyle.columnGap || innerStyle.gap) || 0
        const required = brand.getBoundingClientRect().width
            + list.scrollWidth
            + actions.getBoundingClientRect().width
            + gap * 2

        requiredWidth.value = Math.round(required)
        availableWidth.value = Math.round(available)
        freeSpace.value = Math.round(available - required)

        const shouldCollapse = window.innerWidth < cssBreakpoint() || required + MIN_FREE_SPACE > available
        if (shouldCollapse !== collapsed.value) {
            collapsed.value = shouldCollapse
            open.value = false
            openDropdownId.value = null
        }

        header.dataset.nav = collapsed.value ? 'collapsed' : 'expanded'
    } finally {
        measuring = false
    }
}

function updateInk(instant = false) {
    if (typeof window === 'undefined' || collapsed.value) {
        ink.value = { ...ink.value, visible: false, instant }
        return
    }

    const list = barListRef.value
    const key = inkTargetKey.value
    if (!list || !key) {
        ink.value = { ...ink.value, visible: false, instant }
        return
    }

    const link = list.querySelector(`[data-nav-key="${key}"]`)
    if (!link || !link.offsetParent) {
        ink.value = { ...ink.value, visible: false, instant }
        return
    }

    const listRect = list.getBoundingClientRect()
    const rect = link.getBoundingClientRect()
    ink.value = {
        x: rect.left - listRect.left,
        w: rect.width,
        visible: true,
        instant: instant || reducedMotion.value,
    }
}

function onHover(key) { hoveredKey.value = key; updateInk() }
function onLeave() { hoveredKey.value = null; updateInk() }

let ro = null
let mql = null
let onMql = null
let onResize = null
let onFrame = null

function scheduleMeasure() {
    if (typeof window === 'undefined') return
    if (onFrame) cancelAnimationFrame(onFrame)
    onFrame = requestAnimationFrame(() => {
        onFrame = null
        measureNav()
        nextTick(() => updateInk(true))
    })
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        open.value = false
        document.querySelector('.ps-burger')?.focus()
    }
}

watch(() => page.url, () => {
    closeMenu()
    openDropdownId.value = null
    nextTick(() => { measureNav(); updateInk(true) })
})

watch(open, (value) => {
    if (typeof document !== 'undefined') document.body.classList.toggle('ps-menu-open', value)
    if (!value) nextTick(() => updateInk(true))
})

watch([activeBarKey, () => barEntries.value, collapsed, locale], () => {
    nextTick(() => { measureNav(); updateInk(true) })
}, { deep: true })

onBeforeMount(() => {
    if (typeof window !== 'undefined') {
        collapsed.value = window.innerWidth < NAV_BP_FALLBACK
    }
})

onMounted(() => {
    onScroll()
    measureNav()
    window.addEventListener('scroll', onScroll, { passive: true })
    document.addEventListener('keydown', onKeydown)
    mql = window.matchMedia('(prefers-reduced-motion: reduce)')
    reducedMotion.value = mql.matches
    onMql = (event) => { reducedMotion.value = event.matches; updateInk(true) }
    mql.addEventListener?.('change', onMql)
    nextTick(() => updateInk(true))
    onResize = () => scheduleMeasure()
    if (typeof ResizeObserver !== 'undefined' && headerRef.value) {
        ro = new ResizeObserver(scheduleMeasure)
        ro.observe(headerRef.value)
    }
    window.addEventListener('resize', onResize, { passive: true })
    if (document.fonts?.ready) document.fonts.ready.then(scheduleMeasure)
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll)
    document.removeEventListener('keydown', onKeydown)
    window.removeEventListener('resize', onResize)
    if (onFrame) cancelAnimationFrame(onFrame)
    ro?.disconnect()
    mql?.removeEventListener?.('change', onMql)
    if (typeof document !== 'undefined') document.body.classList.remove('ps-menu-open')
})
</script>

<template>
    <header
        ref="headerRef"
        class="ps-header"
        :class="{ 'is-scrolled': scrolled, 'is-transparent': isTransparent, 'is-open': open }"
        :data-nav="collapsed ? 'collapsed' : 'expanded'"
        :data-nav-required="requiredWidth"
        :data-nav-available="availableWidth"
        :data-nav-free="freeSpace"
    >
        <div class="ps-container ps-header__inner">
            <Link :href="localPath()" class="ps-brand" @click="closeMenu">
                <img :src="'/assets/pcdr-homepage/logo/pcdr-logo.png'" width="48" height="48" :alt="site.shortName || 'PCDR'">
                <span class="ps-brand__text">
                    <strong>{{ site.shortName || 'PCDR' }}</strong>
                    <span>{{ site.name }}</span>
                </span>
            </Link>

            <nav id="public-nav" class="ps-nav" :class="{ 'is-open': open }" :aria-label="t('ui.main_navigation', t('cms_menu_primary'))">
                <ul ref="barListRef" class="ps-nav__list ps-nav__list--bar" @mouseleave="onLeave">
                    <span
                        class="ps-nav__ink"
                        :class="{ 'is-visible': ink.visible, 'is-instant': ink.instant }"
                        :style="{ transform: `translateX(${ink.x}px)`, width: `${ink.w}px` }"
                        aria-hidden="true"
                    ></span>
                    <template v-for="entry in barEntries" :key="entry.key">
                        <NavDropdown
                            v-if="entry.kind === 'dropdown'"
                            :entry="entry"
                            :transparent="isTransparent"
                            :active-item-key="activeLinkKey"
                            @hover="onHover"
                            @leave="onLeave"
                        />
                        <li v-else class="ps-nav__item">
                            <a
                                v-if="entry.external"
                                :href="entry.url"
                                class="ps-nav__link"
                                :data-nav-key="entry.key"
                                target="_blank"
                                rel="noopener noreferrer"
                                @mouseenter="onHover(entry.key)"
                                @focus="onHover(entry.key)"
                                @blur="onLeave"
                                @click="closeMenu"
                            >
                                <span class="ps-nav__label">{{ entry.label }}</span>
                                <span class="ps-nav__active-dot" aria-hidden="true"></span>
                            </a>
                            <Link
                                v-else
                                :href="entry.url"
                                class="ps-nav__link"
                                :data-nav-key="entry.key"
                                :aria-current="isActive(entry.url) ? 'page' : undefined"
                                @mouseenter="onHover(entry.key)"
                                @focus="onHover(entry.key)"
                                @blur="onLeave"
                                @click="closeMenu"
                            >
                                <span class="ps-nav__label">{{ entry.label }}</span>
                                <span class="ps-nav__active-dot" aria-hidden="true"></span>
                            </Link>
                        </li>
                    </template>
                </ul>

                <ul class="ps-nav__list ps-nav__list--mobile" @mouseleave="onLeave">
                    <li v-for="(item, i) in links" :key="item.key" class="ps-nav__item" :style="{ '--nav-i': i }">
                        <a
                            v-if="item.external"
                            :href="item.url"
                            class="ps-nav__link"
                            :data-nav-key="item.key"
                            target="_blank"
                            rel="noopener noreferrer"
                            @click="closeMenu"
                        >
                            <span class="ps-nav__label">{{ item.label }}</span>
                            <span class="ps-nav__mobile-bar" aria-hidden="true"></span>
                            <span class="ps-nav__mobile-dot" aria-hidden="true"></span>
                        </a>
                        <Link
                            v-else
                            :href="item.url"
                            class="ps-nav__link"
                            :aria-current="isActive(item.url) ? 'page' : undefined"
                            @click="closeMenu"
                        >
                            <span class="ps-nav__label">{{ item.label }}</span>
                            <span class="ps-nav__mobile-bar" aria-hidden="true"></span>
                            <span class="ps-nav__mobile-dot" aria-hidden="true"></span>
                        </Link>
                    </li>
                </ul>

                <div class="ps-nav__extra">
                    <Link :href="cta.url" class="ps-btn ps-btn-primary" @click="closeMenu">{{ cta.label }}</Link>
                    <div class="ps-nav__lang-row">
                        <span>{{ t('ui.switch_language', locale === 'en' ? 'Language' : 'تغيير اللغة') }}</span>
                        <LanguageSwitcher />
                    </div>
                </div>
            </nav>

            <div class="ps-header__actions">
                <LanguageSwitcher class="ps-header__lang" />
                <Link :href="cta.url" class="ps-btn ps-btn-primary ps-header__cta">{{ cta.label }}</Link>
                <button
                    class="ps-burger"
                    type="button"
                    :aria-expanded="String(open)"
                    aria-controls="public-nav"
                    :aria-label="open ? t('ui.close_menu') : t('ui.open_menu')"
                    @click="open = !open"
                >
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </header>
</template>
