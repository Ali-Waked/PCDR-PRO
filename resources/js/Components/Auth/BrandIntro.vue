<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from '../../composables/useI18n'

const emit = defineEmits(['finish'])

const { t } = useI18n()

const STORAGE_KEY = 'pcdr.auth.intro.v1'
const LEAVE_AT = 2450
const FINISH_AT = 3000

/* Keep replay suppression within one SPA runtime even if Inertia remounts Login. */
let introStarted = false

const visible = ref(false)
const leaving = ref(false)

let timers = []
let mainElement = null
let finished = false

const organizationName = computed(() => t('site.name', 'PCDR'))
const tagline = computed(() => t('site.slogan', ''))

function prefersReducedMotion() {
    return typeof window !== 'undefined'
        && (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false)
}

function markPlayed() {
    try {
        sessionStorage.setItem(STORAGE_KEY, '1')
    } catch {
        // Storage can be unavailable in private browsing; the SPA flag still prevents replay.
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

function clearTimers() {
    timers.forEach((timer) => window.clearTimeout(timer))
    timers = []
}

function finish() {
    if (finished) {
        return
    }

    finished = true
    visible.value = false
    markPlayed()
    restoreMain()
    emit('finish')
}

/* Decide before the first paint so the login form never flashes above the intro. */
if (typeof window !== 'undefined') {
    introStarted = true
    visible.value = !hasPlayed() && !prefersReducedMotion()
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return
    }

    if (!visible.value) {
        finish()

        return
    }

    mainElement = document.getElementById('pc-auth-main')
    mainElement?.setAttribute('inert', '')

    timers.push(window.setTimeout(() => { leaving.value = true }, LEAVE_AT))
    timers.push(window.setTimeout(finish, FINISH_AT))
})

onBeforeUnmount(() => {
    clearTimers()

    if (!finished) {
        restoreMain()
    }
})
</script>

<template>
    <!-- Teleport keeps the fixed overlay outside transformed auth ancestors. -->
    <Teleport to="body">
        <div v-if="visible" class="auth-intro" :class="{ 'is-leaving': leaving }" data-auth-intro aria-hidden="true">
            <span class="auth-intro__halo" aria-hidden="true"></span>
            <div class="auth-intro__center">
                <div class="auth-intro__glyph">
                    <img
                        src="/assets/pcdr-homepage/logo/pcdr-logo-animated.svg"
                        alt=""
                        width="280"
                        height="280"
                        fetchpriority="high"
                    >
                </div>
                <p class="auth-intro__identity">
                    <strong>{{ organizationName }}</strong>
                    <span v-if="tagline">{{ tagline }}</span>
                </p>
            </div>
        </div>
    </Teleport>
</template>
