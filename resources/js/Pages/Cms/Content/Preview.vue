<script setup>
import { Link } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import CmsStatusBadge from '../../../Components/Dashboard/CmsStatusBadge.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    resource: { type: String, required: true },
    title: { type: String, required: true },
    summary: { type: String, default: '' },
    body: { type: String, default: '' },
    status: { type: String, required: true },
    previewLocale: { type: String, required: true },
})

const { t, localPath } = useI18n()
</script>

<template>
    <CmsDashboardLayout :title="t('ui.cms_preview_title')" :subtitle="t('ui.cms_preview_subtitle')">
        <div class="pc-stack pcdr-enter">
            <div class="pc-page-actions">
                <Link :href="localPath(`cms/${props.resource}`)" class="pc-btn pc-btn--secondary">
                    <UiIcon name="arrowForward" :size="16" />
                    {{ t('back') }}
                </Link>
            </div>

            <div class="pc-callout pc-callout--sand" role="status">
                <UiIcon name="info" :size="17" />
                <span>{{ t('ui.cms_preview_notice') }}</span>
            </div>

            <article
                class="pc-card pc-article-preview"
                :lang="props.previewLocale"
                :dir="props.previewLocale === 'ar' ? 'rtl' : 'ltr'"
            >
                <header class="pc-card__head">
                    <div class="pc-list__meta">
                        <CmsStatusBadge :status="props.status" />
                        <span class="pc-badge pc-badge--slate" dir="ltr">{{ props.previewLocale.toUpperCase() }}</span>
                    </div>
                    <span class="pc-card__subtitle">{{ t('ui.cms_preview_title') }}</span>
                </header>
                <div class="pc-card__body">
                    <h1 class="pc-page-title">{{ props.title }}</h1>
                    <p v-if="props.summary" class="pc-page-subtitle">{{ props.summary }}</p>
                    <!-- Server-sanitized by App\Support\RichText before it reaches the client. -->
                    <div class="ps-article" v-html="props.body" />
                </div>
            </article>
        </div>
    </CmsDashboardLayout>
</template>
