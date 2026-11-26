<script setup>
import { computed, reactive, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    preview: { type: Array, default: () => [] },
    media: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ view: false, update: false }) },
})

const { t, locale, localPath } = useI18n()
const locales = ['ar', 'en']
const itemTypes = ['inline', 'pillar', 'article', 'publication', 'page', 'project']
const statuses = ['draft', 'pending_review', 'approved', 'published', 'archived']
const itemTypeOptions = itemTypes.map((type) => ({ value: type, label: type }))
const statusOptions = statuses.map((status) => ({ value: status, label: status }))
const mediaOptions = computed(() => [
    { value: '', label: locale.value === 'ar' ? 'بدون صورة' : 'No image' },
    ...props.media.map((asset) => ({ value: asset.id, label: asset.name || asset.original_name || `#${asset.id}` })),
])

const activeLocale = reactive({})
const canUpdate = computed(() => props.can?.update === true)
const canView = computed(() => props.can?.view === true)

function localeFor(sectionId) {
    if (!activeLocale[sectionId]) {
        activeLocale[sectionId] = 'ar'
    }
    return activeLocale[sectionId]
}

function emptyItemTranslations() {
    return Object.fromEntries(locales.map((code) => [code, {
        label: '', title: '', body: '', display_value: '', url: '',
    }]))
}

function cloneSections() {
    return props.sections.map((section) => ({
        id: section.id,
        key: section.key,
        status: section.status || 'published',
        is_enabled: section.enabled !== false,
        display_order: section.order || 0,
        translations: Object.fromEntries(locales.map((code) => [code, {
            eyebrow: section.translations?.[code]?.eyebrow || '',
            title: section.translations?.[code]?.title || '',
            body: section.translations?.[code]?.body || '',
            cta_label: section.translations?.[code]?.cta_label || '',
            cta_link: section.translations?.[code]?.cta_link || '',
        }])),
        items: (section.items || []).map((item) => ({
            id: item.id,
            item_type: item.itemType,
            item_id: item.itemId,
            item_key: item.itemKey || '',
            media_id: item.mediaId ?? '',
            is_published: item.isPublished !== false,
            numeric_value: item.numericValue ?? '',
            translations: Object.fromEntries(locales.map((code) => [code, {
                label: item.translations?.[code]?.label || '',
                title: item.translations?.[code]?.title || '',
                body: item.translations?.[code]?.body || '',
                display_value: item.translations?.[code]?.display_value || '',
                url: item.translations?.[code]?.url || '',
            }])),
        })),
    }))
}

const form = useForm({ sections: cloneSections() })

function addItem(section) {
    section.items.push({
        item_type: 'inline',
        item_id: '',
        item_key: '',
        media_id: '',
        is_published: true,
        numeric_value: '',
        translations: emptyItemTranslations(),
    })
}

/** Mark existing items for removal so the backend deletes them on save. */
function removeItem(section, itemIndex) {
    const item = section.items[itemIndex]
    if (!item) {
        return
    }
    if (item.id) {
        item.remove = true
        return
    }
    section.items.splice(itemIndex, 1)
}

function save() {
    if (!canUpdate.value) {
        return
    }
    form.put(localPath('cms/homepage'), { preserveScroll: true })
}

function moveSection(index, offset) {
    if (!canUpdate.value) {
        return
    }
    const destination = index + offset
    if (destination < 0 || destination >= form.sections.length) {
        return
    }
    const current = form.sections[index]
    form.sections[index] = form.sections[destination]
    form.sections[destination] = current
    form.sections.forEach((section, order) => {
        section.display_order = order
    })

    router.post(localPath('cms/homepage/reorder'), {
        order: form.sections.map((section) => ({ id: section.id, display_order: section.display_order })),
    }, { preserveScroll: true })
}

function toggleSection(section) {
    if (!canUpdate.value) {
        return
    }
    router.post(localPath(`cms/homepage/${section.id}/toggle`), {
        is_enabled: !section.is_enabled,
    }, {
        preserveScroll: true,
        onSuccess: () => { section.is_enabled = !section.is_enabled },
    })
}
</script>

<template>
    <CmsDashboardLayout :title="t('ui.cms_homepage')" :subtitle="t('cms_sections')">
        <div class="pc-stack pcdr-enter">
            <div class="pc-page-actions">
                <button
                    class="pc-btn pc-btn--primary"
                    type="submit"
                    form="homepage-form"
                    :disabled="form.processing || !canUpdate"
                >
                    <UiIcon name="check" :size="16" />
                    {{ t('cms_save') }}
                </button>
            </div>

            <form id="homepage-form" class="pc-stack" @submit.prevent="save">
                <div class="pc-accordion">
                    <details
                        v-for="(section, index) in form.sections"
                        :key="section.id"
                        class="pc-accordion__item"
                        :open="index < 3"
                    >
                        <summary class="pc-accordion__trigger">
                            <UiIcon name="chevronRight" :size="17" />
                            <span>{{ section.translations.ar.title || section.translations.en.title || section.key }}</span>
                            <span class="pc-badge pc-badge--slate">{{ section.status }}</span>
                            <span class="pc-badge pc-badge--slate">{{ section.display_order }}</span>
                        </summary>

                        <div class="pc-accordion__body">
                            <div class="pc-form-grid pc-form-grid--2">
                                <label class="pc-checkbox">
                                    <input :checked="section.is_enabled" type="checkbox" :disabled="!canUpdate" @change="toggleSection(section)">
                                    <span>{{ t('cms_section_enabled') }}</span>
                                </label>
                                <div class="pc-field">
                                    <SelectionControl
                                        v-model="section.status"
                                        :label="t('cms_status', 'Publication status')"
                                        :options="statusOptions"
                                        :searchable="false"
                                        :disabled="!canUpdate"
                                        size="sm"
                                    />
                                </div>
                                <label class="pc-field">
                                    <span class="pc-label">{{ t('cms_order') }}</span>
                                    <input v-model.number="section.display_order" class="pc-input" type="number" min="0" :disabled="!canUpdate" dir="ltr">
                                </label>
                                <div class="pc-field pc-page-actions">
                                    <button class="pc-btn pc-btn--secondary pc-btn--sm" type="button" :disabled="!canUpdate || index === 0" :aria-label="locale === 'ar' ? 'نقل للأعلى' : 'Move up'" @click="moveSection(index, -1)">
                                        <UiIcon name="chevronRight" :size="14" />
                                    </button>
                                    <button class="pc-btn pc-btn--secondary pc-btn--sm" type="button" :disabled="!canUpdate || index === form.sections.length - 1" :aria-label="locale === 'ar' ? 'نقل للأسفل' : 'Move down'" @click="moveSection(index, 1)">
                                        <UiIcon name="chevronRight" :size="14" />
                                    </button>
                                </div>
                            </div>

                            <div class="pc-tabs" role="tablist" :aria-label="locale === 'ar' ? 'اللغة' : 'Language'">
                                <button
                                    v-for="code in locales"
                                    :key="code"
                                    type="button"
                                    role="tab"
                                    :aria-selected="localeFor(section.id) === code ? 'true' : 'false'"
                                    :tabindex="localeFor(section.id) === code ? 0 : -1"
                                    @click="activeLocale[section.id] = code"
                                >
                                    {{ code === 'ar' ? t('cms_arabic') : t('cms_english') }}
                                </button>
                            </div>
                            <div v-for="code in locales" v-show="localeFor(section.id) === code" :key="code" class="pc-translation-pane">
                                <h3 class="pc-eyebrow">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }}</h3>
                                <div class="pc-form-grid">
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_intro') }}</span>
                                        <input v-model="section.translations[code].eyebrow" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_title') }}</span>
                                        <input v-model="section.translations[code].title" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_body') }}</span>
                                        <textarea v-model="section.translations[code].body" class="pc-textarea" rows="3" maxlength="5000" :disabled="!canUpdate"></textarea>
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('cms_cta') }}</span>
                                        <input v-model="section.translations[code].cta_label" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                    </label>
                                    <label class="pc-field">
                                        <span class="pc-label">{{ t('ui.website') }}</span>
                                        <input v-model="section.translations[code].cta_link" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate" dir="ltr">
                                    </label>
                                </div>
                            </div>

                            <div class="pc-stack">
                                <h3 class="pc-eyebrow">{{ t('cms_add_item') }}</h3>
                                <div
                                    v-for="(item, itemIndex) in section.items"
                                    v-show="!item.remove"
                                    :key="item.id || `new-${itemIndex}`"
                                    class="pc-stack pc-picker"
                                >
                                    <div class="pc-form-grid pc-form-grid--2">
                                        <div class="pc-field">
                                            <SelectionControl
                                                v-model="item.item_type"
                                                :label="t('ui.category')"
                                                :options="itemTypeOptions"
                                                :searchable="false"
                                                :disabled="!canUpdate"
                                                size="sm"
                                            />
                                        </div>
                                        <label v-if="item.item_type !== 'inline'" class="pc-field">
                                            <span class="pc-label">ID</span>
                                            <input v-model="item.item_id" class="pc-input" type="number" min="1" :disabled="!canUpdate" dir="ltr" required>
                                        </label>
                                        <label v-if="item.item_type === 'inline'" class="pc-field">
                                            <span class="pc-label">Key</span>
                                            <input v-model="item.item_key" class="pc-input" type="text" maxlength="64" :disabled="!canUpdate" dir="ltr">
                                        </label>
                                        <label v-if="item.item_type === 'inline'" class="pc-field">
                                            <span class="pc-label">Numeric value</span>
                                            <input v-model="item.numeric_value" class="pc-input" type="number" step="any" :disabled="!canUpdate" dir="ltr">
                                        </label>
                                        <label class="pc-checkbox">
                                            <input v-model="item.is_published" type="checkbox" :disabled="!canUpdate">
                                            <span>{{ t('cms_section_enabled') }}</span>
                                        </label>
                                        <div class="pc-field">
                                            <SelectionControl
                                                v-model="item.media_id"
                                                :label="t('cms_media_picker', 'Media library')"
                                                :options="mediaOptions"
                                                :searchable="false"
                                                :disabled="!canUpdate"
                                                size="sm"
                                            />
                                        </div>
                                        <button class="pc-btn pc-btn--danger pc-btn--sm" type="button" :disabled="!canUpdate" @click="removeItem(section, itemIndex)">
                                            <UiIcon name="trash" :size="14" />
                                            {{ t('cms_remove_item') }}
                                        </button>
                                    </div>

                                    <div v-if="item.item_type === 'inline'" class="pc-stack">
                                        <div v-for="code in locales" v-show="localeFor(section.id) === code" :key="code" class="pc-translation-pane">
                                            <h4 class="pc-eyebrow">{{ code === 'ar' ? t('cms_arabic') : t('cms_english') }}</h4>
                                            <div class="pc-form-grid">
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('cms_title') }}</span>
                                                    <input v-model="item.translations[code].title" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('cms_cta') }}</span>
                                                    <input v-model="item.translations[code].label" class="pc-input" type="text" maxlength="120" :disabled="!canUpdate">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('cms_body') }}</span>
                                                    <textarea v-model="item.translations[code].body" class="pc-textarea" rows="2" maxlength="5000" :disabled="!canUpdate"></textarea>
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">Display value</span>
                                                    <input v-model="item.translations[code].display_value" class="pc-input" type="text" maxlength="64" :disabled="!canUpdate" dir="ltr">
                                                </label>
                                                <label class="pc-field">
                                                    <span class="pc-label">{{ t('ui.website') }}</span>
                                                    <input v-model="item.translations[code].url" class="pc-input" type="text" maxlength="255" :disabled="!canUpdate" dir="ltr">
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button class="pc-btn pc-btn--secondary pc-btn--sm" type="button" :disabled="!canUpdate" @click="addItem(section)">
                                <UiIcon name="plus" :size="15" />
                                {{ t('cms_add_item') }}
                            </button>
                        </div>
                    </details>
                </div>

                <section v-if="canView" class="pc-card">
                    <div class="pc-card__head">
                        <h2 class="pc-card__title">{{ t('cms_preview') }}</h2>
                        <span class="pc-badge pc-badge--teal">{{ preview.length }}</span>
                    </div>
                    <ul class="pc-list">
                        <li v-for="section in preview" :key="section.key" class="pc-list__item">
                            <div class="pc-list__copy">
                                <span class="pc-list__title">{{ section.title || section.key }}</span>
                                <span v-if="section.body" class="pc-list__meta">{{ section.body }}</span>
                            </div>
                            <span class="pc-list__meta">{{ section.items.length }} {{ t('cms_add_item') }}</span>
                        </li>
                    </ul>
                </section>

                <div class="pc-form-actions">
                    <button class="pc-btn pc-btn--primary" type="submit" :disabled="form.processing || !canUpdate" :aria-busy="form.processing">
                        <UiIcon name="check" :size="16" />
                        {{ t('cms_save') }}
                    </button>
                </div>
            </form>
        </div>
    </CmsDashboardLayout>
</template>
