<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import DataTable from './DataTable.vue'
import EmptyState from './EmptyState.vue'
import UiIcon from './UiIcon.vue'
import { useLocale } from '../../Composables/useLocale.js'

const props = defineProps({
    activity: { type: Object, required: true },
})

const { t, locale } = useLocale()

const intlLocale = computed(() => (locale.value === 'ar' ? 'ar-EG' : 'en-GB'))

const columns = computed(() => [
    { key: 'action', label: t('account_profile.action') },
    { key: 'device', label: t('account_profile.device') },
    { key: 'ip', label: t('account_profile.ip_address') },
    { key: 'date', label: t('account_profile.date') },
])

function formatDateTime(value) {
    if (! value) {
        return '—'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    return new Intl.DateTimeFormat(intlLocale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function normalized(value) {
    return String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_')
}

function actionLabel(row) {
    if (row.label) {
        return String(row.label)
    }

    const key = normalized(row.action)
    return String(t(`account_activity.action_${key}`, t('account_activity.other')) || '')
}

function labelForDevice(value) {
    const key = normalized(value)
    if (! key) {
        return String(value || t('account_security.device_unknown') || '')
    }

    return String(t(`account_security.device_${key}`) || value || '')
}

function decode(value) {
    return String(value ?? '')
        .replaceAll('&laquo;', '«')
        .replaceAll('&raquo;', '»')
        .replaceAll('&amp;', '&')
        .replaceAll('&quot;', '"')
        .replaceAll('&#039;', "'")
}
</script>

<template>
    <div class="pc-stack">
        <div class="pc-card">
            <div class="pc-card__head">
                <div>
                    <h2 id="account-activity-title" class="pc-card__title">{{ t('account_activity.title') }}</h2>
                    <p class="pc-card__subtitle">{{ t('account_activity.subtitle') }}</p>
                </div>
                <UiIcon name="history" :size="18" />
            </div>

            <div class="pc-card__body--flush">
                <DataTable :columns="columns" :empty="!props.activity.data?.length">
                    <tr v-for="row in props.activity.data" :key="row.id">
                        <td>
                            <div class="pc-stack" style="gap: 2px">
                                <span>{{ actionLabel(row) }}</span>
                                <code class="pc-card__subtitle" dir="ltr">{{ row.action }}</code>
                            </div>
                        </td>
                        <td>{{ labelForDevice(row.device) }}</td>
                        <td dir="ltr">{{ row.ip_masked || '—' }}</td>
                        <td dir="ltr">{{ formatDateTime(row.created_at) }}</td>
                    </tr>
                    <template #empty>
                        <EmptyState :title="t('account_profile.no_activity')" :message="t('account_activity.subtitle')" icon="history" />
                    </template>
                </DataTable>

                <nav
                    v-if="props.activity.links?.length > 1"
                    class="pc-pagination__nav"
                    :aria-label="t('account_activity.title')"
                    style="padding: 14px 20px"
                >
                    <template v-for="(link, index) in props.activity.links" :key="index">
                        <span v-if="!link.url" class="pc-page-link is-disabled" aria-disabled="true">{{ decode(link.label) }}</span>
                        <Link
                            v-else
                            :href="link.url"
                            class="pc-page-link"
                            :class="{ 'is-active': link.active }"
                            :aria-current="link.active ? 'page' : undefined"
                            preserve-state
                            preserve-scroll
                            :only="['activity']"
                        >{{ decode(link.label) }}</Link>
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
