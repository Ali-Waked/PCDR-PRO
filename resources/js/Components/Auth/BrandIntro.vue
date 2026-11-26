<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from '../../composables/useI18n'

const emit = defineEmits(['finish'])

const { t } = useI18n()

const visible = ref(false)
const leaving = ref(false)

const STORAGE_KEY = 'pcdr.auth.intro.v1'
const LEAVE_AT = 2450
const FINISH_AT = 3000

let timers = []
let mainElement = null

function remember() {
    try {
        sessionStorage.setItem(STORAGE_KEY, '1')
    } catch {
        // Storage can be unavailable in private browsing; the intro is still safe to play once.
    }
}

function hasPlayed() {
    try {
        return sessionStorage.getItem(STORAGE_KEY) === '1'
    } catch {
        return false
    }
}

function restoreMain() {
    if (mainElement) {
        mainElement.removeAttribute('inert')
        mainElement = null
    }
}

function finish() {
    restoreMain()
    emit('finish')
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return
    }

    const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

    if (reduced || hasPlayed()) {
        remember()
        finish()

        return
    }

    remember()
    mainElement = document.getElementById('pc-auth-main')
    mainElement?.setAttribute('inert', '')
    visible.value = true

    timers.push(window.setTimeout(() => { leaving.value = true }, LEAVE_AT))
    timers.push(window.setTimeout(() => {
        visible.value = false
        finish()
    }, FINISH_AT))
})

onBeforeUnmount(() => {
    timers.forEach((timer) => window.clearTimeout(timer))
    timers = []
    restoreMain()
})
</script>

<template>
    <!-- Teleport keeps the fixed overlay outside transformed auth ancestors. -->
    <Teleport to="body">
        <div v-if="visible" class="auth-intro" :class="{ 'is-leaving': leaving }" aria-hidden="true">
            <span class="auth-intro__halo"></span>
            <div class="auth-intro__center">
                <div class="auth-intro__glyph">
                    <img
                        src="/assets/pcdr-homepage/logo/pcdr-logo-animated.svg"
                        alt=""
                        width="260"
                        height="260"
                    >
                </div>
                <p class="auth-intro__identity">
                    <strong>{{ t('site.name') }}</strong>
                    <span>{{ t('site.slogan') }}</span>
                </p>
            </div>
        </div>
    </Teleport>
</template>
