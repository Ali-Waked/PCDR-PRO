<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
import ContentMediaField from '../../../Components/Cms/ContentMediaField.vue'
import { useAdminNotifications } from '../../../composables/useAdminNotifications'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    locale: { type: String, default: 'ar' },
    section: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    media: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    organization: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
})

const { localPath } = useI18n()
const { confirm } = useAdminNotifications()
const locales = ['ar', 'en']
const editing = ref(null)

const canUpdate = computed(() => props.can?.update === true)
const isLinks = computed(() => props.section.content === 'links')
const isInline = computed(() => !isLinks.value && props.section.content !== 'organization')
const isOrganization = computed(() => props.section.content === 'organization')
const isStories = computed(() => props.section.key === 'stories')
const isFooter = computed(() => props.section.key === 'footer')

const copy = computed(() => props.locale === 'ar' ? {
    title: props.section.name,
    subtitle: 'إدارة سجلات هذا القسم المعرّف مسبقاً. كل تعديل يُحفظ في مصدر المحتوى نفسه.',
    hint: 'إخفاء السجل يحفظ بياناته؛ يمكنك إظهاره مرة أخرى في أي وقت.',
    add: 'إضافة سجل',
    edit: 'تعديل',
    remove: 'حذف',
    cancelEdit: 'إلغاء التعديل',
    save: 'حفظ',
    record: 'سجل',
    records: 'السجلات',
    enabled: 'مظهور',
    disabled: 'مخفي',
    titleField: 'العنوان',
    labelField: 'النص القصير',
    bodyField: 'النص',
    summaryField: 'الملخص',
    altField: 'النص البديل للصورة',
    urlField: 'الرابط',
    ctaField: 'نص الرابط',
    dateField: 'تاريخ العرض',
    captionField: 'وصف الصورة',
    displayField: 'القيمة المعروضة',
    unitField: 'الوحدة',
    media: 'الوسائط من المكتبة',
    source: 'السجل المرتبط',
    parent: 'الصورة الأم',
    tab: 'التبويب',
    motif: 'الرمز',
    featured: 'مميز',
    sort: 'الترتيب',
    startAt: 'بداية العرض',
    endAt: 'نهاية العرض',
    visibility: 'الظهور',
    organizationTitle: 'اسم الجهة',
    description: 'الوصف',
    visionTitle: 'عنوان الرؤية',
    vision: 'الرؤية',
    missionTitle: 'عنوان الرسالة',
    mission: 'الرسالة',
    destinationUrl: 'رابط الوجهة',
    logo: 'الشعار',
    empty: 'لا توجد سجلات في هذا القسم بعد.',
    noSources: 'لا توجد سجلات متاحة للاختيار في الوحدة المرتبطة.',
    hideTitle: 'إخفاء هذا السجل؟',
    hideMessage: 'سيختفي السجل من العرض العام وتبقى بياناته محفوظة.',
    deleteTitle: 'حذف هذا السجل؟',
    deleteMessage: 'سيتم حذف السجل وترجماته. استخدم الإخفاء إذا كنت تريد الاحتفاظ به.',
    confirmHide: 'إخفاء',
    confirmDelete: 'حذف',
    cancel: 'إلغاء',
    moveUp: 'تحريك للأعلى',
    moveDown: 'تحريك للأسفل',
    mediaLabels: {
        select: 'اختيار صورة', change: 'تغيير الصورة', remove: 'إزالة',
        title: 'مكتبة الوسائط', insert: 'إدراج', empty: 'لا توجد صور', close: 'إغلاق',
    },
} : {
    title: props.section.name,
    subtitle: 'Manage the predefined records of this section. Every change writes to the same public content source.',
    hint: 'Hiding a record preserves it. You can show it again at any time.',
    add: 'Add record',
    edit: 'Edit',
    remove: 'Delete',
    cancelEdit: 'Cancel edit',
    save: 'Save',
    record: 'Record',
    records: 'Records',
    enabled: 'Visible',
    disabled: 'Hidden',
    titleField: 'Title',
    labelField: 'Short label',
    bodyField: 'Body',
    summaryField: 'Summary',
    altField: 'Image alt text',
    urlField: 'URL',
    ctaField: 'CTA label',
    dateField: 'Display date',
    captionField: 'Caption',
    displayField: 'Display value',
    unitField: 'Unit',
    media: 'Media library image',
    source: 'Linked record',
    parent: 'Parent image',
    tab: 'Tab',
    motif: 'Motif',
    featured: 'Featured',
    sort: 'Order',
    startAt: 'Display from',
    endAt: 'Display until',
    visibility: 'Visibility',
    organizationTitle: 'Organization name',
    description: 'Description',
    visionTitle: 'Vision title',
    vision: 'Vision',
    missionTitle: 'Mission title',
    mission: 'Mission',
    destinationUrl: 'Destination URL',
    logo: 'Logo',
    empty: 'No records in this section yet.',
    noSources: 'No linked records are available in the source module.',
    hideTitle: 'Hide this record?',
    hideMessage: 'The record will disappear from the public website while its data is preserved.',
    deleteTitle: 'Delete this record?',
    deleteMessage: 'The record and its translations will be deleted. Use hide if you want to keep it.',
    confirmHide: 'Hide',
    confirmDelete: 'Delete',
    cancel: 'Cancel',
    moveUp: 'Move up',
    moveDown: 'Move down',
    mediaLabels: {
        select: 'Choose image', change: 'Change image', remove: 'Remove',
        title: 'Media library', insert: 'Insert', empty: 'No images', close: 'Close',
    },
})

function emptyTranslation() {
    return {
        label: '', title: '', body: '', summary: '', alt_text: '', display_value: '',
        url: '', date_label: '', cta_label: '', caption: '', unit: '',
    }
}

function emptyForm() {
    return {
        item_key: '',
        item_type: isLinks.value ? (props.section.source || 'article') : 'inline',
        item_id: '',
        parent_item_id: '',
        media_id: '',
        tab: '',
        is_featured: false,
        is_published: true,
        motif_key: '',
        display_start_at: '',
        display_end_at: '',
        sort_order: props.items.length,
        translations: { ar: emptyTranslation(), en: emptyTranslation() },
    }
}

const form = useForm(emptyForm())

const organizationForm = useForm({
    logo_media_id: props.organization?.logoMediaId ?? '',
    destination_url: props.organization?.destinationUrl ?? '',
    translations: {
        ar: organizationTranslation('ar'),
        en: organizationTranslation('en'),
    },
})

function organizationTranslation(locale) {
    const values = props.organization?.translations?.[locale] ?? {}

    return {
        title: values.title ?? '',
        description: values.description ?? '',
        vision_title: values.vision_title ?? '',
        vision: values.vision ?? '',
        mission_title: values.mission_title ?? '',
        mission: values.mission ?? '',
    }
}

function editItem(item) {
    editing.value = item.id
    form.reset({
        item_key: item.itemKey ?? '',
        item_type: item.itemType ?? (isLinks.value ? props.section.source : 'inline'),
        item_id: item.itemId ?? '',
        parent_item_id: item.parentItemId ?? '',
        media_id: item.mediaId ?? '',
        tab: item.tab ?? '',
        is_featured: Boolean(item.featured),
        is_published: item.enabled !== false,
        motif_key: item.motifKey ?? '',
        display_start_at: item.displayStartAt ? String(item.displayStartAt).slice(0, 10) : '',
        display_end_at: item.displayEndAt ? String(item.displayEndAt).slice(0, 10) : '',
        sort_order: item.sortOrder ?? 0,
        translations: {
            ar: { ...emptyTranslation(), ...(item.translations?.ar ?? {}) },
            en: { ...emptyTranslation(), ...(item.translations?.en ?? {}) },
        },
    })
}

function cancelEdit() {
    editing.value = null
    form.reset(emptyForm())
}

function submitItem() {
    const base = localPath(`cms/content/${props.section.key}`)

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!editing.value) {
                cancelEdit()
            }
        },
    }

    if (editing.value !== null) {
        form.put(`${base}/${editing.value}`, options)
    } else {
        form.post(base, options)
    }
}

async function destroyItem(item) {
    if (!canUpdate.value || !await confirm({
        title: copy.value.deleteTitle,
        message: copy.value.deleteMessage,
        confirmLabel: copy.value.confirmDelete,
        cancelLabel: copy.value.cancel,
        danger: true,
    })) {
        return
    }

    router.delete(localPath(`cms/content/${props.section.key}/${item.id}`), { preserveScroll: true })
}

async function toggleItem(item, event) {
    const enabled = Boolean(event.target.checked)

    if (!enabled && !await confirm({
        title: copy.value.hideTitle,
        message: copy.value.hideMessage,
        confirmLabel: copy.value.confirmHide,
        cancelLabel: copy.value.cancel,
        danger: true,
    })) {
        event.target.checked = !enabled
        return
    }

    router.patch(localPath(`cms/content/${props.section.key}/${item.id}/toggle`), { enabled }, { preserveScroll: true })
}

function moveItem(index, direction) {
    const target = index + direction
    if (target < 0 || target >= props.items.length || !canUpdate.value) {
        return
    }

    const order = props.items.map((item, position) => ({
        id: item.id,
        sort_order: position === index
            ? props.items[target].sortOrder
            : position === target
                ? (item.sortOrder === props.items[target].sortOrder ? props.items[index].sortOrder : item.sortOrder)
                : item.sortOrder,
    }))

    router.post(localPath(`cms/content/${props.section.key}/reorder`), { order }, { preserveScroll: true })
}

function submitOrganization() {
    organizationForm.put(localPath(`cms/content/${props.section.key}/organization`), { preserveScroll: true })
}

const parentOptions = computed(() => props.items.filter((item) => !item.parentItemId))
const visibleFields = computed(() => ({
    title: props.section.key !== 'albums',
    label: ['slides', 'objectives'].includes(props.section.key) || isFooter.value,
    body: ['slides', 'stories', 'objectives'].includes(props.section.key) || isFooter.value,
    summary: props.section.key === 'stories',
    url: ['slides', 'stories'].includes(props.section.key) || isFooter.value,
    cta: ['slides', 'stories'].includes(props.section.key) || isFooter.value,
    caption: props.section.key === 'albums' || isFooter.value,
    display: props.section.key === 'objectives',
}))
</script>

<template>
    <CmsDashboardLayout :title="copy.title" :subtitle="copy.subtitle" :hint="copy.hint">
        <div class="pc-stack">
            <section v-if="isOrganization" class="pc-card">
                <header class="pc-card__head"><h2 class="pc-card__title">{{ copy.organizationTitle }}</h2></header>
                <form class="pc-form-grid" @submit.prevent="submitOrganization">
                    <div v-for="code in locales" :key="code" class="pc-fieldset">
                        <span class="pc-eyebrow">{{ code.toUpperCase() }}</span>
                        <label class="pc-field">
                            <span>{{ copy.organizationTitle }}</span>
                            <input v-model="organizationForm.translations[code].title" type="text" maxlength="255">
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.description }}</span>
                            <textarea v-model="organizationForm.translations[code].description" rows="4" />
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.visionTitle }}</span>
                            <input v-model="organizationForm.translations[code].vision_title" type="text" maxlength="160">
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.vision }}</span>
                            <textarea v-model="organizationForm.translations[code].vision" rows="3" />
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.missionTitle }}</span>
                            <input v-model="organizationForm.translations[code].mission_title" type="text" maxlength="160">
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.mission }}</span>
                            <textarea v-model="organizationForm.translations[code].mission" rows="3" />
                        </label>
                    </div>
                    <div class="pc-fieldset">
                        <label class="pc-field">
                            <span>{{ copy.destinationUrl }}</span>
                            <input v-model="organizationForm.destination_url" type="text" maxlength="255">
                            <small v-if="organizationForm.errors.destination_url" class="pc-error">{{ organizationForm.errors.destination_url }}</small>
                        </label>
                        <ContentMediaField
                            v-model="organizationForm.logo_media_id"
                            :label="copy.logo"
                            :options="media"
                            :labels="copy.mediaLabels"
                        />
                        <button class="pc-btn pc-btn--primary" type="submit" :disabled="!canUpdate || organizationForm.processing">
                            {{ copy.save }}
                        </button>
                    </div>
                </form>
            </section>

            <template v-else>
                <section class="pc-card">
                    <header class="pc-card__head">
                        <h2 class="pc-card__title">{{ editing === null ? copy.add : copy.edit }}</h2>
                        <button v-if="editing !== null" class="pc-btn pc-btn--ghost pc-btn--sm" type="button" @click="cancelEdit">
                            {{ copy.cancelEdit }}
                        </button>
                    </header>
                    <form class="pc-form-grid" @submit.prevent="submitItem">
                        <label v-if="isLinks" class="pc-field">
                            <span>{{ copy.source }}</span>
                            <select v-model="form.item_id">
                                <option value="">{{ copy.source }}</option>
                                <option v-for="source in sources" :key="source.id" :value="source.id">
                                    {{ source.title }} (#{{ source.id }})
                                </option>
                            </select>
                            <small v-if="form.errors.item_id" class="pc-error">{{ form.errors.item_id }}</small>
                        </label>
                        <label v-if="isStories" class="pc-field">
                            <span>{{ copy.parent }}</span>
                            <select v-model="form.parent_item_id">
                                <option value="">—</option>
                                <option v-for="item in parentOptions" :key="item.id" :value="item.id">
                                    {{ item.title || item.translations?.ar?.title || `#${item.id}` }}
                                </option>
                            </select>
                        </label>
                        <label v-if="isInline" class="pc-field">
                            <span>{{ copy.record }} key</span>
                            <input v-model="form.item_key" type="text" maxlength="64">
                        </label>
                        <label v-if="isLinks" class="pc-field">
                            <span>{{ copy.tab }}</span>
                            <input v-model="form.tab" type="text" maxlength="32">
                        </label>
                        <label v-if="isLinks" class="pc-field">
                            <span>{{ copy.motif }}</span>
                            <input v-model="form.motif_key" type="text" maxlength="32">
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.sort }}</span>
                            <input v-model.number="form.sort_order" type="number" min="0" max="999">
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.startAt }}</span>
                            <input v-model="form.display_start_at" type="date">
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.endAt }}</span>
                            <input v-model="form.display_end_at" type="date">
                            <small v-if="form.errors.display_end_at" class="pc-error">{{ form.errors.display_end_at }}</small>
                        </label>
                        <label v-if="isLinks" class="pc-check">
                            <input v-model="form.is_featured" type="checkbox">
                            <span>{{ copy.featured }}</span>
                        </label>
                        <label class="pc-check">
                            <input v-model="form.is_published" type="checkbox">
                            <span>{{ copy.visibility }}</span>
                        </label>

                        <div v-for="code in locales" :key="code" class="pc-fieldset">
                            <span class="pc-eyebrow">{{ code.toUpperCase() }}</span>
                            <label v-if="visibleFields.title" class="pc-field">
                                <span>{{ copy.titleField }}</span>
                                <input v-model="form.translations[code].title" type="text" maxlength="255">
                            </label>
                            <label v-if="visibleFields.label" class="pc-field">
                                <span>{{ copy.labelField }}</span>
                                <input v-model="form.translations[code].label" type="text" maxlength="2000">
                            </label>
                            <label v-if="visibleFields.summary" class="pc-field">
                                <span>{{ copy.summaryField }}</span>
                                <textarea v-model="form.translations[code].summary" rows="3" />
                            </label>
                            <label v-if="visibleFields.body" class="pc-field">
                                <span>{{ copy.bodyField }}</span>
                                <textarea v-model="form.translations[code].body" rows="4" />
                            </label>
                            <label class="pc-field">
                                <span>{{ copy.altField }}</span>
                                <input v-model="form.translations[code].alt_text" type="text" maxlength="255">
                            </label>
                            <label v-if="visibleFields.url" class="pc-field">
                                <span>{{ copy.urlField }}</span>
                                <input v-model="form.translations[code].url" type="text" maxlength="255">
                                <small v-if="form.errors[`translations.${code}.url`]" class="pc-error">{{ form.errors[`translations.${code}.url`] }}</small>
                            </label>
                            <label v-if="visibleFields.cta" class="pc-field">
                                <span>{{ copy.ctaField }}</span>
                                <input v-model="form.translations[code].cta_label" type="text" maxlength="120">
                            </label>
                            <label class="pc-field">
                                <span>{{ copy.dateField }}</span>
                                <input v-model="form.translations[code].date_label" type="text" maxlength="64">
                            </label>
                            <label v-if="visibleFields.caption" class="pc-field">
                                <span>{{ copy.captionField }}</span>
                                <input v-model="form.translations[code].caption" type="text" maxlength="500">
                            </label>
                            <label v-if="visibleFields.display" class="pc-field">
                                <span>{{ copy.displayField }}</span>
                                <input v-model="form.translations[code].display_value" type="text" maxlength="64">
                            </label>
                            <label class="pc-field">
                                <span>{{ copy.unitField }}</span>
                                <input v-model="form.translations[code].unit" type="text" maxlength="64">
                            </label>
                        </div>

                        <ContentMediaField
                            v-if="isInline"
                            v-model="form.media_id"
                            :label="copy.media"
                            :options="media"
                            :labels="copy.mediaLabels"
                        />
                        <small v-if="form.errors.media_id" class="pc-error">{{ form.errors.media_id }}</small>

                        <div class="pc-form-actions">
                            <button class="pc-btn pc-btn--primary" type="submit" :disabled="!canUpdate || form.processing">
                                {{ copy.save }}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="pc-card">
                    <header class="pc-card__head"><h2 class="pc-card__title">{{ copy.records }} ({{ items.length }})</h2></header>
                    <p v-if="!items.length" class="pc-empty">{{ copy.empty }}</p>
                    <div v-else class="pc-table-wrap">
                        <table class="pc-table">
                            <thead>
                                <tr>
                                    <th>{{ copy.record }}</th>
                                    <th>{{ copy.sort }}</th>
                                    <th>{{ copy.visibility }}</th>
                                    <th class="is-end">{{ copy.edit }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in items" :key="item.id">
                                    <td>
                                        <strong>{{ item.title || item.label || item.translations?.ar?.title || `#${item.id}` }}</strong>
                                        <span class="pc-hint"><code>{{ item.itemKey || `${item.itemType}-${item.itemId || item.id}` }}</code></span>
                                    </td>
                                    <td>
                                        {{ item.sortOrder }}
                                        <div class="pc-row-actions">
                                            <button type="button" class="pc-btn pc-btn--ghost pc-btn--xs" :disabled="!canUpdate || index === 0" :aria-label="copy.moveUp" @click="moveItem(index, -1)">↑</button>
                                            <button type="button" class="pc-btn pc-btn--ghost pc-btn--xs" :disabled="!canUpdate || index === items.length - 1" :aria-label="copy.moveDown" @click="moveItem(index, 1)">↓</button>
                                        </div>
                                    </td>
                                    <td>
                                        <label class="pc-toggle">
                                            <input type="checkbox" :checked="item.enabled" :disabled="!canUpdate" @change="toggleItem(item, $event)">
                                            <span>{{ item.enabled ? copy.enabled : copy.disabled }}</span>
                                        </label>
                                    </td>
                                    <td class="is-end">
                                        <button type="button" class="pc-btn pc-btn--secondary pc-btn--sm" :disabled="!canUpdate" @click="editItem(item)">{{ copy.edit }}</button>
                                        <button type="button" class="pc-btn pc-btn--danger pc-btn--sm" :disabled="!canUpdate" @click="destroyItem(item)">{{ copy.remove }}</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>
    </CmsDashboardLayout>
</template>
