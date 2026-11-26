<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    settings: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    locales: { type: Array, default: () => ['ar', 'en'] },
    canUpdate: { type: Boolean, default: false },
    uploadKeys: { type: Array, default: () => [] },
})

const { t, localPath } = useI18n()

const rows = computed(() => props.settings.map((setting, index) => ({ ...setting, index })))

const groupList = computed(() => {
    if (props.groups.length) {
        return props.groups.filter((group) => rows.value.some((row) => row.group === group.key))
    }

    return [...new Set(rows.value.map((row) => row.group))].map((key) => ({ key, label: key, description: '' }))
})

function rowsFor(groupKey) {
    return rows.value.filter((row) => row.group === groupKey)
}

function localeLabel(code) {
    if (code === 'ar') {
        return t('ui.cms_setting_locale_ar', 'العربية')
    }
    if (code === 'en') {
        return t('ui.cms_setting_locale_en', 'English')
    }

    return code
}

const localeOptions = computed(() => props.locales.map((code) => ({ value: code, label: localeLabel(code) })))

const form = useForm({
    settings: props.settings.map((setting) => ({
        key: setting.key,
        value: setting.value ?? '',
        translations: Object.fromEntries(props.locales.map((code) => [code, setting.translations?.[code] ?? ''])),
    })),
})

const upload = useForm({ key: '', file: null })
const fileInputs = ref({})
const pendingUploadKey = ref('')
const clientErrors = ref({})

function inputType(setting) {
    if (setting.type === 'url') {
        return 'text'
    }
    if (setting.type === 'email') {
        return 'email'
    }
    if (setting.type === 'textarea') {
        return 'textarea'
    }
    if (setting.type === 'locale') {
        return 'locale'
    }

    return 'text'
}

function canUpload(setting) {
    return props.canUpdate && props.uploadKeys.includes(setting.key)
}

function errorKey(setting, locale = null) {
    return `${setting.key}:${locale ?? 'value'}`
}

function valueError(setting, locale = null) {
    const prefix = `settings.${setting.index}.`
    const key = locale ? `${prefix}translations.${locale}` : `${prefix}value`

    return form.errors[key] || clientErrors.value[errorKey(setting, locale)] || ''
}

function urlIsValid(value) {
    const text = String(value ?? '').trim()
    if (! text) {
        return true
    }
    if (text.startsWith('/') && ! text.startsWith('//') && ! text.includes('\\')) {
        return true
    }

    try {
        const parsed = new URL(text)

        return ['http:', 'https:'].includes(parsed.protocol) && parsed.hostname !== ''
    } catch {
        return false
    }
}

function isSocial(setting) {
    return String(setting?.key ?? '').startsWith('social.')
}

function socialValueIsValid(value) {
    const text = String(value ?? '').trim()
    if (! text) {
        return true
    }
    if (/javascript:|data:/i.test(text)) {
        return false
    }
    if (/^(https?:\/\/|\/\/)/i.test(text) || /^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i.test(text)) {
        return urlIsValid(text.startsWith('//') ? `https:${text}` : text)
    }

    return /^\+?[\d\s().-]{7,20}$/.test(text) || /^@?[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/.test(text)
}

function valueIsValid(setting, value) {
    return isSocial(setting) ? socialValueIsValid(value) : urlIsValid(value)
}

function save() {
    if (! props.canUpdate || form.processing) {
        return
    }

    clientErrors.value = {}

    rows.value.forEach((setting) => {
        if (setting.type !== 'url') {
            return
        }

        if (! valueIsValid(setting, form.settings[setting.index]?.value)) {
            clientErrors.value[errorKey(setting)] = isSocial(setting)
                ? t('ui.cms_setting_social_invalid', 'Enter a valid profile URL, @handle, username or WhatsApp number.')
                : t('ui.cms_setting_url_invalid', 'Enter a valid URL.')
        }

        props.locales.forEach((code) => {
            if (! valueIsValid(setting, form.settings[setting.index]?.translations?.[code])) {
                clientErrors.value[errorKey(setting, code)] = isSocial(setting)
                    ? t('ui.cms_setting_social_invalid', 'Enter a valid profile URL, @handle, username or WhatsApp number.')
                    : t('ui.cms_setting_url_invalid', 'Enter a valid URL.')
            }
        })
    })

    if (Object.keys(clientErrors.value).length) {
        return
    }

    form.put(localPath('cms/settings'), { preserveScroll: true })
}

function currentValue(setting) {
    return form.settings[setting.index]?.value ?? ''
}

function clearValue(setting) {
    if (! props.canUpdate) {
        return
    }

    form.settings[setting.index].value = ''
    delete clientErrors.value[errorKey(setting)]
}

function setFileInput(key, element) {
    if (element) {
        fileInputs.value[key] = element
    } else {
        delete fileInputs.value[key]
    }
}

function chooseFile(setting, event) {
    upload.key = setting.key
    upload.file = event.target.files?.[0] || null
    pendingUploadKey.value = upload.file ? setting.key : ''
}

function hasFileFor(setting) {
    return Boolean(upload.file) && pendingUploadKey.value === setting.key
}

function submitImage(setting) {
    if (! canUpload(setting) || upload.processing || ! hasFileFor(setting)) {
        return
    }

    upload.key = setting.key
    upload.post(localPath('cms/settings/image'), {
        preserveScroll: true,
        onSuccess: (page) => {
            const updated = (page.props.settings ?? []).find((item) => item.key === setting.key)
            if (updated) {
                form.settings[setting.index].value = updated.value ?? ''
            }

            Object.values(fileInputs.value).forEach((input) => {
                if (input) {
                    input.value = ''
                }
            })
            pendingUploadKey.value = ''
            upload.reset()
        },
    })
}
</script>

<template>
    <CmsDashboardLayout :title="t('ui.cms_settings')" :subtitle="t('cms_settings_note')">
        <div class="pc-stack pcdr-enter pc-site-settings">
            <div class="pc-callout" role="note">
                <UiIcon name="info" :size="17" />
                <span>{{ t('cms_settings_intro') }}</span>
            </div>

            <div class="pc-callout pc-callout--sand" role="note">
                <UiIcon name="lock" :size="17" />
                <span>{{ t('cms_protected_settings_note') }}</span>
            </div>

            <div v-if="!canUpdate" class="pc-callout pc-callout--warning" role="status">
                <UiIcon name="lock" :size="17" />
                <span>{{ t('cms_settings_no_access') }}</span>
            </div>

            <section class="pc-card pc-settings-groups" aria-labelledby="settings-groups-title">
                <div class="pc-card__head">
                    <h2 id="settings-groups-title" class="pc-card__title">{{ t('cms_settings_groups_title') }}</h2>
                    <span class="pc-badge pc-badge--slate">{{ groupList.length }}</span>
                </div>
                <div class="pc-card__body pc-settings-groups__list">
                    <a
                        v-for="group in groupList"
                        :key="group.key"
                        class="pc-settings-groups__link"
                        :href="`#settings-group-${group.key}`"
                    >
                        <UiIcon name="layers" :size="14" />
                        <span>{{ group.label }}</span>
                    </a>
                </div>
            </section>

            <div class="pc-page-actions">
                <button
                    class="pc-btn pc-btn--primary"
                    type="submit"
                    form="settings-form"
                    :disabled="!canUpdate || form.processing"
                    :aria-busy="form.processing"
                >
                    <UiIcon name="check" :size="16" />
                    {{ t('cms_save') }}
                </button>
            </div>

            <form id="settings-form" class="pc-stack" novalidate @submit.prevent="save">
                <section
                    v-for="group in groupList"
                    :id="`settings-group-${group.key}`"
                    :key="group.key"
                    class="pc-card"
                    :aria-labelledby="`settings-group-title-${group.key}`"
                >
                    <div class="pc-card__head">
                        <div>
                            <h2 :id="`settings-group-title-${group.key}`" class="pc-card__title">{{ group.label }}</h2>
                            <p v-if="group.description" class="pc-card__subtitle">{{ group.description }}</p>
                        </div>
                        <span class="pc-badge pc-badge--slate">{{ rowsFor(group.key).length }}</span>
                    </div>
                    <div class="pc-card__body pc-form-grid">
                        <div
                            v-for="setting in rowsFor(group.key)"
                            :key="setting.key"
                            class="pc-field"
                            :class="{
                                'pc-field--full': setting.type === 'textarea' || (setting.localized && inputType(setting) === 'textarea') || canUpload(setting),
                            }"
                        >
                            <template v-if="!setting.localized">
                                <SelectionControl
                                    v-if="inputType(setting) === 'locale'"
                                    v-model="form.settings[setting.index].value"
                                    :label="setting.label"
                                    :options="localeOptions"
                                    :error="valueError(setting)"
                                    :searchable="false"
                                    :disabled="!canUpdate"
                                />
                                <label v-else class="pc-field">
                                    <span class="pc-label">{{ setting.label }}</span>
                                    <span v-if="setting.description" class="pc-hint">{{ setting.description }}</span>
                                    <textarea
                                        v-if="inputType(setting) === 'textarea'"
                                        v-model="form.settings[setting.index].value"
                                        class="pc-textarea"
                                        rows="3"
                                        maxlength="2000"
                                        :disabled="!canUpdate"
                                        :aria-invalid="valueError(setting) ? 'true' : 'false'"
                                    ></textarea>
                                    <input
                                        v-else
                                        v-model="form.settings[setting.index].value"
                                        class="pc-input"
                                        :type="inputType(setting)"
                                        :dir="['url', 'email'].includes(setting.type) ? 'ltr' : undefined"
                                        maxlength="2000"
                                        :disabled="!canUpdate"
                                        :aria-invalid="valueError(setting) ? 'true' : 'false'"
                                    >
                                    <span v-if="setting.type === 'url'" class="pc-hint">
                                        {{ isSocial(setting) ? t('ui.cms_setting_social_hint') : t('ui.cms_setting_url_hint') }}
                                    </span>
                                    <span v-if="valueError(setting)" class="pc-error" role="alert">{{ valueError(setting) }}</span>
                                </label>

                                <div v-if="canUpload(setting)" class="pc-settings-image">
                                    <div class="pc-settings-image__value">
                                        <span class="pc-label">{{ t('ui.cms_setting_current_value') }}</span>
                                        <code dir="ltr">{{ currentValue(setting) || '—' }}</code>
                                    </div>
                                    <div v-if="currentValue(setting)" class="pc-settings-image__preview">
                                        <span class="pc-hint">{{ t('ui.cms_setting_preview') }}</span>
                                        <img :src="currentValue(setting)" :alt="setting.label" loading="lazy">
                                    </div>
                                    <div class="pc-settings-image__actions">
                                        <label class="pc-field">
                                            <span class="pc-label">{{ t('ui.cms_setting_choose_image') }}</span>
                                            <input
                                                :ref="(element) => setFileInput(setting.key, element)"
                                                class="pc-input"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                :disabled="upload.processing"
                                                :aria-invalid="upload.errors.file ? 'true' : 'false'"
                                                @change="chooseFile(setting, $event)"
                                            >
                                        </label>
                                        <button
                                            class="pc-btn pc-btn--primary"
                                            type="button"
                                            :disabled="upload.processing || !hasFileFor(setting)"
                                            :aria-busy="upload.processing"
                                            @click="submitImage(setting)"
                                        >
                                            <UiIcon :name="upload.processing ? 'refresh' : 'upload'" :size="16" />
                                            {{ upload.processing ? t('ui.cms_setting_uploading') : t('ui.cms_setting_upload_image') }}
                                        </button>
                                        <button
                                            v-if="currentValue(setting)"
                                            class="pc-btn"
                                            type="button"
                                            :disabled="upload.processing || !canUpdate"
                                            @click="clearValue(setting)"
                                        >
                                            {{ t('ui.cms_setting_clear_value') }}
                                        </button>
                                    </div>
                                    <span class="pc-hint">{{ t('ui.cms_setting_image_hint') }}</span>
                                    <span v-if="upload.errors.file" class="pc-error" role="alert">{{ upload.errors.file }}</span>
                                    <span v-if="upload.errors.key" class="pc-error" role="alert">{{ upload.errors.key }}</span>
                                </div>
                            </template>

                            <fieldset v-else class="pc-field pc-picker">
                                <legend class="pc-label">
                                    {{ setting.label }}
                                    <span class="pc-hint">— {{ t('ui.cms_setting_translations') }}</span>
                                </legend>
                                <span v-if="setting.description" class="pc-hint">{{ setting.description }}</span>
                                <label v-for="code in locales" :key="code" class="pc-field pc-settings-locale">
                                    <span class="pc-label pc-settings-locale__label">{{ localeLabel(code) }}</span>
                                    <textarea
                                        v-if="inputType(setting) === 'textarea'"
                                        v-model="form.settings[setting.index].translations[code]"
                                        class="pc-textarea"
                                        rows="3"
                                        maxlength="2000"
                                        :disabled="!canUpdate"
                                        :aria-invalid="valueError(setting, code) ? 'true' : 'false'"
                                    ></textarea>
                                    <input
                                        v-else
                                        v-model="form.settings[setting.index].translations[code]"
                                        class="pc-input"
                                        :type="inputType(setting) === 'locale' ? 'text' : inputType(setting)"
                                        :dir="['url', 'email'].includes(setting.type) ? 'ltr' : undefined"
                                        maxlength="2000"
                                        :disabled="!canUpdate"
                                        :aria-invalid="valueError(setting, code) ? 'true' : 'false'"
                                    >
                                    <span v-if="setting.type === 'url'" class="pc-hint">
                                        {{ isSocial(setting) ? t('ui.cms_setting_social_hint') : t('ui.cms_setting_url_hint') }}
                                    </span>
                                    <span v-if="valueError(setting, code)" class="pc-error" role="alert">{{ valueError(setting, code) }}</span>
                                </label>
                            </fieldset>
                        </div>
                    </div>
                </section>

                <div class="pc-form-actions">
                    <button
                        class="pc-btn pc-btn--primary"
                        type="submit"
                        :disabled="!canUpdate || form.processing"
                        :aria-busy="form.processing"
                    >
                        <UiIcon name="check" :size="16" />
                        {{ t('cms_save') }}
                    </button>
                </div>
            </form>
        </div>
    </CmsDashboardLayout>
</template>
