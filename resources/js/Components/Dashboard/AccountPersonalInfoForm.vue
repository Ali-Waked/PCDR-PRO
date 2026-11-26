<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import TextField from '../Ui/TextField.vue'
import SelectField from '../Ui/SelectField.vue'
import Button from '../Ui/Button.vue'
import Alert from '../Ui/Alert.vue'
import FormSection from './FormSection.vue'
import ThemeSelector from './ThemeSelector.vue'
import { useLocale } from '../../Composables/useLocale.js'

const props = defineProps({
    profile: { type: Object, required: true },
})

const { t, localePath } = useLocale()
const notice = ref('')

const form = useForm({
    name: props.profile.name ?? '',
    locale: props.profile.locale ?? 'en',
    theme: props.profile.theme ?? 'system',
})

const localeOptions = computed(() => [
    { value: 'ar', label: String(t('users_page.locale_ar') || '') },
    { value: 'en', label: String(t('users_page.locale_en') || '') },
])

function submit() {
    form.put(localePath('/dashboard/profile'), {
        preserveScroll: true,
        onSuccess: () => {
            notice.value = String(t('account_profile.saved') || '')
            form.defaults({ name: form.name, locale: form.locale, theme: form.theme })
        },
    })
}
</script>

<template>
    <div class="pc-stack">
        <Alert v-if="notice" type="success" :message="notice" />

        <form @submit.prevent="submit">
            <FormSection :title="t('account_profile.personal_information')">
                <div class="pc-form-grid pc-form-grid--2">
                    <TextField
                        v-model="form.name"
                        name="name"
                        :label="t('account_profile.name')"
                        :error="form.errors.name"
                        autocomplete="name"
                        required
                    />
                    <SelectField
                        v-model="form.locale"
                        name="locale"
                        :label="t('account_profile.locale')"
                        :options="localeOptions"
                        :error="form.errors.locale"
                        :searchable="false"
                        required
                    />
                    <ThemeSelector v-model="form.theme" />
                </div>

                <div class="pc-form-actions">
                    <Button type="submit" :processing="form.processing">{{ t('save') }}</Button>
                </div>
            </FormSection>
        </form>
    </div>
</template>
