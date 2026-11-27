<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import SocialPlatformIcon from './SocialPlatformIcon.vue'
import { videoSource } from '../../Support/videoEmbed'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    // Accepted for compatibility with inner pages; destinations use real routes.
    anchorBase: { type: String, default: '' },
})

const page = usePage()
const { locale, site, t } = useI18n()

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
const mediaLink = computed(() => navigationItem('media'))
const mediaLinks = computed(() => Array.isArray(mediaLink.value?.children)
    ? mediaLink.value.children.filter((item) => item && typeof item.url === 'string' && item.url !== '' && item.url !== '#')
    : [])

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
const videoItem = computed(() => itemFor('footer-video', 'video-'))

const aboutHeading = computed(() => content.text(aboutItem.value, 'title') || sectionText('title'))
const aboutBody = computed(() => content.text(aboutItem.value, 'body') || sectionText('body'))
const aboutCta = computed(() => content.text(aboutItem.value, 'ctaLabel') || sectionText('ctaLabel'))
const aboutCtaHref = computed(() => aboutLinks.value[0]?.url || '')

const complaintsHeading = computed(() => content.text(complaintItem.value, 'title'))
const complaintsBody = computed(() => content.text(complaintItem.value, 'body'))
const complaintsCta = computed(() => content.text(complaintItem.value, 'ctaLabel') || complaintsLink.value?.label || '')
const complaintsHref = computed(() => complaintsLink.value?.url || '')

const mediaHeading = computed(() => content.text(videoItem.value, 'title'))
const mediaCtaHref = computed(() => mediaLinks.value[0]?.url || '')
const videoCaption = computed(() => sectionText('videoCaption')
    || content.text(videoItem.value, 'caption')
    || content.text(videoItem.value, 'label'))
const video = computed(() => videoSource(sectionText('videoUrl') || content.text(videoItem.value, 'url')))
const videoAlt = computed(() => content.mediaAlt(videoItem.value))
const videoLabel = computed(() => videoCaption.value || mediaHeading.value)

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

function photoStyle() {
    const media = content.mediaUrl(videoItem.value)
    return media ? { '--photo': `url("${media.replace(/"/g, '%22')}")` } : null
}

const videoOpen = ref(false)
const videoTrigger = ref(null)
const dialog = ref(null)
const dialogClose = ref(null)

function openVideo() {
    if (video.value !== null) {
        videoOpen.value = true
    }
}

async function closeVideo() {
    videoOpen.value = false
    await nextTick()
    videoTrigger.value?.focus()
}

function onDialogKeydown(event) {
    if (!videoOpen.value) {
        return
    }

    if (event.key === 'Escape') {
        event.preventDefault()
        closeVideo()
        return
    }

    if (event.key !== 'Tab' || dialog.value === null) {
        return
    }

    const focusable = Array.from(dialog.value.querySelectorAll('button, iframe, video[controls]'))
        .filter((element) => !element.hasAttribute('disabled'))
    if (focusable.length === 0) {
        return
    }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

watch(videoOpen, async (open) => {
    if (open) {
        await nextTick()
        dialogClose.value?.focus()
        document.addEventListener('keydown', onDialogKeydown)
    } else {
        document.removeEventListener('keydown', onDialogKeydown)
    }
})

onBeforeUnmount(() => document.removeEventListener('keydown', onDialogKeydown))
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

                <section>
                    <h3 v-if="mediaHeading">{{ mediaHeading }}</h3>
                    <button
                        v-if="video"
                        ref="videoTrigger"
                        type="button"
                        class="video foot-video-trigger"
                        :aria-label="videoLabel"
                        :style="photoStyle()"
                        @click="openVideo"
                    >
                        <span class="p"><svg aria-hidden="true"><use href="#i-play" /></svg></span>
                        <em v-if="videoCaption">{{ videoCaption }}</em>
                    </button>
                    <div
                        v-else
                        class="video"
                        role="img"
                        :aria-label="videoAlt || videoLabel"
                        :style="photoStyle()"
                    >
                        <span class="p"><svg aria-hidden="true"><use href="#i-play" /></svg></span>
                        <em v-if="videoCaption">{{ videoCaption }}</em>
                    </div>
                    <Link
                        v-if="mediaCtaHref && (videoCaption || mediaLink?.label)"
                        class="link"
                        :href="mediaCtaHref"
                    >{{ videoCaption || mediaLink?.label }}</Link>
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

        <div
            v-if="videoOpen && video"
            ref="dialog"
            class="foot-video-dialog"
            role="dialog"
            aria-modal="true"
            :aria-label="videoLabel"
            @click.self="closeVideo"
        >
            <div class="foot-video-dialog__panel">
                <button
                    ref="dialogClose"
                    class="foot-video-dialog__close"
                    type="button"
                    :aria-label="t('cms_close', t('ui.cancel'))"
                    @click="closeVideo"
                >×</button>
                <iframe
                    v-if="video.type === 'embed'"
                    :src="video.src"
                    :title="videoLabel"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"
                />
                <video
                    v-else
                    :src="video.src"
                    :aria-label="videoLabel"
                    controls
                    playsinline
                />
            </div>
        </div>
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

.foot-video-trigger {
    width: 100%;
    padding: 0;
    border: 0;
    color: inherit;
    text-align: inherit;
    cursor: pointer;
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

.foot-video-dialog {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgba(6, 11, 22, 0.82);
}

.foot-video-dialog__panel {
    position: relative;
    width: min(960px, 100%);
    background: var(--deep);
    border: 1px solid var(--on-dark-border);
    border-radius: 12px;
    padding: 12px;
}

.foot-video-dialog__panel iframe,
.foot-video-dialog__panel video {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    border: 0;
    background: #000;
}

.foot-video-dialog__close {
    position: absolute;
    inset-block-start: -18px;
    inset-inline-end: -18px;
    width: 40px;
    height: 40px;
    border: 1px solid var(--sand);
    border-radius: 50%;
    background: var(--deep);
    color: var(--on-dark);
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}
</style>
