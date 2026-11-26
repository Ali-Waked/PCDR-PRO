<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from '../composables/useI18n'

const { locale, direction, alternateUrl } = useI18n()
const busy = ref(false)
const announcement = ref('')
const groupRef = ref(null)

const options = [
    { code: 'ar', short: 'ع', full: 'العربية', ariaLabel: 'العربية', lang: 'ar' },
    { code: 'en', short: 'EN', full: 'English', ariaLabel: 'English', lang: 'en' },
]

function switchTo(code) {
    if (busy.value || code === locale.value) return
    busy.value = true
    const url = alternateUrl(code)
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            busy.value = false
        },
        onSuccess: () => {
            announcement.value = code === 'ar' ? 'تم التبديل إلى العربية' : 'Switched to English'
        },
    })
}

function onKeydown(e, index) {
    const keys = ['ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'Enter', ' ', 'Spacebar']
    if (!keys.includes(e.key)) return
    if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
        e.preventDefault()
        switchTo(options[index].code)
        return
    }
    e.preventDefault()
    const rtl = direction.value === 'rtl'
    let delta = 0
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') delta = rtl ? -1 : 1
    if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') delta = rtl ? 1 : -1
    const next = (index + delta + options.length) % options.length
    const nextCode = options[next].code
    const btns = groupRef.value?.querySelectorAll('[role="radio"]')
    btns?.[next]?.focus()
    switchTo(nextCode)
}

watch(locale, async (val) => {
    if (typeof document === 'undefined') return
    document.documentElement.lang = val === 'ar' ? 'ar' : 'en'
    document.documentElement.dir = val === 'ar' ? 'rtl' : 'ltr'
    document.documentElement.dataset.locale = val
    await nextTick()
})
</script>

<template>
    <div class="admin-lang" :class="{ 'is-busy': busy }">
        <span class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</span>
        <div
            ref="groupRef"
            class="admin-lang__pill"
            role="radiogroup"
            aria-label="Language / اللغة"
            :aria-busy="busy ? 'true' : 'false'"
            :data-active="locale"
            :data-dir="direction"
        >
            <span class="admin-lang__globe" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </span>
            <div class="admin-lang__segment">
                <span class="admin-lang__thumb" aria-hidden="true"></span>
                <button
                    v-for="(option, index) in options"
                    :key="option.code"
                    type="button"
                    class="admin-lang__btn"
                    role="radio"
                    :aria-checked="option.code === locale ? 'true' : 'false'"
                    :tabindex="option.code === locale ? 0 : -1"
                    :aria-label="option.ariaLabel"
                    :lang="option.lang"
                    :disabled="busy"
                    @click="switchTo(option.code)"
                    @keydown="onKeydown($event, index)"
                >
                    <span class="admin-lang__short">{{ option.short }}</span>
                    <span class="admin-lang__full">{{ option.full }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-lang { display: inline-flex; align-items: center; }
.admin-lang__pill {
    --ls-border: var(--pcdr-border-strong);
    --ls-text: var(--pcdr-text);
    --ls-thumb: var(--pcdr-primary);
    --ls-active-text: var(--pcdr-on-primary);
    --ls-focus: var(--pcdr-focus-ring);
    display: inline-flex;
    align-items: center;
    inline-size: fit-content;
    block-size: 36px;
    padding: 3px;
    border: 1.5px solid var(--ls-border);
    border-radius: 999px;
    background: var(--pcdr-surface);
    box-sizing: border-box;
}
.admin-lang__globe {
    display: none;
    align-items: center;
    justify-content: center;
    color: var(--pcdr-text);
    padding-inline: 0.45rem 0.2rem;
}
.admin-lang__segment {
    position: relative;
    display: inline-flex;
    align-items: stretch;
    block-size: 100%;
}
.admin-lang__thumb {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    inline-size: 50%;
    border-radius: 999px;
    background: var(--ls-thumb);
    transition: transform 0.25s ease;
    pointer-events: none;
}
.admin-lang__pill[data-active='en'] .admin-lang__thumb { transform: translateX(100%); }
[dir='rtl'] .admin-lang__pill[data-active='en'] .admin-lang__thumb { transform: translateX(-100%); }
.admin-lang__btn {
    position: relative;
    z-index: 1;
    appearance: none;
    border: 0;
    background: transparent;
    color: var(--ls-text);
    font: 600 0.88rem/1 Inter, "IBM Plex Sans Arabic", system-ui, sans-serif;
    min-inline-size: 44px;
    min-block-size: 44px;
    margin-block: -7px;
    padding: 0 0.7rem;
    border-radius: 999px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    white-space: nowrap;
    transition: color 0.2s ease;
}
.admin-lang__btn[aria-checked='true'] { color: var(--ls-active-text); font-weight: 700; }
.admin-lang__btn:focus-visible { outline: 3px solid var(--ls-focus); outline-offset: 3px; }
.admin-lang__btn:disabled { cursor: progress; }
.admin-lang__full { display: none; font-size: 0.82rem; }
@media (min-width: 1100px) {
    .admin-lang__globe { display: inline-flex; }
    .admin-lang__full { display: inline; }
    .admin-lang__short { display: none; }
}
@media (prefers-reduced-motion: reduce) {
    .admin-lang__thumb { transition: none; }
}
</style>
