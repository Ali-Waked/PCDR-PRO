<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import TextField from '../../../Components/Ui/TextField.vue'
import Button from '../../../Components/Ui/Button.vue'
import FormSection from '../../../Components/Dashboard/FormSection.vue'
import { useLocale } from '../../../Composables/useLocale'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'roles_page.new',
        subtitleKey: 'roles_page.subtitle',
    }, () => page),
})

const { t, localePath } = useLocale()
const form = useForm({ key: '', name: '', description: '' })
</script>

<template>
    <Card>
        <Head :title="t('roles_page.new')" />
        <form @submit.prevent="form.post(localePath('/admin/roles'))">
            <FormSection :title="t('roles_page.new')" :hint="t('roles_page.permissions')">
                <div class="pc-stack">
                    <TextField v-model="form.key" name="key" :label="t('roles_page.key')" required :error="form.errors.key" dir="ltr" />
                    <TextField v-model="form.name" name="name" :label="t('users_page.name')" required :error="form.errors.name" />
                    <TextField v-model="form.description" name="description" :label="t('roles_page.description')" :error="form.errors.description" />
                </div>
            </FormSection>
            <div class="pc-form-actions">
                <Button type="submit" :processing="form.processing">{{ t('create') }}</Button>
            </div>
        </form>
    </Card>
</template>
