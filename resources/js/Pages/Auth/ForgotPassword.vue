<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthHeader from '../../Components/Auth/AuthHeader.vue'
import AuthSuccessState from '../../Components/Auth/AuthSuccessState.vue'
import Button from '../../Components/Ui/Button.vue'
import TextField from '../../Components/Ui/TextField.vue'
import { useLocale } from '../../Composables/useLocale'

defineOptions({ layout: AuthLayout })

const { t, localePath } = useLocale()
const page = usePage()
const flash = computed(() => page.props.flash ?? {})
const form = useForm({ email: '' })

function submit() {
    form.post(localePath('/forgot-password'))
}
</script>

<template>
    <div>
        <Head :title="t('auth.forgot_title')" />

        <AuthSuccessState
            v-if="flash.success"
            :title="t('auth.check_email_title')"
            :message="flash.success"
            :action-label="t('auth.login_title')"
            :href="localePath('/login')"
        />
        <template v-else>
            <AuthHeader :title="t('auth.forgot_title')" :subtitle="t('auth.forgot_subtitle')" />

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
                <div class="pc-auth__links">
                    <Link :href="localePath('/login')" class="pc-link">{{ t('auth.login_title') }}</Link>
                    <Button type="submit" :processing="form.processing">{{ t('auth.send_link') }}</Button>
                </div>
            </form>
        </template>
    </div>
</template>
