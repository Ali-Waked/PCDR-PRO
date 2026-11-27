<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLanguageSwitcher from '../Components/AdminLanguageSwitcher.vue'
import FlashMessages from '../Components/FlashMessages.vue'
import UiIcon from '../Components/Dashboard/UiIcon.vue'
import { useI18n } from '../composables/useI18n'
import { useLocale } from '../Composables/useLocale'
import { usePermission } from '../Composables/usePermission'

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
})

const { page, t: siteT, localPath, direction, locale } = useI18n()
const { t } = useLocale()
const { canInstitutionalApprove } = usePermission()
const mobileNavigationOpen = ref(false)
const userMenuOpen = ref(false)
const toggleRef = ref(null)
const drawerRef = ref(null)
const userMenuRef = ref(null)
const isDesktop = ref(false)
let mql = null
let onMql = null

const user = computed(() => page.props.auth?.user || null)
const currentPath = computed(() => (page.url || '/').split('?')[0])
const userInitials = computed(() => {
    const name = String(user.value?.name || '').trim()
    if (!name) return '?'
    return name.split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('')
})

const iconFor = {
    dashboard: 'layout',
    users: 'users',
    roles: 'shield',
    audit_logs: 'history',
    audit_archives: 'history',
    platform_settings: 'settings',
    cms_dashboard: 'layout',
    pillars: 'layers',
    programs: 'folder',
    projects: 'folder',
    news: 'newspaper',
    publications: 'folder',
    media: 'image',
    navigation: 'link',
    homepage: 'home',
    inquiries: 'mail',
    email_templates: 'template',
    cms_settings: 'settings',
}

const navigationGroups = computed(() => (page.props.adminNavigation ?? []).map((group) => ({
    ...group,
    items: group.items.map((item) => {
        const href = localPath(item.path.replace(/^\//, ''))

        return {
            ...item,
            href,
            icon: iconFor[item.key] ?? 'file',
            active: currentPath.value === href || (href !== localPath('cms') && currentPath.value.startsWith(`${href}/`)),
        }
    }),
})))

const openGroups = ref({})
watch(navigationGroups, (groups) => {
    const next = { ...openGroups.value }
    for (const g of groups) {
        if (next[g.key] === undefined) next[g.key] = g.items.some((i) => i.active)
        if (g.items.some((i) => i.active)) next[g.key] = true
    }
    openGroups.value = next
}, { immediate: true })
function toggleGroup(key) { openGroups.value = { ...openGroups.value, [key]: !openGroups.value[key] } }

function hrefFor(path) {
    return localPath(path.replace(/^\//, ''))
}

function logout() {
    router.post(hrefFor('cms/logout'))
}

function closeNav() {
    if (isDesktop.value) return
    mobileNavigationOpen.value = false
    requestAnimationFrame(() => toggleRef.value?.focus?.())
}
function closeUserMenu() { userMenuOpen.value = false }
function focusables() {
    const root = drawerRef.value
    if (!root) return []
    return [...root.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')].filter((el) => !el.closest('[inert]'))
}
function onDocumentClick(e) {
    if (userMenuOpen.value && userMenuRef.value && !userMenuRef.value.contains(e.target)) closeUserMenu()
}
function onKeydown(e) {
    if (e.key === 'Escape' && userMenuOpen.value) { e.preventDefault(); closeUserMenu(); return }
    if (!mobileNavigationOpen.value || isDesktop.value) return
    if (e.key === 'Escape') { e.preventDefault(); closeNav(); return }
    if (e.key === 'Tab') {
        const items = focusables()
        if (!items.length) { e.preventDefault(); return }
        const first = items[0]; const last = items[items.length - 1]
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
    }
}

watch(() => page.url, (next, prev) => {
    const norm = (u = '') => String(u).split('?')[0].replace(/^\/(ar|en)(?=\/|$)/, '') || '/'
    if (norm(next) !== norm(prev)) mobileNavigationOpen.value = false
    userMenuOpen.value = false
})
watch(mobileNavigationOpen, (open) => {
    if (typeof document === 'undefined') return
    document.body.classList.toggle('admin-nav-open', open)
    if (open && !isDesktop.value) requestAnimationFrame(() => { (focusables()[0] || drawerRef.value)?.focus?.() })
})
watch([locale, direction], ([loc, dir]) => {
    if (typeof document === 'undefined') return
    document.documentElement.lang = loc === 'ar' ? 'ar' : 'en'
    document.documentElement.dir = dir || (loc === 'ar' ? 'rtl' : 'ltr')
    document.documentElement.dataset.locale = loc
}, { immediate: true })
onMounted(() => {
    if (typeof window === 'undefined') return
    mql = window.matchMedia('(min-width: 1100px)')
    isDesktop.value = mql.matches
    onMql = (e) => { isDesktop.value = e.matches; if (e.matches) mobileNavigationOpen.value = false }
    mql.addEventListener?.('change', onMql)
    document.addEventListener('keydown', onKeydown)
    document.addEventListener('click', onDocumentClick)
})
onBeforeUnmount(() => {
    mql?.removeEventListener?.('change', onMql)
    if (typeof document !== 'undefined') {
        document.removeEventListener('keydown', onKeydown)
        document.removeEventListener('click', onDocumentClick)
        document.body.classList.remove('admin-nav-open')
    }
})
</script>

<template>
    <div class="admin-shell cms-admin-shell" :dir="direction">
        <a href="#main" class="skip-link">
            {{ siteT('ui.skip_to_content', t('skip_to_content')) }}
        </a>

        <header class="admin-topbar" :inert="mobileNavigationOpen && !isDesktop">
            <div class="admin-topbar__inner">
                <button
                    ref="toggleRef"
                    type="button"
                    class="admin-nav-toggle"
                    :aria-expanded="mobileNavigationOpen ? 'true' : 'false'"
                    aria-controls="cms-navigation"
                    :aria-label="t('admin_nav.toggle')"
                    @click="mobileNavigationOpen = !mobileNavigationOpen"
                >
                    <span aria-hidden="true">☰</span>
                </button>

                <Link :href="hrefFor('cms')" class="admin-brand-link focus-ring admin-brand-link--compact">
                    <img class="admin-brand-logo" src="/assets/pcdr-homepage/logo/pcdr-logo.png" alt="" aria-hidden="true" width="40" height="40">
                    <span class="admin-brand-name">PCDR</span>
                </Link>

                <div class="admin-topbar__context admin-topbar__context--desktop" aria-hidden="true">
                    <span class="admin-topbar__divider" />
                    <span>{{ t('admin_brand.content_studio') }}</span>
                </div>

                <div class="admin-topbar__actions">
                    <div class="admin-topbar__lang-desktop">
                        <AdminLanguageSwitcher />
                    </div>
                    <div ref="userMenuRef" class="admin-user-menu">
                        <button
                            type="button"
                            class="admin-avatar-btn focus-ring"
                            :aria-expanded="userMenuOpen ? 'true' : 'false'"
                            aria-haspopup="menu"
                            :aria-label="user?.name || t('users_page.user')"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <span class="admin-avatar-btn__initials" aria-hidden="true">{{ userInitials }}</span>
                        </button>
                        <div v-if="userMenuOpen" class="admin-user-popover" role="menu">
                            <div class="admin-user-popover__identity">
                                <strong>{{ user?.name }}</strong>
                                <small>{{ siteT('ui.cms_welcome', t('admin_brand.content_studio')) }}</small>
                            </div>
                            <div v-if="canInstitutionalApprove" class="admin-user-popover__badge">
                                <span class="admin-role-badge admin-role-badge--authority">{{ t('admin_brand.approval_authority') }}</span>
                            </div>
                            <div class="admin-user-popover__row admin-user-popover__lang">
                                <span class="admin-user-popover__label">{{ t('language') }} / Language</span>
                                <AdminLanguageSwitcher />
                            </div>
                            <button class="btn btn-ghost btn-small admin-user-popover__logout" type="button" role="menuitem" @click="logout(); closeUserMenu()">
                                {{ siteT('ui.cms_logout', t('logout')) }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-shell__body">
            <Teleport to="body" :disabled="isDesktop">
                <div v-if="mobileNavigationOpen" class="admin-nav-scrim" aria-hidden="true" @click="closeNav" />
                <aside
                    id="cms-navigation"
                    ref="drawerRef"
                    class="admin-sidebar"
                    :class="{ 'is-open': mobileNavigationOpen }"
                    :dir="direction"
                    :role="isDesktop ? undefined : 'dialog'"
                    :aria-modal="!isDesktop && mobileNavigationOpen ? 'true' : undefined"
                    :aria-hidden="!isDesktop && !mobileNavigationOpen ? 'true' : undefined"
                    :aria-label="t('admin_nav.title')"
                >
                    <nav>
                        <div v-for="group in navigationGroups" :key="group.key" class="admin-sidebar__group">
                            <button
                                type="button"
                                class="admin-sidebar__label admin-sidebar__label-btn"
                                :aria-expanded="openGroups[group.key] ? 'true' : 'false'"
                                @click="toggleGroup(group.key)"
                            >
                                <span>{{ group.label }}</span>
                                <span class="admin-sidebar__chevron" aria-hidden="true">{{ openGroups[group.key] ? '▾' : '▸' }}</span>
                            </button>
                            <ul v-show="openGroups[group.key]">
                                <li v-for="item in group.items" :key="item.key">
                                    <Link
                                        :href="item.href"
                                        class="admin-nav-link"
                                        :class="{ 'is-active': item.active }"
                                        :aria-current="item.active ? 'page' : undefined"
                                        @click="closeNav"
                                    >
                                        <span class="admin-nav-link__icon"><UiIcon :name="item.icon" :size="17" /></span>
                                        <span class="admin-nav-link__label">{{ item.label }}</span>
                                        <span v-if="item.active" class="admin-nav-link__marker" aria-hidden="true" />
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </nav>

                    <div class="admin-sidebar__footer">
                        <Link :href="hrefFor('')" class="admin-nav-link">
                            {{ siteT('cms_preview', t('admin_brand.preview_site')) }}
                        </Link>
                        <div class="admin-sidebar__lang">
                            <AdminLanguageSwitcher />
                        </div>
                        <p class="admin-sidebar__note" role="note">
                            {{ t('admin_brand.authority_note') }}
                        </p>
                    </div>
                </aside>
            </Teleport>

            <main id="main" class="admin-main" tabindex="-1" :inert="mobileNavigationOpen && !isDesktop">
                <header class="admin-page-heading pcdr-enter">
                    <p class="admin-eyebrow">{{ subtitle || t('admin_brand.content_studio') }}</p>
                    <h1>{{ title }}</h1>
                </header>
                <FlashMessages />
                <slot />
            </main>
        </div>
    </div>
</template>
