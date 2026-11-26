<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from '../../composables/useI18n'
import { openDropdownId } from '../../Support/navDropdownState'

const props = defineProps({
    entry: { type: Object, required: true },
    transparent: { type: Boolean, default: false },
    activeItemKey: { type: String, default: null },
})

const emit = defineEmits(['hover', 'leave'])

const { page, t } = useI18n()

const uid = `ps-nav-dropdown-${useId()}`
const panelId = `${uid}-panel`
const triggerId = `${uid}-trigger`

const rootRef = ref(null)
const triggerRef = ref(null)
const panelRef = ref(null)
const finePointer = ref(false)
const flipped = ref(false)
const shift = ref(0)
const openTimer = ref(null)
const closeTimer = ref(null)

const isOpen = computed(() => openDropdownId.value === uid)
const groupActive = computed(() => props.activeItemKey !== null
    && props.entry.items.some((item) => item.key === props.activeItemKey))
const panelStyle = computed(() => ({ '--ps-drop-shift': `${shift.value}px` }))

function cancelTimers() {
    if (openTimer.value) { clearTimeout(openTimer.value); openTimer.value = null }
    if (closeTimer.value) { clearTimeout(closeTimer.value); closeTimer.value = null }
}

function focusableItems() {
    return Array.from(panelRef.value?.querySelectorAll('a[href]') || [])
}

function positionPanel() {
    const panel = panelRef.value
    const trigger = triggerRef.value
    if (!panel || !trigger) {
        return
    }

    flipped.value = false
    shift.value = 0

    const margin = 8
    const triggerRect = trigger.getBoundingClientRect()
    const panelRect = panel.getBoundingClientRect()

    if (panelRect.left < margin) {
        shift.value = margin - panelRect.left
    } else if (panelRect.right > window.innerWidth - margin) {
        shift.value = (window.innerWidth - margin) - panelRect.right
    }

    if (panelRect.bottom > window.innerHeight - margin
        && triggerRect.top - panelRect.height - margin > margin) {
        flipped.value = true
    }
}

function open(focusTarget = null) {
    if (isOpen.value) {
        if (focusTarget) { nextTick(() => focusItem(focusTarget)) }
        return
    }

    cancelTimers()
    openDropdownId.value = uid
    nextTick(() => {
        positionPanel()
        document.addEventListener('pointerdown', onOutsidePointer, true)
        window.addEventListener('blur', onWindowBlur)
        window.addEventListener('resize', positionPanel)
        if (focusTarget) { focusItem(focusTarget) }
    })
}

function focusItem(target) {
    const items = focusableItems()
    if (items.length === 0) {
        return
    }

    const index = target === 'last' ? items.length - 1 : 0
    items[index]?.focus()
}

function scheduleOpen() {
    if (!finePointer.value || isOpen.value || openTimer.value) {
        return
    }

    cancelTimers()
    openTimer.value = setTimeout(() => {
        openTimer.value = null
        open()
    }, 80)
}

function scheduleClose() {
    if (!finePointer.value || !isOpen.value || closeTimer.value) {
        return
    }

    cancelTimers()
    closeTimer.value = setTimeout(() => {
        closeTimer.value = null
        close()
    }, 200)
}

function toggle() {
    if (isOpen.value) {
        close()
        return
    }

    open()
}

function close(restoreFocus = false) {
    if (!isOpen.value) {
        return
    }

    cancelTimers()
    openDropdownId.value = null
    document.removeEventListener('pointerdown', onOutsidePointer, true)
    window.removeEventListener('blur', onWindowBlur)
    window.removeEventListener('resize', positionPanel)

    if (restoreFocus) {
        nextTick(() => triggerRef.value?.focus())
    }
}

function onOutsidePointer(event) {
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        close()
    }
}

function onWindowBlur() {
    close()
}

function focusAdjacent(direction, event) {
    event.preventDefault()
    const controls = Array.from(document.querySelectorAll('.ps-header a[href], .ps-header button'))
        .filter((element) => element.getClientRects().length > 0 && !element.closest('.ps-dropdown'))
    const index = controls.indexOf(triggerRef.value)
    const target = controls[index + direction]

    if (target) {
        target.focus()
    } else {
        triggerRef.value?.focus()
    }
}

function onTriggerKeydown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault()
        if (isOpen.value) { close(); return }
        open('first')
        return
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()
        open(event.key === 'ArrowDown' ? 'first' : 'last')
        return
    }

    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault()
        event.stopPropagation()
        close(true)
        return
    }

    if (event.key === 'Tab' && isOpen.value) {
        close()
        focusAdjacent(event.shiftKey ? -1 : 1, event)
    }
}

function onPanelKeydown(event) {
    const items = focusableItems()
    const index = items.indexOf(document.activeElement)

    if (event.key === 'ArrowDown') {
        event.preventDefault()
        items[(index + 1) % items.length]?.focus()
        return
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()
        items[(index - 1 + items.length) % items.length]?.focus()
        return
    }

    if (event.key === 'Home') {
        event.preventDefault()
        items[0]?.focus()
        return
    }

    if (event.key === 'End') {
        event.preventDefault()
        items[items.length - 1]?.focus()
        return
    }

    if (event.key === 'Escape') {
        event.preventDefault()
        event.stopPropagation()
        close(true)
        return
    }

    if (event.key === 'Tab') {
        close()
        focusAdjacent(event.shiftKey ? -1 : 1, event)
    }
}

function onFocusOut(event) {
    if (rootRef.value && !rootRef.value.contains(event.relatedTarget)) {
        cancelTimers()
        close()
        emit('leave')
    }
}

function descriptionOf(item) {
    return item.descriptionKey ? t(item.descriptionKey) : ''
}

watch(() => page.url, () => close())
watch(isOpen, (value) => {
    if (value) {
        nextTick(positionPanel)
    }
})

onMounted(() => {
    if (typeof window === 'undefined') {
        return
    }

    const query = window.matchMedia('(hover: hover) and (pointer: fine)')
    finePointer.value = query.matches
    query.addEventListener?.('change', (event) => { finePointer.value = event.matches })
})

onBeforeUnmount(() => {
    cancelTimers()
    close()
})
</script>

<template>
    <li
        ref="rootRef"
        class="ps-nav__group"
        @pointerenter="scheduleOpen"
        @pointerleave="scheduleClose"
        @focusin="scheduleOpen"
        @focusout="onFocusOut"
    >
        <button
            :id="triggerId"
            ref="triggerRef"
            type="button"
            class="ps-nav__link ps-nav__group-trigger"
            :class="{ 'is-active': isOpen, 'is-group-active': groupActive }"
            :data-nav-key="entry.key"
            :aria-expanded="String(isOpen)"
            :aria-controls="panelId"
            aria-haspopup="true"
            @click="toggle"
            @keydown="onTriggerKeydown"
            @mouseenter="emit('hover', entry.key)"
            @focus="emit('hover', entry.key)"
        >
            <span class="ps-nav__label">{{ t(entry.labelKey) }}</span>
            <span class="ps-nav__active-dot" aria-hidden="true"></span>
            <svg class="ps-nav__chevron" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M3.5 5.25 7 8.75l3.5-3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <div
            v-if="isOpen"
            :id="panelId"
            ref="panelRef"
            class="ps-dropdown"
            :class="{ 'is-flipped': flipped, 'is-on-dark': transparent }"
            :style="panelStyle"
            role="menu"
            :aria-labelledby="triggerId"
            @keydown="onPanelKeydown"
        >
            <Link
                v-for="item in entry.items"
                :key="item.key"
                :href="item.url"
                class="ps-dropdown__item"
                :class="{ 'is-active': item.key === activeItemKey }"
                :aria-current="item.key === activeItemKey ? 'page' : undefined"
                role="menuitem"
                :target="item.external ? '_blank' : undefined"
                :rel="item.external ? 'noopener noreferrer' : undefined"
                @click="close()"
            >
                <span class="ps-dropdown__title">{{ item.label }}</span>
                <span v-if="descriptionOf(item)" class="ps-dropdown__description">{{ descriptionOf(item) }}</span>
            </Link>
        </div>
    </li>
</template>
