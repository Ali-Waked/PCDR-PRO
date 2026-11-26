<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Button from '../../../Components/Ui/Button.vue'
import MultiSelect from '../../../Components/Selection/MultiSelect.vue'
import SelectField from '../../../Components/Ui/SelectField.vue'
import StatusSelect from '../../../Components/Selection/StatusSelect.vue'
import TextField from '../../../Components/Ui/TextField.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useLocale } from '../../../Composables/useLocale'
import { useI18n } from '../../../composables/useI18n'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'users_page.new',
        subtitleKey: 'users_page.subtitle',
        eyebrowKey: 'users_page.eyebrow',
    }, () => page),
})

const props = defineProps({
    availableRoles: { type: Array, default: () => [] },
    can: {
        type: Object,
        default: () => ({ create: false, update: false, delete: false, assignRoles: false, resetPassword: false }),
    },
})

const { t, localePath } = useLocale()
const { t: tWebsite } = useI18n()
const form = useForm({
    name: '',
    email: '',
    password: '',
    status: 'active',
    locale: 'ar',
    roles: [],
})

const roleOptions = computed(() => props.availableRoles.map((role) => ({
    value: role.id,
    label: role.name,
})))

const statusOptions = computed(() => [
    { value: 'active', label: t('users_page.status_active') },
    { value: 'inactive', label: t('users_page.status_inactive') },
    { value: 'suspended', label: t('users_page.status_suspended') },
])

const localeOptions = [
    { value: 'ar', label: t('users_page.locale_ar') },
    { value: 'en', label: t('users_page.locale_en') },
]
</script>

<template>
    <div class="pc-stack pcdr-enter">
        <Head :title="t('users_page.new')" />

        <div class="pc-callout pc-callout--sand" role="note">
            <UiIcon name="shield" :size="17" />
            <span>{{ t('users_page.technical_note') }}</span>
        </div>

        <form class="pc-stack" @submit.prevent="form.post(localePath('/admin/users'))">
            <section class="pc-card">
                <div class="pc-card__head">
                    <h2 class="pc-card__title">{{ t('users_page.account_details') }}</h2>
                </div>
                <div class="pc-card__body pc-form-grid">
                    <TextField v-model="form.name" name="name" :label="t('users_page.name')" required :error="form.errors.name" />
                    <TextField v-model="form.email" name="email" :label="t('users_page.email')" type="email" required :error="form.errors.email" />
                    <TextField
                        v-model="form.password"
                        name="password"
                        :label="t('users_page.password')"
                        type="password"
                        autocomplete="new-password"
                        required
                        :error="form.errors.password"
                    />
                    <p class="pc-hint pc-field--full">{{ t('users_page.password_hint') }}</p>
                    <SelectField
                        v-model="form.locale"
                        name="locale"
                        :label="t('users_page.locale')"
                        :options="localeOptions"
                        :error="form.errors.locale"
                    />
                    <StatusSelect
                        v-model="form.status"
                        name="status"
                        :label="t('users_page.status')"
                        :options="statusOptions"
                        :error="form.errors.status || ''"
                        :searchable="false"
                    />
                </div>
            </section>

            <section v-if="can.assignRoles" class="pc-card">
                <div class="pc-card__head">
                    <h2 class="pc-card__title">{{ t('users_page.access_details') }}</h2>
                    <span class="pc-badge pc-badge--slate">{{ roleOptions.length }}</span>
                </div>
                <div class="pc-card__body pc-form-grid">
                    <MultiSelect
                        v-model="form.roles"
                        :label="t('users_page.roles')"
                        :options="roleOptions"
                        :error="form.errors.roles || ''"
                        :empty-text="tWebsite('ui.no_results')"
                        clearable
                    />
                    <p class="pc-hint pc-field--full">{{ t('users_page.technical_note') }}</p>
                </div>
            </section>

            <div class="pc-form-actions">
                <Button type="submit" :processing="form.processing">{{ t('create') }}</Button>
                <Link :href="localePath('/admin/users')" class="pc-btn pc-btn--ghost">{{ t('users_page.back_to_list') }}</Link>
            </div>
        </form>
    </div>
</template>
