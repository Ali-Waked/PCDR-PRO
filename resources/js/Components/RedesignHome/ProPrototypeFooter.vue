<script setup>
import { computed, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import SocialPlatformIcon from './SocialPlatformIcon.vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    // Accepted for compatibility with inner pages; destinations use real routes.
    anchorBase: { type: String, default: '' },
})

const page = usePage()
const { site, t } = useI18n()

// Shared middleware data is the default. Home also passes its sections prop;
// an empty shared array is respected so a hidden footer is never resurrected.
const effectiveSections = computed(() => Array.isArray(page.props.footer)
    ? page.props.footer
    : (Array.isArray(props.sections) ? props.sections : []))
const content = useHomepageContent(() => effectiveSections.value)

const primaryNavigation = computed(() => Array.isArray(page.props.navigation?.primary)
    ? page.props.navigation.primary.filter((item) => item && typeof item.url === 'string' && item.url !== '')
    : [])
const navigationItem = (key) => primaryNavigation.value.find((item) => item.key === key) || null
const aboutGroup = computed(() => navigationItem('about'))
const aboutLinks = computed(() => Array.isArray(aboutGroup.value?.children)
    ? aboutGroup.value.children.filter((item) => item && typeof item.url === 'string' && item.url !== '' && item.url !== '#')
    : [])
const complaintsLink = computed(() => navigationItem('complaints'))

const footerSection = computed(() => content.visibleSection('footer'))
const footerItems = computed(() => content.items('footer'))

function itemFor(...prefixes) {
    return footerItems.value.find((item) => prefixes.some((prefix) => (
        String(item.key || '').toLowerCase().startsWith(prefix)
    )))
}

function sectionText(field) {
    return content.text(footerSection.value, field)
}

const aboutItem = computed(() => itemFor('footer-about', 'about-'))
const complaintItem = computed(() => itemFor('footer-contact', 'complaint-', 'contact-'))

const aboutHeading = computed(() => content.text(aboutItem.value, 'title') || sectionText('title'))
const aboutBody = computed(() => content.text(aboutItem.value, 'body') || sectionText('body'))
const aboutCta = computed(() => content.text(aboutItem.value, 'ctaLabel') || sectionText('ctaLabel'))
const aboutCtaHref = computed(() => aboutLinks.value[0]?.url || '')

const complaintsHeading = computed(() => content.text(complaintItem.value, 'title'))
const complaintsBody = computed(() => content.text(complaintItem.value, 'body'))
const complaintsCta = computed(() => content.text(complaintItem.value, 'ctaLabel') || complaintsLink.value?.label || '')
const complaintsHref = computed(() => complaintsLink.value?.url || '')

// The introductory film is configured in Website Settings and delivered as a
// safe, already-resolved embed payload. The footer never builds iframe URLs.
const footerVideo = computed(() => site.value?.footerVideo || null)
const videoAvailable = computed(() => Boolean(footerVideo.value?.visible && footerVideo.value?.embedUrl))
const videoHeading = computed(() => t('footer_video_heading', 'المرئيات'))
const videoTitle = computed(() => footerVideo.value?.title || '')
const watchLabel = computed(() => t('footer_watch_video', 'مشاهدة الفيديو'))
const fallbackMessage = computed(() => t(
    'footer_video_fallback',
    'لا يمكن تشغيل هذا الفيديو هنا. يمكنك مشاهدته على موقع المزوّد.',
))
const playbackActive = ref(false)
const playbackFailed = ref(false)

watch(() => footerVideo.value?.embedUrl, () => {
    playbackActive.value = false
    playbackFailed.value = false
})

function startPlayback() {
    playbackFailed.value = false
    playbackActive.value = true
}

function handleIframeError() {
    playbackFailed.value = true
    playbackActive.value = false
}

const copyright = computed(() => site.value?.footerCopyright || '')
const legalNote = computed(() => site.value?.footerLegalNote || '')

const social = computed(() => (Array.isArray(site.value?.social) ? site.value.social : [])
    .filter((item) => item && typeof item.url === 'string' && item.url !== '')
    .map((item) => ({
        key: item.platform,
        platform: item.platform,
        label: item.label || item.platform,
        url: content.safeUrl(item.url),
    }))
    .filter((item) => item.url !== ''))

const footerContact = computed(() => {
    const contact = site.value?.contact || {}
    const whatsapp = (Array.isArray(site.value?.social) ? site.value.social : [])
        .find((item) => item?.platform === 'whatsapp' && typeof item.url === 'string' && item.url !== '')
    const phoneDigits = String(contact.phone || '').replace(/[^\d+]/g, '')

    return [
        contact.email ? { label: contact.email, href: `mailto:${contact.email}` } : null,
        phoneDigits ? { label: contact.phone, href: `tel:${phoneDigits}` } : null,
        whatsapp?.url ? { label: whatsapp.label || whatsapp.platform, href: whatsapp.url } : null,
        contact.address ? { label: contact.address, href: null } : null,
        contact.hours ? { label: contact.hours, href: null } : null,
    ].filter((item) => item !== null)
})

const values = computed(() => content.items('footer', 'value-')
    .map((item) => content.text(item, 'title') || content.text(item, 'label'))
    .filter((value) => value !== ''))
</script>

<template>
    <div class="prototype-footer-wrap">
        <footer class="foot surface-dark" data-surface="dark">
            <div class="wrap foot-grid">
                <section>
                    <h3 v-if="aboutHeading">{{ aboutHeading }}</h3>
                    <p v-if="aboutBody">{{ aboutBody }}</p>
                    <ul v-if="aboutLinks.length" class="foot-links">
                        <li v-for="link in aboutLinks" :key="`${link.key}-${link.url}`">
                            <a
                                v-if="link.external"
                                :href="link.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >{{ link.label }}</a>
                            <Link v-else :href="link.url">{{ link.label }}</Link>
                        </li>
                    </ul>
                    <Link
                        v-if="aboutCta && aboutCtaHref"
                        class="link"
                        :href="aboutCtaHref"
                    >{{ aboutCta }}</Link>
                </section>

                <section>
                    <h3 v-if="complaintsHeading">{{ complaintsHeading }}</h3>
                    <p v-if="complaintsBody">{{ complaintsBody }}</p>
                    <Link
                        v-if="complaintsCta && complaintsHref"
                        class="link"
                        :href="complaintsHref"
                    >{{ complaintsCta }}</Link>
                    <div v-if="social.length" class="social">
                        <a
                            v-for="item in social"
                            :key="item.key"
                            :href="item.url"
                            :aria-label="item.label"
                            :title="item.label"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <SocialPlatformIcon :platform="item.platform" />
                        </a>
                    </div>
                </section>

                <section v-if="videoAvailable" class="foot-video-section" data-footer-video>
                    <h3>{{ videoHeading }}</h3>
                    <p class="foot-video-title" data-footer-video-title>{{ videoTitle }}</p>
                    <div class="foot-video-frame" data-footer-video-frame>
                        <button
                            v-if="!playbackActive"
                            type="button"
                            class="foot-video-cover"
                            :aria-label="`${t('footer_video_play')}: ${videoTitle}`"
                            data-footer-video-play
                            @click="startPlayback"
                        >
                            <img
                                v-if="footerVideo.posterUrl"
                                class="foot-video-poster"
                                :src="footerVideo.posterUrl"
                                alt=""
                                aria-hidden="true"
                                loading="lazy"
                            >
                            <span class="foot-video-play" aria-hidden="true">
                                <svg><use href="#i-play" /></svg>
                            </span>
                            <span class="foot-video-cover__title">{{ videoTitle }}</span>
                        </button>
                        <iframe
                            v-else
                            :src="footerVideo.embedUrl"
                            :title="videoTitle"
                            :sandbox="footerVideo.sandbox"
                            :allow="footerVideo.allow"
                            referrerpolicy="strict-origin-when-cross-origin"
                            loading="lazy"
                            allowfullscreen
                            data-footer-video-player
                            @error="handleIframeError"
                        ></iframe>
                    </div>
                    <p v-if="playbackFailed" class="foot-video-fallback" role="status">{{ fallbackMessage }}</p>
                    <a
                        v-if="footerVideo.watchUrl"
                        class="link foot-video-external"
                        :href="footerVideo.watchUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-footer-video-external
                    >{{ watchLabel }}</a>
                </section>
            </div>

            <div class="wrap foot-base">
                <span>
                    <span v-if="copyright">{{ copyright }}</span>
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

        <Link
            v-if="complaintsHref && (complaintsCta || complaintsLink?.label)"
            class="fab"
            :href="complaintsHref"
            :aria-label="complaintsCta || complaintsLink?.label"
        >
            <svg aria-hidden="true"><use href="#i-mail" /></svg>{{ complaintsCta || complaintsLink?.label }}
        </Link>
    </div>
</template>

<style scoped>
.foot-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 16px;
    margin: 12px 0 0;
    padding: 0;
    list-style: none;
}

.foot-links a {
    color: var(--on-dark-muted);
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.foot-links a:hover,
.foot-links a:focus-visible {
    color: var(--on-dark);
}

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

.foot-video-section {
    min-width: 0;
}

.foot-video-title {
    margin: 0 0 12px;
    color: var(--on-dark);
    font-weight: 600;
    line-height: 1.5;
}

.foot-video-frame {
    position: relative;
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    aspect-ratio: 16 / 9;
    border: 1px solid rgba(218, 195, 163, 0.42);
    border-radius: 12px;
    background: linear-gradient(135deg, #3e5172 0%, #2a3b62 100%);
    box-shadow: 0 12px 30px rgba(4, 9, 20, 0.32);
}

.foot-video-frame iframe,
.foot-video-cover {
    display: block;
    width: 100%;
    height: 100%;
    border: 0;
}

.foot-video-cover {
    position: relative;
    display: grid;
    place-items: center;
    padding: 0;
    color: #fff;
    background: linear-gradient(135deg, rgba(62, 81, 114, 0.96), rgba(42, 59, 98, 0.96));
    cursor: pointer;
    text-align: center;
}

.foot-video-poster {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.82;
}

.foot-video-play {
    position: relative;
    z-index: 1;
    display: grid;
    place-items: center;
    width: 56px;
    height: 56px;
    border: 2px solid var(--sand);
    border-radius: 50%;
    background: rgba(8, 13, 26, 0.62);
    transition: transform 160ms ease;
}

.foot-video-cover:hover .foot-video-play,
.foot-video-cover:focus-visible .foot-video-play {
    transform: scale(1.08);
}

.foot-video-play svg {
    width: 24px;
    height: 24px;
    margin-inline-start: -3px;
}

.foot-video-cover__title {
    position: absolute;
    z-index: 1;
    inset-inline: 0;
    bottom: 0;
    padding: 10px 14px;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.4;
    background: linear-gradient(to top, rgba(8, 13, 26, 0.82), transparent);
}

.foot-video-external {
    margin-top: 10px;
}

.foot-video-fallback {
    margin: 10px 0 0;
    color: var(--on-dark-muted);
    font-size: 13px;
    line-height: 1.6;
}

@media (max-width: 480px) {
    .foot-video-play {
        width: 48px;
        height: 48px;
    }

    .foot-video-play svg {
        width: 20px;
        height: 20px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .foot-video-play {
        transition: none;
    }
}
</style>
