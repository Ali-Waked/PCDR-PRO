<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import TextField from '../Ui/TextField.vue'
import Button from '../Ui/Button.vue'
import Alert from '../Ui/Alert.vue'
import FormSection from './FormSection.vue'
import StatusBadge from './StatusBadge.vue'
import UiIcon from './UiIcon.vue'
import { useLocale } from '../../Composables/useLocale.js'

const props = defineProps({
    email: { type: String, default: '' },
    emailVerified: { type: Boolean, default: false },
    pendingEmail: { type: Object, default: null },
    passwordPolicy: { type: Object, default: () => ({ min_length: 12 }) },
})

const { t, locale, localePath } = useLocale()

const notice = ref('')
const showEmailCurrent = ref(false)
const showCancelCurrent = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmation = ref(false)

const emailForm = useForm({ email: '', current_password: '' })
const cancelForm = useForm({ current_password: '' })
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const intlLocale = computed(() => (locale.value === 'ar' ? 'ar-EG' : 'en-GB'))
const minLength = computed(() => Number(props.passwordPolicy?.min_length ?? 12))

function translate(key, replacements = {}) {
    let value = String(t(key) || '')

    Object.entries(replacements).forEach(([name, replacement]) => {
        value = value.split(`:${name}`).join(String(replacement))
    })

    return value
}

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

function toggleLabel(visible) {
    return visible ? t('account_security.hide_password') : t('account_security.show_password')
}

function changeEmail() {
    notice.value = ''
    emailForm.post(localePath('/dashboard/profile/email'), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = translate('account_profile.email_change_sent')
            emailForm.reset()
        },
    })
}

function cancelEmailChange() {
    notice.value = ''
    cancelForm.delete(localePath('/dashboard/profile/email'), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = translate('account_profile.email_change_cancelled')
            cancelForm.reset('current_password')
        },
    })
}

function changePassword() {
    notice.value = ''
    passwordForm.post(localePath('/dashboard/profile/password'), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = translate('account_profile.password_changed')
            passwordForm.reset()
        },
    })
}
</script>

<template>
    <div class="pc-stack">
        <Alert v-if="notice" type="success" :message="notice" />

        <div class="pc-card">
            <div class="pc-card__head">
                <div>
                    <h2 id="account-security-title" class="pc-card__title">{{ t('account_security.title') }}</h2>
                    <p class="pc-card__subtitle">{{ t('account_security.subtitle') }}</p>
                </div>
                <StatusBadge
                    :label="props.emailVerified ? t('account_profile.verified') : t('account_profile.unverified')"
                    :tone="props.emailVerified ? 'teal' : 'amber'"
                />
            </div>

            <div class="pc-card__body pc-stack">
                <div v-if="props.pendingEmail" class="pc-callout pc-callout--warning" role="status">
                    <UiIcon name="mail" :size="17" />
                    <div style="flex: 1">
                        <span>
                            {{ translate('account_profile.pending_email_notice', {
                                expires: formatDateTime(props.pendingEmail.expires_at),
                            }) }}
                        </span>
                        <p class="pc-hint" dir="ltr"><strong>{{ props.pendingEmail.new_email }}</strong></p>
                        <form class="pc-stack" style="gap: 8px; margin-block-start: 12px" @submit.prevent="cancelEmailChange">
                            <div style="display: flex; flex-wrap: wrap; align-items: end; gap: 10px">
                                <TextField
                                    v-model="cancelForm.current_password"
                                    name="cancel_current_password"
                                    :label="t('account_profile.current_password')"
                                    :type="showCancelCurrent ? 'text' : 'password'"
                                    :error="cancelForm.errors.current_password"
                                    autocomplete="current-password"
                                    required
                                />
                                <button
                                    type="button"
                                    class="pc-btn pc-btn--ghost pc-btn--sm"
                                    :aria-label="toggleLabel(showCancelCurrent)"
                                    @click="showCancelCurrent = !showCancelCurrent"
                                >
                                    <UiIcon :name="showCancelCurrent ? 'eyeOff' : 'eye'" :size="15" />
                                </button>
                                <Button type="submit" variant="secondary" :disabled="!cancelForm.current_password" :processing="cancelForm.processing">
                                    {{ t('account_profile.cancel_email_change') }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <form @submit.prevent="changeEmail">
                    <FormSection :title="t('account_profile.change_email')">
                        <div class="pc-form-grid pc-form-grid--2">
                            <TextField
                                v-model="emailForm.email"
                                name="email"
                                :label="t('account_profile.email')"
                                :error="emailForm.errors.email"
                                type="email"
                                dir="ltr"
                                autocomplete="email"
                                required
                            />
                            <div class="pc-stack" style="gap: 6px">
                                <div style="display: flex; align-items: end; gap: 10px">
                                    <TextField
                                        v-model="emailForm.current_password"
                                        name="email_current_password"
                                        :label="t('account_profile.current_password')"
                                        :type="showEmailCurrent ? 'text' : 'password'"
                                        :error="emailForm.errors.current_password"
                                        autocomplete="current-password"
                                        required
                                    />
                                    <button
                                        type="button"
                                        class="pc-btn pc-btn--ghost pc-btn--sm"
                                        :aria-label="toggleLabel(showEmailCurrent)"
                                        @click="showEmailCurrent = !showEmailCurrent"
                                    >
                                        <UiIcon :name="showEmailCurrent ? 'eyeOff' : 'eye'" :size="15" />
                                    </button>
                                </div>
                                <p class="pc-hint" dir="ltr">{{ props.email }}</p>
                            </div>
                        </div>
                        <div class="pc-form-actions">
                            <Button type="submit" :processing="emailForm.processing">
                                <UiIcon name="mail" :size="15" />
                                {{ t('account_profile.change_email') }}
                            </Button>
                        </div>
                    </FormSection>
                </form>

                <form @submit.prevent="changePassword">
                    <FormSection :title="t('account_security.change_password')" :hint="translate('account_security.password_hint', { min: minLength })">
                        <div class="pc-form-grid pc-form-grid--2">
                            <div style="display: flex; align-items: end; gap: 10px">
                                <TextField
                                    v-model="passwordForm.current_password"
                                    name="current_password"
                                    :label="t('account_profile.current_password')"
                                    :type="showCurrentPassword ? 'text' : 'password'"
                                    :error="passwordForm.errors.current_password"
                                    autocomplete="current-password"
                                    required
                                />
                                <button
                                    type="button"
                                    class="pc-btn pc-btn--ghost pc-btn--sm"
                                    :aria-label="toggleLabel(showCurrentPassword)"
                                    @click="showCurrentPassword = !showCurrentPassword"
                                >
                                    <UiIcon :name="showCurrentPassword ? 'eyeOff' : 'eye'" :size="15" />
                                </button>
                            </div>
                            <div style="display: flex; align-items: end; gap: 10px">
                                <TextField
                                    v-model="passwordForm.password"
                                    name="password"
                                    :label="t('account_security.new_password')"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    :error="passwordForm.errors.password"
                                    autocomplete="new-password"
                                    required
                                />
                                <button
                                    type="button"
                                    class="pc-btn pc-btn--ghost pc-btn--sm"
                                    :aria-label="toggleLabel(showNewPassword)"
                                    @click="showNewPassword = !showNewPassword"
                                >
                                    <UiIcon :name="showNewPassword ? 'eyeOff' : 'eye'" :size="15" />
                                </button>
                            </div>
                            <div style="display: flex; align-items: end; gap: 10px">
                                <TextField
                                    v-model="passwordForm.password_confirmation"
                                    name="password_confirmation"
                                    :label="t('account_security.confirm_password')"
                                    :type="showConfirmation ? 'text' : 'password'"
                                    :error="passwordForm.errors.password_confirmation"
                                    autocomplete="new-password"
                                    required
                                />
                                <button
                                    type="button"
                                    class="pc-btn pc-btn--ghost pc-btn--sm"
                                    :aria-label="toggleLabel(showConfirmation)"
                                    @click="showConfirmation = !showConfirmation"
                                >
                                    <UiIcon :name="showConfirmation ? 'eyeOff' : 'eye'" :size="15" />
                                </button>
                            </div>
                        </div>
                        <div class="pc-form-actions">
                            <Button type="submit" :processing="passwordForm.processing">
                                <UiIcon name="lock" :size="15" />
                                {{ t('account_security.change_password') }}
                            </Button>
                        </div>
                    </FormSection>
                </form>
            </div>
        </div>
    </div>
</template>
