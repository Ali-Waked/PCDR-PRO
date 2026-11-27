<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    pages: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
})

const { locale, localPath } = useI18n()
const { confirm } = useAdminNotifications()

const canUpdate = computed(() => props.can?.update === true)
const canManageContent = computed(() => props.can?.contentUpdate === true)

const copy = computed(() => locale.value === 'ar' ? {
    title: 'إدارة محتوى الموقع',
    subtitle: 'تحكم واحد في ظهور الصفحات والأقسام، وفي المحتوى المعروض في الموقع.',
    hint: 'إخفاء أي عنصر يحفظ محتواه بالكامل؛ إظهاره مرة أخرى يستعيد العرض دون فقدان البيانات.',
    page: 'الصفحة',
    path: 'المسار',
    pageVisibility: 'ظهور الصفحة',
    sections: 'الأقسام',
    sectionVisibility: 'ظهور القسم',
    records: 'السجلات',
    manage: 'إدارة المحتوى',
    visible: 'ظاهر',
    hidden: 'مخفي',
    moveUp: 'تحريك للأعلى',
    moveDown: 'تحريك للأسفل',
    hideTitle: 'إخفاء العنصر؟',
    hideMessage: 'سيختفي العنصر من الموقع العام وسيبقى محتواه محفوظاً بالكامل.',
    confirmHide: 'إخفاء',
    cancel: 'إلغاء',
    noSections: 'لا توجد أقسام معرفة لهذه الصفحة.',
} : {
    title: 'Content Management',
    subtitle: 'One place to control page visibility, section visibility and homepage display.',
    hint: 'Hiding anything keeps its content intact. Re-enabling restores presentation without data loss.',
    page: 'Page',
    path: 'Path',
    pageVisibility: 'Page visibility',
    sections: 'Sections',
    sectionVisibility: 'Section visibility',
    records: 'Records',
    manage: 'Manage content',
    visible: 'Visible',
    hidden: 'Hidden',
    moveUp: 'Move up',
    moveDown: 'Move down',
    hideTitle: 'Hide this item?',
    hideMessage: 'The item will disappear from the public website while all of its content is preserved.',
    confirmHide: 'Hide',
    cancel: 'Cancel',
    noSections: 'No predefined sections belong to this page.',
})

async function setVisibility(url, current, enabled, event) {
    if (!canUpdate.value) {
        return
    }

    if (!enabled && await confirm({
        title: copy.value.hideTitle,
        message: copy.value.hideMessage,
        confirmLabel: copy.value.confirmHide,
        cancelLabel: copy.value.cancel,
        danger: true,
    }) === false) {
        if (event?.target) {
            event.target.checked = current
        }

        return
    }

    router.patch(url, { enabled }, { preserveScroll: true })
}

function moveSection(page, index, direction) {
    if (!canUpdate.value) {
        return
    }

    const target = index + direction
    if (target < 0 || target >= page.sections.length) {
        return
    }

    const order = page.sections.map((section, position) => ({
        id: section.id,
        display_order: position === index
            ? page.sections[target].order
            : position === target
                ? section.order === page.sections[target].order
                    ? page.sections[index].order
                    : section.order
                : section.order,
    }))

    router.post(localPath('cms/content-management/sections/reorder'), { order }, { preserveScroll: true })
}
</script>

<template>
    <CmsDashboardLayout
        :title="copy.title"
        :subtitle="copy.subtitle"
        :hint="copy.hint"
    >
        <div class="pc-stack">
            <section v-for="page in pages" :key="page.key" class="pc-card">
                <header class="pc-card__head">
                    <div>
                        <h2 class="pc-card__title">{{ page.name }}</h2>
                        <p class="pc-hint"><code>{{ page.path }}</code></p>
                    </div>
                    <label class="pc-toggle">
                        <input
                            type="checkbox"
                            :checked="page.enabled"
                            :disabled="!canUpdate"
                            @change="setVisibility(
                                localPath(`cms/content-management/pages/${page.key}/toggle`),
                                page.enabled,
                                $event.target.checked,
                                $event,
                            )"
                        >
                        <span>{{ page.enabled ? copy.visible : copy.hidden }}</span>
                    </label>
                </header>

                <div v-if="page.sections.length" class="pc-table-wrap">
                    <table class="pc-table">
                        <thead>
                            <tr>
                                <th>{{ copy.sections }}</th>
                                <th>{{ copy.records }}</th>
                                <th>{{ copy.sectionVisibility }}</th>
                                <th class="is-end">{{ copy.manage }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(section, index) in page.sections" :key="section.id">
                                <td>
                                    <strong>{{ section.name }}</strong>
                                    <span class="pc-hint"><code>{{ section.key }}</code></span>
                                </td>
                                <td>{{ section.recordCount }}</td>
                                <td>
                                    <label class="pc-toggle">
                                        <input
                                            type="checkbox"
                                            :checked="section.enabled"
                                            :disabled="!canUpdate"
                                            @change="setVisibility(
                                                localPath(`cms/content-management/sections/${section.key}/toggle`),
                                                section.enabled,
                                                $event.target.checked,
                                                $event,
                                            )"
                                        >
                                        <span>{{ section.enabled ? copy.visible : copy.hidden }}</span>
                                    </label>
                                    <div class="pc-row-actions">
                                        <button
                                            type="button"
                                            class="pc-btn pc-btn--ghost pc-btn--xs"
                                            :disabled="!canUpdate || index === 0"
                                            :aria-label="copy.moveUp"
                                            @click="moveSection(page, index, -1)"
                                        >↑</button>
                                        <button
                                            type="button"
                                            class="pc-btn pc-btn--ghost pc-btn--xs"
                                            :disabled="!canUpdate || index === page.sections.length - 1"
                                            :aria-label="copy.moveDown"
                                            @click="moveSection(page, index, 1)"
                                        >↓</button>
                                    </div>
                                </td>
                                <td class="is-end">
                                    <Link
                                        v-if="canManageContent"
                                        class="pc-btn pc-btn--secondary pc-btn--sm"
                                        :href="localPath(`cms/content/${section.key}`)"
                                    >{{ copy.manage }}</Link>
                                    <span v-else class="pc-hint">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="pc-empty">{{ copy.noSections }}</p>
            </section>
        </div>
    </CmsDashboardLayout>
</template>
