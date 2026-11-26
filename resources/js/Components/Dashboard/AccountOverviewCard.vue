<script setup>
import { computed } from 'vue'
import StatusBadge from './StatusBadge.vue'
import UiIcon from './UiIcon.vue'
import { useLocale } from '../../Composables/useLocale.js'

const props = defineProps({
    profile: { type: Object, required: true },
    sessions: { type: Object, default: null },
})

const { t, locale } = useLocale()

const intlLocale = computed(() => (locale.value === 'ar' ? 'ar-EG' : 'en-GB'))

const initials = computed(() => {
    const parts = String(props.profile.name || '').trim().split(/\s+/).filter(Boolean)
    return parts.length ? parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('') : '?'
})

const statusTone = computed(() => {
    if (props.profile.status === 'active') {
        return 'green'
    }
    if (props.profile.status === 'suspended') {
        return 'amber'
    }
    return 'slate'
})

function formatDateTime(value) {
    if (! value) {
        return '—'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    return new Intl.DateTimeFormat(intlLocale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function statusLabel(value) {
    const key = String(value || '').toLowerCase()
    return String(t(`account_profile.${key}`) || value || '')
}
</script>

<template>
    <div class="pc-card">
        <div class="pc-card__head">
            <div>
                <h2 id="account-overview-title" class="pc-card__title">{{ t('account_profile.overview') }}</h2>
                <p class="pc-card__subtitle">{{ t('account_profile.subtitle') }}</p>
            </div>
            <div class="pc-table__actions" style="padding: 0; border: 0">
                <StatusBadge :label="statusLabel(props.profile.status)" :tone="statusTone" />
                <StatusBadge
                    :label="props.profile.email_verified ? t('account_profile.verified') : t('account_profile.unverified')"
                    :tone="props.profile.email_verified ? 'teal' : 'amber'"
                />
            </div>
        </div>

        <div class="pc-card__body pc-stack">
            <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap">
                <span class="pc-avatar" style="inline-size: 56px; block-size: 56px; border-radius: 14px; font-size: 19px; overflow: hidden">
                    <img
                        v-if="props.profile.avatar_url"
                        :src="props.profile.avatar_url"
                        :alt="props.profile.name"
                        style="inline-size: 100%; block-size: 100%; object-fit: cover"
                    >
                    <template v-else>{{ initials }}</template>
                </span>
                <div>
                    <strong>{{ props.profile.name }}</strong>
                    <p class="pc-card__subtitle" dir="ltr">{{ props.profile.email }}</p>
                </div>
                <StatusBadge
                    v-if="props.profile.is_platform_owner"
                    :label="t('users_page.platform_owner')"
                    tone="slate"
                />
            </div>

            <dl class="pc-detail-list">
                <div class="pc-detail-list__item">
                    <dt>{{ t('account_profile.role') }}</dt>
                    <dd>
                        <template v-if="props.profile.roles?.length === 1">{{ props.profile.roles[0].label }}</template>
                        <template v-else-if="props.profile.roles?.length">
                            {{ props.profile.roles.length }} {{ t('account_profile.roles') }}
                        </template>
                        <template v-else>—</template>
                    </dd>
                </div>
                <div class="pc-detail-list__item">
                    <dt>{{ t('account_profile.created_at') }}</dt>
                    <dd dir="ltr">{{ formatDateTime(props.profile.created_at) }}</dd>
                </div>
                <div class="pc-detail-list__item">
                    <dt>{{ t('account_profile.last_login') }}</dt>
                    <dd dir="ltr">{{ formatDateTime(props.profile.last_login_at) }}</dd>
                </div>
                <div class="pc-detail-list__item">
                    <dt>{{ t('account_profile.last_activity') }}</dt>
                    <dd dir="ltr">{{ formatDateTime(props.profile.last_activity_at) }}</dd>
                </div>
                <div v-if="props.sessions" class="pc-detail-list__item">
                    <dt>{{ t('admin_dashboard.account_activity_active_sessions') }}</dt>
                    <dd>{{ props.sessions.total ?? 0 }}</dd>
                </div>
            </dl>

            <div class="pc-table__actions" style="padding: 0; border: 0">
                <a href="#personal" class="pc-btn pc-btn--secondary pc-btn--sm">
                    <UiIcon name="edit" :size="14" />
                    {{ t('account_profile.manage_account') }}
                </a>
                <a href="#security" class="pc-btn pc-btn--ghost pc-btn--sm">
                    <UiIcon name="shield" :size="14" />
                    {{ t('account_profile.security_settings') }}
                </a>
                <a href="#activity" class="pc-btn pc-btn--ghost pc-btn--sm">
                    <UiIcon name="history" :size="14" />
                    {{ t('account_profile.view_all') }}
                </a>
            </div>
        </div>
    </div>
</template>
