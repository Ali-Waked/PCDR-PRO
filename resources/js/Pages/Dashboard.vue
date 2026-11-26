<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import MetricCard from '../Components/Dashboard/MetricCard.vue'
import AccountActivityWidget from '../Components/Dashboard/AccountActivityWidget.vue'
import AdminHintBox from '../Components/Dashboard/AdminHintBox.vue'
import EmptyState from '../Components/Dashboard/EmptyState.vue'
import UiIcon from '../Components/Dashboard/UiIcon.vue'
import { useLocale } from '../Composables/useLocale'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'ui.dashboard',
        subtitleKey: 'dashboard.subtitle',
    }, () => page),
})

const props = defineProps({
    statistics: { type: Object, required: true },
    latestEvent: { type: Object, default: null },
    overview: { type: Object, required: true },
})

const { t, locale, localePath, direction } = useLocale()

const intlLocale = computed(() => (locale.value === 'ar' ? 'ar-EG' : 'en-GB'))
const kpiTones = ['teal', 'sand', 'navy']

const quickIcons = {
    news: 'newspaper',
    programs: 'layers',
    projects: 'panel',
    publications: 'folder',
    media: 'image',
    inquiries: 'mail',
    email_templates: 'template',
    cms_settings: 'settings',
}

function translate(path, replacements = {}) {
    const raw = t(path)
    let value = typeof raw === 'string' ? raw : String(raw ?? '')

    Object.entries(replacements).forEach(([key, replacement]) => {
        value = value.split(`:${key}`).join(String(replacement))
    })

    return value
}

function formatNumber(value) {
    const numeric = Number(value)

    return new Intl.NumberFormat(intlLocale.value).format(Number.isFinite(numeric) ? numeric : 0)
}

function formatDateTime(value) {
    if (! value) {
        return '—'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    const options = { dateStyle: 'medium', timeStyle: 'short' }

    try {
        return new Intl.DateTimeFormat(intlLocale.value, { ...options, timeZone: props.overview?.timezone }).format(date)
    } catch {
        try {
            return new Intl.DateTimeFormat(intlLocale.value, options).format(date)
        } catch {
            return '—'
        }
    }
}

function labelFor(group, key) {
    return t(`admin_dashboard.${group}.${key}`) || key
}

function metricLabel(key) {
    return t(`admin_dashboard.metric_${key}`) || key
}

const kpis = computed(() => props.overview?.kpis ?? [])
const contentRows = computed(() => props.overview?.content ?? [])
const pendingRows = computed(() => props.overview?.pending ?? [])
const activityRows = computed(() => props.overview?.activity ?? [])
const accountActivity = computed(() => props.overview?.account_activity ?? null)
const quickActions = computed(() => props.overview?.quick_actions ?? [])
const websiteStatus = computed(() => props.overview?.website ?? null)

const websiteChecks = computed(() => {
    const status = websiteStatus.value
    if (! status) {
        return []
    }

    return [
        { key: 'website_name', configured: status.name_configured === true },
        { key: 'website_logo', configured: status.logo_configured === true },
        { key: 'website_description', configured: status.description_configured === true },
        { key: 'website_contact', configured: status.contact_configured === true },
    ]
})
</script>

<template>
    <div class="pc-stack pcdr-enter pc-overview" :dir="direction">
        <Head :title="t('dashboard.title')" />

        <AdminHintBox :text="t('admin_dashboard.overview_hint')" />

        <section class="pc-overview-hero" aria-labelledby="overview-greeting">
            <div class="pc-overview-hero__copy">
                <span class="pc-eyebrow">{{ t('admin_dashboard.platform_overview') }}</span>
                <h2 id="overview-greeting" class="pc-overview-hero__title">{{ overview.greeting }}</h2>
                <p class="pc-overview-hero__description">{{ overview.description }}</p>
                <p class="pc-overview-hero__meta" role="status">
                    <UiIcon name="clock" :size="14" />
                    <span>{{ translate('admin_dashboard.overview_updated_at', { time: formatDateTime(overview.generated_at) }) }}</span>
                </p>
            </div>
            <span class="pc-overview-hero__mark" aria-hidden="true"><UiIcon name="spark" :size="30" /></span>
        </section>

        <p v-if="overview.timezone" class="pc-overview-timezone">
            {{ translate('admin_dashboard.system_timezone', { timezone: overview.timezone }) }}
        </p>

        <section aria-labelledby="overview-kpis">
            <div class="pc-card__head pc-card__head--plain pc-overview-section-head">
                <div>
                    <h2 id="overview-kpis" class="pc-card__title">{{ t('admin_dashboard.kpi_title') }}</h2>
                    <p class="pc-card__subtitle">{{ t('admin_dashboard.kpi_subtitle') }}</p>
                </div>
            </div>
            <div v-if="kpis.length" class="pc-metric-grid pc-overview-kpi-grid">
                <Link
                    v-for="(kpi, index) in kpis"
                    :key="kpi.key"
                    :href="localePath(kpi.href)"
                    class="pc-overview-kpi-link"
                >
                    <MetricCard
                        :label="labelFor('kpis', kpi.key)"
                        :value="formatNumber(kpi.value)"
                        :icon="kpi.icon || 'file'"
                        :tone="kpiTones[index % kpiTones.length]"
                    />
                </Link>
            </div>
            <EmptyState
                v-else
                :title="t('admin_dashboard.empty_title')"
                :message="t('admin_dashboard.empty_message')"
                icon="inbox"
            />
        </section>

        <section class="pc-card" aria-labelledby="overview-content">
            <div class="pc-card__head">
                <div>
                    <h2 id="overview-content" class="pc-card__title">{{ t('admin_dashboard.content_title') }}</h2>
                    <p class="pc-card__subtitle">{{ t('admin_dashboard.content_subtitle') }}</p>
                </div>
                <UiIcon name="layers" :size="18" />
            </div>
            <div class="pc-card__body pc-overview-content">
                <EmptyState
                    v-if="! contentRows.length"
                    :title="t('admin_dashboard.empty_title')"
                    :message="t('admin_dashboard.empty_message')"
                    icon="file"
                />
                <article v-for="row in contentRows" :key="row.key" class="pc-overview-content__row">
                    <div class="pc-overview-content__head">
                        <h3 class="pc-overview-content__title">{{ labelFor('content', row.key) }}</h3>
                        <Link :href="localePath(row.href)" class="pc-overview-view">
                            <span>{{ t('admin_dashboard.view_item') }}</span>
                            <UiIcon name="arrowRight" :size="14" />
                        </Link>
                    </div>
                    <dl class="pc-overview-metrics">
                        <div v-for="metric in row.metrics" :key="metric.key" class="pc-overview-metric">
                            <dt>{{ metricLabel(metric.key) }}</dt>
                            <dd>{{ formatNumber(metric.value) }}</dd>
                        </div>
                    </dl>
                    <p v-if="row.updated_at" class="pc-overview-content__updated">
                        {{ translate('admin_dashboard.content_updated', { time: formatDateTime(row.updated_at) }) }}
                    </p>
                </article>
            </div>
        </section>

        <div class="pc-grid pc-overview-panels">
            <section v-if="pendingRows.length" class="pc-card" aria-labelledby="overview-pending">
                <div class="pc-card__head">
                    <div>
                        <h2 id="overview-pending" class="pc-card__title">{{ t('admin_dashboard.pending_title') }}</h2>
                        <p class="pc-card__subtitle">{{ t('admin_dashboard.pending_subtitle') }}</p>
                    </div>
                    <UiIcon name="inbox" :size="18" />
                </div>
                <div class="pc-card__body pc-overview-pending">
                    <Link
                        v-for="item in pendingRows"
                        :key="item.key"
                        :href="localePath(item.href)"
                        class="pc-overview-pending__item"
                    >
                        <span class="pc-metric__icon"><UiIcon :name="item.icon || 'inbox'" :size="17" /></span>
                        <span class="pc-overview-pending__copy">
                            <span class="pc-overview-pending__label">{{ labelFor('pending', item.key) }}</span>
                            <span class="pc-overview-pending__value">{{ formatNumber(item.value) }}</span>
                        </span>
                        <UiIcon name="arrowRight" :size="16" />
                    </Link>
                </div>
            </section>

            <section v-if="activityRows.length" class="pc-card" aria-labelledby="overview-activity">
                <div class="pc-card__head">
                    <div>
                        <h2 id="overview-activity" class="pc-card__title">{{ t('admin_dashboard.activity_title') }}</h2>
                        <p class="pc-card__subtitle">{{ t('admin_dashboard.activity_subtitle') }}</p>
                    </div>
                    <UiIcon name="history" :size="18" />
                </div>
                <div class="pc-card__body">
                    <div class="pc-timeline">
                        <div v-for="item in activityRows" :key="item.id" class="pc-timeline__item">
                            <span class="pc-timeline__dot" aria-hidden="true" />
                            <div class="pc-timeline__title">{{ item.label }}</div>
                            <div class="pc-timeline__meta" dir="ltr">{{ item.action }}</div>
                            <div class="pc-overview-activity__time">
                                {{ formatDateTime(item.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <AccountActivityWidget
                v-if="accountActivity"
                :events="accountActivity.events"
                :last-activity-at="accountActivity.last_activity_at"
                :last-login-at="accountActivity.last_login_at"
                :active-sessions="accountActivity.active_sessions"
                :security-status="accountActivity.security_status"
                :profile-url="accountActivity.profile_url"
                :security-url="accountActivity.security_url"
                :timezone="overview.timezone"
            />
        </div>

        <div class="pc-grid pc-overview-panels">
            <section class="pc-card" aria-labelledby="overview-quick">
                <div class="pc-card__head">
                    <div>
                        <h2 id="overview-quick" class="pc-card__title">{{ t('admin_dashboard.quick_access') }}</h2>
                        <p class="pc-card__subtitle">{{ t('admin_dashboard.quick_access_subtitle') }}</p>
                    </div>
                    <UiIcon name="layout" :size="18" />
                </div>
                <div v-if="quickActions.length" class="pc-list">
                    <Link
                        v-for="item in quickActions"
                        :key="item.key"
                        :href="localePath(item.path)"
                        class="pc-list__item"
                    >
                        <span class="pc-overview-quick__copy">
                            <span class="pc-metric__icon"><UiIcon :name="quickIcons[item.key] || 'file'" :size="17" /></span>
                            <span class="pc-list__title">{{ item.label }}</span>
                        </span>
                        <UiIcon name="arrowRight" :size="16" />
                    </Link>
                </div>
                <div v-else class="pc-card__body">
                    <EmptyState
                        :title="t('admin_dashboard.empty_title')"
                        :message="t('admin_dashboard.empty_message')"
                        icon="inbox"
                    />
                </div>
            </section>

            <section v-if="websiteStatus" class="pc-card" aria-labelledby="overview-website">
                <div class="pc-card__head">
                    <div>
                        <h2 id="overview-website" class="pc-card__title">{{ t('admin_dashboard.website_title') }}</h2>
                        <p class="pc-card__subtitle">{{ t('admin_dashboard.website_subtitle') }}</p>
                    </div>
                    <UiIcon name="globe" :size="18" />
                </div>
                <div class="pc-card__body pc-overview-website">
                    <dl class="pc-overview-website__list">
                        <div v-for="check in websiteChecks" :key="check.key" class="pc-overview-website__row">
                            <dt>{{ t(`admin_dashboard.${check.key}`) }}</dt>
                            <dd>
                                <span class="pc-badge" :class="check.configured ? 'pc-badge--green' : 'pc-badge--slate'">
                                    {{ check.configured ? t('admin_dashboard.configured') : t('admin_dashboard.not_configured') }}
                                </span>
                            </dd>
                        </div>
                        <div class="pc-overview-website__row">
                            <dt>{{ t('admin_dashboard.website_saved_settings') }}</dt>
                            <dd class="pc-overview-website__count">{{ formatNumber(websiteStatus.saved_settings) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>
    </div>
</template>
