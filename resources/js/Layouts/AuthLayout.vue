<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from '../composables/useI18n'
import AdminToastProvider from '../Components/Dashboard/AdminToastProvider.vue'
import LanguageSwitcher from '../Components/LanguageSwitcher.vue'

const { t, direction } = useI18n()
const isReady = ref(false)

onMounted(() => {
    isReady.value = true
})
</script>

<template>
    <div class="pc-auth" :class="{ 'is-ready': isReady }" :dir="direction">
        <a href="#pc-auth-main" class="skip-link">{{ t('ui.skip_to_content') }}</a>
        <AdminToastProvider>

        <section class="pc-auth__brand" aria-labelledby="pc-auth-intro">
            <span class="pc-auth__geometry" aria-hidden="true"></span>
            <div class="pc-auth__logo">
                <img src="/assets/pcdr-homepage/logo/pcdr-logo.png" alt="" aria-hidden="true" width="56" height="56">
                <span class="pc-auth__logo-copy">
                    <strong>PCDR</strong>
                    <span>{{ t('site.name') }}</span>
                </span>
            </div>

            <div class="pc-auth__statement">
                <p class="pc-auth__eyebrow">{{ t('ui.dashboard_secure_workspace') }}</p>
                <p id="pc-auth-intro" class="pc-auth__title">{{ t('ui.dashboard_auth_title') }}</p>
                <p class="pc-auth__text">{{ t('site.slogan') }}</p>
            </div>

            <div class="pc-auth__footer">
                <span>{{ t('ui.dashboard_humanitarian') }}</span>
                <span aria-hidden="true">•</span>
                <span>{{ t('ui.dashboard_editorial') }}</span>
            </div>
        </section>

        <section class="pc-auth__panel">
            <div class="pc-auth__panel-top">
                <LanguageSwitcher />
            </div>
            <div class="pc-auth__form-wrap">
                <main id="pc-auth-main" class="pc-auth__form" tabindex="-1">
                    <slot />
                </main>
            </div>
        </section>
        </AdminToastProvider>
    </div>
</template>
