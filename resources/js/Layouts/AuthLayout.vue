<script setup>
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue'
import { useI18n } from '../composables/useI18n'
import { ANIMATED_LOGO_SRC, STATIC_LOGO_SRC, waitForAuthSurfaceReady } from '../Support/authIntro'
import AdminToastProvider from '../Components/Dashboard/AdminToastProvider.vue'
import LanguageSwitcher from '../Components/LanguageSwitcher.vue'

const { t, direction, site } = useI18n()
const isReady = ref(false)
const logo = computed(() => site.value?.logo || STATIC_LOGO_SRC)
const animatedLogo = computed(() => site.value?.logo || ANIMATED_LOGO_SRC)
const shortName = computed(() => site.value?.shortName || 'PCDR')
const name = computed(() => site.value?.name || t('site.name'))

let resolveReady = () => {}
const readyPromise = new Promise((resolve) => {
    resolveReady = resolve
})
provide('pcdr-auth-ready', { isReady, readyPromise })

let readinessController = null

onMounted(() => {
    const controller = new AbortController()
    readinessController = controller

    waitForAuthSurfaceReady({ signal: controller.signal, logoSource: animatedLogo.value }).then((result) => {
        if (controller.signal.aborted) {
            return
        }

        isReady.value = true
        resolveReady(result)
    })
})

onBeforeUnmount(() => {
    readinessController?.abort()
    readinessController = null
})
</script>

<template>
    <div class="pc-auth" :class="{ 'is-ready': isReady }" :dir="direction">
        <a href="#pc-auth-main" class="skip-link">{{ t('ui.skip_to_content') }}</a>
        <AdminToastProvider>

        <section class="pc-auth__brand" aria-labelledby="pc-auth-intro">
            <span class="pc-auth__geometry" aria-hidden="true"></span>
            <div class="pc-auth__logo">
                <img :src="logo" alt="" aria-hidden="true" width="56" height="56">
                <span class="pc-auth__logo-copy">
                    <strong>{{ shortName }}</strong>
                    <span>{{ name }}</span>
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
