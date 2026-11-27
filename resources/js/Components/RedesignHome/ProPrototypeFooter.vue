<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    // Accepted for compatibility with inner pages; destinations use real routes.
    anchorBase: { type: String, default: '' },
})

const { locale, localPath, site } = useI18n()
const content = useHomepageContent(() => props.sections)

const SOCIAL_KINDS = {
    'social-rss': 'rss',
    'social-youtube': 'youtube',
    'social-x': 'text-x',
    'social-facebook': 'text-f',
    'social-instagram': 'text-ig',
    'social-linkedin': 'text-in',
    'social-tiktok': 'text-tt',
    'social-snapchat': 'text-sn',
    'social-whatsapp': 'text-wa',
}

const SOCIAL_GLYPHS = {
    'text-x': 'X',
    'text-f': 'f',
    'text-ig': 'IG',
    'text-in': 'in',
    'text-tt': 'TT',
    'text-sn': 'SN',
    'text-wa': 'WA',
}

const chrome = computed(() => (locale.value === 'ar' ? {
    readMore: 'اقرأ المزيد',
    complaintsTitle: 'اتصل بنا / الشكاوى :',
    complaints: 'اتصل بنا / الشكاوى',
    mediaTitle: 'المرئيات :',
    copyright: '© 2026 جمعية العمل التشاركي في الأزمات والكوارث الخيرية – قطاع غزة',
    fab: 'الشكاوى',
} : {
    readMore: 'Read more',
    complaintsTitle: 'Contact us / complaints:',
    complaints: 'Contact us / complaints',
    mediaTitle: 'Media:',
    copyright: '© 2026 Participatory Action in Crises and Disasters Charity – Gaza Strip',
    fab: 'Complaints',
}))

const footerSection = computed(() => content.visibleSection('footer'))
const footerItems = computed(() => content.items('footer'))

function itemFor(prefix) {
    return footerItems.value.find((item) => String(item.key || '').toLowerCase().startsWith(prefix))
}

function sectionText(field) {
    return content.text(footerSection.value, field)
}

const aboutItem = computed(() => itemFor('about'))
const complaintItem = computed(() => itemFor('complaint'))
const videoItem = computed(() => itemFor('video'))
const copyrightItem = computed(() => itemFor('copyright'))

const aboutHeading = computed(() => content.text(aboutItem.value, 'title') || sectionText('title'))
const aboutBody = computed(() => content.text(aboutItem.value, 'body') || sectionText('body'))
const aboutCta = computed(() => content.text(aboutItem.value, 'ctaLabel')
    || sectionText('ctaLabel')
    || chrome.value.readMore)

const aboutHref = computed(() => {
    const stored = content.safeUrl(content.text(aboutItem.value, 'url') || sectionText('ctaLink'))
    return stored && !stored.startsWith('#') ? stored : localPath('about')
})

const complaintsHeading = computed(() => content.text(complaintItem.value, 'title') || chrome.value.complaintsTitle)
const complaintsBody = computed(() => content.text(complaintItem.value, 'body'))
const complaintsCta = computed(() => content.text(complaintItem.value, 'ctaLabel') || chrome.value.complaints)
const complaintsHref = computed(() => localPath('complaints'))

const mediaHeading = computed(() => content.text(videoItem.value, 'title') || chrome.value.mediaTitle)
const videoCaption = computed(() => sectionText('videoCaption')
    || content.text(videoItem.value, 'caption')
    || content.text(videoItem.value, 'label'))
const videoUrl = computed(() => content.safeUrl(sectionText('videoUrl') || content.text(videoItem.value, 'url')))
const videoAlt = computed(() => content.mediaAlt(videoItem.value))

const legalNoteItem = computed(() => itemFor('legal'))

const copyright = computed(() => content.text(copyrightItem.value, 'title')
    || site.value?.footerCopyright
    || chrome.value.copyright)

const legalNote = computed(() => content.text(legalNoteItem.value, 'title')
    || site.value?.footerLegalNote
    || '')

const social = computed(() => {
    const editorial = content.items('footer', 'social-').map((item) => {
        const key = content.text(item, 'key')
        const kind = SOCIAL_KINDS[key] || 'link'
        return {
            key,
            kind,
            glyph: SOCIAL_GLYPHS[kind] || String(content.text(item, 'title') || kind).slice(0, 2),
            label: content.text(item, 'title') || content.text(item, 'label') || kind,
            url: content.safeUrl(content.text(item, 'url')),
        }
    })

    if (editorial.length) {
        return editorial
    }

    return (Array.isArray(site.value?.social) ? site.value.social : []).map((item) => {
        const key = `social-${item.platform}`
        const kind = SOCIAL_KINDS[key] || 'link'
        return {
            key,
            kind,
            glyph: SOCIAL_GLYPHS[kind] || String(item.label || item.platform).slice(0, 2),
            label: item.label || item.platform,
            url: content.safeUrl(item.url),
        }
    })
})

const footerContact = computed(() => {
    const contact = site.value?.contact || {}
    const whatsapp = (Array.isArray(site.value?.social) ? site.value.social : [])
        .find((item) => item.platform === 'whatsapp')
    const phoneDigits = String(contact.phone || '').replace(/[^\d+]/g, '')

    return [
        contact.email ? { label: contact.email, href: `mailto:${contact.email}` } : null,
        phoneDigits ? { label: contact.phone, href: `tel:${phoneDigits}` } : null,
        whatsapp?.url ? { label: whatsapp.label || 'WhatsApp', href: whatsapp.url } : null,
        contact.address ? { label: contact.address, href: null } : null,
        contact.hours ? { label: contact.hours, href: null } : null,
    ].filter((item) => item !== null)
})

const values = computed(() => content.items('footer', 'value-')
    .map((item) => content.text(item, 'title') || content.text(item, 'label'))
    .filter((value) => value !== ''))

function photoStyle() {
    const media = content.mediaUrl(videoItem.value)
    return media ? { '--photo': `url("${media.replace(/"/g, '%22')}")` } : null
}
</script>

<template>
    <div class="prototype-footer-wrap">
        <footer class="foot surface-dark" data-surface="dark">
            <div class="wrap foot-grid">
                <section>
                    <h3 v-if="aboutHeading">{{ aboutHeading }}</h3>
                    <p v-if="aboutBody">{{ aboutBody }}</p>
                    <Link class="link" :href="aboutHref">{{ aboutCta }}</Link>
                </section>
                <section>
                    <h3 v-if="complaintsHeading">{{ complaintsHeading }}</h3>
                    <p v-if="complaintsBody">{{ complaintsBody }}</p>
                    <Link class="link" :href="complaintsHref">{{ complaintsCta }}</Link>
                    <div class="social">
                        <template v-for="item in social" :key="item.key">
                            <a v-if="item.url" :href="item.url" :aria-label="item.label">
                                <svg v-if="item.kind === 'rss'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="6" cy="18" r="1.6" fill="currentColor"/><path d="M4.5 11a8.5 8.5 0 0 1 8.5 8.5M4.5 4.5a15 15 0 0 1 15 15"/></svg>
                                <svg v-else-if="item.kind === 'youtube'" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4" fill="currentColor"/><path d="M10 9.2v5.6l5-2.8z" style="fill:var(--deep)"/></svg>
                                <template v-else>{{ item.glyph }}</template>
                            </a>
                            <span v-else :aria-label="item.label" :title="item.label">
                                <svg v-if="item.kind === 'rss'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="6" cy="18" r="1.6" fill="currentColor"/><path d="M4.5 11a8.5 8.5 0 0 1 8.5 8.5M4.5 4.5a15 15 0 0 1 15 15"/></svg>
                                <svg v-else-if="item.kind === 'youtube'" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4" fill="currentColor"/><path d="M10 9.2v5.6l5-2.8z" style="fill:var(--deep)"/></svg>
                                <template v-else>{{ item.glyph }}</template>
                            </span>
                        </template>
                    </div>
                </section>
                <section>
                    <h3 v-if="mediaHeading">{{ mediaHeading }}</h3>
                    <a
                        v-if="videoUrl"
                        class="video"
                        :href="videoUrl"
                        :aria-label="videoCaption || videoAlt"
                        :style="photoStyle()"
                    >
                        <span class="p"><svg aria-hidden="true"><use href="#i-play" /></svg></span>
                        <em v-if="videoCaption">{{ videoCaption }}</em>
                    </a>
                    <div
                        v-else
                        class="video"
                        role="img"
                        :aria-label="videoCaption || videoAlt"
                        :style="photoStyle()"
                    >
                        <span class="p"><svg aria-hidden="true"><use href="#i-play" /></svg></span>
                        <em v-if="videoCaption">{{ videoCaption }}</em>
                    </div>
                </section>
            </div>
            <div class="wrap foot-base">
                <span>
                    <span>{{ copyright }}</span>
                    <span v-if="legalNote" class="foot-legal">{{ legalNote }}</span>
                </span>
                <span v-if="footerContact.length" class="foot-contact">
                    <template v-for="(item, index) in footerContact" :key="`${item.label}-${index}`">
                        <a v-if="item.href" :href="item.href">{{ item.label }}</a>
                        <b v-else>{{ item.label }}</b>
                        <template v-if="index < footerContact.length - 1"> · </template>
                    </template>
                </span>
                <span v-if="values.length">
                    <b v-for="(value, index) in values" :key="`${value}-${index}`">{{ value }}<template v-if="index < values.length - 1"> · </template></b>
                </span>
            </div>
        </footer>
        <Link class="fab" :href="complaintsHref" :aria-label="chrome.fab">
            <svg aria-hidden="true"><use href="#i-mail" /></svg>{{ chrome.fab }}
        </Link>
    </div>
</template>

<style scoped>
.foot-legal {
    display: block;
    margin-top: 4px;
    opacity: 0.82;
}

.foot-contact {
    max-width: 46ch;
}

.foot-contact a {
    color: inherit;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.foot-contact b {
    color: inherit;
    font-weight: 500;
}
</style>
