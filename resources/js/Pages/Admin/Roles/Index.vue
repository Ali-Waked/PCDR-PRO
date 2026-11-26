<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import Badge from '../../../Components/Ui/Badge.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import { useLocale } from '../../../Composables/useLocale'
import { usePermission } from '../../../Composables/usePermission'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'roles_page.title',
        subtitleKey: 'roles_page.subtitle',
    }, () => page),
})

defineProps({ roles: { type: Array, required: true } })

const { t, localePath } = useLocale()
const { can } = usePermission()
</script>

<template>
    <div class="pc-stack pcdr-enter">
        <Head :title="t('roles_page.title')" />
        <div class="pc-page-actions">
            <Link v-if="can('roles.create')" :href="localePath('/admin/roles/create')" class="pc-btn pc-btn--primary">{{ t('roles_page.new') }}</Link>
        </div>
        <Card>
            <div class="pc-table-wrap">
                <table class="pc-table">
                    <thead>
                        <tr>
                            <th>{{ t('roles_page.key') }}</th>
                            <th>{{ t('roles_page.description') }}</th>
                            <th>{{ t('roles_page.permissions') }}</th>
                            <th>{{ t('roles_page.members') }}</th>
                            <th>{{ t('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles" :key="role.id">
                            <td class="pc-table__primary pc-directional">
                                {{ role.key }} <Badge v-if="role.is_system" class="ms-1">system</Badge>
                            </td>
                            <td>{{ role.description || '—' }}</td>
                            <td class="pc-directional">{{ role.permissions_count }}</td>
                            <td class="pc-directional">{{ role.users_count }}</td>
                            <td>
                                <div class="pc-table__actions">
                                    <Link :href="localePath(`/admin/roles/${role.id}/edit`)" class="pc-btn pc-btn--secondary pc-btn--sm">{{ t('edit') }}</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!roles.length">
                            <td colspan="5"><EmptyState :title="t('no_results')" icon="shield" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</template>
