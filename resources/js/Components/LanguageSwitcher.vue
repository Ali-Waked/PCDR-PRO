<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from '../composables/useI18n'

const { locale, direction, alternateUrl } = useI18n()

const options = [
    { code: 'ar', label: 'ع', ariaLabel: 'العربية', lang: 'ar' },
    { code: 'en', label: 'EN', ariaLabel: 'English', lang: 'en' },
]

const activeIndex = computed(() => (locale.value === 'en' ? 1 : 0))

function switchTo(code) {
    if (code === locale.value) return
    router.visit(alternateUrl(code), {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <div
        class="lang-switcher"
        role="group"
        aria-label="Language / اللغة"
        :data-active="locale"
        :data-dir="direction"
    >
        <span class="lang-switcher__thumb" aria-hidden="true"></span>
        <button
            v-for="option in options"
            :key="option.code"
            type="button"
            class="lang-switcher__btn"
            :class="{ 'is-active': option.code === locale }"
            :aria-label="`${option.label} — ${option.ariaLabel}`"
            :aria-pressed="option.code === locale ? 'true' : 'false'"
            :lang="option.lang"
            @click="switchTo(option.code)"
        >
            <span class="lang-switcher__label">{{ option.label }}</span>
        </button>
    </div>
</template>

<style scoped>
.lang-switcher {
    --ls-border: rgba(62, 81, 114, 0.35);
    --ls-text: #25334B;
    --ls-thumb: #3E5172;
    --ls-active-text: #fff;
    --ls-focus: #397F7B;
    position: relative;
    display: inline-flex;
    inline-size: fit-content;
    flex: 0 0 auto;
    block-size: 40px;
    min-block-size: 44px;
    padding: 3px;
    border: 1.5px solid var(--ls-border);
    border-radius: 999px;
    background: transparent;
    align-items: stretch;
    box-sizing: border-box;
    vertical-align: middle;
}

.lang-switcher__thumb {
    position: absolute;
    inset-block: 3px;
    inset-inline-start: 3px;
    inline-size: calc(50% - 3px);
    border-radius: 999px;
    background: var(--ls-thumb);
    transition: transform 0.25s ease;
    will-change: transform;
    pointer-events: none;
}

.lang-switcher[data-active='en'] .lang-switcher__thumb {
    transform: translateX(100%);
}

[dir='rtl'] .lang-switcher[data-active='en'] .lang-switcher__thumb {
    transform: translateX(-100%);
}

.lang-switcher__btn {
    position: relative;
    z-index: 1;
    appearance: none;
    border: 0;
    background: transparent;
    color: var(--ls-text);
    font: 600 0.88rem/1 Inter, "IBM Plex Sans Arabic", system-ui, sans-serif;
    min-inline-size: 44px;
    min-block-size: 34px;
    padding: 0 0.7rem;
    border-radius: 999px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    transition: color 0.2s ease, font-weight 0.2s ease;
}

.lang-switcher__btn.is-active {
    color: var(--ls-active-text);
    font-weight: 700;
}

.lang-switcher__btn:focus-visible {
    outline: 3px solid var(--ls-focus);
    outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
    .lang-switcher__thumb,
    .lang-switcher__btn {
        transition: none;
    }
}
</style>
