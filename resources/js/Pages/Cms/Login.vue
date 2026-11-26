<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthAlert from '../../Components/Auth/AuthAlert.vue'
import AuthHeader from '../../Components/Auth/AuthHeader.vue'
import Button from '../../Components/Ui/Button.vue'
import PasswordInput from '../../Components/Auth/PasswordInput.vue'
import TextField from '../../Components/Ui/TextField.vue'
import { useI18n } from '../../composables/useI18n'
import { useLocale } from '../../Composables/useLocale'

defineOptions({ layout: AuthLayout })

const props = defineProps({
    bootstrapHint: { type: Boolean, default: false },
})

const { t, localPath } = useI18n()
const { t: tAuth } = useLocale()
const page = usePage()
const flash = computed(() => page.props.flash ?? {})

const form = useForm({
    email: '',
    password: '',
})

function submit() {
    form.post(localPath('cms/login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <div>
        <Head :title="t('cms_login')" />
        <AuthHeader :title="t('cms_login')" :subtitle="t('cms_auth_note')" />
        <AuthAlert :message="flash.error" tone="error" />

        <form class="pc-stack" novalidate @submit.prevent="submit">
            <TextField
                v-model="form.email"
                name="email"
                :label="t('ui.email')"
                type="email"
                autocomplete="username"
                inputmode="email"
                dir="ltr"
                required
                :error="form.errors.email"
            />
            <PasswordInput
                v-model="form.password"
                name="password"
                :label="t('cms_password')"
                :show-label="tAuth('auth.show_password')"
                :hide-label="tAuth('auth.hide_password')"
                autocomplete="current-password"
                required
                :error="form.errors.password"
            />

            <p v-if="bootstrapHint" class="pc-hint">{{ t('cms_login_hint') }}</p>

            <div class="pc-auth__links">
                <Link :href="localPath()" class="pc-link">{{ t('back_home') }}</Link>
                <Button type="submit" :processing="form.processing">{{ t('cms_login') }}</Button>
            </div>
        </form>
    </div>
</template>
