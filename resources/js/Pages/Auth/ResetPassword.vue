<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthHeader from '../../Components/Auth/AuthHeader.vue'
import Button from '../../Components/Ui/Button.vue'
import PasswordInput from '../../Components/Auth/PasswordInput.vue'
import TextField from '../../Components/Ui/TextField.vue'
import { useLocale } from '../../Composables/useLocale'

defineOptions({ layout: AuthLayout })

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, required: true },
})

const { t, localePath } = useLocale()
const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post(localePath('/reset-password'))
}
</script>

<template>
    <div>
        <Head :title="t('auth.reset_title')" />
        <AuthHeader :title="t('auth.reset_title')" :subtitle="t('auth.reset_subtitle')" />

        <form class="pc-stack" novalidate @submit.prevent="submit">
            <TextField
                v-model="form.email"
                name="email"
                :label="t('auth.email')"
                type="email"
                autocomplete="email"
                inputmode="email"
                dir="ltr"
                required
                :error="form.errors.email"
            />
            <PasswordInput
                v-model="form.password"
                name="password"
                :label="t('auth.password')"
                :show-label="t('auth.show_password')"
                :hide-label="t('auth.hide_password')"
                autocomplete="new-password"
                required
                :error="form.errors.password"
            />
            <PasswordInput
                v-model="form.password_confirmation"
                name="password_confirmation"
                :label="t('auth.confirm_password')"
                :show-label="t('auth.show_password')"
                :hide-label="t('auth.hide_password')"
                autocomplete="new-password"
                required
                :error="form.errors.password_confirmation"
            />
            <div class="pc-auth__links">
                <Link :href="localePath('/login')" class="pc-link">{{ t('auth.login_title') }}</Link>
                <Button type="submit" :processing="form.processing">{{ t('auth.reset_title') }}</Button>
            </div>
        </form>
    </div>
</template>
