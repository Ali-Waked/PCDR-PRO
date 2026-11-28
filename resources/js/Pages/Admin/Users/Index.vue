<script setup>
import { computed, watch } from 'vue'
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
    statusOptions: { type: Array, default: () => [] },
    roleOptions: { type: Array, default: () => [] },
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
const search = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    role: props.filters?.role ?? '',
    sort: props.filters?.sort ?? 'created_at',
    direction: props.filters?.direction ?? 'desc',
})

watch(() => props.filters, (filters) => {
    search.search = filters?.search ?? ''
    search.status = filters?.status ?? ''
    search.role = filters?.role ?? ''
    search.sort = filters?.sort ?? 'created_at'
    search.direction = filters?.direction ?? 'desc'
}, { deep: true })

const columns = computed(() => [
    { key: 'name', label: t('users_page.name') },
    { key: 'email', label: t('users_page.email') },
    { key: 'status', label: t('users_page.status') },
    { key: 'roles', label: t('users_page.roles') },
    { key: 'last_login', label: t('users_page.last_login') },
    { key: 'actions', label: t('users_page.actions'), align: 'end' },
])

const sortOptions = computed(() => [
    { value: 'created_at:desc', label: t('users_page.sort_created_desc') },
    { value: 'created_at:asc', label: t('users_page.sort_created_asc') },
    { value: 'name:asc', label: t('users_page.sort_name_asc') },
    { value: 'name:desc', label: t('users_page.sort_name_desc') },
    { value: 'status:asc', label: t('users_page.sort_status_asc') },
    { value: 'status:desc', label: t('users_page.sort_status_desc') },
])

const sortValue = computed({
    get: () => `${search.sort}:${search.direction}`,
    set: (value) => {
        [search.sort, search.direction] = String(value).split(':')
        applyFilters()
    },
})

const resultCount = computed(() => props.users?.data?.length ?? 0)
const totalAccounts = computed(() => props.counts?.total ?? 0)
const currentPage = computed(() => Number(props.users?.current_page ?? 1))
const lastPage = computed(() => Number(props.users?.last_page ?? 1))
const hasFilters = computed(() => Boolean(search.search || search.status || search.role))
const invalidPage = computed(() => resultCount.value === 0 && totalAccounts.value > 0 && currentPage.value > lastPage.value)

const emptyState = computed(() => {
    if (invalidPage.value) {
        return {
            title: t('users_page.invalid_page'),
            message: t('users_page.invalid_page_hint'),
            icon: 'refresh',
            reset: true,
            create: false,
        }
    }

    if (totalAccounts.value === 0) {
        return {
            title: t('users_page.no_users_title'),
            message: t('users_page.no_users_hint'),
            icon: 'users',
            reset: false,
            create: props.can?.create === true,
        }
    }

    if (! hasFilters.value) {
        return {
            title: t('users_page.no_results'),
            message: t('users_page.no_results_hint'),
            icon: 'users',
            reset: false,
            create: props.can?.create === true,
        }
    }

    const onlyFilters = ! search.search && Boolean(search.status || search.role)

    return {
        title: onlyFilters ? t('users_page.filter_no_results') : t('users_page.search_no_results'),
        message: onlyFilters ? t('users_page.filter_no_results_hint') : t('users_page.search_no_results_hint'),
        icon: onlyFilters ? 'layers' : 'search',
        reset: true,
        create: false,
    }
})

function applyFilters() {
    search.get(localePath('/admin/users'), { preserveState: true, preserveScroll: true })
}

function clearFilters() {
    search.search = ''
    search.status = ''
    search.role = ''
    search.sort = 'created_at'
    search.direction = 'desc'
    applyFilters()
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
            <form class="pc-card__body pc-users-filters" @submit.prevent="applyFilters">
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
                <div class="pc-users-filters__row">
                    <label class="pc-field">
                        <span class="pc-label">{{ t('users_page.status_filter') }}</span>
                        <select v-model="search.status" class="pc-input" @change="applyFilters">
                            <option value="">{{ t('users_page.all_statuses') }}</option>
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="pc-field">
                        <span class="pc-label">{{ t('users_page.role_filter') }}</span>
                        <select v-model="search.role" class="pc-input" @change="applyFilters">
                            <option value="">{{ t('users_page.all_roles') }}</option>
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="pc-field">
                        <span class="pc-label">{{ t('users_page.sort_by') }}</span>
                        <select v-model="sortValue" class="pc-input">
                            <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <button v-if="hasFilters" class="pc-btn pc-btn--ghost" type="button" @click="clearFilters">
                        <UiIcon name="refresh" :size="15" />
                        {{ t('users_page.reset_filters') }}
                    </button>
                </div>
            </form>
        </section>

        <section class="pc-card" aria-labelledby="users-table-title">
            <div class="pc-card__head">
                <h2 id="users-table-title" class="pc-card__title">{{ t('users_page.title') }}</h2>
                <span class="pc-badge pc-badge--teal">{{ users.total ?? 0 }}</span>
            </div>
            <DataTable :columns="columns" :empty="!resultCount">
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
                    <EmptyState :title="emptyState.title" :message="emptyState.message" :icon="emptyState.icon">
                        <div class="pc-empty__actions">
                            <button
                                v-if="emptyState.reset"
                                class="pc-btn pc-btn--secondary pc-btn--sm"
                                type="button"
                                @click="clearFilters"
                            >
                                <UiIcon name="refresh" :size="14" />
                                {{ t('users_page.reset_filters') }}
                            </button>
                            <Link
                                v-if="emptyState.create"
                                :href="localePath('/admin/users/create')"
                                class="pc-btn pc-btn--primary pc-btn--sm"
                            >
                                <UiIcon name="plus" :size="14" />
                                {{ t('users_page.new') }}
                            </Link>
                        </div>
                    </EmptyState>
                </template>
            </DataTable>
            <div class="pc-card__body">
                <CmsPagination :pagination="users" />
            </div>
        </section>

        <p class="pc-hint">{{ t('users_page.technical_note') }}</p>
    </div>
</template>
