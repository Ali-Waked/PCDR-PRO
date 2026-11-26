<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import UiIcon from '../Dashboard/UiIcon.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    size: { type: String, default: 'standard' },
    closeLabel: { type: String, default: 'Close' },
})

const emit = defineEmits(['close'])
const dialog = ref(null)
let previousFocus = null

function focusables() {
    const root = dialog.value
    if (!root) {
        return []
    }

    return [...root.querySelectorAll(
        'button:not([disabled]),[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[contenteditable="true"],[tabindex]:not([tabindex="-1"])',
    )]
}

function onKeydown(event) {
    if (!props.open) {
        return
    }

    if (event.key === 'Escape') {
        event.preventDefault()
        emit('close')

        return
    }

    if (event.key !== 'Tab') {
        return
    }

    const items = focusables()
    if (!items.length) {
        event.preventDefault()

        return
    }

    const first = items[0]
    const last = items[items.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

function activate() {
    previousFocus = document.activeElement
    document.addEventListener('keydown', onKeydown)
    requestAnimationFrame(() => (focusables()[0] || dialog.value)?.focus?.())
}

function deactivate() {
    document.removeEventListener('keydown', onKeydown)
    previousFocus?.focus?.()
    previousFocus = null
}

watch(() => props.open, (open) => {
    if (open) {
        activate()
    } else {
        deactivate()
    }
})

onMounted(() => {
    if (props.open) {
        activate()
    }
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown)
    previousFocus?.focus?.()
})
</script>

<template>
    <div v-if="open" class="et-backdrop" @click.self="emit('close')">
        <div
            ref="dialog"
            class="et-dialog"
            :class="`et-dialog--${size}`"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            tabindex="-1"
        >
            <header class="et-head">
                <div class="et-head__text">
                    <h2 class="et-head__title">{{ title }}</h2>
                    <p v-if="subtitle" class="et-head__subtitle">{{ subtitle }}</p>
                </div>
                <div class="et-head__actions">
                    <slot name="toolbar" />
                    <button type="button" class="pc-icon-button" :aria-label="closeLabel" @click="emit('close')">
                        <UiIcon name="close" :size="16" />
                    </button>
                </div>
            </header>

            <div class="et-body">
                <slot />
            </div>

            <footer v-if="$slots.footer" class="et-foot">
                <slot name="footer" />
            </footer>
        </div>
    </div>
</template>

<style scoped>
.et-backdrop {
    position: fixed;
    inset: 0;
    z-index: 140;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: var(--pc-overlay);
    backdrop-filter: blur(3px);
}

.et-dialog {
    --et-width: 560px;

    display: flex;
    flex-direction: column;
    inline-size: min(100%, var(--et-width));
    max-block-size: min(92vh, 900px);
    overflow: hidden;
    border: 1px solid var(--pc-border);
    border-radius: 16px;
    background: var(--pc-surface);
    box-shadow: var(--pcdr-shadow-strong);
}

.et-dialog--wide { --et-width: 860px; }
.et-dialog--preview { --et-width: 1020px; }
.et-dialog--wizard { --et-width: 980px; max-block-size: 96vh; }

.et-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 22px;
    border-block-end: 1px solid var(--pc-border);
    background: var(--pc-surface);
}

.et-head__text { min-inline-size: 0; }

.et-head__title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--pc-text);
    overflow-wrap: anywhere;
}

.et-head__subtitle {
    margin: 4px 0 0;
    color: var(--pc-muted);
    font-size: 0.82rem;
    line-height: 1.6;
}

.et-head__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.et-body {
    min-block-size: 0;
    overflow: auto;
    padding: 22px;
    background: var(--pc-surface-2);
}

.et-foot {
    padding: 14px 22px;
    border-block-start: 1px solid var(--pc-border);
    background: var(--pc-surface);
}

@media (max-width: 640px) {
    .et-backdrop { padding: 0; }

    .et-dialog,
    .et-dialog--wizard {
        inline-size: 100%;
        max-block-size: 100dvh;
        border: 0;
        border-radius: 0;
    }

    .et-head,
    .et-foot { padding: 14px 16px; }

    .et-body { padding: 16px; }
}
</style>
