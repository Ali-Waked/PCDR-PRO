<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '../../../Layouts/AppLayout.vue'
import CmsStatusBadge from '../../../Components/Dashboard/CmsStatusBadge.vue'
import EmptyState from '../../../Components/Dashboard/EmptyState.vue'
import UiIcon from '../../../Components/Dashboard/UiIcon.vue'
import { useLocale } from '../../../Composables/useLocale'
import { useI18n } from '../../../composables/useI18n'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'

defineOptions({
    layout: (h, page) => h(AppLayout, {
        titleKey: 'users_page.view',
        subtitle: page.props.target?.name || '',
        eyebrowKey: 'users_page.eyebrow',
    }, () => page),
})

const props = defineProps({
    target: { type: Object, required: true },
    can: {
        type: Object,
        default: () => ({ create: false, update: false, delete: false, assignRoles: false, resetPassword: false }),
    },
    isSelf: { type: Boolean, default: false },
    isProtected: { type: Boolean, default: false },
})

const { t, localePath } = useLocale()
const { formatDate } = useI18n()
const { confirm, confirmDelete, finishConfirmation } = useAdminNotifications()

const protectedMessage = computed(() => props.isProtected ? t('users_page.protected_owner') : t('users_page.protected_self'))
const canManage = computed(() => props.can.update && ! props.isSelf && ! props.isProtected)
const canAssign = computed(() => props.can.assignRoles && ! props.isSelf && ! props.isProtected)
const canReset = computed(() => props.can.resetPassword && ! props.isSelf && ! props.isProtected)
const canDelete = computed(() => props.can.delete && ! props.isSelf && ! props.isProtected)

async function sendResetLink() {
    if (! canReset.value) {
        return
    }

    const accepted = await confirm({
        title: t('users_page.reset_password'),
        message: t('users_page.reset_password_hint'),
        confirmLabel: t('users_page.reset_password'),
    })
    if (! accepted) {
        return
    }

    router.post(localePath(`/admin/users/${props.target.id}/reset-password`), {}, {
        preserveScroll: true,
        onFinish: finishConfirmation,
    })
}

async function remove() {
    if (! canDelete.value) {
        return
    }

    const accepted = await confirmDelete(props.target.name || `#${props.target.id}`, {
        message: t('users_page.confirm_delete'),
    })
    if (! accepted) {
        return
    }

    router.delete(localePath(`/admin/users/${props.target.id}`), {
        preserveScroll: true,
        onFinish: finishConfirmation,
    })
}
</script>

<template>
    <div class="pc-stack pcdr-enter">
        <Head :title="target.name" />

        <div class="pc-page-actions">
            <Link :href="localePath('/admin/users')" class="pc-btn pc-btn--ghost">
                <UiIcon name="chevronBack" :size="16" />
                {{ t('users_page.back_to_list') }}
            </Link>
        </div>

        <div v-if="isSelf || isProtected" class="pc-callout pc-callout--warning" role="status">
            <UiIcon name="lock" :size="17" />
            <span>{{ protectedMessage }}</span>
        </div>

        <section class="pc-card" aria-labelledby="account-summary-title">
            <div class="pc-card__head">
                <div>
                    <h2 id="account-summary-title" class="pc-card__title">{{ t('users_page.account_details') }}</h2>
                    <p class="pc-card__subtitle">#{{ target.id }}</p>
                </div>
                <CmsStatusBadge :status="target.status" :label="t(`users_page.status_${target.status}`, target.status)" />
            </div>
            <div class="pc-card__body pc-stack">
                <dl class="pc-detail-list">
                    <div class="pc-detail-list__item">
                        <dt>{{ t('users_page.name') }}</dt>
                        <dd>{{ target.name }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('users_page.email') }}</dt>
                        <dd class="pc-directional">{{ target.email }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('users_page.locale') }}</dt>
                        <dd>{{ t(`users_page.locale_${target.locale}`, target.locale) }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('users_page.created_at') }}</dt>
                        <dd>{{ formatDate(target.created_at) || '—' }}</dd>
                    </div>
                    <div class="pc-detail-list__item">
                        <dt>{{ t('users_page.last_login') }}</dt>
                        <dd>{{ formatDate(target.last_login_at) || '—' }}</dd>
                    </div>
                </dl>

                <div v-if="target.is_platform_owner" class="pc-callout pc-callout--sand" role="note">
                    <UiIcon name="shield" :size="16" />
                    <span>{{ t('users_page.platform_owner') }}</span>
                </div>

                <div class="pc-table__actions">
                    <button
                        v-if="can.update"
                        class="pc-btn pc-btn--secondary pc-btn--sm"
                        type="button"
                        :disabled="!canManage"
                        :title="canManage ? t('users_page.edit') : protectedMessage"
                        @click="router.visit(localePath(`/admin/users/${target.id}/edit`))"
                    >
                        <UiIcon name="edit" :size="14" />
                        {{ t('users_page.edit') }}
                    </button>
                    <button
                        v-if="can.resetPassword"
                        class="pc-btn pc-btn--ghost pc-btn--sm"
                        type="button"
                        :disabled="!canReset"
                        :title="canReset ? t('users_page.reset_password') : protectedMessage"
                        @click="sendResetLink"
                    >
                        <UiIcon name="mail" :size="14" />
                        {{ t('users_page.reset_password') }}
                    </button>
                    <button
                        v-if="can.delete"
                        class="pc-btn pc-btn--danger pc-btn--sm"
                        type="button"
                        :disabled="!canDelete"
                        :title="canDelete ? t('users_page.delete') : protectedMessage"
                        @click="remove"
                    >
                        <UiIcon name="trash" :size="14" />
                        {{ t('users_page.delete') }}
                    </button>
                </div>
                <p class="pc-hint">{{ t('users_page.reset_password_hint') }}</p>
            </div>
        </section>

        <section class="pc-card" aria-labelledby="account-roles-title">
            <div class="pc-card__head">
                <div>
                    <h2 id="account-roles-title" class="pc-card__title">{{ t('users_page.access_details') }}</h2>
                    <p class="pc-card__subtitle">{{ t('users_page.technical_note') }}</p>
                </div>
                <span class="pc-badge pc-badge--teal">{{ target.roles.length }} {{ t('users_page.role_count') }}</span>
            </div>
            <div class="pc-card__body pc-stack">
                <EmptyState
                    v-if="!target.roles.length"
                    :title="t('users_page.no_roles')"
                    :message="t('users_page.technical_note')"
                    icon="users"
                />
                <ul v-else class="pc-list">
                    <li v-for="role in target.roles" :key="role.id" class="pc-list__item">
                        <span class="pc-list__title">{{ role.name }}</span>
                        <code class="pc-list__meta" dir="ltr">{{ role.key }}</code>
                    </li>
                </ul>
                <div class="pc-table__actions">
                    <button
                        v-if="can.assignRoles"
                        class="pc-btn pc-btn--secondary pc-btn--sm"
                        type="button"
                        :disabled="!canAssign"
                        :title="canAssign ? t('users_page.assign_roles') : protectedMessage"
                        @click="router.visit(localePath(`/admin/users/${target.id}/edit`))"
                    >
                        <UiIcon name="shield" :size="14" />
                        {{ t('users_page.assign_roles') }}
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
