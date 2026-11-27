<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from '../../composables/useI18n'
import {
    ANIMATED_LOGO_SRC,
    AUTH_INTRO_READY_FALLBACK_MS,
    STATIC_LOGO_SRC,
    createAuthIntroGate,
} from '../../Support/authIntro'

const emit = defineEmits(['finish'])

const { t } = useI18n()
const authSurface = inject('pcdr-auth-ready', null)

const organizationName = computed(() => t('site.name', 'PCDR'))
const tagline = computed(() => t('site.slogan', ''))

const reducedMotion = typeof window !== 'undefined'
    && (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false)

const visible = ref(!reducedMotion)
const leaving = ref(false)
const logoSrc = ref(ANIMATED_LOGO_SRC)

let finished = false
let mainElement = null
let frameIds = []
let visibilityListener = null
let readyFallbackTimer = null

const gate = createAuthIntroGate({
    onLeave() {
        leaving.value = true
    },
    onFinish() {
        if (finished) {
            return
        }

        finished = true
        visible.value = false
        restoreMain()
        emit('finish')
    },
})

function restoreMain() {
    if (mainElement) {
        mainElement.removeAttribute('inert')
        mainElement = null
    }
}

function cancelFrames() {
    frameIds.forEach((id) => window.cancelAnimationFrame(id))
    frameIds = []
}

function clearVisibilityListener() {
    if (visibilityListener) {
        document.removeEventListener('visibilitychange', visibilityListener)
        visibilityListener = null
    }
}

function clearReadyFallback() {
    if (readyFallbackTimer !== null) {
        window.clearTimeout(readyFallbackTimer)
        readyFallbackTimer = null
    }
}

function markShown() {
    clearVisibilityListener()
    gate.markShown()
}

function waitForVisibleFrame() {
    if (typeof window === 'undefined') {
        return
    }

    const requestFrame = window.requestAnimationFrame
    if (typeof requestFrame !== 'function') {
        markShown()

        return
    }

    frameIds.push(requestFrame.call(window, () => {
        frameIds.push(requestFrame.call(window, () => {
            if (document.visibilityState === 'hidden') {
                visibilityListener = () => {
                    if (document.visibilityState === 'visible') {
                        markShown()
                    }
                }
                document.addEventListener('visibilitychange', visibilityListener)

                return
            }

            markShown()
        }))
    }))
}

function useStaticLogo() {
    logoSrc.value = STATIC_LOGO_SRC
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return
    }

    /* The Vue overlay replaces the server-rendered boot intro in the same commit. */
    document.getElementById('pcdr-boot-intro')?.remove()

    if (reducedMotion) {
        gate.finish()

        return
    }

    mainElement = document.getElementById('pc-auth-main')
    mainElement?.setAttribute('inert', '')

    const ready = authSurface?.readyPromise ?? Promise.resolve()
    Promise.resolve(ready).then(
        () => {
            clearReadyFallback()
            gate.markReady()
        },
        () => {
            clearReadyFallback()
            gate.markReady()
        },
    )

    /* Fail-safe only: the layout normally resolves readiness well before this. */
    readyFallbackTimer = window.setTimeout(() => {
        readyFallbackTimer = null
        gate.markReady()
    }, AUTH_INTRO_READY_FALLBACK_MS + 1000)

    waitForVisibleFrame()
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return
    }

    cancelFrames()
    clearVisibilityListener()
    clearReadyFallback()
    gate.destroy()

    if (!finished) {
        restoreMain()
    }
})
</script>

<template>
    <!-- Teleport keeps the fixed overlay outside transformed auth ancestors. -->
    <Teleport to="body">
        <div
            v-if="visible"
            class="auth-intro"
            :class="{ 'is-leaving': leaving }"
            :data-intro-state="leaving ? 'leaving' : 'visible'"
            data-auth-intro
            aria-hidden="true"
        >
            <span class="auth-intro__halo" aria-hidden="true"></span>
            <div class="auth-intro__center">
                <div class="auth-intro__glyph">
                    <img
                        :src="logoSrc"
                        alt=""
                        width="280"
                        height="280"
                        fetchpriority="high"
                        @error="useStaticLogo"
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
