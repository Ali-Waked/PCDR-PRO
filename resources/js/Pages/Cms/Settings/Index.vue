<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import ContentMediaField from '../../../Components/Cms/ContentMediaField.vue'
import FooterVideoPreview from '../../../Components/Cms/FooterVideoPreview.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    settings: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    locales: { type: Array, default: () => ['ar', 'en'] },
    canUpdate: { type: Boolean, default: false },
    uploadKeys: { type: Array, default: () => [] },
    media: { type: Array, default: () => [] },
    timezones: { type: Array, default: () => [] },
    automation: { type: Object, default: null },
})

const { locale, t, localPath, formatDate } = useI18n()

const rows = computed(() => props.settings.map((setting, index) => ({ ...setting, index })))

const groupList = computed(() => {
    if (props.groups.length) {
        return props.groups.filter((group) => rows.value.some((row) => row.group === group.key))
    }

    return [...new Set(rows.value.map((row) => row.group))].map((key) => ({ key, label: key, description: '' }))
})

function rowsFor(groupKey) {
    return rows.value.filter((row) => (
        row.group === groupKey && !(row.group === 'footer' && row.key.startsWith('footer.video.'))
    ))
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

const intervalOptions = [
    { value: '1', label: '1' },
    { value: '2', label: '2' },
    { value: '3', label: '3' },
]

const videoProviderOptions = [
    { value: 'youtube', label: 'YouTube' },
    { value: 'facebook', label: 'Facebook' },
]

const booleanOptions = computed(() => [
    { value: '1', label: t('cms_automation_status_enabled', 'Enabled') },
    { value: '0', label: t('cms_automation_status_disabled', 'Disabled') },
])

const timezoneOptions = computed(() => props.timezones.map((value) => ({ value, label: value })))

function selectOptions(setting) {
    if (setting.type === 'boolean') {
        return booleanOptions.value
    }
    if (setting.type === 'interval') {
        return intervalOptions
    }
    if (setting.type === 'timezone') {
        return timezoneOptions.value
    }
    if (setting.type === 'video_provider') {
        return videoProviderOptions
    }

    return []
}

function numberBounds(setting) {
    if (setting.key === 'audit_automation.lookback_months') {
        return { min: 1, max: 12 }
    }

    return { min: 1, max: 28 }
}

function healthLabel(health) {
    return t(`cms_automation_health_${health}`, health || t('cms_automation_status_none', 'Not available'))
}

function automationDate(value) {
    return value ? formatDate(value, { hour: '2-digit', minute: '2-digit' }) : t('cms_automation_status_none', 'Not available')
}

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

function videoRow(key) {
    return rows.value.find((row) => row.key === key) || null
}

function videoValue(key) {
    const row = videoRow(key)
    return row ? (form.settings[row.index]?.value ?? '') : ''
}

function videoTranslation(key) {
    const row = videoRow(key)
    return row ? (form.settings[row.index]?.translations?.[locale.value] ?? '') : ''
}

const videoProvider = computed(() => String(videoValue('footer.video.provider') || 'youtube'))
const videoUrl = computed(() => String(videoValue('footer.video.url') || ''))
const videoTitle = computed(() => String(videoTranslation('footer.video.title') || ''))

const posterOptions = computed(() => props.media.filter((item) => (
    item && String(item.type) === 'image' && item.restricted !== true
)))

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
    if (['boolean', 'interval', 'timezone', 'video_provider'].includes(setting.type)) {
        return 'select'
    }
    if (setting.type === 'integer') {
        return 'number'
    }
    if (setting.type === 'time') {
        return 'time'
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

            <div class="pc-callout pc-callout--sand" role="note">
                <UiIcon name="shield" :size="17" />
                <span>{{ t('cms_setting_private_note') }}</span>
            </div>

            <section v-if="automation" class="pc-card pc-audit-automation" aria-labelledby="automation-status-title">
                <div class="pc-card__head">
                    <div>
                        <h2 id="automation-status-title" class="pc-card__title">{{ t('cms_automation_status_title') }}</h2>
                        <p class="pc-card__subtitle">{{ t('cms_automation_status_hint') }}</p>
                    </div>
                    <span class="pc-badge" :class="automation.health === 'degraded' ? 'pc-badge--red' : 'pc-badge--slate'">
                        {{ healthLabel(automation.health) }}
                    </span>
                </div>
                <div class="pc-card__body pc-audit-automation__grid">
                    <div>
                        <span class="pc-label">{{ t('cms_automation_status_enabled') }}</span>
                        <strong>{{ automation.enabled ? t('cms_automation_status_enabled') : t('cms_automation_status_disabled') }}</strong>
                    </div>
                    <div>
                        <span class="pc-label">{{ t('cms_automation_status_next') }}</span>
                        <strong>{{ automationDate(automation.next_expected_at) }}</strong>
                    </div>
                    <div>
                        <span class="pc-label">{{ t('cms_automation_status_last_check') }}</span>
                        <strong>{{ automationDate(automation.last_execution_at) }}</strong>
                    </div>
                    <div>
                        <span class="pc-label">{{ t('cms_automation_status_last_success') }}</span>
                        <strong>{{ automation.last_successful_archive || t('cms_automation_status_none') }}</strong>
                    </div>
                    <div v-if="automation.last_failure" class="pc-audit-automation__failure">
                        <span class="pc-label">{{ t('cms_automation_status_last_failure') }}</span>
                        <strong>{{ automation.last_failure }}</strong>
                    </div>
                </div>
            </section>

            <div v-if="!canUpdate" class="pc-callout pc-callout--warning" role="status">
                <UiIcon name="lock" :size="17" />
                <span>{{ t('cms_settings_no_access') }}</span>
            </div>

            <section
                v-if="['footer.video.provider', 'footer.video.url', 'footer.video.title', 'footer.video.poster_media_id', 'footer.video.visible'].every(videoRow)"
                class="pc-card pc-footer-video"
                aria-labelledby="footer-video-title"
                data-cms-footer-video
            >
                <div class="pc-card__head">
                    <div>
                        <h2 id="footer-video-title" class="pc-card__title">{{ t('cms_footer_video_title') }}</h2>
                        <p class="pc-card__subtitle">{{ t('cms_footer_video_note') }}</p>
                    </div>
                    <UiIcon name="video" :size="18" />
                </div>
                <div class="pc-card__body pc-form-grid">
                    <SelectionControl
                        v-model="form.settings[videoRow('footer.video.provider').index].value"
                        :label="t('cms_footer_video_provider')"
                        :options="videoProviderOptions.map((option) => ({ ...option, label: option.value === 'youtube' ? t('cms_footer_video_youtube') : t('cms_footer_video_facebook') }))"
                        :error="valueError(videoRow('footer.video.provider'))"
                        :searchable="false"
                        :disabled="!canUpdate"
                        data-testid="cms-footer-video-provider"
                    />
                    <label class="pc-field pc-field--full">
                        <span class="pc-label">{{ t('cms_footer_video_url') }}</span>
                        <input
                            v-model="form.settings[videoRow('footer.video.url').index].value"
                            class="pc-input"
                            type="text"
                            dir="ltr"
                            maxlength="2000"
                            :disabled="!canUpdate"
                            :aria-invalid="valueError(videoRow('footer.video.url')) ? 'true' : 'false'"
                            data-testid="cms-footer-video-url"
                        >
                        <span class="pc-hint">{{ t('cms_footer_video_url_hint') }}</span>
                        <span v-if="valueError(videoRow('footer.video.url'))" class="pc-error" role="alert">
                            {{ valueError(videoRow('footer.video.url')) }}
                        </span>
                    </label>
                    <fieldset class="pc-field pc-field--full pc-picker">
                        <legend class="pc-label">{{ t('cms_footer_video_title_label') }}</legend>
                        <label v-for="code in locales" :key="code" class="pc-field pc-settings-locale">
                            <span class="pc-label pc-settings-locale__label">{{ localeLabel(code) }}</span>
                            <input
                                v-model="form.settings[videoRow('footer.video.title').index].translations[code]"
                                class="pc-input"
                                type="text"
                                maxlength="2000"
                                :disabled="!canUpdate"
                                :dir="code === 'ar' ? 'rtl' : 'ltr'"
                                :data-testid="`cms-footer-video-title-${code}`"
                            >
                        </label>
                    </fieldset>
                    <div class="pc-field pc-field--full">
                        <ContentMediaField
                            :model-value="form.settings[videoRow('footer.video.poster_media_id').index].value"
                            :label="t('cms_footer_video_poster')"
                            :options="posterOptions"
                            accept="image"
                            :disabled="!canUpdate"
                            data-testid="cms-footer-video-poster"
                            @update:model-value="form.settings[videoRow('footer.video.poster_media_id').index].value = $event"
                        />
                    </div>
                    <SelectionControl
                        v-model="form.settings[videoRow('footer.video.visible').index].value"
                        :label="t('cms_footer_video_visible')"
                        :options="booleanOptions"
                        :error="valueError(videoRow('footer.video.visible'))"
                        :searchable="false"
                        :disabled="!canUpdate"
                        data-testid="cms-footer-video-visible"
                    />
                    <div class="pc-field pc-field--full">
                        <FooterVideoPreview
                            :provider="videoProvider"
                            :url="videoUrl"
                            :title="videoTitle"
                            :path="localPath('cms/settings/video-preview')"
                        />
                    </div>
                </div>
            </section>

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
                                <template v-else-if="inputType(setting) === 'select'">
                                    <SelectionControl
                                        v-model="form.settings[setting.index].value"
                                        :label="setting.label"
                                        :options="selectOptions(setting)"
                                        :error="valueError(setting)"
                                        :searchable="setting.type === 'timezone'"
                                        :disabled="!canUpdate"
                                    />
                                    <span v-if="!setting.public" class="pc-badge pc-badge--slate pc-badge--small">
                                        {{ t('cms_setting_private') }}
                                    </span>
                                </template>
                                <label v-else class="pc-field">
                                    <span class="pc-label">
                                        {{ setting.label }}
                                        <span v-if="!setting.public" class="pc-badge pc-badge--slate pc-badge--small">
                                            {{ t('cms_setting_private') }}
                                        </span>
                                    </span>
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
                                        :dir="['url', 'email', 'time', 'number'].includes(setting.type) ? 'ltr' : undefined"
                                        :min="inputType(setting) === 'number' ? numberBounds(setting).min : undefined"
                                        :max="inputType(setting) === 'number' ? numberBounds(setting).max : undefined"
                                        step="1"
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
