<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthAlert from '../../Components/Auth/AuthAlert.vue'
import AuthHeader from '../../Components/Auth/AuthHeader.vue'
import BrandIntro from '../../Components/Auth/BrandIntro.vue'
import Button from '../../Components/Ui/Button.vue'
import CheckboxField from '../../Components/Ui/CheckboxField.vue'
import PasswordInput from '../../Components/Auth/PasswordInput.vue'
import TextField from '../../Components/Ui/TextField.vue'
import { useLocale } from '../../Composables/useLocale'

defineOptions({ layout: AuthLayout })

const { t, localePath } = useLocale()
const page = usePage()
const flash = computed(() => page.props.flash ?? {})
const form = useForm({ email: '', password: '', remember: false })

function submit() {
    form.post(localePath('/login'), { preserveScroll: true })
}

function focusEmail() {
    window.requestAnimationFrame(() => {
        document.querySelector('input[name="email"]')?.focus()
    })
}
</script>

<template>
    <div>
        <Head :title="t('auth.login_title')" />
        <BrandIntro @finish="focusEmail" />

        <AuthHeader :title="t('auth.login_title')" :subtitle="t('auth.login_subtitle')" />
        <AuthAlert :message="flash.success" tone="success" />

        <form class="pc-stack" novalidate @submit.prevent="submit">
            <TextField
                v-model="form.email"
                name="email"
                :label="t('auth.email')"
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
                :label="t('auth.password')"
                :show-label="t('auth.show_password')"
                :hide-label="t('auth.hide_password')"
                autocomplete="current-password"
                required
                :error="form.errors.password"
            />
            <CheckboxField v-model="form.remember" name="remember" :label="t('auth.remember')" />
            <div class="pc-auth__links">
                <Link :href="localePath('/forgot-password')" class="pc-link">{{ t('auth.forgot') }}</Link>
                <Button type="submit" :processing="form.processing">{{ t('auth.submit') }}</Button>
            </div>
        </form>
    </div>
</template>
