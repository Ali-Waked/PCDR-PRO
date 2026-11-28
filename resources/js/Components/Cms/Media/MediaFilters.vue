<script setup>
import UiIcon from '../../Dashboard/UiIcon.vue'
import SelectionControl from '../../Selection/SelectionControl.vue'
import { useI18n } from '../../../composables/useI18n'

defineProps({
    modelValue: { type: Object, required: true },
    typeOptions: { type: Array, default: () => [] },
    kindOptions: { type: Array, default: () => [] },
    visibilityOptions: { type: Array, default: () => [] },
    sortOptions: { type: Array, default: () => [] },
    hasFilters: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit', 'clear'])
const { t } = useI18n()
</script>

<template>
    <form class="pc-media-filters" role="search" @submit.prevent="emit('submit')">
        <label class="pc-field">
            <span class="pc-label">{{ t('ui.search') }}</span>
            <input
                class="pc-input"
                type="search"
                name="q"
                :value="modelValue.q"
                @input="emit('update:modelValue', { ...modelValue, q: $event.target.value })"
            >
        </label>

        <div class="pc-field">
            <SelectionControl
                :modelValue="modelValue.type"
                :label="t('cms_type', t('ui.status'))"
                name="type"
                :options="typeOptions"
                :placeholder="t('ui.all')"
                clearable
                :searchable="false"
                @update:modelValue="emit('update:modelValue', { ...modelValue, type: $event })"
            />
        </div>

        <div class="pc-field">
            <SelectionControl
                :modelValue="modelValue.kind"
                :label="t('cms_type', t('ui.status'))"
                name="kind"
                :options="kindOptions"
                :placeholder="t('ui.all')"
                clearable
                :searchable="false"
                @update:modelValue="emit('update:modelValue', { ...modelValue, kind: $event })"
            />
        </div>

        <div class="pc-field">
            <SelectionControl
                :modelValue="modelValue.visibility"
                :label="t('cms_media_visibility', 'Visibility')"
                name="visibility"
                :options="visibilityOptions"
                :placeholder="t('ui.all')"
                clearable
                :searchable="false"
                @update:modelValue="emit('update:modelValue', { ...modelValue, visibility: $event })"
            />
        </div>

        <label class="pc-field">
            <span class="pc-label">{{ t('cms_media_date_from') }}</span>
            <input
                class="pc-input"
                type="date"
                :value="modelValue.date_from"
                @input="emit('update:modelValue', { ...modelValue, date_from: $event.target.value })"
            >
        </label>

        <label class="pc-field">
            <span class="pc-label">{{ t('cms_media_date_to') }}</span>
            <input
                class="pc-input"
                type="date"
                :value="modelValue.date_to"
                @input="emit('update:modelValue', { ...modelValue, date_to: $event.target.value })"
            >
        </label>

        <div class="pc-field">
            <SelectionControl
                :modelValue="modelValue.sort"
                :label="t('ui.cms_content_page.sort', '')"
                name="sort"
                :options="sortOptions"
                :searchable="false"
                @update:modelValue="emit('update:modelValue', { ...modelValue, sort: $event })"
            />
        </div>

        <div class="pc-media-filters__actions">
            <div class="pc-media-filters__view" role="group" :aria-label="t('cms_media_view_grid')">
                <button
                    type="button"
                    class="pc-icon-button"
                    :class="{ 'is-active': modelValue.view === 'grid' }"
                    :aria-pressed="modelValue.view === 'grid' ? 'true' : 'false'"
                    :title="t('cms_media_view_grid')"
                    @click="emit('update:modelValue', { ...modelValue, view: 'grid' })"
                >
                    <UiIcon name="grid" :size="16" />
                </button>
                <button
                    type="button"
                    class="pc-icon-button"
                    :class="{ 'is-active': modelValue.view === 'list' }"
                    :aria-pressed="modelValue.view === 'list' ? 'true' : 'false'"
                    :title="t('cms_media_view_list')"
                    @click="emit('update:modelValue', { ...modelValue, view: 'list' })"
                >
                    <UiIcon name="list" :size="16" />
                </button>
            </div>
            <button class="pc-btn pc-btn--secondary" type="submit">
                <UiIcon name="search" :size="16" />
                {{ t('ui.search') }}
            </button>
            <button class="pc-btn pc-btn--ghost" type="button" :disabled="!hasFilters" @click="emit('clear')">
                <UiIcon name="refresh" :size="16" />
                {{ t('ui.cms_content_page.clear_filters', '') }}
            </button>
        </div>
    </form>
</template>
