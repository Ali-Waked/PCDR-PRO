<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import TextField from '../Ui/TextField.vue'
import Button from '../Ui/Button.vue'
import Alert from '../Ui/Alert.vue'
import DataTable from './DataTable.vue'
import EmptyState from './EmptyState.vue'
import StatusBadge from './StatusBadge.vue'
import UiIcon from './UiIcon.vue'
import { useLocale } from '../../Composables/useLocale.js'
import { useAdminNotifications } from '../../composables/useAdminNotifications'

const props = defineProps({
    sessions: { type: Object, required: true },
})

const { t, locale, localePath } = useLocale()
const { confirmDelete, finishConfirmation } = useAdminNotifications()

const notice = ref('')
const localError = ref('')
const showCurrentPassword = ref(false)

const sessionForm = useForm({ current_password: '' })

const intlLocale = computed(() => (locale.value === 'ar' ? 'ar-EG' : 'en-GB'))
const errorText = computed(() => localError.value || sessionForm.errors.current_password || '')

const columns = computed(() => [
    { key: 'device', label: t('account_profile.device') },
    { key: 'browser', label: t('account_profile.browser') },
    { key: 'platform', label: t('account_profile.platform') },
    { key: 'ip', label: t('account_profile.ip_address') },
    { key: 'first_seen', label: t('account_profile.first_seen') },
    { key: 'last_seen', label: t('account_profile.last_seen') },
    { key: 'status', label: t('account_profile.status') },
    { key: 'actions', label: t('ui.actions'), align: 'end' },
])

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

function normalized(value) {
    return String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_')
}

function labelFor(group, value) {
    const key = normalized(value)
    if (! key) {
        return String(value || t(`account_security.${group}_unknown`) || '')
    }

    return String(t(`account_security.${group}_${key}`) || value || '')
}

function isCurrent(session) {
    return session.is_current === true || session.reference === props.sessions.current_reference
}

function refresh() {
    router.reload({ only: ['sessions'], preserveScroll: true })
}

function requirePassword() {
    if (! sessionForm.current_password) {
        localError.value = String(t('account_profile.required_current_password') || '')
        return false
    }

    localError.value = ''
    return true
}

async function revokeSession(session) {
    if (! requirePassword() || ! session.reference) {
        return
    }

    const accepted = await confirmDelete(String(t('account_profile.revoke') || ''), {
        title: String(t('account_profile.revoke') || ''),
        message: String(t('account_profile.confirm_revoke_session') || ''),
    })
    if (! accepted) {
        return
    }

    notice.value = ''
    sessionForm.delete(localePath(`/dashboard/profile/sessions/${encodeURIComponent(session.reference)}`), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = String(t('account_profile.session_revoked') || '')
            sessionForm.reset('current_password')
            router.reload({ only: ['sessions'], preserveScroll: true })
        },
        onFinish: finishConfirmation,
    })
}

async function revokeOthers() {
    if (! requirePassword()) {
        return
    }

    const label = String(t('account_profile.revoke_others') || '')
    const accepted = await confirmDelete(label, {
        title: label,
        message: String(t('account_profile.confirm_revoke_others') || ''),
    })
    if (! accepted) {
        return
    }

    notice.value = ''
    sessionForm.delete(localePath('/dashboard/profile/sessions'), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = String(t('account_profile.sessions_revoked') || '')
            sessionForm.reset('current_password')
            router.reload({ only: ['sessions'], preserveScroll: true })
        },
        onFinish: finishConfirmation,
    })
}
</script>

<template>
    <div class="pc-stack">
        <Alert v-if="errorText" type="error" :message="errorText" />
        <Alert v-else-if="notice" type="success" :message="notice" />

        <div class="pc-card">
            <div class="pc-card__head">
                <div>
                    <h2 id="account-sessions-title" class="pc-card__title">{{ t('account_profile.connected_devices') }}</h2>
                    <p class="pc-card__subtitle">{{ props.sessions.total ?? 0 }} {{ t('account_profile.session_active') }}</p>
                </div>
                <Button variant="secondary" :processing="false" @click="refresh">
                    <UiIcon name="refresh" :size="15" />
                    {{ t('account_profile.refresh') }}
                </Button>
            </div>

            <div class="pc-card__body pc-stack">
                <form class="pc-stack" style="gap: 10px" @submit.prevent>
                    <div style="display: flex; flex-wrap: wrap; align-items: end; gap: 10px">
                        <TextField
                            v-model="sessionForm.current_password"
                            name="session_current_password"
                            :label="t('account_profile.current_password')"
                            :type="showCurrentPassword ? 'text' : 'password'"
                            :error="errorText"
                            autocomplete="current-password"
                            required
                        />
                        <button
                            type="button"
                            class="pc-btn pc-btn--ghost pc-btn--sm"
                            :aria-label="showCurrentPassword ? t('account_security.hide_password') : t('account_security.show_password')"
                            @click="showCurrentPassword = !showCurrentPassword"
                        >
                            <UiIcon :name="showCurrentPassword ? 'eyeOff' : 'eye'" :size="15" />
                        </button>
                        <Button variant="destructive" :disabled="!sessionForm.current_password || !props.sessions.data?.length" :processing="sessionForm.processing" @click="revokeOthers">
                            <UiIcon name="logout" :size="15" />
                            {{ t('account_profile.revoke_others') }}
                        </Button>
                    </div>
                    <p class="pc-hint">{{ t('account_profile.required_current_password') }}</p>
                </form>

                <DataTable :columns="columns">
                    <tr v-for="session in props.sessions.data" :key="session.reference">
                        <td>{{ labelFor('device', session.device) }}</td>
                        <td>{{ labelFor('browser', session.browser) }}</td>
                        <td>{{ labelFor('platform', session.platform) }}</td>
                        <td dir="ltr">{{ session.ip_masked || '—' }}</td>
                        <td dir="ltr">{{ formatDateTime(session.first_seen_at) }}</td>
                        <td dir="ltr">{{ formatDateTime(session.last_activity_at) }}</td>
                        <td>
                            <StatusBadge
                                :label="isCurrent(session) ? t('account_profile.session_current') : t('account_profile.session_active')"
                                :tone="isCurrent(session) ? 'teal' : 'green'"
                            />
                        </td>
                        <td class="is-end" style="text-align: end">
                            <Button
                                v-if="!isCurrent(session)"
                                variant="ghost"
                                :disabled="!sessionForm.current_password"
                                :processing="sessionForm.processing"
                                @click="revokeSession(session)"
                            >
                                <UiIcon name="trash" :size="14" />
                                {{ t('account_profile.revoke') }}
                            </Button>
                        </td>
                    </tr>
                    <template #empty>
                        <EmptyState :title="t('account_profile.no_sessions')" :message="t('account_profile.subtitle')" icon="monitor" />
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
</template>
