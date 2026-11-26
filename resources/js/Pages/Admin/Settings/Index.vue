<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Button from '../../../Components/Ui/Button.vue'
import MultiSelect from '../../../Components/Selection/MultiSelect.vue'
import PageHeader from '../../../Components/Dashboard/PageHeader.vue'
import SelectField from '../../../Components/Ui/SelectField.vue'
import TextField from '../../../Components/Ui/TextField.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useLocale } from '../../../Composables/useLocale'
import { useI18n } from '../../../composables/useI18n'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'settings_page.title',
        subtitleKey: 'settings_page.subtitle',
        eyebrowKey: 'settings_page.eyebrow',
        showHeader: false,
    }, () => page),
})

const props = defineProps({
    definitions: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    values: { type: Object, default: () => ({}) },
    canUpdate: { type: Boolean, default: false },
    secretReferences: { type: Array, default: () => [] },
})

const { t, localePath } = useLocale()
const { t: tWebsite } = useI18n()

const form = useForm({})
const draft = reactive({})
const jsonErrors = reactive({})

for (const definition of props.definitions) {
    const stored = props.values[definition.key]
    const value = stored === undefined || stored === null ? definition.default : stored

    if (definition.type === 'boolean') {
        draft[definition.key] = Boolean(value)
    } else if (definition.type === 'json') {
        draft[definition.key] = Array.isArray(value) ? [...value] : (value ?? [])
    } else {
        draft[definition.key] = String(value ?? '')
    }
}

const localeOptions = computed(() => [
    { value: 'ar', label: t('users_page.locale_ar') },
    { value: 'en', label: t('users_page.locale_en') },
])

const visibleGroups = computed(() => {
    if (props.groups.length) {
        return props.groups.filter((group) => group.definitions?.length)
    }

    return [...new Set(props.definitions.map((definition) => definition.group))].map((key) => ({
        key,
        label: key,
        definitions: props.definitions.filter((definition) => definition.group === key),
    }))
})

function fieldKind(definition) {
    if (definition.key === 'localization.default_locale') {
        return 'locale'
    }

    if (definition.key === 'localization.available_locales') {
        return 'locales'
    }

    if (definition.type === 'boolean') {
        return 'boolean'
    }

    if (definition.type === 'integer') {
        return 'integer'
    }

    if (definition.type === 'json') {
        return 'json'
    }

    return 'string'
}

function typeLabel(definition) {
    const labels = {
        string: tWebsite('ui.text', ''),
        integer: tWebsite('ui.number', ''),
        boolean: tWebsite('ui.boolean', ''),
        json: tWebsite('ui.list', ''),
    }

    return labels[definition.type] || definition.type
}

function clearJsonError(key) {
    if (jsonErrors[key]) {
        delete jsonErrors[key]
    }
}

function submit() {
    if (! props.canUpdate || form.processing) {
        return
    }

    let hasJsonError = false
    for (const definition of props.definitions) {
        const raw = draft[definition.key]
        const kind = fieldKind(definition)

        if (kind === 'boolean') {
            form[definition.key] = Boolean(raw)
        } else if (kind === 'integer') {
            form[definition.key] = Number(raw)
        } else if (kind === 'locales') {
            form[definition.key] = Array.isArray(raw) ? [...raw] : []
        } else if (kind === 'json') {
            try {
                form[definition.key] = JSON.parse(String(raw))
                clearJsonError(definition.key)
            } catch {
                form[definition.key] = raw
                jsonErrors[definition.key] = tWebsite('ui.notification.validation_message', '')
                hasJsonError = true
            }
        } else {
            form[definition.key] = String(raw ?? '')
        }
    }

    if (hasJsonError) {
        return
    }

    form.put(localePath('/admin/settings'))
}
</script>

<template>
    <div class="pc-stack pcdr-enter">
        <Head :title="t('settings_page.title')" />

        <PageHeader
            :eyebrow="t('settings_page.eyebrow')"
            :title="t('settings_page.title')"
            :subtitle="t('settings_page.subtitle')"
        >
            <template #actions>
                <Link :href="localePath('/cms/settings')" class="pc-btn pc-btn--ghost">
                    <UiIcon name="settings" :size="16" />
                    {{ t('admin_nav.cms_settings') }}
                </Link>
            </template>
        </PageHeader>

        <div class="pc-callout" role="note">
            <UiIcon name="info" :size="17" />
            <p>{{ t('settings_page.distinction') }}</p>
        </div>

        <div v-if="!canUpdate" class="pc-callout pc-callout--warning" role="status">
            <UiIcon name="lock" :size="17" />
            <p>{{ t('settings_page.read_only') }}</p>
        </div>

        <form class="pc-stack" @submit.prevent="submit">
            <section
                v-for="group in visibleGroups"
                :key="group.key"
                class="pc-card"
                :aria-labelledby="`settings-group-${group.key}`"
            >
                <div class="pc-card__head">
                    <div>
                        <h2 :id="`settings-group-${group.key}`" class="pc-card__title">{{ group.label }}</h2>
                        <p class="pc-card__subtitle">{{ t('settings_page.group_summary') }}</p>
                    </div>
                    <span class="pc-badge pc-badge--teal">{{ group.definitions.length }}</span>
                </div>
                <div class="pc-card__body pc-form-grid pc-form-grid--2">
                    <div
                        v-for="definition in group.definitions"
                        :key="definition.key"
                        class="pc-field"
                        :class="{ 'pc-field--full': fieldKind(definition) === 'json' }"
                    >
                        <div class="pc-table__actions">
                            <span
                                class="pc-badge"
                                :class="definition.public ? 'pc-badge--teal' : 'pc-badge--slate'"
                            >
                                {{ definition.public ? t('settings_page.public_badge') : t('settings_page.private_badge') }}
                            </span>
                            <span class="pc-hint">{{ typeLabel(definition) }}</span>
                        </div>

                        <SelectField
                            v-if="fieldKind(definition) === 'locale'"
                            v-model="draft[definition.key]"
                            :name="definition.key"
                            :label="definition.label"
                            :options="localeOptions"
                            :error="form.errors[definition.key] || ''"
                            :disabled="!canUpdate"
                            :searchable="false"
                        />

                        <MultiSelect
                            v-else-if="fieldKind(definition) === 'locales'"
                            v-model="draft[definition.key]"
                            :label="definition.label"
                            :options="localeOptions"
                            :error="form.errors[definition.key] || ''"
                            :disabled="!canUpdate"
                            :empty-text="tWebsite('ui.no_results')"
                            :searchable="false"
                            :clearable="false"
                        />

                        <label v-else-if="fieldKind(definition) === 'boolean'" class="pc-checkbox pc-field--action">
                            <input v-model="draft[definition.key]" type="checkbox" :disabled="!canUpdate">
                            <span>{{ definition.label }}</span>
                        </label>

                        <TextField
                            v-else-if="fieldKind(definition) === 'integer'"
                            v-model="draft[definition.key]"
                            :name="definition.key"
                            :label="definition.label"
                            type="number"
                            inputmode="numeric"
                            dir="ltr"
                            :error="form.errors[definition.key] || ''"
                            :disabled="!canUpdate"
                        />

                        <label v-else-if="fieldKind(definition) === 'json'" class="pc-field">
                            <span class="pc-label">{{ definition.label }}</span>
                            <textarea
                                v-model="draft[definition.key]"
                                class="pc-textarea"
                                :name="definition.key"
                                rows="4"
                                dir="ltr"
                                :disabled="!canUpdate"
                                :aria-invalid="jsonErrors[definition.key] || form.errors[definition.key] ? 'true' : 'false'"
                                @input="clearJsonError(definition.key)"
                            />
                            <span v-if="jsonErrors[definition.key] || form.errors[definition.key]" class="pc-error" role="alert">
                                {{ jsonErrors[definition.key] || form.errors[definition.key] }}
                            </span>
                        </label>

                        <TextField
                            v-else
                            v-model="draft[definition.key]"
                            :name="definition.key"
                            :label="definition.label"
                            :type="definition.key === 'branding.logo_url' ? 'url' : 'text'"
                            dir="auto"
                            :error="form.errors[definition.key] || ''"
                            :disabled="!canUpdate"
                        />
                    </div>
                </div>
            </section>

            <section v-if="secretReferences.length" class="pc-card" aria-labelledby="settings-secrets-title">
                <div class="pc-card__head">
                    <h2 id="settings-secrets-title" class="pc-card__title">{{ t('settings_page.secret_references') }}</h2>
                    <span class="pc-badge pc-badge--sand">{{ secretReferences.length }}</span>
                </div>
                <div class="pc-card__body pc-stack">
                    <p class="pc-hint">{{ t('settings_page.secret_notice') }}</p>
                    <ul class="pc-list">
                        <li v-for="reference in secretReferences" :key="reference" class="pc-list__item">
                            <code class="pc-list__meta" dir="ltr">{{ reference }}</code>
                        </li>
                    </ul>
                </div>
            </section>

            <div v-if="canUpdate" class="pc-form-actions">
                <Button type="submit" :processing="form.processing">{{ t('settings_page.save') }}</Button>
            </div>
        </form>

        <p class="pc-hint">{{ t('settings_page.localized_note') }}</p>
    </div>
</template>
