<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import TextField from '../../../Components/Ui/TextField.vue'
import Button from '../../../Components/Ui/Button.vue'
import Badge from '../../../Components/Ui/Badge.vue'
import FormSection from '../../../Components/Dashboard/FormSection.vue'
import { useLocale } from '../../../Composables/useLocale'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        title: page.props.permission?.name || '',
        subtitleKey: 'permissions_page.subtitle',
    }, () => page),
})

const props = defineProps({ permission: { type: Object, required: true } })
const { t, localePath } = useLocale()
const form = useForm({
    key: props.permission.key,
    name: props.permission.name,
    module: props.permission.module,
    description: props.permission.description ?? '',
})
</script>

<template>
    <Card>
        <Head :title="permission.name" />
        <form @submit.prevent="form.put(localePath(`/admin/permissions/${permission.id}`))">
            <FormSection :title="t('permissions_page.title')" :hint="t('permissions_page.subtitle')">
                <Badge v-if="permission.is_protected" tone="warning">{{ t('permissions_page.protected') }}</Badge>
                <div class="pc-stack">
                    <TextField v-model="form.key" name="key" :label="t('roles_page.key')" required :disabled="permission.is_protected" :error="form.errors.key" dir="ltr" />
                    <TextField v-model="form.name" name="name" :label="t('users_page.name')" required :error="form.errors.name" />
                    <TextField v-model="form.module" name="module" :label="t('permissions_page.module')" required :error="form.errors.module" dir="ltr" />
                    <TextField v-model="form.description" name="description" :label="t('roles_page.description')" :error="form.errors.description" />
                </div>
            </FormSection>
            <div class="pc-form-actions">
                <Button type="submit" :processing="form.processing">{{ t('save') }}</Button>
            </div>
        </form>
    </Card>
</template>
