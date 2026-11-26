<script setup>
import { computed, ref, watch } from 'vue'
import EmailTemplateDialog from './EmailTemplateDialog.vue'
import UiIcon from '../Dashboard/UiIcon.vue'
import SelectionControl from '../Selection/SelectionControl.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    preview: { type: Object, default: null },
    strings: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    error: { type: String, default: '' },
    locales: { type: Array, default: () => [] },
    activeLocale: { type: String, default: '' },
    imagesBlocked: { type: Boolean, default: false },
    testBusy: { type: Boolean, default: false },
    testNotice: { type: String, default: '' },
})

const emit = defineEmits(['close', 'refresh', 'change-locale', 'toggle-images-blocked', 'send-test'])
const localeOptions = computed(() => props.locales.map((locale) => ({
    value: locale,
    label: locale.toUpperCase(),
})))
const mode = ref('desktop')
const tab = ref('visual')
const refreshKey = ref(0)

watch(() => props.open, (open) => {
    if (open) {
        mode.value = 'desktop'
        tab.value = 'visual'
    }
})

function refresh() {
    refreshKey.value += 1
    emit('refresh')
}
</script>

<template>
    <EmailTemplateDialog
        :open="open"
        :title="strings.preview_title"
        :subtitle="strings.preview_no_email"
        :close-label="strings.close"
        size="preview"
        @close="emit('close')"
    >
        <template #toolbar>
            <div v-if="locales.length" class="preview-locale">
                <SelectionControl
                    :model-value="activeLocale"
                    :label="strings.locale_label"
                    :options="localeOptions"
                    :searchable="false"
                    size="sm"
                    @update:model-value="emit('change-locale', $event)"
                />
            </div>
            <div class="et-segmented" role="group" :aria-label="strings.preview_title">
                <button type="button" :class="{ 'is-active': mode === 'desktop' }" :aria-pressed="mode === 'desktop'" @click="mode = 'desktop'">
                    <UiIcon name="monitor" :size="15" />
                    <span>{{ strings.preview_desktop }}</span>
                </button>
                <button type="button" :class="{ 'is-active': mode === 'mobile' }" :aria-pressed="mode === 'mobile'" @click="mode = 'mobile'">
                    <UiIcon name="phone" :size="15" />
                    <span>{{ strings.preview_mobile }}</span>
                </button>
            </div>
        </template>

        <div class="preview-shell" data-testid="email-preview" :dir="preview?.direction || 'ltr'">
            <div v-if="preview" class="preview-meta">
                <div class="preview-meta__subject">
                    <span class="pc-eyebrow">{{ strings.review_subject }}</span>
                    <strong dir="auto">{{ preview.subject || '—' }}</strong>
                </div>
                <div class="preview-meta__chips">
                    <span class="preview-chip">{{ preview.locale.toUpperCase() }}</span>
                    <span class="preview-chip">{{ preview.isDraft ? strings.preview_draft : strings.preview_saved }}</span>
                    <span class="preview-chip preview-chip--soft">{{ strings.preview_sample }}</span>
                </div>
            </div>

            <div class="preview-toolbar">
                <div class="et-tabs" role="tablist" :aria-label="strings.preview_title">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="tab === 'visual'"
                        :class="{ 'is-active': tab === 'visual' }"
                        @click="tab = 'visual'"
                    >
                        {{ strings.preview_visual }}
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="tab === 'text'"
                        :class="{ 'is-active': tab === 'text' }"
                        @click="tab = 'text'"
                    >
                        {{ strings.preview_text }}
                    </button>
                </div>
                <button
                    type="button"
                    class="pc-btn pc-btn--secondary pc-btn--sm"
                    :aria-pressed="imagesBlocked ? 'true' : 'false'"
                    @click="emit('toggle-images-blocked', !imagesBlocked)"
                >
                    <UiIcon name="eyeOff" :size="15" />
                    {{ imagesBlocked ? strings.preview_images_on : strings.preview_images_blocked }}
                </button>
                <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" :disabled="busy" @click="refresh">
                    <UiIcon name="refresh" :size="15" />
                    {{ strings.preview_refresh }}
                </button>
            </div>

            <div v-if="busy" class="preview-state" role="status">{{ strings.preview_loading }}</div>
            <div v-else-if="error" class="preview-state preview-state--error" role="alert">{{ error }}</div>
            <template v-else-if="preview">
                <div v-show="tab === 'visual'" class="preview-stage" :class="`preview-stage--${mode}`">
                    <iframe
                        :key="refreshKey"
                        class="preview-frame"
                        :srcdoc="preview.html"
                        sandbox=""
                        referrerpolicy="no-referrer"
                        :title="strings.preview_visual"
                    />
                </div>
                <pre v-show="tab === 'text'" class="preview-text" :dir="preview.direction">{{ preview.plainBody }}</pre>
            </template>
            <div v-else class="preview-state">{{ strings.preview_no_email }}</div>

            <p class="preview-footer-note">
                <UiIcon name="lock" :size="13" />
                {{ strings.preview_no_email }}
            </p>
        </div>

        <template #footer>
            <div class="et-footer-actions">
                <span v-if="testNotice" class="preview-test-notice" role="status">{{ testNotice }}</span>
                <button
                    type="button"
                    class="pc-btn pc-btn--secondary"
                    :disabled="testBusy"
                    @click="emit('send-test')"
                >
                    <UiIcon name="mail" :size="15" />
                    {{ testBusy ? strings.preview_loading : strings.preview_send_test }}
                </button>
                <button type="button" class="pc-btn pc-btn--primary" @click="emit('close')">{{ strings.close }}</button>
            </div>
        </template>
    </EmailTemplateDialog>
</template>

<style scoped>
.preview-shell { min-inline-size: 0; }
.preview-test-notice { margin-inline-end: auto; color: var(--pc-teal); font-size: 0.8rem; }
.preview-locale { display: inline-flex; align-items: center; }
.preview-locale__label {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.preview-locale__select { min-block-size: 34px; padding-block: 5px; font-size: 0.76rem; }
.preview-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm, 9px);
    background: var(--pc-surface);
}
.preview-meta__subject { display: grid; gap: 4px; min-inline-size: 0; }
.preview-meta__subject strong { font-size: 1rem; overflow-wrap: anywhere; }
.preview-meta__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.preview-chip {
    padding: 3px 9px;
    border-radius: 999px;
    background: var(--pc-teal-soft);
    color: var(--pc-teal);
    font-size: 0.72rem;
    font-weight: 700;
}
.preview-chip--soft { background: var(--pc-sand-soft); color: var(--pc-warning); }
.preview-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-block: 14px 10px;
}
.et-tabs, .et-segmented {
    display: inline-flex;
    gap: 3px;
    padding: 3px;
    border: 1px solid var(--pc-border);
    border-radius: 999px;
    background: var(--pc-surface);
}
.et-tabs button, .et-segmented button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--pc-muted);
    font: inherit;
    font-size: 0.78rem;
    cursor: pointer;
}
.et-tabs button.is-active, .et-segmented button.is-active {
    background: var(--pc-navy);
    color: var(--pc-on-primary);
}
.preview-stage {
    display: flex;
    justify-content: center;
    min-inline-size: 0;
    padding: 14px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius, 14px);
    background:
        linear-gradient(45deg, color-mix(in srgb, var(--pc-teal) 3.5%, transparent) 25%, transparent 25%, transparent 75%, color-mix(in srgb, var(--pc-teal) 3.5%, transparent) 75%),
        var(--pc-surface-2);
    background-size: 18px 18px;
}
.preview-frame {
    display: block;
    inline-size: 100%;
    block-size: clamp(360px, 56vh, 620px);
    border: 0;
    border-radius: 10px;
    background: var(--pcdr-white);
}
.preview-stage--mobile .preview-frame {
    inline-size: min(390px, 100%);
    block-size: clamp(420px, 66vh, 720px);
    border-radius: 22px;
    box-shadow: var(--pcdr-shadow-strong);
}
.preview-text {
    margin: 0;
    padding: 18px;
    max-block-size: clamp(360px, 56vh, 620px);
    overflow: auto;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius, 14px);
    background: var(--pc-surface);
    color: var(--pc-text);
    font: inherit;
    line-height: 1.8;
}
.preview-state {
    padding: 44px 18px;
    text-align: center;
    color: var(--pc-muted);
}
.preview-state--error { color: var(--pc-danger); }
.preview-footer-note {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 10px 2px 0;
    color: var(--pc-muted);
    font-size: 0.76rem;
}
.et-footer-actions { display: flex; justify-content: flex-end; }

@media (max-width: 640px) {
    .preview-stage { padding: 8px; }
    .preview-frame, .preview-stage--mobile .preview-frame {
        block-size: min(64vh, 620px);
        border-radius: 10px;
    }
    .et-segmented button { padding: 6px 9px; }
}
</style>
