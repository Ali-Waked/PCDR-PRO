<script setup>
import { computed, ref } from 'vue'
import EmailTemplateDialog from './EmailTemplateDialog.vue'
import UiIcon from '../Dashboard/UiIcon.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    strings: { type: Object, required: true },
    example: { type: String, default: '' },
})

const emit = defineEmits(['close'])
const copied = ref(false)

const steps = computed(() => Array.from({ length: 8 }, (_, index) => ({
    number: index + 1,
    title: props.strings[`guide_${index + 1}_title`] || '',
    body: props.strings[`guide_${index + 1}_body`] || '',
})))

async function copyExample() {
    try {
        await navigator.clipboard.writeText(props.example)
        copied.value = true
        window.setTimeout(() => { copied.value = false }, 1800)
    } catch {
        copied.value = false
    }
}
</script>

<template>
    <EmailTemplateDialog
        :open="open"
        :title="strings.guide_title"
        :subtitle="strings.guide_intro"
        :close-label="strings.close"
        size="wide"
        @close="emit('close')"
    >
        <div class="guide" data-testid="template-guide">
            <ol class="guide-steps">
                <li v-for="step in steps" :key="step.number" class="guide-step">
                    <span class="guide-step__number" aria-hidden="true">{{ step.number }}</span>
                    <div class="guide-step__content">
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.body }}</p>
                    </div>
                </li>
            </ol>

            <section v-if="example" class="guide-example">
                <div class="guide-example__head">
                    <div>
                        <h3>{{ strings.guide_example_title }}</h3>
                        <p>{{ strings.guide_example_label }}</p>
                    </div>
                    <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="copyExample">
                        <UiIcon :name="copied ? 'check' : 'copy'" :size="14" />
                        {{ copied ? strings.copied : strings.guide_copy_example }}
                    </button>
                </div>
                <pre class="guide-example__code" dir="auto">{{ example }}</pre>
            </section>
        </div>

        <template #footer>
            <div class="et-footer-actions">
                <button type="button" class="pc-btn pc-btn--primary" @click="emit('close')">{{ strings.close }}</button>
            </div>
        </template>
    </EmailTemplateDialog>
</template>

<style scoped>
.guide { display: grid; gap: 18px; }
.guide-steps {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.guide-step {
    display: flex;
    gap: 12px;
    min-inline-size: 0;
    padding: 16px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius, 14px);
    background: var(--pc-surface);
}
.guide-step__number {
    display: grid;
    place-items: center;
    inline-size: 30px;
    block-size: 30px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--pc-teal-soft);
    color: var(--pc-teal);
    font-weight: 700;
}
.guide-step__content { min-inline-size: 0; }
.guide-step h3 {
    margin: 0 0 6px;
    font-size: 0.92rem;
    color: var(--pc-text);
}
.guide-step p {
    margin: 0;
    color: var(--pc-muted);
    font-size: 0.82rem;
    line-height: 1.75;
    overflow-wrap: anywhere;
}
.guide-example {
    padding: 16px;
    border: 1px solid var(--pc-sand);
    border-radius: var(--pc-radius, 14px);
    background: var(--pc-sand-soft);
}
.guide-example__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-block-end: 12px;
}
.guide-example h3 { margin: 0; font-size: 0.95rem; }
.guide-example p { margin: 4px 0 0; color: var(--pc-muted); font-size: 0.8rem; }
.guide-example__code {
    margin: 0;
    padding: 14px;
    max-block-size: 280px;
    overflow: auto;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    border-radius: var(--pc-radius-sm, 9px);
    background: var(--pc-surface);
    color: var(--pc-text);
    font: inherit;
    font-size: 0.82rem;
    line-height: 1.8;
}
.et-footer-actions { display: flex; justify-content: flex-end; }

@media (max-width: 700px) {
    .guide-steps { grid-template-columns: minmax(0, 1fr); }
}
</style>
