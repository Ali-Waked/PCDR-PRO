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
import { useAdminNotifications } from '../../../composables/useAdminNotifications'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'users_page.edit',
        subtitle: page.props.target?.name || '',
        eyebrowKey: 'users_page.eyebrow',
    }, () => page),
})

const props = defineProps({
    target: { type: Object, required: true },
    availableRoles: { type: Array, default: () => [] },
    can: {
        type: Object,
        default: () => ({ create: false, update: false, delete: false, assignRoles: false, resetPassword: false }),
    },
    isSelf: { type: Boolean, default: false },
    isProtected: { type: Boolean, default: false },
})

const { t, localePath } = useLocale()
const { t: tWebsite } = useI18n()
const { confirmStatus } = useAdminNotifications()

const profileLocked = computed(() => props.isSelf || props.isProtected || ! props.can.update)
const rolesLocked = computed(() => props.isSelf || props.isProtected || ! props.can.assignRoles)
const protectedMessage = computed(() => props.isProtected ? t('users_page.protected_owner') : t('users_page.protected_self'))

const form = useForm({
    name: props.target.name,
    email: props.target.email,
    status: props.target.status,
    locale: props.target.locale,
})
const roles = useForm({ roles: props.target.roleIds ?? [] })

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

async function saveProfile() {
    if (profileLocked.value || ! form.isDirty) {
        return
    }

    if (form.status !== props.target.status) {
        const label = statusOptions.value.find((option) => option.value === form.status)?.label ?? form.status
        const message = `${tWebsite('ui.notification.confirm_status_message', '')} ${label} — ${form.name}`.trim()
        if (! (await confirmStatus(form.name, { message }))) {
            return
        }
    }

    form.put(localePath(`/admin/users/${props.target.id}`))
}

function saveRoles() {
    if (! rolesLocked.value) {
        roles.put(localePath(`/admin/users/${props.target.id}/roles`))
    }
}
</script>

<template>
    <div class="pc-stack pcdr-enter">
        <Head :title="t('users_page.edit')" />

        <div v-if="isProtected || isSelf" class="pc-callout pc-callout--warning" role="status">
            <UiIcon name="lock" :size="17" />
            <span>{{ protectedMessage }}</span>
        </div>

        <form class="pc-stack" @submit.prevent="saveProfile">
            <section class="pc-card">
                <div class="pc-card__head">
                    <div>
                        <h2 class="pc-card__title">{{ t('users_page.account_details') }}</h2>
                        <p class="pc-card__subtitle">{{ target.email }}</p>
                    </div>
                    <span class="pc-badge pc-badge--slate">#{{ target.id }}</span>
                </div>
                <div class="pc-card__body pc-form-grid">
                    <TextField v-model="form.name" name="name" :label="t('users_page.name')" required :disabled="profileLocked" :error="form.errors.name" />
                    <TextField v-model="form.email" name="email" :label="t('users_page.email')" type="email" required :disabled="profileLocked" :error="form.errors.email" />
                    <SelectField
                        v-model="form.locale"
                        name="locale"
                        :label="t('users_page.locale')"
                        :options="localeOptions"
                        :disabled="profileLocked"
                        :error="form.errors.locale"
                    />
                    <StatusSelect
                        v-model="form.status"
                        name="status"
                        :label="t('users_page.status')"
                        :options="statusOptions"
                        :disabled="profileLocked"
                        :error="form.errors.status || ''"
                        :searchable="false"
                    />
                </div>
                <div class="pc-card__body pc-form-actions">
                    <Button type="submit" :processing="form.processing" :disabled="profileLocked || !form.isDirty">{{ t('save') }}</Button>
                    <Link :href="localePath('/admin/users')" class="pc-btn pc-btn--ghost">{{ t('users_page.back_to_list') }}</Link>
                </div>
            </section>
        </form>

        <form class="pc-stack" @submit.prevent="saveRoles">
            <section class="pc-card">
                <div class="pc-card__head">
                    <div>
                        <h2 class="pc-card__title">{{ t('users_page.access_details') }}</h2>
                        <p class="pc-card__subtitle">{{ t('users_page.technical_note') }}</p>
                    </div>
                    <span class="pc-badge pc-badge--teal">{{ roles.roles.length }}</span>
                </div>
                <div class="pc-card__body pc-form-grid">
                    <MultiSelect
                        v-model="roles.roles"
                        :label="t('users_page.roles')"
                        :options="roleOptions"
                        :disabled="rolesLocked"
                        :error="roles.errors.roles || ''"
                        :empty-text="tWebsite('ui.no_results')"
                        clearable
                    />
                </div>
                <div class="pc-card__body pc-form-actions">
                    <Button type="submit" variant="secondary" :processing="roles.processing" :disabled="rolesLocked || !roles.isDirty">
                        {{ t('users_page.assign_roles') }}
                    </Button>
                </div>
            </section>
        </form>
    </div>
</template>
