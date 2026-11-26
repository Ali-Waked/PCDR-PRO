<script setup>
import { computed, ref, watch } from 'vue'
import UiIcon from '../Dashboard/UiIcon.vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    direction: { type: String, default: 'ltr' },
    label: { type: String, default: '' },
    help: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    strings: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const editor = ref(null)
const lastValue = ref(props.modelValue || '')

const characterCount = computed(() => (props.modelValue || '').length)

watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.innerHTML) {
        editor.value.innerHTML = value || ''
        lastValue.value = value || ''
    }
})

function sync() {
    const html = editor.value?.innerHTML || ''
    lastValue.value = html
    emit('update:modelValue', html)
}

function exec(command, value = null) {
    if (props.disabled || !editor.value) {
        return
    }

    editor.value.focus()
    document.execCommand(command, false, value)
    sync()
}

function block(tag) {
    exec('formatBlock', tag)
}

function insertLink() {
    const input = window.prompt(props.strings.link_prompt || 'Enter an http(s) or mailto URL', 'https://')
    if (!input) {
        return
    }

    const url = input.trim()
    if (!/^(https?:|mailto:)/i.test(url)) {
        window.alert(props.strings.link_invalid || props.strings.preview_failed)

        return
    }

    exec('createLink', url)
}

function insertText(token) {
    if (!token) {
        return
    }

    exec('insertText', token)
}

defineExpose({ insertText, focus: () => editor.value?.focus() })
</script>

<template>
    <div class="et-editor">
        <div v-if="label" class="pc-field">
            <span class="pc-label">{{ label }}</span>
        </div>

        <div class="et-editor__toolbar" role="toolbar" :aria-label="strings.cms_toolbar || strings.body_label">
            <button type="button" :title="strings.step_1_short" :aria-label="strings.step_1_short" :disabled="disabled" @mousedown.prevent @click="block('p')">P</button>
            <button type="button" :title="strings.cms_toolbar_heading || 'Heading'" :aria-label="strings.cms_toolbar_heading || 'Heading'" :disabled="disabled" @mousedown.prevent @click="block('h2')">H2</button>
            <button type="button" :title="strings.cms_toolbar_heading || 'Heading'" :aria-label="strings.cms_toolbar_heading || 'Heading'" :disabled="disabled" @mousedown.prevent @click="block('h3')">H3</button>
            <span class="et-editor__divider" aria-hidden="true" />
            <button type="button" :title="strings.cms_toolbar_bold || 'Bold'" :aria-label="strings.cms_toolbar_bold || 'Bold'" :disabled="disabled" @mousedown.prevent @click="exec('bold')"><strong>B</strong></button>
            <button type="button" :title="strings.cms_toolbar_italic || 'Italic'" :aria-label="strings.cms_toolbar_italic || 'Italic'" :disabled="disabled" @mousedown.prevent @click="exec('italic')"><em>I</em></button>
            <span class="et-editor__divider" aria-hidden="true" />
            <button type="button" :title="strings.align_start || 'Align start'" :aria-label="strings.align_start || 'Align start'" :disabled="disabled" @mousedown.prevent @click="exec(direction === 'rtl' ? 'justifyRight' : 'justifyLeft')">↤</button>
            <button type="button" :title="strings.align_center || 'Align center'" :aria-label="strings.align_center || 'Align center'" :disabled="disabled" @mousedown.prevent @click="exec('justifyCenter')">↔</button>
            <button type="button" :title="strings.align_end || 'Align end'" :aria-label="strings.align_end || 'Align end'" :disabled="disabled" @mousedown.prevent @click="exec(direction === 'rtl' ? 'justifyLeft' : 'justifyRight')">↦</button>
            <span class="et-editor__divider" aria-hidden="true" />
            <button type="button" :title="strings.cms_toolbar_list || 'List'" :aria-label="strings.cms_toolbar_list || 'List'" :disabled="disabled" @mousedown.prevent @click="exec('insertUnorderedList')">•</button>
            <button type="button" :title="strings.cms_toolbar_list || 'Numbered list'" :aria-label="strings.cms_toolbar_list || 'Numbered list'" :disabled="disabled" @mousedown.prevent @click="exec('insertOrderedList')">1.</button>
            <button type="button" :title="strings.cms_toolbar_link || 'Link'" :aria-label="strings.cms_toolbar_link || 'Link'" :disabled="disabled" @mousedown.prevent @click="insertLink"><UiIcon name="link" :size="14" /></button>
            <span class="et-editor__divider" aria-hidden="true" />
            <button type="button" title="Undo" aria-label="Undo" :disabled="disabled" @mousedown.prevent @click="exec('undo')">↶</button>
            <button type="button" title="Redo" aria-label="Redo" :disabled="disabled" @mousedown.prevent @click="exec('redo')">↷</button>
            <span class="et-editor__direction" dir="ltr">{{ direction.toUpperCase() }}</span>
        </div>

        <div
            ref="editor"
            class="et-editor__area"
            :class="{ 'is-disabled': disabled }"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            :aria-label="label || strings.body_label"
            :dir="direction"
            :data-placeholder="placeholder || strings.body_help"
            @input="sync"
            @blur="sync"
        />

        <div class="et-editor__foot">
            <span>{{ help }}</span>
            <span dir="ltr">{{ characterCount }} / 10000</span>
        </div>
    </div>
</template>

<style scoped>
.et-editor { min-inline-size: 0; }
.et-editor__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    padding: 8px;
    border: 1px solid var(--pc-border-strong);
    border-block-end: 0;
    border-start-start-radius: var(--pc-radius-sm, 9px);
    border-start-end-radius: var(--pc-radius-sm, 9px);
    background: var(--pc-surface-2);
}
.et-editor__toolbar button {
    display: grid;
    place-items: center;
    min-inline-size: 30px;
    min-block-size: 30px;
    padding: 0 7px;
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    color: var(--pc-text);
    font: inherit;
    font-size: 0.8rem;
    cursor: pointer;
}
.et-editor__toolbar button:hover:not(:disabled) {
    border-color: var(--pc-border);
    background: var(--pc-surface);
}
.et-editor__toolbar button:disabled { opacity: 0.4; cursor: not-allowed; }
.et-editor__divider { inline-size: 1px; block-size: 20px; margin-inline: 3px; background: var(--pc-border); }
.et-editor__direction {
    margin-inline-start: auto;
    color: var(--pc-muted);
    font-size: 0.7rem;
    font-weight: 700;
}
.et-editor__area {
    min-block-size: 230px;
    max-block-size: 48vh;
    overflow: auto;
    padding: 16px;
    border: 1px solid var(--pc-border-strong);
    border-radius: 0 0 var(--pc-radius-sm, 9px) var(--pc-radius-sm, 9px);
    background: var(--pc-surface);
    color: var(--pc-text);
    font: inherit;
    font-size: 0.9rem;
    line-height: 1.8;
    overflow-wrap: anywhere;
}
.et-editor__area:empty::before {
    content: attr(data-placeholder);
    color: var(--pc-muted);
    opacity: 0.65;
}
.et-editor__area.is-disabled { background: var(--pc-surface-2); }
.et-editor__area :deep(h2) { font-size: 1.35rem; font-weight: 700; }
.et-editor__area :deep(h3) { font-size: 1.15rem; font-weight: 700; }
.et-editor__area :deep(a) { color: var(--pc-teal); text-decoration: underline; }
.et-editor__area :deep(ul), .et-editor__area :deep(ol) { padding-inline-start: 22px; }
.et-editor__foot {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-block-start: 6px;
    color: var(--pc-muted);
    font-size: 0.72rem;
}
</style>
