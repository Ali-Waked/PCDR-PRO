<script setup>
import { computed, onBeforeUnmount } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useLocale } from '../../Composables/useLocale.js'
import { applyThemePreference, normalizeThemePreference, themePreference } from '../../composables/useTheme'
import UiIcon from './UiIcon.vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    persist: { type: Boolean, default: false },
    inMenu: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const page = usePage()
const { t, localePath } = useLocale()

const options = [
    { value: 'light', icon: 'sun', labelKey: 'account_profile.theme_light' },
    { value: 'dark', icon: 'moon', labelKey: 'account_profile.theme_dark' },
    { value: 'system', icon: 'monitor', labelKey: 'account_profile.theme_system' },
]

const selected = computed(() => normalizeThemePreference(
    props.modelValue || themePreference.value || page.props.theme || page.props.auth?.user?.theme,
))

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
let persistTimer = null
let previousPreference = selected.value

function persist(value) {
    const user = page.props.auth?.user
    const profile = page.props.profile ?? {}

    router.put(localePath('/dashboard/profile'), {
        name: user?.name ?? profile.name ?? '',
        locale: user?.locale ?? profile.locale ?? 'en',
        theme: value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['theme', 'auth', 'flash'],
        onSuccess: () => {
            previousPreference = value
        },
        onError: () => {
            applyThemePreference(previousPreference)
            emit('update:modelValue', previousPreference)
        },
    })
}

function choose(value) {
    const preference = normalizeThemePreference(value)

    if (preference === selected.value) {
        return
    }

    previousPreference = selected.value
    applyThemePreference(preference)
    emit('update:modelValue', preference)

    if (props.persist && isAuthenticated.value) {
        window.clearTimeout(persistTimer)
        persistTimer = window.setTimeout(() => persist(preference), 200)
    }
}

function onKeydown(event) {
    const currentIndex = options.findIndex((option) => option.value === selected.value)
    let nextIndex = null

    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        nextIndex = (currentIndex + 1) % options.length
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        nextIndex = (currentIndex - 1 + options.length) % options.length
    } else if (event.key === 'Home') {
        nextIndex = 0
    } else if (event.key === 'End') {
        nextIndex = options.length - 1
    }

    if (nextIndex === null) {
        return
    }

    event.preventDefault()
    choose(options[nextIndex].value)

    const buttons = event.currentTarget?.parentElement?.querySelectorAll('button')
    buttons?.[nextIndex]?.focus()
}

onBeforeUnmount(() => {
    window.clearTimeout(persistTimer)
})
</script>

<template>
    <div
        class="pc-theme-selector"
        :class="{ 'pc-theme-selector--compact': compact, 'pc-theme-selector--menu': inMenu }"
        :role="inMenu ? 'menu' : 'radiogroup'"
        :aria-label="t('account_profile.theme')"
    >
        <span v-if="!compact" class="pc-theme-selector__label">{{ t('account_profile.theme') }}</span>
        <div class="pc-theme-selector__options">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                class="pc-theme-selector__option"
                :class="{ 'is-selected': selected === option.value }"
                :role="inMenu ? 'menuitemradio' : 'radio'"
                :aria-checked="selected === option.value ? 'true' : 'false'"
                :aria-label="t(option.labelKey)"
                :title="t(option.labelKey)"
                @click="choose(option.value)"
                @keydown="onKeydown"
            >
                <UiIcon :name="option.icon" :size="compact ? 15 : 16" />
                <span v-if="!compact" class="pc-theme-selector__name">{{ t(option.labelKey) }}</span>
                <UiIcon v-if="selected === option.value" name="check" :size="14" class="pc-theme-selector__check" />
            </button>
        </div>
        <p v-if="!compact" class="pc-theme-selector__hint">{{ t('account_profile.theme_hint') }}</p>
    </div>
</template>
