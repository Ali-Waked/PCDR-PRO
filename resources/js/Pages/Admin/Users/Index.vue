<script setup>
import { computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import CmsPagination from '../../../Components/Dashboard/CmsPagination.vue'
import CmsStatusBadge from '../../../Components/Dashboard/CmsStatusBadge.vue'
import DataToolbar from '../../../Components/Dashboard/DataToolbar.vue'
import DataTable from '../../../Components/Dashboard/DataTable.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import MetricCard from '../../../Components/Dashboard/MetricCard.vue'
import PageHeader from '../../../Components/Dashboard/PageHeader.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useLocale } from '../../../Composables/useLocale'
import { useI18n } from '../../../composables/useI18n'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'users_page.title',
        subtitleKey: 'users_page.subtitle',
        eyebrowKey: 'users_page.eyebrow',
        showHeader: false,
    }, () => page),
})

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: {
        type: Object,
        default: () => ({ total: 0, active: 0, suspended: 0, owners: 0 }),
    },
    can: {
        type: Object,
        default: () => ({ create: false, update: false, delete: false, assignRoles: false, resetPassword: false }),
    },
})

const { t, localePath } = useLocale()
const { t: tWebsite, formatDate } = useI18n()
const { confirmDelete, confirmStatus, finishConfirmation } = useAdminNotifications()
const search = useForm({ search: props.filters?.search ?? '' })

const columns = computed(() => [
    { key: 'name', label: t('users_page.name') },
    { key: 'email', label: t('users_page.email') },
    { key: 'status', label: t('users_page.status') },
    { key: 'roles', label: t('users_page.roles') },
    { key: 'last_login', label: t('users_page.last_login') },
    { key: 'actions', label: t('users_page.actions'), align: 'end' },
])

function applySearch() {
    search.get(localePath('/admin/users'), { preserveState: true, preserveScroll: true })
}

function canManage(user) {
    return props.can.update && ! user.is_platform_owner && ! user.is_self
}

function statusMessage(user, status) {
    const label = status === 'active' ? t('users_page.status_active') : t('users_page.status_inactive')
    return `${tWebsite('ui.notification.confirm_status_message', '')} ${label} — ${user.name || `#${user.id}`}`.trim()
}

async function toggleStatus(user) {
    const status = user.status === 'active' ? 'inactive' : 'active'
    if (! (await confirmStatus(user.name || `#${user.id}`, { message: statusMessage(user, status) }))) {
        return
    }

    router.patch(localePath(`/admin/users/${user.id}/status`), { status }, {
        preserveScroll: true,
        onFinish: finishConfirmation,
    })
}

async function remove(user) {
    if (! (await confirmDelete(user.name || `#${user.id}`, { message: t('users_page.confirm_delete') }))) {
        return
    }

    router.delete(localePath(`/admin/users/${user.id}`), {
        preserveScroll: true,
        onFinish: finishConfirmation,
    })
}

function editUrl(user) {
    return localePath(`/admin/users/${user.id}/edit`)
}
</script>

<template>
    <div class="pc-stack pcdr-enter">
        <Head :title="t('users_page.title')" />

        <PageHeader
            :eyebrow="t('users_page.eyebrow')"
            :title="t('users_page.title')"
            :subtitle="t('users_page.subtitle')"
        >
            <template #actions>
                <Link v-if="can.create" :href="localePath('/admin/users/create')" class="pc-btn pc-btn--primary">
                    <UiIcon name="plus" :size="16" />
                    {{ t('users_page.new') }}
                </Link>
            </template>
        </PageHeader>

        <div class="pc-metric-grid">
            <MetricCard :label="t('users_page.total')" :value="counts.total ?? 0" icon="users" tone="sand" />
            <MetricCard :label="t('users_page.active')" :value="counts.active ?? 0" icon="check" />
            <MetricCard :label="t('users_page.suspended')" :value="counts.suspended ?? 0" icon="lock" tone="navy" />
            <MetricCard :label="t('users_page.owners')" :value="counts.owners ?? 0" icon="shield" />
        </div>

        <section class="pc-card" aria-labelledby="users-filters-title">
            <div class="pc-card__head">
                <h2 id="users-filters-title" class="pc-card__title">{{ t('users_page.search') }}</h2>
            </div>
            <form class="pc-card__body" @submit.prevent="applySearch">
                <DataToolbar
                    v-model:search="search.search"
                    :placeholder="t('users_page.search_placeholder')"
                    :summary="`${users.total ?? 0} ${t('users_page.total')}`"
                >
                    <button class="pc-btn pc-btn--secondary" type="submit">
                        <UiIcon name="search" :size="16" />
                        {{ t('search') }}
                    </button>
                </DataToolbar>
            </form>
        </section>

        <section class="pc-card" aria-labelledby="users-table-title">
            <div class="pc-card__head">
                <h2 id="users-table-title" class="pc-card__title">{{ t('users_page.title') }}</h2>
                <span class="pc-badge pc-badge--teal">{{ users.total ?? 0 }}</span>
            </div>
            <DataTable :columns="columns">
                <tr v-for="user in users.data" :key="user.id">
                    <td class="pc-table__primary">
                        <Link :href="localePath(`/admin/users/${user.id}`)" class="pc-link">{{ user.name }}</Link>
                        <span v-if="user.is_platform_owner" class="pc-badge pc-badge--sand">{{ t('users_page.platform_owner') }}</span>
                        <span v-if="user.is_self" class="pc-badge pc-badge--slate">{{ t('users_page.protected_self') }}</span>
                    </td>
                    <td class="pc-directional">{{ user.email }}</td>
                    <td><CmsStatusBadge :status="user.status" :label="t(`users_page.status_${user.status}`, user.status)" /></td>
                    <td class="pc-directional">{{ user.roles.join(', ') || t('users_page.no_roles') }}</td>
                    <td class="pc-list__meta">{{ formatDate(user.last_login_at) || '—' }}</td>
                    <td>
                        <div class="pc-table__actions">
                            <Link :href="localePath(`/admin/users/${user.id}`)" class="pc-btn pc-btn--secondary pc-btn--sm">
                                {{ t('users_page.view') }}
                            </Link>
                            <button
                                v-if="can.update"
                                class="pc-btn pc-btn--secondary pc-btn--sm"
                                type="button"
                                :disabled="!canManage(user)"
                                :title="user.is_platform_owner ? t('users_page.protected_owner') : t('users_page.protected_self')"
                                @click="router.visit(editUrl(user))"
                            >
                                {{ t('users_page.edit') }}
                            </button>
                            <button
                                v-if="can.update"
                                class="pc-btn pc-btn--ghost pc-btn--sm"
                                type="button"
                                :disabled="!canManage(user)"
                                :title="user.is_platform_owner ? t('users_page.protected_owner') : t('users_page.protected_self')"
                                @click="toggleStatus(user)"
                            >
                                {{ t('users_page.change_status') }}
                            </button>
                            <button
                                v-if="can.delete"
                                class="pc-btn pc-btn--danger pc-btn--sm"
                                type="button"
                                :disabled="user.is_platform_owner || user.is_self"
                                :title="user.is_platform_owner ? t('users_page.protected_owner') : t('users_page.protected_self')"
                                @click="remove(user)"
                            >
                                {{ t('users_page.delete') }}
                            </button>
                        </div>
                    </td>
                </tr>
                <template #empty>
                    <EmptyState :title="t('users_page.no_results')" :message="t('users_page.no_results_hint')" icon="users" />
                </template>
            </DataTable>
            <div class="pc-card__body">
                <CmsPagination :pagination="users" />
            </div>
        </section>

        <p class="pc-hint">{{ t('users_page.technical_note') }}</p>
    </div>
</template>
