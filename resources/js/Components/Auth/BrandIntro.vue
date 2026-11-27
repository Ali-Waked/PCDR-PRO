<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from '../../composables/useI18n'

const emit = defineEmits(['finish'])

const { t } = useI18n()
const authReady = inject('pcdr-auth-ready', ref(true))

const STORAGE_KEY = 'pcdr.auth.intro.v1'
const LEAVE_AT = 2450
const MIN_VISIBLE_MS = 3000

/* Keep replay suppression within one SPA runtime even if Inertia remounts Login. */
let introStarted = false

const visible = ref(false)
const leaving = ref(false)

let timers = []
let mainElement = null
let finished = false
let startedAt = 0

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

function scheduleTimers() {
    clearTimers()

    const elapsed = Date.now() - startedAt
    if (!leaving.value) {
        timers.push(window.setTimeout(() => { leaving.value = true }, Math.max(0, LEAVE_AT - elapsed)))
    }

    if (!authReady.value) {
        return
    }

    timers.push(window.setTimeout(finish, Math.max(0, MIN_VISIBLE_MS - elapsed)))
}

function finish(force = false) {
    if (finished) {
        return
    }

    if (!force && !authReady.value) {
        scheduleTimers()

        return
    }

    finished = true
    visible.value = false
    markPlayed()
    restoreMain()
    emit('finish')
}

watch(authReady, (ready) => {
    if (ready && visible.value && !finished) {
        scheduleTimers()
    }
})

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
        finish(true)

        return
    }

    mainElement = document.getElementById('pc-auth-main')
    mainElement?.setAttribute('inert', '')

    startedAt = Date.now()
    scheduleTimers()
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
