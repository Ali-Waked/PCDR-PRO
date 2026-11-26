<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import Badge from '../../../Components/Ui/Badge.vue'
import TextField from '../../../Components/Ui/TextField.vue'
import Button from '../../../Components/Ui/Button.vue'
import Pagination from '../../../Components/Ui/Pagination.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import { useLocale } from '../../../Composables/useLocale'
import { usePermission } from '../../../Composables/usePermission'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'permissions_page.title',
        subtitleKey: 'permissions_page.subtitle',
    }, () => page),
})

const props = defineProps({
    permissions: { type: Object, required: true },
    filters: { type: Object, required: true },
})

const { t, localePath } = useLocale()
const { can } = usePermission()
const search = useForm({ search: props.filters.search ?? '' })
</script>

<template>
    <div class="pc-stack pcdr-enter">
        <Head :title="t('permissions_page.title')" />
        <div class="pc-page-actions">
            <Link v-if="can('permissions.create')" :href="localePath('/admin/permissions/create')" class="pc-btn pc-btn--primary">{{ t('permissions_page.new') }}</Link>
        </div>
        <Card>
            <form class="pc-form-grid" @submit.prevent="search.get(localePath('/admin/permissions'), { preserveState: true })">
                <TextField v-model="search.search" name="search" :label="t('search')" />
                <div class="pc-field" style="align-self: end"><Button type="submit" variant="secondary">{{ t('search') }}</Button></div>
            </form>
        </Card>
        <Card>
            <div class="pc-table-wrap">
                <table class="pc-table">
                    <thead>
                        <tr>
                            <th>{{ t('permissions_page.module') }}</th>
                            <th>{{ t('roles_page.key') }}</th>
                            <th>{{ t('users_page.name') }}</th>
                            <th>{{ t('permissions_page.protected') }}</th>
                            <th>{{ t('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="permission in permissions.data" :key="permission.id">
                            <td class="pc-directional">{{ permission.module }}</td>
                            <td class="pc-table__primary pc-directional">{{ permission.key }}</td>
                            <td>{{ permission.name }}</td>
                            <td><Badge v-if="permission.is_protected" tone="warning">{{ t('permissions_page.protected') }}</Badge><span v-else>—</span></td>
                            <td>
                                <div class="pc-table__actions">
                                    <Link :href="localePath(`/admin/permissions/${permission.id}/edit`)" class="pc-btn pc-btn--secondary pc-btn--sm">{{ t('edit') }}</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!permissions.data.length">
                            <td colspan="5"><EmptyState :title="t('no_results')" icon="shield" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :data="permissions" />
        </Card>
    </div>
</template>
