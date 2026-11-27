<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import UiIcon from './Dashboard/UiIcon.vue'
import SelectionControl from './Selection/SelectionControl.vue'
import { useI18n } from '../composables/useI18n'
import {
    buildPageItems,
    buildPageUrl,
    formatCount,
    normalizePaginator,
    pageUrlFor,
    renderSummary,
} from '../Support/pagination'

const props = defineProps({
    pagination: { type: Object, default: () => ({}) },
    data: { type: Object, default: null },
    paginator: { type: Object, default: null },
    links: { type: Array, default: null },
    from: { type: [Number, String], default: null },
    to: { type: [Number, String], default: null },
    total: { type: [Number, String], default: null },
    summary: { type: String, default: '' },
    pageKey: { type: String, default: 'page' },
    currentPage: { type: [Number, String], default: null },
    lastPage: { type: [Number, String], default: null },
    perPage: { type: [Number, String], default: null },
    perPageOptions: { type: Array, default: () => [] },
    showSummary: { type: Boolean, default: true },
    variant: { type: String, default: 'dashboard' },
    preserveState: { type: Boolean, default: true },
    replace: { type: Boolean, default: false },
})

const emit = defineEmits(['update:perPage'])

const { t, page, locale, direction } = useI18n()

const currentUrl = computed(() => {
    if (page.url) {
        return page.url
    }
    if (typeof window === 'undefined') {
        return '/'
    }
    return `${window.location.pathname}${window.location.search}${window.location.hash}`
})

const source = computed(() => {
    if (props.pagination && Object.keys(props.pagination).length > 0) {
        return props.pagination
    }
    if (props.data && Object.keys(props.data).length > 0) {
        return props.data
    }
    if (props.paginator && Object.keys(props.paginator).length > 0) {
        return props.paginator
    }

    return {}
})

const state = computed(() => normalizePaginator(source.value, {
    pageKey: props.pageKey,
    links: props.links,
    from: props.from,
    to: props.to,
    total: props.total,
    currentPage: props.currentPage,
    lastPage: props.lastPage,
    perPage: props.perPage,
}))

const items = computed(() => buildPageItems(state.value.currentPage, state.value.lastPage).map((item) => {
    if (item.type === 'ellipsis') {
        return item
    }

    return {
        ...item,
        url: pageUrlFor(state.value, item.number, currentUrl.value),
        mobilePrimary: item.number === 1
            || item.number === state.value.lastPage
            || item.number === state.value.currentPage,
    }
}))

const previousUrl = computed(() => {
    if (state.value.currentPage <= 1) {
        return null
    }
    if (state.value.previousUrl) {
        return state.value.previousUrl
    }
    return pageUrlFor(state.value, state.value.currentPage - 1, currentUrl.value)
})

const nextUrl = computed(() => {
    if (state.value.currentPage >= state.value.lastPage) {
        return null
    }
    if (state.value.nextUrl) {
        return state.value.nextUrl
    }
    return pageUrlFor(state.value, state.value.currentPage + 1, currentUrl.value)
})

const summaryText = computed(() => {
    if (props.summary) {
        return props.summary
    }
    if (state.value.total === null && state.value.from === null && state.value.to === null) {
        return ''
    }

    const fallback = locale.value === 'ar'
        ? 'عرض :from إلى :to من :total'
        : 'Showing :from–:to of :total'

    return renderSummary(t('ui.pagination.showing', fallback), {
        from: state.value.from ?? 0,
        to: state.value.to ?? 0,
        total: state.value.total ?? 0,
        locale: locale.value,
    })
})

const previousLabel = computed(() => t('ui.pagination.previous', t('ui.dashboard_previous', 'Previous')))
const nextLabel = computed(() => t('ui.pagination.next', t('ui.dashboard_next', 'Next')))
const navLabel = computed(() => t('ui.pagination.label', 'Pagination'))
const perPageLabel = computed(() => t('ui.pagination.per_page', 'Rows per page'))
const ellipsisLabel = computed(() => t('ui.pagination.ellipsis', 'Skipped pages'))

function pageLabel(number) {
    const template = t('ui.pagination.page', 'Page :page of :total')
    return String(template)
        .replaceAll(':page', formatCount(number, locale.value))
        .replaceAll(':total', formatCount(state.value.lastPage, locale.value))
}

function onPerPageChange(value) {
    const parsed = Number(value)
    if (!props.perPageOptions.includes(parsed)) {
        return
    }

    emit('update:perPage', parsed)
    router.visit(buildPageUrl(currentUrl.value, 1, state.value.pageKey, { per_page: parsed }), {
        preserveScroll: true,
        preserveState: props.preserveState,
        replace: props.replace,
    })
}
</script>

<template>
    <div
        v-if="state.hasPages || perPageOptions.length || summaryText"
        class="pc-pagination"
        :class="{ 'pc-pagination--public': variant === 'public' }"
        :dir="direction"
    >
        <p v-if="showSummary && summaryText" class="pc-pagination__summary">{{ summaryText }}</p>

        <div class="pc-pagination__controls">
            <div v-if="perPageOptions.length" class="pc-pagination__per-page">
                <SelectionControl
                    :model-value="state.perPage ?? perPage ?? ''"
                    :options="perPageOptions.map((option) => ({
                        value: option,
                        label: formatCount(option, locale),
                    }))"
                    :label="perPageLabel"
                    :searchable="false"
                    :clearable="false"
                    variant="dashboard"
                    size="sm"
                    @change="onPerPageChange"
                />
            </div>

            <nav class="pc-pagination__nav" :aria-label="navLabel">
                <Link
                    v-if="previousUrl"
                    :href="previousUrl"
                    class="pc-page-link pc-page-link--nav"
                    preserve-scroll
                    :preserve-state="preserveState"
                    :replace="replace"
                    :aria-label="previousLabel"
                >
                    <UiIcon name="chevronBack" :size="15" />
                    <span class="pc-pagination__nav-label">{{ previousLabel }}</span>
                </Link>
                <span v-else class="pc-page-link pc-page-link--nav is-disabled" aria-disabled="true">
                    <UiIcon name="chevronBack" :size="15" />
                    <span class="pc-pagination__nav-label">{{ previousLabel }}</span>
                </span>

                <template v-for="item in items" :key="item.key ?? item.number">
                    <span
                        v-if="item.type === 'ellipsis'"
                        class="pc-pagination__ellipsis"
                        :class="{ 'pc-page-item--optional': !item.mobilePrimary }"
                        :aria-label="ellipsisLabel"
                    >…</span>
                    <span
                        v-else-if="item.number === state.currentPage"
                        class="pc-page-link is-active"
                        :class="{ 'pc-page-item--optional': !item.mobilePrimary }"
                        aria-current="page"
                        :aria-label="pageLabel(item.number)"
                    >{{ formatCount(item.number, locale) }}</span>
                    <Link
                        v-else
                        :href="item.url"
                        class="pc-page-link"
                        :class="{ 'pc-page-item--optional': !item.mobilePrimary }"
                        :aria-label="pageLabel(item.number)"
                        preserve-scroll
                        :preserve-state="preserveState"
                        :replace="replace"
                    >{{ formatCount(item.number, locale) }}</Link>
                </template>

                <Link
                    v-if="nextUrl"
                    :href="nextUrl"
                    class="pc-page-link pc-page-link--nav"
                    preserve-scroll
                    :preserve-state="preserveState"
                    :replace="replace"
                    :aria-label="nextLabel"
                >
                    <span class="pc-pagination__nav-label">{{ nextLabel }}</span>
                    <UiIcon name="chevronForward" :size="15" />
                </Link>
                <span v-else class="pc-page-link pc-page-link--nav is-disabled" aria-disabled="true">
                    <span class="pc-pagination__nav-label">{{ nextLabel }}</span>
                    <UiIcon name="chevronForward" :size="15" />
                </span>
            </nav>
        </div>
    </div>
</template>
