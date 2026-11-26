<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import PublicLayout from '../../Layouts/PublicLayout.vue'
import PublicPageHero from '../../Components/Public/PublicPageHero.vue'
import PublicCta from '../../Components/Public/PublicCta.vue'
import SocialLinks from '../../Components/Public/SocialLinks.vue'
import SectionHeading from '../../Components/SectionHeading.vue'
import InquiryForm from '../../Components/InquiryForm.vue'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    contact: { type: Object, required: true },
    meta: { type: Object, default: null },
})

const { t, localPath, site } = useI18n()
const hasContact = computed(() => Object.values(props.contact || {}).some(Boolean))
const closingActions = computed(() => [
    { label: t('volunteer_form'), url: localPath('get-involved'), primary: true },
])
</script>

<template>
    <PublicLayout :meta="meta" page-class="contact-page">
        <PublicPageHero
            :title="t('nav.contact')"
            :lead="t('privacy_notice_short')"
            :breadcrumbs="[{ label: t('nav.contact') }]"
            accent="teal"
            motif="envelope"
        />

        <section class="ps-section">
            <div class="ps-container ps-split">
                <div v-reveal>
                    <SectionHeading :eyebrow="t('nav.contact')" :title="t('contact_details')" />
                    <p v-if="! hasContact" class="ps-notice">{{ t('ui.no_verified_contact') }}</p>
                    <div v-else class="ps-rows">
                        <div v-if="contact.email" class="ps-row">
                            <span class="ps-card__code">01</span>
                            <h3>{{ t('cms_email') }}</h3>
                            <p><a :href="`mailto:${contact.email}`" dir="ltr">{{ contact.email }}</a></p>
                        </div>
                        <div v-if="contact.phone" class="ps-row">
                            <span class="ps-card__code">02</span>
                            <h3>{{ t('cms_phone') }}</h3>
                            <p dir="ltr">{{ contact.phone }}</p>
                        </div>
                        <div v-if="contact.address" class="ps-row">
                            <span class="ps-card__code">03</span>
                            <h3>{{ t('cms_address') }}</h3>
                            <p>{{ contact.address }}</p>
                        </div>
                        <div v-if="contact.hours" class="ps-row">
                            <span class="ps-card__code">04</span>
                            <h3>{{ t('cms_hours') }}</h3>
                            <p>{{ contact.hours }}</p>
                        </div>
                    </div>

                    <div v-if="site.social?.length" class="ps-social-block">
                        <h2 style="margin-block-start: 2rem; font-size: 1.2rem">{{ t('social_links') }}</h2>
                        <SocialLinks :links="site.social" tone="light" />
                    </div>
                </div>

                <div class="ps-form-card" v-reveal>
                    <p class="ps-eyebrow">{{ t('nav.contact') }}</p>
                    <h2>{{ t('contact_form') }}</h2>
                    <p class="ps-note">{{ t('privacy_notice_short') }}</p>
                    <InquiryForm type="contact" />
                    <div class="ps-actions">
                        <Link :href="localPath('get-involved')" class="ps-text-link">{{ t('nav.involve') }}</Link>
                    </div>
                </div>
            </div>
        </section>

        <PublicCta :title="t('nav.involve')" :lede="t('volunteer_intro')" :actions="closingActions" />
    </PublicLayout>
</template>
