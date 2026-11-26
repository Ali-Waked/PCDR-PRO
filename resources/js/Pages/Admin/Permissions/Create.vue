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
        titleKey: 'permissions_page.new',
        subtitleKey: 'permissions_page.subtitle',
    }, () => page),
})

const { t, localePath } = useLocale()
const form = useForm({ key: '', name: '', module: '', description: '' })
</script>

<template>
    <Card>
        <Head :title="t('permissions_page.new')" />
        <form @submit.prevent="form.post(localePath('/admin/permissions'))">
            <FormSection :title="t('permissions_page.new')" :hint="t('permissions_page.subtitle')">
                <div class="pc-stack">
                    <TextField v-model="form.key" name="key" :label="t('roles_page.key')" required :error="form.errors.key" dir="ltr" />
                    <TextField v-model="form.name" name="name" :label="t('users_page.name')" required :error="form.errors.name" />
                    <TextField v-model="form.module" name="module" :label="t('permissions_page.module')" required :error="form.errors.module" dir="ltr" />
                    <TextField v-model="form.description" name="description" :label="t('roles_page.description')" :error="form.errors.description" />
                </div>
            </FormSection>
            <div class="pc-form-actions">
                <Button type="submit" :processing="form.processing">{{ t('create') }}</Button>
            </div>
        </form>
    </Card>
</template>
