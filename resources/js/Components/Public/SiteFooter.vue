<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from '../../composables/useI18n'
import { usePublicNavigation } from '../../composables/usePublicNavigation'
import { useInView } from '../../composables/useInView'
import SocialLinks from './SocialLinks.vue'

const { page, t, site, localPath, locale } = useI18n()
const { footerGroups: navFooterGroups } = usePublicNavigation()

const fallbackGroups = computed(() => {
    const isEn = locale.value === 'en'
    const L = (path, ar, en) => ({ key: path, label: isEn ? en : ar, url: localPath(path) })
    return [
        { key: 'about', title: isEn ? 'About' : 'عن الجمعية', items: [L('about','عن الجمعية','About'), L('strategic-areas','المحاور الاستراتيجية','Strategic Areas'), L('programs','البرامج والمشاريع','Programs & Projects')] },
        { key: 'knowledge', title: isEn ? 'Knowledge & Accountability' : 'المعرفة والمساءلة', items: [L('news','الأخبار والأنشطة','News & Activities'), L('publications','التقارير والإصدارات','Reports & Publications'), L('transparency','الشفافية والمساءلة','Transparency & Accountability'), L('complaints','صندوق الشكاوى','Complaints Box')] },
        { key: 'involve', title: isEn ? 'Get Involved' : 'شارك معنا', items: [L('partnerships','الشراكات','Partnerships'), L('get-involved','شارك معنا','Get Involved'), L('contact','تواصل معنا','Contact Us')] },
    ]
})
const footerGroups = computed(() => {
    const groups = (navFooterGroups.value && navFooterGroups.value.length ? navFooterGroups.value : fallbackGroups.value)
    return groups
        .map((group) => ({
            ...group,
            items: (group.items || []).filter((item) => item && item.label && item.url),
        }))
        .filter((group) => group.items.length > 0)
})

const logo = '/assets/pcdr-homepage/logo/pcdr-logo.png'
const year = new Date().getFullYear()
const footerRef = ref(null)
const { isInView } = useInView(footerRef)
const showTop = ref(false)
const openGroups = ref({})
const reducedMotion = ref(false)

const contact = computed(() => site.value.contact || {})
const social = computed(() => site.value.social || [])
const hasContactData = computed(() => {
    const c = contact.value
    return !!(c.email || c.phone || c.address || c.hours || (social.value && social.value.length))
})

function hasRoute(name) {
    try {
        if (typeof route === 'function') { route(name); return true }
    } catch (_) {}
    return false
}

const legalLinks = computed(() => {
    const links = []
    // Only render if routes exist
    if (hasRoute('privacy')) links.push({ key: 'privacy', label: locale.value === 'en' ? 'Privacy & Data Protection' : 'الخصوصية وحماية البيانات', url: route('privacy') })
    else if (hasRoute('policy.show')) {
        try { links.push({ key: 'privacy', label: locale.value === 'en' ? 'Privacy & Data Protection' : 'الخصوصية وحماية البيانات', url: route('policy.show') }) } catch (_) {}
    }
    return links
})

function toggleGroup(key) {
    openGroups.value = { ...openGroups.value, [key]: !openGroups.value[key] }
}

function onScroll() {
    showTop.value = window.scrollY > 600
}

function scrollTop() {
    window.scrollTo({ top: 0, behavior: reducedMotion.value ? 'auto' : 'smooth' })
}

let mql = null
let onMql = null
onMounted(() => {
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    mql = window.matchMedia('(prefers-reduced-motion: reduce)')
    reducedMotion.value = mql.matches
    onMql = (e) => { reducedMotion.value = e.matches }
    mql.addEventListener?.('change', onMql)
})
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll)
    mql?.removeEventListener?.('change', onMql)
})

const footerLabel = computed(() => locale.value === 'en' ? 'Site footer' : 'تذييل الموقع')
</script>

<template>
    <footer ref="footerRef" class="ps-footer" :aria-label="footerLabel">
        <div class="ps-footer__topline" aria-hidden="true"><span class="ps-footer__topline-accent"></span></div>
        <div class="ps-container ps-footer__inner">
            <div class="ps-footer__grid" :class="{ 'is-in': isInView || reducedMotion }">
                <!-- Brand -->
                <div class="ps-footer__col ps-footer__brand" style="--fi:0">
                    <Link :href="localPath()" class="ps-footer__brand-link" :aria-label="site.name || 'PCDR'">
                        <img :src="logo" width="64" height="64" :alt="site.shortName || 'PCDR'" class="ps-footer__logo">
                        <span class="ps-footer__brand-text">
                            <strong class="ps-footer__brand-short">{{ site.shortName || 'PCDR' }}</strong>
                            <span class="ps-footer__brand-name">{{ site.name }}</span>
                        </span>
                    </Link>
                    <p class="ps-footer__slogan">{{ site.slogan }}</p>
                    <p class="ps-footer__note">{{ t('strategic_statement') }}</p>
                </div>

                <!-- Link groups -->
                <nav
                    v-for="(group, gi) in footerGroups"
                    :key="group.key"
                    class="ps-footer__col ps-footer__group"
                    :class="{ 'is-open': !!openGroups[group.key] }"
                    :aria-labelledby="`footer-group-${group.key}`"
                    :style="{ '--fi': gi + 1 }"
                >
                    <h3 class="ps-footer__heading ps-footer__heading--desktop" :id="`footer-group-${group.key}`">{{ group.title }}</h3>
                    <button
                        type="button"
                        class="ps-footer__accordion-btn"
                        :id="`footer-acc-${group.key}`"
                        :aria-expanded="String(!!openGroups[group.key])"
                        :aria-controls="`footer-panel-${group.key}`"
                        @click="toggleGroup(group.key)"
                    >
                        <span>{{ group.title }}</span>
                        <svg class="ps-footer__acc-icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div
                        :id="`footer-panel-${group.key}`"
                        class="ps-footer__panel"
                        role="region"
                        :aria-labelledby="`footer-acc-${group.key}`"
                    >
                        <div class="ps-footer__panel-inner">
                            <ul class="ps-footer__links">
                                <li v-for="item in group.items" :key="item.key">
                                    <a
                                        v-if="item.external"
                                        :href="item.url"
                                        class="ps-footer__link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >{{ item.label }}</a>
                                    <Link v-else :href="item.url" class="ps-footer__link">{{ item.label }}</Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <!-- Contact -->
                <div class="ps-footer__col ps-footer__contact" :style="{ '--fi': 4 }">
                    <h3 class="ps-footer__heading" id="footer-contact-heading">{{ t('contact_details') }}</h3>
                    <p v-if="!hasContactData" class="ps-footer__contact-placeholder">{{ t('ui.no_verified_contact') }}</p>
                    <ul v-else class="ps-footer__contact-list">
                        <li v-if="contact.address" class="ps-footer__contact-item">
                            <span class="ps-footer__contact-label">{{ locale === 'en' ? 'Address' : 'العنوان' }}:</span>
                            <span>{{ contact.address }}</span>
                        </li>
                        <li v-if="contact.email" class="ps-footer__contact-item">
                            <span class="ps-footer__contact-label">{{ locale === 'en' ? 'Email' : 'البريد الإلكتروني' }}:</span>
                            <a :href="`mailto:${contact.email}`" class="ps-footer__link" dir="ltr">{{ contact.email }}</a>
                        </li>
                        <li v-if="contact.phone" class="ps-footer__contact-item">
                            <span class="ps-footer__contact-label">{{ locale === 'en' ? 'Phone' : 'الهاتف' }}:</span>
                            <a :href="`tel:${contact.phone}`" class="ps-footer__link" dir="ltr">{{ contact.phone }}</a>
                        </li>
                        <li v-if="contact.hours" class="ps-footer__contact-item">
                            <span class="ps-footer__contact-label">{{ locale === 'en' ? 'Hours' : 'ساعات العمل' }}:</span>
                            <span>{{ contact.hours }}</span>
                        </li>
                        <li v-if="social.length" class="ps-footer__contact-item ps-footer__contact-item--social">
                            <SocialLinks :links="social" compact />
                        </li>
                    </ul>
                </div>
            </div>

            <div class="ps-footer__bottom">
                <div class="ps-footer__bottom-start">
                    <span>© {{ year }} {{ site.shortName || 'PCDR' }}</span>
                    <nav v-if="legalLinks.length" class="ps-footer__legal" :aria-label="locale === 'en' ? 'Legal links' : 'روابط قانونية'">
                        <Link v-for="l in legalLinks" :key="l.key" :href="l.url" class="ps-footer__link ps-footer__link--legal">{{ l.label }}</Link>
                    </nav>
                </div>
                <button
                    type="button"
                    class="ps-footer__top"
                    :class="{ 'is-visible': showTop }"
                    :aria-label="locale === 'en' ? 'Back to top' : 'العودة للأعلى'"
                    @click="scrollTop"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <span>{{ locale === 'en' ? 'Back to top' : 'الأعلى' }}</span>
                </button>
            </div>
        </div>
    </footer>
</template>