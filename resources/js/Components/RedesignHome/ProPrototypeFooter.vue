<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    anchorBase: { type: String, default: '' },
})

const { locale, page } = useI18n()
const complaintsUrl = computed(() => page.props.publicRoutes?.complaints || '')
const anchor = (id) => `${props.anchorBase}#${id}`
const content = useHomepageContent(() => props.sections)

const FALLBACK = {
    aboutTitle: { ar: 'عن الجمعية :', en: 'About the association:' },
    aboutBody: {
        ar: 'جمعية العمل التشاركي في الأزمات والكوارث الخيرية جمعية أهلية في قطاع غزة، تعمل على الانتقال من التدخلات الإغاثية الموسمية إلى نموذج مؤسسي متكامل يجمع بين الاستجابة الإنسانية الفورية والحماية الاجتماعية والتنمية الاقتصادية والتعافي المبكر، وتجعل المجتمع شريكاً أساسياً في تحديد الاحتياجات وتنفيذ التدخلات وتقييم أثرها…',
        en: 'The Participatory Action in Crises and Disasters Charity is a civil society association in the Gaza Strip. It works to move from seasonal relief interventions to an integrated institutional model combining immediate humanitarian response, social protection, economic development and early recovery, and makes the community a key partner in identifying needs, implementing interventions and evaluating their impact…',
    },
    readMore: { ar: 'اقرأ المزيد', en: 'Read more' },
    complaintsTitle: { ar: 'اتصل بنا / الشكاوى :', en: 'Contact us / Complaints:' },
    complaintsBody: {
        ar: 'نوفر قنوات آمنة وسرية لتقديم الشكاوى والملاحظات، ونلتزم بالرد عليها وفق سياسة الشكاوى والتظلمات المعتمدة.',
        en: 'We provide safe and confidential channels for submitting complaints and feedback, and we commit to responding to them in accordance with the approved complaints and grievances policy.',
    },
    complaintsLink: { ar: 'اتصل بنا / الشكاوى', en: 'Contact us / Complaints' },
    mediaTitle: { ar: 'المرئيات :', en: 'Media:' },
    video: { ar: 'الفيلم التعريفي بالجمعية', en: 'Introductory film about the association' },
    copyright: {
        ar: '© 2026 جمعية العمل التشاركي في الأزمات والكوارث الخيرية – قطاع غزة',
        en: '© 2026 Participatory Action in Crises and Disasters Charity – Gaza Strip',
    },
    fab: { ar: 'الشكاوى', en: 'Complaints' },
    social: [
        { key: 'social-rss', kind: 'rss', ar: 'خلاصة RSS', en: 'RSS feed' },
        { key: 'social-youtube', kind: 'youtube', ar: 'يوتيوب', en: 'YouTube' },
        { key: 'social-x', kind: 'text-x', ar: 'منصة X', en: 'X' },
        { key: 'social-facebook', kind: 'text-f', ar: 'فيسبوك', en: 'Facebook' },
    ],
}

const arabic = computed(() => locale.value === 'ar')
const label = (value) => (arabic.value ? value.ar : value.en)
const section = computed(() => content.section('footer'))
const footerItems = computed(() => content.items('footer'))

function itemFor(prefix) {
    return footerItems.value.find((item) => String(item.key || '').toLowerCase().startsWith(prefix))
}

const aboutBody = computed(() => content.text(section.value, 'body')
    || content.text(itemFor('about'), 'body')
    || label(FALLBACK.aboutBody))
const complaintsBody = computed(() => content.text(itemFor('complaint'), 'body') || label(FALLBACK.complaintsBody))
const videoCaption = computed(() => content.text(section.value, 'videoCaption')
    || content.text(itemFor('video'), 'caption')
    || label(FALLBACK.video))
const copyright = computed(() => content.text(itemFor('copyright'), 'title') || label(FALLBACK.copyright))

const social = computed(() => FALLBACK.social.map((fallback) => {
    const item = footerItems.value.find((entry) => String(entry.key || '') === fallback.key)
    return {
        key: fallback.key,
        kind: fallback.kind,
        label: content.text(item, 'title') || content.text(item, 'label') || label(fallback),
        url: content.safeUrl(content.text(item, 'url')),
    }
}))

const videoUrl = computed(() => content.safeUrl(content.text(section.value, 'videoUrl')
    || content.text(itemFor('video'), 'url')))

function photoStyle() {
    const item = itemFor('video')
    const media = content.mediaUrl(item)
    return media ? { '--photo': `url("${media.replace(/"/g, '%22')}")` } : null
}
</script>

<template>
    <div class="prototype-footer-wrap">
    <footer class="foot" id="contact">
        <div class="wrap foot-grid">
            <section>
                <h3>{{ label(FALLBACK.aboutTitle) }}</h3>
                <p>{{ aboutBody }}</p>
                <a class="link" :href="anchor('about')">{{ label(FALLBACK.readMore) }}</a>
            </section>
            <section>
                <h3>{{ label(FALLBACK.complaintsTitle) }}</h3>
                <p>{{ complaintsBody }}</p>
                <Link v-if="complaintsUrl" class="link" :href="complaintsUrl">{{ label(FALLBACK.complaintsLink) }}</Link>
                <a v-else class="link" :href="anchor('contact')">{{ label(FALLBACK.complaintsLink) }}</a>
                <div class="social">
                    <template v-for="item in social" :key="item.key">
                        <a v-if="item.url" :href="item.url" :aria-label="item.label">
                            <svg v-if="item.kind === 'rss'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="6" cy="18" r="1.6" fill="currentColor"/><path d="M4.5 11a8.5 8.5 0 0 1 8.5 8.5M4.5 4.5a15 15 0 0 1 15 15"/></svg>
                            <svg v-else-if="item.kind === 'youtube'" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4" fill="currentColor"/><path d="M10 9.2v5.6l5-2.8z" style="fill:var(--deep)"/></svg>
                            <template v-else>{{ item.kind === 'text-x' ? 'X' : 'f' }}</template>
                        </a>
                        <span v-else :aria-label="item.label" :title="item.label">
                            <svg v-if="item.kind === 'rss'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="6" cy="18" r="1.6" fill="currentColor"/><path d="M4.5 11a8.5 8.5 0 0 1 8.5 8.5M4.5 4.5a15 15 0 0 1 15 15"/></svg>
                            <svg v-else-if="item.kind === 'youtube'" viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4" fill="currentColor"/><path d="M10 9.2v5.6l5-2.8z" style="fill:var(--deep)"/></svg>
                            <template v-else>{{ item.kind === 'text-x' ? 'X' : 'f' }}</template>
                        </span>
                    </template>
                </div>
            </section>
            <section>
                <h3>{{ label(FALLBACK.mediaTitle) }}</h3>
                <a v-if="videoUrl" class="video" :href="videoUrl" :aria-label="videoCaption" :style="photoStyle()">
                    <span class="p"><svg aria-hidden="true"><use href="#i-play" /></svg></span>
                    <em>{{ videoCaption }}</em>
                </a>
                <div v-else class="video" role="img" :aria-label="videoCaption" :style="photoStyle()">
                    <span class="p"><svg aria-hidden="true"><use href="#i-play" /></svg></span>
                    <em>{{ videoCaption }}</em>
                </div>
            </section>
        </div>
        <div class="wrap foot-base">
            <span>{{ copyright }}</span>
            <span v-if="arabic"><b>الشراكة</b> · <b>الكرامة الإنسانية</b> · <b>العدالة</b> · <b>الاستدامة</b></span>
            <span v-else><b>Partnership</b> · <b>Human dignity</b> · <b>Justice</b> · <b>Sustainability</b></span>
        </div>
    </footer>
    <Link v-if="complaintsUrl" class="fab" :href="complaintsUrl"><svg aria-hidden="true"><use href="#i-mail" /></svg>{{ label(FALLBACK.fab) }}</Link>
    <a v-else class="fab" :href="anchor('contact')"><svg aria-hidden="true"><use href="#i-mail" /></svg>{{ label(FALLBACK.fab) }}</a>
    </div>
</template>
