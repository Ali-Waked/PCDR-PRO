<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Button from '../Ui/Button.vue'
import Alert from '../Ui/Alert.vue'
import FormSection from './FormSection.vue'
import UiIcon from './UiIcon.vue'
import { useLocale } from '../../Composables/useLocale.js'
import { useAdminNotifications } from '../../composables/useAdminNotifications'

const props = defineProps({
    avatarUrl: { type: String, default: '' },
    name: { type: String, default: '' },
})

const { t, localePath } = useLocale()
const { confirmDelete, finishConfirmation } = useAdminNotifications()

const fileInput = ref(null)
const selectedFile = ref(null)
const previewUrl = ref('')
const localError = ref('')
const notice = ref('')

const acceptedTypes = ['image/jpeg', 'image/png', 'image/webp']
const maxFileSize = 2 * 1024 * 1024

const upload = useForm({ avatar: null })
const remove = useForm({})

const initials = computed(() => {
    const parts = String(props.name || '').trim().split(/\s+/).filter(Boolean)
    return parts.length ? parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('') : '?'
})

const imageSource = computed(() => previewUrl.value || props.avatarUrl || '')
const errorText = computed(() => localError.value || upload.errors.avatar || remove.errors.avatar || '')

function revokePreview() {
    if (previewUrl.value && typeof URL !== 'undefined') {
        URL.revokeObjectURL(previewUrl.value)
    }
    previewUrl.value = ''
}

function clearSelection() {
    revokePreview()
    selectedFile.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

function onFileChange(event) {
    revokePreview()
    selectedFile.value = null
    localError.value = ''
    upload.avatar = null

    const file = event.target.files?.[0] ?? null
    if (! file) {
        return
    }

    if (! acceptedTypes.includes(file.type)) {
        localError.value = String(t('account_profile.avatar_invalid') || '')
        event.target.value = ''
        return
    }

    if (file.size > maxFileSize) {
        localError.value = String(t('account_profile.avatar_too_large') || '')
        event.target.value = ''
        return
    }

    selectedFile.value = file
    upload.avatar = file
    if (typeof URL !== 'undefined') {
        previewUrl.value = URL.createObjectURL(file)
    }
}

function submitUpload() {
    if (! selectedFile.value) {
        return
    }

    notice.value = ''
    upload.post(localePath('/dashboard/profile/avatar'), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = String(t('account_profile.avatar_uploaded') || '')
            upload.reset('avatar')
            clearSelection()
        },
    })
}

async function removeAvatar() {
    const label = String(t('account_profile.remove_photo') || '')
    const accepted = await confirmDelete(label, {
        title: label,
        message: String(t('account_profile.confirm_remove_avatar') || ''),
    })
    if (! accepted) {
        return
    }

    notice.value = ''
    remove.delete(localePath('/dashboard/profile/avatar'), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = String(t('account_profile.avatar_removed') || '')
            clearSelection()
        },
        onFinish: finishConfirmation,
    })
}

onBeforeUnmount(revokePreview)
</script>

<template>
    <div class="pc-stack">
        <Alert v-if="errorText" type="error" :message="errorText" />
        <Alert v-else-if="notice" type="success" :message="notice" />

        <FormSection :title="t('account_profile.profile_photo')">
            <div class="pc-account-avatar-row" style="display: flex; flex-wrap: wrap; align-items: center; gap: 18px">
                <span class="pc-avatar" style="inline-size: 92px; block-size: 92px; border-radius: 18px; font-size: 30px; overflow: hidden">
                    <img
                        v-if="imageSource"
                        :src="imageSource"
                        :alt="props.name"
                        style="inline-size: 100%; block-size: 100%; object-fit: cover"
                    >
                    <template v-else>{{ initials }}</template>
                </span>

                <div class="pc-stack" style="gap: 10px">
                    <span v-if="previewUrl" class="pc-badge pc-badge--teal">{{ t('account_profile.preview') }}</span>
                    <p class="pc-hint">{{ t('account_profile.avatar_hint') }}</p>
                    <div class="pc-table__actions" style="padding: 0; border: 0">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            :aria-label="t('account_profile.choose_photo')"
                            @change="onFileChange"
                        >
                        <Button variant="secondary" @click="fileInput?.click()">
                            <UiIcon name="image" :size="15" />
                            {{ t('account_profile.choose_photo') }}
                        </Button>
                        <Button variant="accent" :disabled="!selectedFile" :processing="upload.processing" @click="submitUpload">
                            <UiIcon name="upload" :size="15" />
                            {{ t('account_profile.upload_photo') }}
                        </Button>
                        <Button
                            v-if="props.avatarUrl"
                            variant="destructive"
                            :processing="remove.processing"
                            @click="removeAvatar"
                        >
                            <UiIcon name="trash" :size="15" />
                            {{ t('account_profile.remove_photo') }}
                        </Button>
                    </div>
                </div>
            </div>
        </FormSection>
    </div>
</template>
