<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthAlert from '../../Components/Auth/AuthAlert.vue'
import AuthHeader from '../../Components/Auth/AuthHeader.vue'
import Button from '../../Components/Ui/Button.vue'
import { useLocale } from '../../Composables/useLocale'

defineOptions({ layout: AuthLayout })

const { t, localePath } = useLocale()
const page = usePage()
const flash = computed(() => page.props.flash ?? {})
const form = useForm({})
</script>

<template>
    <div>
        <Head :title="t('auth.verify_title')" />
        <AuthHeader :title="t('auth.verify_title')" :subtitle="t('auth.verify_subtitle')" />
        <AuthAlert :message="flash.success" tone="success" />

        <form class="pc-stack" novalidate @submit.prevent="form.post(localePath('/email/verification-notification'))">
            <Button type="submit" :processing="form.processing">{{ t('auth.resend') }}</Button>
        </form>

        <div class="pc-auth__links auth-verify__links">
            <Link :href="localePath('/logout')" method="post" as="button" class="pc-link">{{ t('logout') }}</Link>
        </div>
    </div>
</template>
