<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import EmptyState from './EmptyState.vue'
import UiIcon from './UiIcon.vue'
import { useLocale } from '../../Composables/useLocale.js'

const props = defineProps({
    events: { type: Array, required: true },
    lastActivityAt: { type: String, default: null },
    lastLoginAt: { type: String, default: null },
    activeSessions: { type: Number, default: 0 },
    securityStatus: { type: String, default: 'review' },
    profileUrl: { type: String, required: true },
    securityUrl: { type: String, required: true },
    timezone: { type: String, default: '' },
})

const { t, locale } = useLocale()

const textStyle = { fontSize: '13px' }
const dateLocale = computed(() => (locale.value === 'ar' ? 'ar-EG' : 'en-GB'))
const isProtected = computed(() => props.securityStatus === 'protected')
const securityLabel = computed(() => t(isProtected.value
    ? 'admin_dashboard.account_activity_protected'
    : 'admin_dashboard.account_activity_review'))

function formatDate(value) {
    if (! value) {
        return '—'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    const options = { dateStyle: 'medium', timeStyle: 'short' }

    try {
        return new Intl.DateTimeFormat(dateLocale.value, {
            ...options,
            ...(props.timezone ? { timeZone: props.timezone } : {}),
        }).format(date)
    } catch {
        try {
            return new Intl.DateTimeFormat(dateLocale.value, options).format(date)
        } catch {
            return '—'
        }
    }
}
</script>

<template>
    <section class="pc-card" aria-labelledby="account-activity-title">
        <div class="pc-card__head">
            <div>
                <h2 id="account-activity-title" class="pc-card__title">{{ t('admin_dashboard.account_activity_title') }}</h2>
                <p class="pc-card__subtitle" :style="textStyle">{{ t('admin_dashboard.account_activity_subtitle') }}</p>
            </div>
            <UiIcon name="shield" :size="18" />
        </div>
        <div class="pc-card__body pc-account-activity">
            <dl class="pc-overview-metrics">
                <div class="pc-overview-metric">
                    <dt :style="textStyle">
                        <UiIcon name="clock" :size="13" /> {{ t('admin_dashboard.account_activity_last_active') }}
                    </dt>
                    <dd :style="textStyle">{{ formatDate(lastActivityAt) }}</dd>
                </div>
                <div class="pc-overview-metric">
                    <dt :style="textStyle">
                        <UiIcon name="clock" :size="13" /> {{ t('admin_dashboard.account_activity_last_login') }}
                    </dt>
                    <dd :style="textStyle">{{ formatDate(lastLoginAt) }}</dd>
                </div>
                <div class="pc-overview-metric">
                    <dt :style="textStyle">{{ t('admin_dashboard.account_activity_active_sessions') }}</dt>
                    <dd :style="textStyle">{{ activeSessions }}</dd>
                </div>
                <div class="pc-overview-metric">
                    <dt :style="textStyle">{{ t('admin_dashboard.account_activity_security') }}</dt>
                    <dd>
                        <span
                            class="pc-badge"
                            :class="isProtected ? 'pc-badge--green' : 'pc-badge--amber'"
                            :style="textStyle"
                        >
                            {{ securityLabel }}
                        </span>
                    </dd>
                </div>
            </dl>

            <div class="pc-account-activity__links" style="display: flex; flex-wrap: wrap; gap: 14px; margin-block-start: 16px">
                <Link :href="profileUrl" class="pc-overview-view" :style="textStyle">
                    <span>{{ t('admin_dashboard.account_activity_manage') }}</span>
                    <UiIcon name="arrowRight" :size="14" />
                </Link>
                <Link :href="securityUrl" class="pc-overview-view" :style="textStyle">
                    <span>{{ t('admin_dashboard.account_activity_security_settings') }}</span>
                    <UiIcon name="arrowRight" :size="14" />
                </Link>
            </div>

            <EmptyState
                v-if="!events.length"
                :title="t('admin_dashboard.account_activity_empty')"
                icon="clock"
            />
            <div v-else class="pc-timeline" :style="{ marginBlockStart: '18px' }">
                <div v-for="event in events" :key="event.id" class="pc-timeline__item">
                    <span class="pc-timeline__dot" aria-hidden="true" />
                    <div class="pc-timeline__title" :style="textStyle">
                        {{ event.label || t('admin_dashboard.account_activity_other') }}
                    </div>
                    <div class="pc-timeline__meta" dir="ltr" :style="textStyle">{{ event.action }}</div>
                    <div class="pc-overview-activity__time" :style="textStyle">{{ formatDate(event.created_at) }}</div>
                </div>
            </div>
        </div>
    </section>
</template>
