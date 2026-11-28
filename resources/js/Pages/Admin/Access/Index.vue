<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import Card from '../../../Components/Ui/Card.vue'
import Button from '../../../Components/Ui/Button.vue'
import TextField from '../../../Components/Ui/TextField.vue'
import MetricCard from '../../../Components/Dashboard/MetricCard.vue'
import DataTable from '../../../Components/Dashboard/DataTable.vue'
import MultiSelect from '../../../Components/Selection/MultiSelect.vue'
import StatusBadge from '../../../Components/Dashboard/StatusBadge.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import { useLocale } from '../../../Composables/useLocale'
import { usePermission } from '../../../Composables/usePermission'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'access_page.title',
        subtitleKey: 'access_page.subtitle',
        eyebrowKey: 'access_page.eyebrow',
        showHeader: false,
    }, () => page),
})

const props = defineProps({
    roles: { type: Array, required: true },
    catalog: { type: Array, required: true },
    stats: { type: Object, required: true },
})

const page = usePage()
const { t, locale, direction, localePath } = useLocale()
const { can } = usePermission()
const { notify, confirmDelete, finishConfirmation } = useAdminNotifications()

const activeTab = ref('roles')
const roleSearch = ref('')
const permissionSearch = ref('')
const drawerOpen = ref(false)
const drawerRef = ref(null)
const editingRole = ref(null)
const expandedRoleId = ref(null)
let previousFocus = null

const details = useForm({
    key: '',
    name: '',
    description: '',
    permissions: [],
})

const roleColumns = computed(() => [
    { key: 'role', label: t('access_page.role') },
    { key: 'type', label: t('access_page.type') },
    { key: 'permissions', label: t('access_page.permissions') },
    { key: 'members', label: t('access_page.members') },
    { key: 'actions', label: t('access_page.actions'), align: 'end' },
])

const allCatalogItems = computed(() => props.catalog.flatMap((group) => group.items))
const assignableIds = computed(() => allCatalogItems.value.filter((item) => item.assignable).map((item) => item.id))
const selectedCount = computed(() => details.permissions.length)

const permissionOptions = computed(() => props.catalog.flatMap((group) => group.items.map((item) => ({
    value: item.id,
    label: localizedName(item),
    description: localizedDescription(item),
    group: localizedCategory(group),
    badge: item.protected ? t('access_page.protected') : '',
    disabled: !item.assignable,
    testId: 'permission-option',
    dataKey: item.key,
}))))

const filteredRoles = computed(() => {
    const query = roleSearch.value.trim().toLowerCase()
    if (!query) {
        return props.roles
    }

    return props.roles.filter((role) => `${role.name} ${role.key}`.toLowerCase().includes(query))
})

function permissionMatches(item, query) {
    return `${item.name} ${item.name_ar} ${item.key} ${item.description} ${item.description_ar}`
        .toLowerCase()
        .includes(query)
}

const filteredCatalog = computed(() => {
    const query = permissionSearch.value.trim().toLowerCase()

    return props.catalog
        .map((group) => ({
            ...group,
            items: query ? group.items.filter((item) => permissionMatches(item, query)) : group.items,
        }))
        .filter((group) => group.items.length > 0)
})

const selectedSummary = computed(() => props.catalog
    .map((group) => ({
        ...group,
        items: group.items.filter((item) => details.permissions.includes(item.id)),
    }))
    .filter((group) => group.items.length > 0))

function localizedName(item) {
    return locale.value === 'ar' ? (item.name_ar || item.name) : item.name
}

function localizedDescription(item) {
    return locale.value === 'ar' ? (item.description_ar || item.description) : item.description
}

function localizedCategory(group) {
    return locale.value === 'ar' ? group.label.ar : group.label.en
}

function selectedCountLabel() {
    return t('access_page.selected_count').replace(':count', String(selectedCount.value))
}

function openCreate() {
    editingRole.value = null
    details.reset()
    details.clearErrors()
    drawerOpen.value = true
}

function openEdit(role) {
    editingRole.value = role
    details.reset({
        key: role.key,
        name: role.name,
        description: role.description ?? '',
        permissions: [...(role.permissionIds ?? [])],
    })
    details.clearErrors()
    drawerOpen.value = true
}

function closeDrawer() {
    drawerOpen.value = false
    editingRole.value = null
    details.reset()
    details.clearErrors()
}

function selectAllPermissions() {
    details.permissions = [...assignableIds.value]
}

function clearPermissions() {
    details.permissions = []
}

function saveRole() {
    const payload = {
        key: details.key,
        name: details.name,
        description: details.description,
        permissions: details.permissions,
    }

    if (editingRole.value) {
        details.put(localePath(`/admin/roles/${editingRole.value.id}`), {
            ...payload,
            preserveScroll: true,
            onSuccess: () => closeDrawer(),
        })
        return
    }

    details.post(localePath('/admin/roles'), {
        ...payload,
        preserveScroll: true,
        onSuccess: () => closeDrawer(),
    })
}

async function deleteRole(role) {
    if (role.is_system) {
        notify.warning(t('access_page.system_delete_blocked'))
        return
    }

    const accepted = await confirmDelete(role.name, {
        title: t('access_page.delete_title'),
        message: t('access_page.delete_message'),
    })

    if (!accepted) {
        return
    }

    router.delete(localePath(`/admin/roles/${role.id}`), {
        preserveScroll: true,
        onFinish: finishConfirmation,
    })
}

function openFromQuery() {
    const query = new URLSearchParams((page.url || '').split('?')[1] ?? '')
    if (query.get('create') === '1') {
        openCreate()
        return
    }

    const roleId = Number(query.get('role'))
    const role = props.roles.find((item) => item.id === roleId)
    if (query.get('edit') === '1' && role) {
        openEdit(role)
    }
}

function onDrawerKeydown(event) {
    if (!drawerOpen.value) {
        return
    }

    if (event.key === 'Escape') {
        event.preventDefault()
        closeDrawer()
        return
    }

    if (event.key !== 'Tab' || !drawerRef.value) {
        return
    }

    const focusables = [...drawerRef.value.querySelectorAll(
        'button:not([disabled]),input:not([disabled]),[href],[tabindex]:not([tabindex="-1"])',
    )]

    if (!focusables.length) {
        return
    }

    const first = focusables[0]
    const last = focusables[focusables.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

onMounted(() => {
    openFromQuery()
})

watch(drawerOpen, (open) => {
    if (open) {
        previousFocus = document.activeElement
        document.addEventListener('keydown', onDrawerKeydown)
        document.body.style.overflow = 'hidden'
        nextTick(() => drawerRef.value?.querySelector('input,button')?.focus?.())
        return
    }

    document.removeEventListener('keydown', onDrawerKeydown)
    document.body.style.overflow = ''
    previousFocus?.focus?.()
    previousFocus = null
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onDrawerKeydown)
    document.body.style.overflow = ''
})
</script>

<template>
    <div class="pc-stack pcdr-enter access-page" :dir="direction">
        <Head :title="t('access_page.title')" />

        <header class="pc-access-header">
            <div class="pc-access-header__copy">
                <span class="pc-eyebrow">{{ t('access_page.eyebrow') }}</span>
                <h1 class="pc-page-title">{{ t('access_page.title') }}</h1>
                <p class="pc-page-subtitle">{{ t('access_page.subtitle') }}</p>
                <p class="pc-access-note">
                    <UiIcon name="info" :size="14" />
                    {{ t('access_page.technical_note') }}
                </p>
            </div>
            <div v-if="can('roles.create')" class="pc-page-actions">
                <Button type="button" @click="openCreate">
                    <UiIcon name="plus" :size="16" />
                    {{ t('access_page.new_role') }}
                </Button>
            </div>
        </header>

        <div class="pc-metric-grid">
            <MetricCard :label="t('access_page.total_roles')" :value="stats.total_roles" icon="users" tone="navy" />
            <MetricCard :label="t('access_page.system_roles')" :value="stats.system_roles" icon="shield" tone="sand" />
            <MetricCard :label="t('access_page.custom_roles')" :value="stats.custom_roles" icon="layers" tone="teal" />
            <MetricCard :label="t('access_page.available_permissions')" :value="stats.available_permissions" icon="lock" tone="teal" />
        </div>

        <div class="pc-access-tabs" role="tablist" :aria-label="t('access_page.title')">
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'roles'"
                :class="{ 'is-active': activeTab === 'roles' }"
                @click="activeTab = 'roles'"
            >
                <UiIcon name="users" :size="16" />
                {{ t('access_page.roles') }}
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'catalog'"
                :class="{ 'is-active': activeTab === 'catalog' }"
                @click="activeTab = 'catalog'"
            >
                <UiIcon name="lock" :size="16" />
                {{ t('access_page.catalog') }}
            </button>
        </div>

        <Card v-show="activeTab === 'roles'">
            <div class="pc-access-section-head">
                <div>
                    <h2 class="pc-card__title">{{ t('access_page.roles_title') }}</h2>
                    <p class="pc-page-subtitle">{{ t('access_page.roles_hint') }}</p>
                </div>
                <label class="pc-search pc-access-search-field">
                    <UiIcon name="search" :size="16" />
                    <span class="sr-only">{{ t('access_page.search_roles') }}</span>
                    <input v-model="roleSearch" type="search" :placeholder="t('access_page.search_roles')">
                </label>
            </div>

            <div class="access-roles-table">
                <DataTable :columns="roleColumns" :empty="!filteredRoles.length">
                    <tr v-for="role in filteredRoles" :key="role.id">
                        <td class="pc-table__primary">
                            <strong>{{ role.name }}</strong>
                            <span class="pc-table__secondary pc-directional">{{ role.key }}</span>
                        </td>
                        <td>
                            <StatusBadge
                                :label="role.is_system ? t('access_page.system') : t('access_page.custom')"
                                :tone="role.is_system ? 'slate' : 'teal'"
                            />
                        </td>
                        <td class="pc-directional">{{ role.permissions_count }}</td>
                        <td class="pc-directional">{{ role.users_count }}</td>
                        <td>
                            <div class="pc-table__actions">
                                <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" @click="expandedRoleId = expandedRoleId === role.id ? null : role.id">
                                    {{ t('access_page.view_details') }}
                                </button>
                                <button
                                    v-if="can('roles.update')"
                                    type="button"
                                    class="pc-btn pc-btn--secondary pc-btn--sm"
                                    @click="openEdit(role)"
                                >
                                    {{ t('access_page.edit') }}
                                </button>
                                <button
                                    v-if="can('roles.delete') && !role.is_system"
                                    type="button"
                                    class="pc-btn pc-btn--danger pc-btn--sm"
                                    @click="deleteRole(role)"
                                >
                                    {{ t('access_page.delete') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <template #empty>
                        <EmptyState :title="t('access_page.no_roles')" icon="shield" />
                    </template>
                </DataTable>
            </div>

            <div class="access-roles-cards">
                <article v-for="role in filteredRoles" :key="`card-${role.id}`" class="pc-access-role-card">
                    <div class="pc-access-role-card__head">
                        <strong>{{ role.name }}</strong>
                        <StatusBadge
                            :label="role.is_system ? t('access_page.system') : t('access_page.custom')"
                            :tone="role.is_system ? 'slate' : 'teal'"
                        />
                    </div>
                    <p class="pc-access-role-card__key pc-directional">{{ role.key }}</p>
                    <div class="pc-access-role-card__stats">
                        <span>{{ t('access_page.permissions') }}: {{ role.permissions_count }}</span>
                        <span>{{ t('access_page.members') }}: {{ role.users_count }}</span>
                    </div>
                    <div class="pc-access-role-card__actions">
                        <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" @click="expandedRoleId = expandedRoleId === role.id ? null : role.id">
                            {{ t('access_page.view_details') }}
                        </button>
                        <button v-if="can('roles.update')" type="button" class="pc-btn pc-btn--secondary pc-btn--sm" @click="openEdit(role)">
                            {{ t('access_page.edit') }}
                        </button>
                        <button
                            v-if="can('roles.delete') && !role.is_system"
                            type="button"
                            class="pc-btn pc-btn--danger pc-btn--sm"
                            @click="deleteRole(role)"
                        >
                            {{ t('access_page.delete') }}
                        </button>
                    </div>
                </article>
                <EmptyState v-if="!filteredRoles.length" :title="t('access_page.no_roles')" icon="shield" />
            </div>

            <div v-if="expandedRoleId" class="pc-access-detail">
                <template v-for="role in filteredRoles.filter((item) => item.id === expandedRoleId)" :key="`detail-${role.id}`">
                    <div>
                        <h3 class="pc-card__title">{{ role.name }}</h3>
                        <p class="pc-page-subtitle">{{ role.description || '—' }}</p>
                    </div>
                    <div>
                        <strong>{{ t('access_page.members') }}</strong>
                        <ul v-if="role.users.length" class="pc-list">
                            <li v-for="member in role.users" :key="member.id" class="pc-list__item">
                                <span class="pc-list__title">{{ member.name }}</span>
                                <span class="pc-list__meta pc-directional">{{ member.email }}</span>
                            </li>
                        </ul>
                        <p v-else class="pc-hint">{{ t('access_page.members_none') }}</p>
                    </div>
                </template>
            </div>
        </Card>

        <Card v-show="activeTab === 'catalog'" data-testid="permission-catalog">
            <div class="pc-access-section-head">
                <div>
                    <h2 class="pc-card__title">{{ t('access_page.catalog_title') }}</h2>
                    <p class="pc-page-subtitle">{{ t('access_page.catalog_hint') }}</p>
                </div>
                <div class="pc-access-search">
                    <UiIcon name="lock" :size="15" />
                    <span class="pc-badge pc-badge--slate">{{ t('access_page.read_only') }}</span>
                </div>
            </div>

            <label class="pc-search pc-access-search-field pc-access-search-field--full">
                <UiIcon name="search" :size="16" />
                <span class="sr-only">{{ t('access_page.search_permissions') }}</span>
                <input v-model="permissionSearch" type="search" :placeholder="t('access_page.search_permissions')">
            </label>

            <div class="pc-access-catalog-grid">
                <section v-for="group in filteredCatalog" :key="group.key" class="pc-access-catalog-group">
                    <header class="pc-access-catalog-group__head">
                        <UiIcon name="shield" :size="16" />
                        <h3>{{ localizedCategory(group) }}</h3>
                        <span class="pc-badge pc-badge--teal">{{ group.items.length }}</span>
                    </header>
                    <ul class="pc-access-permission-list">
                        <li v-for="item in group.items" :key="item.key" class="pc-access-permission" data-testid="permission-catalog-item" :data-key="item.key">
                            <div class="pc-access-permission__head">
                                <strong>{{ localizedName(item) }}</strong>
                                <StatusBadge
                                    v-if="item.protected"
                                    :label="t('access_page.protected')"
                                    tone="amber"
                                />
                            </div>
                            <p>{{ localizedDescription(item) }}</p>
                            <div class="pc-access-permission__meta pc-directional">
                                <span>{{ item.key }}</span>
                                <span class="pc-badge" :class="item.assignable ? 'pc-badge--green' : 'pc-badge--slate'">
                                    {{ item.assignable ? t('access_page.assignable') : t('access_page.not_assignable') }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
            <EmptyState v-if="!filteredCatalog.length" :title="t('access_page.no_permissions')" icon="lock" />
        </Card>

        <div v-if="drawerOpen" class="pc-modal-backdrop" @click.self="closeDrawer">
            <div
                ref="drawerRef"
                class="pc-modal pc-modal--wide pc-access-drawer"
                role="dialog"
                aria-modal="true"
                :aria-label="editingRole ? t('access_page.edit') : t('access_page.create')"
                tabindex="-1"
                data-testid="role-drawer"
            >
                <div class="pc-modal__head">
                    <div>
                        <h2 class="pc-modal__title">{{ editingRole ? t('access_page.edit') : t('access_page.create') }}</h2>
                        <p class="pc-page-subtitle">{{ t('access_page.subtitle') }}</p>
                    </div>
                    <button type="button" class="pc-icon-button" :aria-label="t('access_page.close')" @click="closeDrawer">
                        <UiIcon name="close" :size="16" />
                    </button>
                </div>

                <form class="pc-access-drawer__form" @submit.prevent="saveRole">
                    <div class="pc-modal__body">
                        <div class="pc-form-grid pc-form-grid--2">
                            <TextField
                                v-model="details.key"
                                name="key"
                                :label="t('access_page.role_key')"
                                required
                                dir="ltr"
                                :disabled="Boolean(editingRole?.is_system)"
                                :error="details.errors.key"
                            />
                            <TextField
                                v-model="details.name"
                                name="name"
                                :label="t('access_page.name')"
                                required
                                :error="details.errors.name"
                            />
                        </div>
                        <div class="pc-field pc-field--full">
                            <label class="pc-label" for="role-description">{{ t('access_page.description') }}</label>
                            <textarea
                                id="role-description"
                                v-model="details.description"
                                class="pcdr-input"
                                rows="3"
                                maxlength="2000"
                            />
                            <p v-if="details.errors.description" class="pc-error" role="alert">{{ details.errors.description }}</p>
                        </div>

                        <div class="pc-access-permission-toolbar">
                            <div>
                                <h3 class="pc-card__title">{{ t('access_page.select_permissions') }}</h3>
                                <p class="pc-hint">{{ selectedCountLabel() }} · {{ t('access_page.code_owned') }}</p>
                            </div>
                            <div class="pc-access-permission-toolbar__actions">
                                <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" @click="selectAllPermissions">
                                    {{ t('access_page.select_all') }}
                                </button>
                                <button type="button" class="pc-btn pc-btn--ghost pc-btn--sm" @click="clearPermissions">
                                    {{ t('access_page.clear_selection') }}
                                </button>
                            </div>
                        </div>
                        <p v-if="details.errors.permissions" class="pc-error" role="alert">{{ details.errors.permissions }}</p>

                        <MultiSelect
                            v-model="details.permissions"
                            :label="t('access_page.select_permissions')"
                            :options="permissionOptions"
                            :error="details.errors.permissions || ''"
                            :empty-text="t('no_results')"
                            :chip-limit="5"
                            clearable
                        />

                        <section class="pc-access-summary" aria-live="polite">
                            <h3 class="pc-card__title">{{ t('access_page.summary') }}</h3>
                            <p class="pc-hint">{{ selectedCountLabel() }}</p>
                            <ul v-if="selectedSummary.length" class="pc-access-summary__list">
                                <li v-for="group in selectedSummary" :key="`summary-${group.key}`">
                                    <strong>{{ localizedCategory(group) }}:</strong>
                                    {{ group.items.map((item) => localizedName(item)).join('، ') }}
                                </li>
                            </ul>
                            <p v-else class="pc-hint">{{ t('access_page.no_permissions_selected') }}</p>
                        </section>
                    </div>

                    <div class="pc-modal__foot">
                        <Button type="button" variant="secondary" @click="closeDrawer">{{ t('access_page.cancel') }}</Button>
                        <Button type="submit" :processing="details.processing">
                            {{ editingRole ? t('access_page.save') : t('access_page.create') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.access-page {
    gap: 18px;
}

.pc-access-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
}

.pc-access-header__copy {
    max-inline-size: 760px;
}

.pc-access-note {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin: 10px 0 0;
    color: var(--pc-muted);
    font-size: 0.82rem;
}

.pc-access-tabs {
    display: inline-flex;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--pc-border);
    border-radius: 12px;
    background: var(--pc-surface-2);
}

.pc-access-tabs button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
    padding: 0 14px;
    border: 0;
    border-radius: 9px;
    background: transparent;
    color: var(--pc-muted);
    font: inherit;
    font-size: 0.86rem;
    font-weight: 650;
    cursor: pointer;
}

.pc-access-tabs button.is-active {
    background: var(--pc-surface);
    color: var(--pc-text);
    box-shadow: var(--pc-shadow-sm);
}

.pc-access-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-block-end: 16px;
}

.pc-access-search {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 12px;
    border: 1px solid var(--pc-border);
    border-radius: 10px;
    background: var(--pc-surface);
    color: var(--pc-muted);
}

.pc-access-search-field {
    flex: 0 1 260px;
    margin: 0;
}

.pc-access-search-field--full {
    flex: 1 1 100%;
    max-inline-size: none;
    margin-block-end: 16px;
}

.access-roles-cards {
    display: none;
    gap: 12px;
}

.pc-access-role-card {
    padding: 16px;
    border: 1px solid var(--pc-border);
    border-radius: 14px;
    background: var(--pc-surface-2);
}

.pc-access-role-card__head,
.pc-access-role-card__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.pc-access-role-card__key {
    margin: 6px 0 12px;
    color: var(--pc-muted);
    font-size: 0.78rem;
}

.pc-access-role-card__stats {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-block-end: 14px;
    color: var(--pc-muted);
    font-size: 0.82rem;
}

.pc-access-role-card__actions {
    justify-content: flex-start;
}

.pc-access-detail {
    display: grid;
    gap: 18px;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    margin-block-start: 18px;
    padding: 18px;
    border: 1px solid var(--pc-border);
    border-radius: 14px;
    background: var(--pc-surface-2);
}

.pc-access-catalog-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
}

.pc-access-catalog-group {
    padding: 16px;
    border: 1px solid var(--pc-border);
    border-radius: 14px;
    background: var(--pc-surface-2);
}

.pc-access-catalog-group__head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-block-end: 12px;
}

.pc-access-catalog-group__head h3 {
    margin: 0;
    font-size: 0.96rem;
}

.pc-access-permission-list,
.pc-access-summary__list,
.pc-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.pc-access-permission {
    padding: 12px 0;
    border-block-start: 1px solid var(--pc-border);
}

.pc-access-permission:first-child {
    border-block-start: 0;
}

.pc-access-permission__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.88rem;
}

.pc-access-permission p {
    margin: 6px 0;
    color: var(--pc-muted);
    font-size: 0.78rem;
    line-height: 1.6;
}

.pc-access-permission__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: var(--pc-muted);
    font-size: 0.72rem;
}

.pc-access-drawer__form {
    display: flex;
    flex-direction: column;
    min-block-size: 0;
}

.pc-access-permission-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-block-start: 18px;
    padding-block-start: 16px;
    border-block-start: 1px solid var(--pc-border);
}

.pc-access-permission-toolbar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.pc-access-summary {
    margin-block-start: 18px;
    padding: 14px;
    border: 1px dashed var(--pc-border);
    border-radius: 12px;
    background: var(--pc-surface-2);
}

.pc-access-summary__list {
    display: grid;
    gap: 6px;
    margin-block-start: 8px;
    font-size: 0.8rem;
    line-height: 1.6;
}

@media (max-width: 820px) {
    .pc-access-header,
    .pc-access-section-head,
    .pc-access-permission-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .access-roles-table {
        display: none;
    }

    .access-roles-cards {
        display: grid;
    }

    .pc-access-detail {
        grid-template-columns: minmax(0, 1fr);
    }

    .pc-access-search-field {
        flex: 1 1 100%;
    }

    .pc-access-catalog-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
