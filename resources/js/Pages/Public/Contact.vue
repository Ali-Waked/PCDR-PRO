<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import ProPillHeader from '../../Components/RedesignHome/ProPillHeader.vue'
import ProPrototypeFooter from '../../Components/RedesignHome/ProPrototypeFooter.vue'
import ProPrototypeSprite from '../../Components/RedesignHome/ProPrototypeSprite.vue'
import ProTopStrip from '../../Components/RedesignHome/ProTopStrip.vue'
import { useI18n } from '../../composables/useI18n'
import '../../../css/pcdr-prototype.css'

const props = defineProps({
    contact: { type: Object, default: null },
    meta: { type: Object, default: null },
})

const { locale, direction, alternateUrl, localPath, flash, site } = useI18n()

const COPY = {
    ar: {
        skip: 'تخطَّ إلى المحتوى',
        eyebrow: 'تواصل معنا',
        title: 'نحن هنا للاستماع',
        lead: 'أرسلوا استفساركم أو مقترحكم عبر النموذج، أو استخدموا بيانات التواصل الرسمية أدناه.',
        details: 'بيانات التواصل',
        detailsHint: 'القنوات الرسمية لجمعية العمل التشاركي',
        formTitle: 'أرسل رسالة',
        formHint: 'نقرأ كل رسالة ونردّ في أقرب وقت ممكن.',
        name: 'الاسم الكامل',
        email: 'البريد الإلكتروني',
        subject: 'الموضوع',
        message: 'الرسالة',
        messageHint: 'اكتبوا رسالتكم بوضوح، بما لا يقل عن 10 أحرف.',
        send: 'إرسال الرسالة',
        sending: 'جارٍ الإرسال…',
        emailLabel: 'البريد الإلكتروني',
        phoneLabel: 'الهاتف',
        addressLabel: 'العنوان',
        hoursLabel: 'ساعات العمل',
        mapLabel: 'عرض الموقع على الخريطة',
        socialLabel: 'تابعونا على',
        emptyDetails: 'لم تُنشر بيانات تواصل بعد.',
        loading: 'جارٍ تحميل بيانات التواصل…',
        errorSummary: 'يرجى تصحيح الحقول المُحددة قبل الإرسال.',
        successTitle: 'تم إرسال رسالتكم',
        successBody: 'شكراً لتواصلكم معنا. سنراجع رسالتكم ونردّ عليكم قريباً.',
        another: 'إرسال رسالة أخرى',
        programs: 'برامجنا',
        complaints: 'تقديم شكوى',
        required: 'مطلوب',
    },
    en: {
        skip: 'Skip to content',
        eyebrow: 'Contact us',
        title: 'We are here to listen',
        lead: 'Send your enquiry or proposal through the form, or use the official contact details below.',
        details: 'Contact details',
        detailsHint: 'Official channels of the Participatory Development Centre',
        formTitle: 'Send a message',
        formHint: 'We read every message and respond as soon as we can.',
        name: 'Full name',
        email: 'Email address',
        subject: 'Subject',
        message: 'Message',
        messageHint: 'Please write your message clearly, using at least 10 characters.',
        send: 'Send message',
        sending: 'Sending…',
        emailLabel: 'Email',
        phoneLabel: 'Phone',
        addressLabel: 'Address',
        hoursLabel: 'Working hours',
        mapLabel: 'View on the map',
        socialLabel: 'Follow us',
        emptyDetails: 'No contact details have been published yet.',
        loading: 'Loading contact details…',
        errorSummary: 'Please correct the highlighted fields before sending.',
        successTitle: 'Your message has been sent',
        successBody: 'Thank you for contacting us. We will review your message and reply soon.',
        another: 'Send another message',
        programs: 'Our programmes',
        complaints: 'Submit a complaint',
        required: 'Required',
    },
}

const copy = computed(() => COPY[locale.value] ?? COPY.en)
const title = computed(() => (typeof props.meta?.title === 'string' && props.meta.title.trim()) ? props.meta.title : copy.value.title)
const description = computed(() => props.meta?.description || site.value?.slogan || '')
const loading = computed(() => props.contact === null)
const hasDetails = computed(() => details.value.length > 0)
const submitUrl = computed(() => localPath('contact'))

const details = computed(() => {
    const contact = props.contact ?? {}
    const email = typeof contact.email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact.email)
        ? contact.email
        : null
    const phone = typeof contact.phone === 'string' && /^[+\d][\d\s()-]{5,}$/.test(contact.phone)
        ? contact.phone
        : null
    const hours = contact.workingHours || contact.hours

    return [
        email ? { key: 'email', label: copy.value.emailLabel, value: email, href: `mailto:${email}`, external: false } : null,
        phone ? {
            key: 'phone',
            label: copy.value.phoneLabel,
            value: phone,
            href: `tel:${phone.replace(/[^\d+]/g, '')}`,
            external: false,
        } : null,
        contact.address ? { key: 'address', label: copy.value.addressLabel, value: contact.address, href: null } : null,
        hours ? { key: 'hours', label: copy.value.hoursLabel, value: hours, href: null } : null,
    ].filter((item) => item !== null)
})

const socialLinks = computed(() => Array.isArray(props.contact?.socialLinks) ? props.contact.socialLinks : [])
const mapLink = computed(() => typeof props.contact?.mapLink === 'string' && props.contact.mapLink !== '' ? props.contact.mapLink : null)

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
    honeypot: '',
})

const hasErrors = computed(() => Object.keys(form.errors).length > 0)

function submit() {
    if (form.processing) {
        return
    }

    form.post(submitUrl.value, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <div class="pcdr-prototype contact-page" :lang="locale" :dir="direction">
        <Head>
            <title>{{ title }}</title>
            <meta name="description" :content="description">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
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

        <ProPrototypeSprite />
        <a class="skip" href="#contact-content">{{ copy.skip }}</a>
        <ProTopStrip />
        <ProPillHeader :brand-url="localPath('')" :anchor-base="localPath('')" />

        <main id="top">
            <section class="sec contact-hero" aria-labelledby="contact-title">
                <div class="wrap contact-hero__inner">
                    <p class="eyebrow"><b>01</b> {{ copy.eyebrow }}</p>
                    <h1 id="contact-title" class="h2">{{ copy.title }}</h1>
                    <div class="deco" aria-hidden="true" />
                    <p class="contact-lede">{{ copy.lead }}</p>
                    <nav class="contact-quick" :aria-label="copy.eyebrow">
                        <Link :href="localPath('programs')">{{ copy.programs }}</Link>
                        <Link :href="localPath('complaints')">{{ copy.complaints }}</Link>
                    </nav>
                </div>
            </section>

            <section id="contact-content" class="sec contact-body" aria-labelledby="contact-details-title">
                <div class="wrap contact-layout">
                    <aside class="contact-aside" aria-labelledby="contact-details-title">
                        <div class="panel contact-details">
                            <header class="contact-aside__head">
                                <p class="eyebrow"><b>02</b> {{ copy.details }}</p>
                                <h2 id="contact-details-title" class="contact-section-title">{{ copy.details }}</h2>
                                <p class="fine">{{ copy.detailsHint }}</p>
                            </header>

                            <div v-if="loading" class="contact-loading" aria-busy="true" aria-live="polite">
                                <div v-for="index in 4" :key="index" class="contact-skeleton" />
                                <p class="sr-only">{{ copy.loading }}</p>
                            </div>

                            <template v-else>
                                <dl v-if="hasDetails" class="contact-list">
                                    <div v-for="item in details" :key="item.key" class="contact-list__item">
                                        <dt>{{ item.label }}</dt>
                                        <dd>
                                            <a
                                                v-if="item.href"
                                                :href="item.href"
                                                :dir="item.key === 'email' || item.key === 'phone' ? 'ltr' : undefined"
                                                :target="item.external ? '_blank' : undefined"
                                                :rel="item.external ? 'noopener noreferrer' : undefined"
                                            >{{ item.value }}</a>
                                            <span v-else>{{ item.value }}</span>
                                        </dd>
                                    </div>
                                </dl>
                                <p v-else class="contact-empty" role="status">{{ copy.emptyDetails }}</p>

                                <a
                                    v-if="mapLink"
                                    class="contact-map"
                                    :href="mapLink"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >{{ copy.mapLabel }} <span aria-hidden="true">↗</span></a>

                                <div v-if="socialLinks.length" class="contact-social">
                                    <h3>{{ copy.socialLabel }}</h3>
                                    <ul>
                                        <li v-for="link in socialLinks" :key="link.platform">
                                            <a :href="link.url" target="_blank" rel="noopener noreferrer" dir="ltr">
                                                {{ link.label || link.platform }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </aside>

                    <div class="contact-main">
                        <div v-if="flash.success" class="panel contact-success" role="status" aria-live="polite">
                            <span class="contact-success__mark" aria-hidden="true">✓</span>
                            <div>
                                <h2>{{ copy.successTitle }}</h2>
                                <p>{{ flash.success }}</p>
                                <p class="fine">{{ copy.successBody }}</p>
                            </div>
                        </div>

                        <form v-else id="contact-form" class="panel contact-form" novalidate @submit.prevent="submit">
                            <header class="contact-form__head">
                                <p class="eyebrow"><b>03</b> {{ copy.formTitle }}</p>
                                <h2 class="contact-section-title">{{ copy.formTitle }}</h2>
                                <p class="fine">{{ copy.formHint }}</p>
                            </header>

                            <p v-if="hasErrors" class="contact-alert" role="alert">{{ copy.errorSummary }}</p>

                            <div class="contact-grid">
                                <label class="contact-field">
                                    <span>{{ copy.name }} <b aria-hidden="true">*</b></span>
                                    <input
                                        v-model="form.name"
                                        class="contact-input"
                                        type="text"
                                        name="name"
                                        autocomplete="name"
                                        maxlength="255"
                                        :aria-invalid="Boolean(form.errors.name)"
                                        required
                                    >
                                    <small v-if="form.errors.name" class="contact-error" role="alert">{{ form.errors.name }}</small>
                                </label>

                                <label class="contact-field">
                                    <span>{{ copy.email }} <b aria-hidden="true">*</b></span>
                                    <input
                                        v-model="form.email"
                                        class="contact-input"
                                        type="email"
                                        name="email"
                                        autocomplete="email"
                                        maxlength="255"
                                        dir="ltr"
                                        :aria-invalid="Boolean(form.errors.email)"
                                        required
                                    >
                                    <small v-if="form.errors.email" class="contact-error" role="alert">{{ form.errors.email }}</small>
                                </label>

                                <label class="contact-field contact-field--full">
                                    <span>{{ copy.subject }} <b aria-hidden="true">*</b></span>
                                    <input
                                        v-model="form.subject"
                                        class="contact-input"
                                        type="text"
                                        name="subject"
                                        minlength="3"
                                        maxlength="255"
                                        :aria-invalid="Boolean(form.errors.subject)"
                                        required
                                    >
                                    <small v-if="form.errors.subject" class="contact-error" role="alert">{{ form.errors.subject }}</small>
                                </label>

                                <label class="contact-field contact-field--full">
                                    <span>{{ copy.message }} <b aria-hidden="true">*</b></span>
                                    <textarea
                                        v-model="form.message"
                                        class="contact-input"
                                        name="message"
                                        rows="7"
                                        minlength="10"
                                        maxlength="10000"
                                        :aria-invalid="Boolean(form.errors.message)"
                                        required
                                    ></textarea>
                                    <small class="fine">{{ copy.messageHint }}</small>
                                    <small v-if="form.errors.message" class="contact-error" role="alert">{{ form.errors.message }}</small>
                                </label>
                            </div>

                            <label class="contact-honeypot" aria-hidden="true">
                                {{ copy.email }}
                                <input v-model="form.honeypot" type="text" name="honeypot" tabindex="-1" autocomplete="off">
                            </label>

                            <div class="contact-actions">
                                <button class="contact-button" type="submit" :disabled="form.processing">
                                    {{ form.processing ? copy.sending : copy.send }}
                                </button>
                                <progress v-if="form.progress" class="contact-progress" :value="form.progress.percentage" max="100" />
                            </div>
                        </form>

                        <div v-if="flash.success" class="contact-again">
                            <Link class="contact-button contact-button--ghost" :href="localPath('contact')">{{ copy.another }}</Link>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <ProPrototypeFooter :anchor-base="localPath('')" />
    </div>
</template>

<style scoped>
.contact-hero{background:linear-gradient(155deg,var(--deeper),#22345A 56%,#237E7A);color:#fff}
.contact-hero__inner{max-width:760px}
.contact-hero .eyebrow,.contact-hero .h2{color:#fff}
.contact-hero .eyebrow b{color:var(--sand)}
.contact-lede{max-width:72ch;margin-top:18px;color:var(--on-deep-soft);font-size:clamp(16px,2.2vw,19px)}
.contact-quick{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}
.contact-quick a{padding:8px 16px;border:1px solid rgba(255,255,255,.35);border-radius:999px;color:#fff;font-weight:700;text-decoration:none;font-size:14px}
.contact-quick a:hover{background:var(--sand);color:var(--deep);border-color:var(--sand)}
.contact-body{background:var(--bg)}
.contact-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:22px;align-items:start}
@media(min-width:900px){.contact-layout{grid-template-columns:minmax(280px,.8fr) minmax(0,1.35fr)}}
.contact-details,.contact-form,.contact-success{border-top:0;box-shadow:var(--shadow)}
.contact-details{padding:24px;background:var(--surface)}
.contact-aside__head,.contact-form__head{margin-block-end:20px}
.contact-section-title{font:800 clamp(22px,3vw,28px)/1.4 var(--f-head);color:var(--rule);margin-block-start:8px}
.contact-list{display:grid;gap:14px;margin:0;padding:0}
.contact-list__item{padding-block:12px;border-block-start:1px solid var(--line)}
.contact-list__item dt{font:700 13px var(--f-head);color:var(--ink-soft)}
.contact-list__item dd{margin-block-start:4px;font-size:16px;font-weight:700;overflow-wrap:anywhere}
.contact-list__item a{color:var(--accent);text-decoration:none}
.contact-list__item a:hover{text-decoration:underline}
.contact-empty{margin-block-start:20px;border:1px dashed var(--line);border-radius:12px;padding:18px;text-align:center;color:var(--ink-soft)}
.contact-map{display:inline-flex;align-items:center;gap:8px;margin-block-start:22px;padding:9px 18px;border-radius:999px;background:var(--sand);color:var(--sand-ink);font:700 14px var(--f-head);text-decoration:none}
.contact-social{margin-block-start:24px;padding-block-start:18px;border-block-start:1px solid var(--line)}
.contact-social h3{font:700 15px var(--f-head)}
.contact-social ul{list-style:none;display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0;padding:0}
.contact-social a{display:inline-block;padding:6px 12px;border:1px solid var(--line);border-radius:999px;font-size:13px;color:var(--ink-soft);text-decoration:none}
.contact-social a:hover{border-color:var(--accent);color:var(--accent)}
.contact-loading{display:grid;gap:12px}
.contact-skeleton{height:56px;border-radius:10px;background:var(--line);opacity:.6;animation:pcdr-pulse 1.4s ease-in-out infinite}
@keyframes pcdr-pulse{50%{opacity:.25}}
.contact-form,.contact-success{padding:24px}
.contact-alert,.contact-error{color:#A4262C;font-size:13px}
.contact-alert{margin-block-end:18px;padding:12px 16px;border-radius:10px;background:#FDECEC;font-weight:700}
.contact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:16px}
.contact-field{display:grid;gap:7px}
.contact-field--full{grid-column:1/-1}
.contact-field>span{font:700 14px var(--f-head)}
.contact-field>span b{color:#A4262C}
.contact-input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);font:inherit}
.contact-input:focus-visible{outline:3px solid color-mix(in srgb,var(--accent) 45%,transparent);outline-offset:2px;border-color:var(--accent)}
.contact-input[aria-invalid="true"]{border-color:#A4262C}
textarea.contact-input{resize:vertical;min-height:150px}
.contact-honeypot{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0}
.contact-actions{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-block-start:22px}
.contact-button{display:inline-flex;align-items:center;justify-content:center;padding:11px 24px;border:0;border-radius:999px;background:var(--accent);color:var(--accent-ink);font:700 15px var(--f-head);text-decoration:none;cursor:pointer}
.contact-button:disabled{opacity:.6;cursor:not-allowed}
.contact-button--ghost{background:transparent;color:var(--accent);border:1px solid var(--accent)}
.contact-progress{width:160px;accent-color:var(--accent)}
.contact-success{display:flex;gap:18px;align-items:flex-start;border-inline-start:6px solid var(--accent)}
.contact-success__mark{flex:none;display:grid;place-items:center;width:48px;height:48px;border-radius:50%;background:var(--accent);color:var(--accent-ink);font-size:24px;font-weight:800}
.contact-success p{margin-block-start:8px;color:var(--ink-soft)}
.contact-again{margin-block-start:18px}
</style>
