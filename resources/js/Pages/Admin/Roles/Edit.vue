<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import TextField from '../../../Components/Ui/TextField.vue'
import Button from '../../../Components/Ui/Button.vue'
import Badge from '../../../Components/Ui/Badge.vue'
import MultiSelect from '../../../Components/Selection/MultiSelect.vue'
import FormSection from '../../../Components/Dashboard/FormSection.vue'
import { useLocale } from '../../../Composables/useLocale'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        title: page.props.role?.name || '',
        subtitleKey: 'roles_page.subtitle',
    }, () => page),
})

const props = defineProps({
    role: { type: Object, required: true },
    permissionGroups: { type: Array, default: () => [] },
})

const { t, localePath } = useLocale()
const details = useForm({ key: props.role.key, name: props.role.name, description: props.role.description ?? '' })
const permissions = useForm({ permissions: props.role.permissionIds ?? [] })
const protectedCount = computed(() => props.permissionGroups.flatMap((group) => group.items).filter((item) => item.is_protected).length)

const permissionOptions = computed(() => props.permissionGroups.flatMap((group) => group.items.map((item) => ({
    value: item.id,
    label: item.key,
    group: group.module,
    badge: item.is_protected ? t('permissions_page.protected') : '',
}))))
</script>

<template>
    <div class="pc-grid pc-grid--sidebar">
        <Head :title="role.name" />
        <Card>
            <form @submit.prevent="details.put(localePath(`/admin/roles/${role.id}`))">
                <FormSection :title="t('roles_page.new')" :hint="t('roles_page.key')">
                    <div class="pc-stack">
                        <TextField v-model="details.key" name="key" :label="t('roles_page.key')" required :disabled="role.is_system" :error="details.errors.key" dir="ltr" />
                        <TextField v-model="details.name" name="name" :label="t('users_page.name')" required :error="details.errors.name" />
                        <TextField v-model="details.description" name="description" :label="t('roles_page.description')" :error="details.errors.description" />
                        <Badge v-if="role.is_system" tone="slate">system</Badge>
                    </div>
                </FormSection>
                <div class="pc-form-actions">
                    <Button type="submit" :processing="details.processing">{{ t('save') }}</Button>
                </div>
            </form>
        </Card>

        <Card>
            <form @submit.prevent="permissions.put(localePath(`/admin/roles/${role.id}/permissions`))">
                <FormSection :title="t('roles_page.permissions')" :hint="`${protectedCount} ${t('permissions_page.protected')}`">
                    <div class="pc-stack">
                        <MultiSelect
                            v-model="permissions.permissions"
                            :label="t('roles_page.permissions')"
                            :options="permissionOptions"
                            :error="permissions.errors.permissions || ''"
                            :empty-text="t('no_results')"
                            :chip-limit="5"
                            clearable
                        />
                    </div>
                </FormSection>
                <div class="pc-form-actions">
                    <Button type="submit" :processing="permissions.processing">{{ t('save') }}</Button>
                </div>
            </form>
            <div class="pc-form-section">
                <h3 class="pc-card__title">{{ t('roles_page.members') }}</h3>
                <ul class="pc-list">
                    <li v-for="member in role.users" :key="member.id" class="pc-list__item">
                        <span class="pc-list__title">{{ member.name }}</span>
                        <span class="pc-list__meta pc-directional">{{ member.email }}</span>
                    </li>
                    <li v-if="!role.users.length" class="pc-list__item">{{ t('no_results') }}</li>
                </ul>
            </div>
        </Card>
    </div>
</template>
