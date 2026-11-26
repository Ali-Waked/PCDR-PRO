<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import AccountOverviewCard from '../../../Components/Dashboard/AccountOverviewCard.vue'
import AccountPersonalInfoForm from '../../../Components/Dashboard/AccountPersonalInfoForm.vue'
import AccountAvatarUploader from '../../../Components/Dashboard/AccountAvatarUploader.vue'
import AccountSecurityPanel from '../../../Components/Dashboard/AccountSecurityPanel.vue'
import AccountSessionsPanel from '../../../Components/Dashboard/AccountSessionsPanel.vue'
import AccountActivityHistory from '../../../Components/Dashboard/AccountActivityHistory.vue'
import { useLocale } from '../../../Composables/useLocale.js'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        title: page.props.translations?.ui?.account_profile?.title || '',
        subtitle: page.props.translations?.ui?.account_profile?.subtitle || '',
    }, () => page),
})

defineProps({
    profile: { type: Object, required: true },
    pendingEmail: { type: Object, default: null },
    sessions: { type: Object, required: true },
    activity: { type: Object, required: true },
    passwordPolicy: { type: Object, default: () => ({ min_length: 12 }) },
})

const { t, direction } = useLocale()
</script>

<template>
    <div class="pc-stack pcdr-enter" :dir="direction">
        <Head :title="t('account_profile.title')" />

        <nav class="pc-tabs" :aria-label="t('account_profile.title')">
            <a href="#overview" class="pc-tab">{{ t('account_profile.overview') }}</a>
            <a href="#personal" class="pc-tab">{{ t('account_profile.personal_information') }}</a>
            <a href="#photo" class="pc-tab">{{ t('account_profile.profile_photo') }}</a>
            <a href="#security" class="pc-tab">{{ t('account_profile.security') }}</a>
            <a href="#sessions" class="pc-tab">{{ t('account_profile.connected_devices') }}</a>
            <a href="#activity" class="pc-tab">{{ t('account_profile.activity_history') }}</a>
        </nav>

        <section id="overview" aria-labelledby="account-overview-title">
            <AccountOverviewCard :profile="profile" :sessions="sessions" />
        </section>

        <section id="personal">
            <div class="pc-card">
                <div class="pc-card__body">
                    <AccountPersonalInfoForm :profile="profile" />
                </div>
            </div>
        </section>

        <section id="photo">
            <div class="pc-card">
                <div class="pc-card__body">
                    <AccountAvatarUploader :avatar-url="profile.avatar_url" :name="profile.name" />
                </div>
            </div>
        </section>

        <section id="security" aria-labelledby="account-security-title">
            <AccountSecurityPanel
                :email="profile.email"
                :email-verified="profile.email_verified"
                :pending-email="pendingEmail"
                :password-policy="passwordPolicy"
            />
        </section>

        <section id="sessions" aria-labelledby="account-sessions-title">
            <AccountSessionsPanel :sessions="sessions" />
        </section>

        <section id="activity" aria-labelledby="account-activity-title">
            <AccountActivityHistory :activity="activity" />
        </section>
    </div>
</template>
