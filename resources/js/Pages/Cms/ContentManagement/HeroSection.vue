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
const records = computed(() => [...props.items].sort((first, second) => (
    (Number(first.sortOrder) || 0) - (Number(second.sortOrder) || 0)
) || ((Number(first.id) || 0) - (Number(second.id) || 0))))
// Backend registry membership is the only source of truth for slide status.
const slides = computed(() => records.value.filter((item) => item.isSlide === true))
const activeLocale = computed(() => locales.includes(props.locale) ? props.locale : 'ar')

const motifOptions = [
    { value: 'm-sun', label: 'Sun' },
    { value: 'm-bars', label: 'Bars' },
    { value: 'm-wheat', label: 'Wheat' },
    { value: 'm-drop', label: 'Drop' },
    { value: 'm-book', label: 'Book' },
    { value: 'm-doc', label: 'Document' },
    { value: 'm-home', label: 'Home' },
    { value: 'm-people', label: 'People' },
]

const copy = computed(() => props.locale === 'ar' ? {
    title: 'شريط الصور الرئيسي',
    subtitle: 'إدارة جميع سجلات البطل، بما في ذلك الشرائح وعناصر العرض والسجلات القديمة.',
    hint: 'تُحفظ كل سجل في مصدر المحتوى المعتمد. إخفاء السجل يحتفظ ببياناته ولا يحذفه.',
    add: 'إضافة شريحة',
    edit: 'تعديل السجل',
    cancelEdit: 'إلغاء التعديل',
    unsaved: 'تعديلات غير محفوظة',
    save: 'حفظ الشريحة',
    remove: 'حذف',
    cancel: 'إلغاء',
    hideTitle: 'إخفاء هذه الشريحة؟',
    hideMessage: 'ستختفي الشريحة من الموقع العام وتبقى بياناتها محفوظة.',
    deleteTitle: 'حذف هذه الشريحة؟',
    deleteMessage: 'سيتم حذف الشريحة وترجماتها. استخدم الإخفاء للاحتفاظ بها.',
    confirmHide: 'إخفاء',
    confirmDelete: 'حذف',
    moveUp: 'تحريك الشريحة للأعلى',
    moveDown: 'تحريك الشريحة للأسفل',
    labelField: 'النص القصير',
    titleField: 'العنوان',
    bodyField: 'الوصف',
    media: 'صورة الشريحة من مكتبة الوسائط',
    motif: 'الرمز الزخرفي',
    sort: 'الترتيب',
    startAt: 'بداية العرض',
    endAt: 'نهاية العرض',
    scheduleHelp: 'اترك التاريخ فارغاً ليبدأ العرض من اليوم أو يستمر دون تاريخ انتهاء.',
    scheduleTitle: 'جدول العرض',
    visibility: 'ظاهرة',
    always: 'عرض مستمر',
    from: 'من',
    until: 'إلى',
    preview: 'معاينة الشريحة',
    previewHint: 'تعكس المعاينة القيم الحالية قبل الحفظ.',
    noImage: 'لم يتم اختيار صورة بعد',
    records: 'جميع سجلات البطل',
    allRecordsHint: 'تظهر هنا كل السجلات؛ الشرائح جزء من الإجمالي، وتظل عناصر العرض والسجلات القديمة قابلة للتحرير.',
    slideType: 'شريحة',
    controlType: 'عنصر عرض',
    legacyType: 'سجل قديم',
    statusEligible: 'ظاهرة الآن',
    statusHidden: 'مخفي',
    statusScheduled: 'مجدول',
    statusExpired: 'منتهٍ',
    empty: 'لا توجد سجلات في هذا القسم بعد.',
    mediaLabels: {
        select: 'اختيار صورة', change: 'تغيير الصورة', remove: 'إزالة',
        title: 'مكتبة الوسائط', insert: 'إدراج', empty: 'لا توجد صور', close: 'إغلاق',
    },
} : {
    title: 'Hero slider',
    subtitle: 'Manage every hero record, including slides, presentation controls and legacy rows.',
    hint: 'Every record is saved to the canonical content source. Hiding a record preserves its data.',
    add: 'Add slide',
    edit: 'Edit record',
    cancelEdit: 'Cancel edit',
    unsaved: 'Unsaved changes',
    save: 'Save slide',
    remove: 'Delete',
    cancel: 'Cancel',
    hideTitle: 'Hide this slide?',
    hideMessage: 'The slide will disappear from the public website while its data is preserved.',
    deleteTitle: 'Delete this slide?',
    deleteMessage: 'The slide and its translations will be deleted. Use hide if you want to keep it.',
    confirmHide: 'Hide',
    confirmDelete: 'Delete',
    moveUp: 'Move slide up',
    moveDown: 'Move slide down',
    labelField: 'Short label',
    titleField: 'Title',
    bodyField: 'Body',
    media: 'Slide image from the media library',
    motif: 'Decorative motif',
    sort: 'Order',
    startAt: 'Display from',
    endAt: 'Display until',
    scheduleHelp: 'Leave a date empty to start today or continue without an end date.',
    scheduleTitle: 'Display schedule',
    visibility: 'Visible',
    always: 'Always displayed',
    from: 'From',
    until: 'Until',
    preview: 'Slide preview',
    previewHint: 'The preview reflects the current values before saving.',
    noImage: 'No image selected yet',
    records: 'All hero records',
    allRecordsHint: 'Every record is listed here. Slides are part of the total, while presentation controls and legacy rows remain editable.',
    slideType: 'Slide',
    controlType: 'Presentation control',
    legacyType: 'Legacy record',
    statusEligible: 'Eligible now',
    statusHidden: 'Hidden',
    statusScheduled: 'Scheduled',
    statusExpired: 'Expired',
    empty: 'No records in this section yet.',
    mediaLabels: {
        select: 'Choose image', change: 'Change image', remove: 'Remove',
        title: 'Media library', insert: 'Insert', empty: 'No images', close: 'Close',
    },
})

function emptyTranslation() {
    return { label: '', title: '', body: '' }
}

function emptyValues() {
    return {
        item_type: 'inline',
        is_published: true,
        media_id: '',
        motif_key: '',
        sort_order: records.value.length,
        display_start_at: '',
        display_end_at: '',
        translations: { ar: emptyTranslation(), en: emptyTranslation() },
    }
}

const form = useForm(emptyValues())

function setFormValues(values) {
    form.assign(values)
    form.defaults(values)
    form.reset()
}

function dateValue(value) {
    return value ? String(value).slice(0, 10) : ''
}

function translation(item, code) {
    return item?.translations?.[code] ?? {}
}

function editRecord(item) {
    editing.value = item.id
    setFormValues({
        item_type: 'inline',
        is_published: item.enabled !== false,
        media_id: item.mediaId ?? '',
        motif_key: item.motifKey ?? '',
        sort_order: item.sortOrder ?? 0,
        display_start_at: dateValue(item.displayStartAt),
        display_end_at: dateValue(item.displayEndAt),
        translations: {
            ar: { ...emptyTranslation(), ...translation(item, 'ar') },
            en: { ...emptyTranslation(), ...translation(item, 'en') },
        },
    })
}

function cancelEdit() {
    editing.value = null
    setFormValues(emptyValues())
}

function submitRecord() {
    const base = localPath(`cms/content/${props.section.key}`)
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (editing.value === null) {
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

async function destroyRecord(item) {
    if (! canUpdate.value || ! await confirm({
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

async function toggleRecord(item, event) {
    const enabled = Boolean(event.target.checked)

    if (! enabled && ! await confirm({
        title: copy.value.hideTitle,
        message: copy.value.hideMessage,
        confirmLabel: copy.value.confirmHide,
        cancelLabel: copy.value.cancel,
        danger: true,
    })) {
        event.target.checked = ! enabled
        return
    }

    router.patch(localPath(`cms/content/${props.section.key}/${item.id}/toggle`), { enabled }, { preserveScroll: true })
}

function moveRecord(index, direction) {
    const target = index + direction
    if (! canUpdate.value || target < 0 || target >= records.value.length) {
        return
    }

    const reordered = [...records.value]
    const [moved] = reordered.splice(index, 1)
    reordered.splice(target, 0, moved)

    router.post(localPath(`cms/content/${props.section.key}/reorder`), {
        order: reordered.map((item, position) => ({ id: item.id, sort_order: position })),
    }, { preserveScroll: true })
}

function scheduleSummary(item) {
    const start = dateValue(item.displayStartAt)
    const end = dateValue(item.displayEndAt)

    if (! start && ! end) {
        return copy.value.always
    }

    if (start && end) {
        return `${copy.value.from} ${start} — ${copy.value.until} ${end}`
    }

    return start ? `${copy.value.from} ${start}` : `${copy.value.until} ${end}`
}

function recordType(item) {
    if (item.isSlide === true) {
        return copy.value.slideType
    }

    return ['primary', 'secondary'].includes(item.itemKey)
        ? copy.value.controlType
        : copy.value.legacyType
}

function recordStatus(item) {
    return {
        eligible: copy.value.statusEligible,
        hidden: copy.value.statusHidden,
        scheduled: copy.value.statusScheduled,
        expired: copy.value.statusExpired,
    }[item.scheduleState] || copy.value.statusHidden
}

const preview = computed(() => {
    const values = form.translations[activeLocale.value] ?? {}
    const image = props.media.find((item) => String(item.id) === String(form.media_id))

    return {
        label: values.label || '',
        title: values.title || '',
        body: values.body || '',
        motifKey: form.motif_key || 'm-sun',
        imageUrl: image?.url || '',
    }
})

function previewStyle() {
    return preview.value.imageUrl
        ? { '--hero-preview-photo': `url("${preview.value.imageUrl.replace(/"/g, '%22')}")` }
        : null
}
</script>

<template>
    <CmsDashboardLayout :title="copy.title" :subtitle="copy.subtitle" :hint="copy.hint">
        <div class="pc-stack hero-editor">
            <section class="pc-card">
                <header class="pc-card__head">
                    <h2 class="pc-card__title">{{ editing === null ? copy.add : copy.edit }}</h2>
                    <span v-if="form.isDirty" class="pc-hint" role="status">{{ copy.unsaved }}</span>
                    <button v-if="editing !== null" class="pc-btn pc-btn--ghost pc-btn--sm" type="button" @click="cancelEdit">
                        {{ copy.cancelEdit }}
                    </button>
                </header>
                <form class="pc-form-grid hero-editor__form" @submit.prevent="submitRecord">
                    <div v-for="code in locales" :key="code" class="pc-fieldset">
                        <span class="pc-eyebrow">{{ code.toUpperCase() }}</span>
                        <label class="pc-field">
                            <span>{{ copy.labelField }}</span>
                            <input v-model="form.translations[code].label" type="text" maxlength="2000">
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.titleField }}</span>
                            <input v-model="form.translations[code].title" type="text" maxlength="255">
                            <small v-if="form.errors[`translations.${code}.title`]" class="pc-error">{{ form.errors[`translations.${code}.title`] }}</small>
                        </label>
                        <label class="pc-field">
                            <span>{{ copy.bodyField }}</span>
                            <textarea v-model="form.translations[code].body" rows="3" />
                        </label>
                    </div>

                    <div class="pc-fieldset">
                        <ContentMediaField
                            v-model="form.media_id"
                            :label="copy.media"
                            :options="media"
                            :labels="copy.mediaLabels"
                        />
                        <small v-if="form.errors.media_id" class="pc-error">{{ form.errors.media_id }}</small>

                        <label class="pc-field">
                            <span>{{ copy.motif }}</span>
                            <select v-model="form.motif_key">
                                <option value="">—</option>
                                <option v-for="option in motifOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </label>

                        <label class="pc-field">
                            <span>{{ copy.sort }}</span>
                            <input v-model.number="form.sort_order" type="number" min="0" max="999">
                        </label>

                        <fieldset class="hero-editor__schedule">
                            <legend>{{ copy.scheduleTitle }}</legend>
                            <label class="pc-field">
                                <span>{{ copy.startAt }}</span>
                                <input v-model="form.display_start_at" type="date" aria-describedby="hero-schedule-help">
                            </label>
                            <label class="pc-field">
                                <span>{{ copy.endAt }}</span>
                                <input v-model="form.display_end_at" type="date" aria-describedby="hero-schedule-help">
                                <small v-if="form.errors.display_end_at" class="pc-error">{{ form.errors.display_end_at }}</small>
                            </label>
                            <p id="hero-schedule-help" class="pc-hint" role="note">{{ copy.scheduleHelp }}</p>
                        </fieldset>

                        <label class="pc-check">
                            <input v-model="form.is_published" type="checkbox">
                            <span>{{ copy.visibility }}</span>
                        </label>

                        <div class="pc-form-actions">
                            <button class="pc-btn pc-btn--primary" type="submit" :disabled="!canUpdate || form.processing">
                                {{ copy.save }}
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="pc-card">
                <header class="pc-card__head"><h2 class="pc-card__title">{{ copy.preview }}</h2></header>
                <div class="hero-editor__preview-wrap">
                    <div class="hero-preview" :class="{ 'hero-preview--photo': Boolean(preview.imageUrl) }" :style="previewStyle()">
                        <span v-if="preview.label" class="hero-preview__tag">{{ preview.label }}</span>
                        <h3 v-if="preview.title" class="hero-preview__title">{{ preview.title }}</h3>
                        <p v-if="preview.body" class="hero-preview__body">{{ preview.body }}</p>
                        <svg class="hero-preview__motif" aria-hidden="true"><use :href="`#${preview.motifKey}`" /></svg>
                        <span v-if="!preview.imageUrl" class="hero-preview__empty">{{ copy.noImage }}</span>
                    </div>
                    <p class="pc-hint">{{ copy.previewHint }}</p>
                </div>
            </section>

            <section class="pc-card">
                <header class="pc-card__head">
                    <h2 class="pc-card__title">{{ copy.records }} ({{ records.length }} / {{ slides.length }})</h2>
                </header>
                <p class="pc-hint hero-editor__records-hint">{{ copy.allRecordsHint }}</p>
                <p v-if="!records.length" class="pc-empty">{{ copy.empty }}</p>
                <div v-else class="hero-editor__slides">
                    <article v-for="(item, index) in records" :key="item.id" class="hero-slide-card">
                        <div class="hero-slide-card__media">
                            <img v-if="item.mediaUrl" :src="item.mediaUrl" :alt="item.translations?.[activeLocale]?.title || ''">
                            <span v-else>{{ copy.noImage }}</span>
                        </div>
                        <div class="hero-slide-card__body">
                            <span class="pc-eyebrow">#{{ index + 1 }} · {{ item.itemKey || `#${item.id}` }}</span>
                            <h3>{{ item.translations?.[activeLocale]?.title || item.translations?.ar?.title || `#${item.id}` }}</h3>
                            <p class="hero-slide-card__meta">
                                <span>{{ recordType(item) }}</span>
                                <span>{{ recordStatus(item) }}</span>
                            </p>
                            <p v-if="item.translations?.[activeLocale]?.body" class="pc-hint">{{ item.translations[activeLocale].body }}</p>
                            <p class="pc-hint">{{ scheduleSummary(item) }}</p>
                            <div class="hero-slide-card__actions">
                                <button class="pc-btn pc-btn--secondary pc-btn--sm" type="button" :disabled="!canUpdate" @click="editRecord(item)">{{ copy.edit }}</button>
                                <button class="pc-btn pc-btn--ghost pc-btn--xs" type="button" :disabled="!canUpdate || index === 0" :aria-label="`${copy.moveUp} — ${item.itemKey || item.id}`" @click="moveRecord(index, -1)">↑</button>
                                <button class="pc-btn pc-btn--ghost pc-btn--xs" type="button" :disabled="!canUpdate || index === records.length - 1" :aria-label="`${copy.moveDown} — ${item.itemKey || item.id}`" @click="moveRecord(index, 1)">↓</button>
                                <label class="pc-toggle">
                                    <input type="checkbox" :checked="item.enabled" :disabled="!canUpdate" @change="toggleRecord(item, $event)">
                                    <span>{{ item.enabled ? copy.visibility : copy.confirmHide }}</span>
                                </label>
                                <button class="pc-btn pc-btn--danger pc-btn--sm" type="button" :disabled="!canUpdate" @click="destroyRecord(item)">{{ copy.remove }}</button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </CmsDashboardLayout>
</template>

<style scoped>
.hero-editor__form {
    align-items: start;
}

.hero-editor__schedule {
    display: grid;
    gap: 12px;
    min-inline-size: 0;
    padding: 14px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm);
}

.hero-editor__schedule legend {
    padding-inline: 4px;
    color: var(--pc-muted);
    font-size: 0.78rem;
    font-weight: 650;
}

.hero-editor__preview-wrap {
    padding: 20px;
}

.hero-preview {
    position: relative;
    display: grid;
    align-content: end;
    gap: 8px;
    min-block-size: 260px;
    padding: 28px;
    overflow: hidden;
    border-radius: var(--pc-radius);
    background-color: var(--pc-navy);
    background-image: linear-gradient(120deg, rgb(0 0 0 / 55%), rgb(0 0 0 / 10%));
    color: #fff;
}

.hero-preview::before {
    position: absolute;
    inset: 0;
    background-image: var(--hero-preview-photo, none);
    background-position: center;
    background-size: cover;
    content: '';
    opacity: 0;
}

.hero-preview--photo::before {
    opacity: 1;
}

.hero-preview > * {
    position: relative;
    z-index: 1;
}

.hero-preview__tag {
    justify-self: start;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgb(255 255 255 / 20%);
    font-size: 0.75rem;
}

.hero-preview__title {
    margin: 0;
    font-size: clamp(1.4rem, 3vw, 2.25rem);
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.hero-preview__body {
    max-inline-size: 52ch;
    margin: 0;
    overflow-wrap: anywhere;
}

.hero-preview__motif {
    position: absolute;
    inset-block-start: -30px;
    inset-inline-end: -20px;
    inline-size: 180px;
    block-size: 180px;
    opacity: 0.28;
}

.hero-preview__empty {
    position: absolute;
    inset-block-start: 16px;
    inset-inline-start: 16px;
    padding: 5px 9px;
    border-radius: 8px;
    background: rgb(0 0 0 / 55%);
    font-size: 0.75rem;
}

.hero-editor__records-hint {
    margin: 0;
    padding-inline: 20px;
}

.hero-editor__slides {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 240px), 1fr));
    gap: 14px;
    padding: 20px;
}

.hero-slide-card {
    display: grid;
    gap: 12px;
    min-inline-size: 0;
    padding: 12px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm);
    background: var(--pc-surface-2);
}

.hero-slide-card__media {
    display: grid;
    place-items: center;
    min-block-size: 120px;
    overflow: hidden;
    border-radius: 9px;
    background: var(--pc-navy-soft);
    color: var(--pc-muted);
    font-size: 0.75rem;
    text-align: center;
}

.hero-slide-card__media img {
    inline-size: 100%;
    block-size: 120px;
    object-fit: cover;
}

.hero-slide-card__body {
    display: grid;
    gap: 8px;
    min-inline-size: 0;
}

.hero-slide-card__body h3 {
    margin: 0;
    font-size: 0.95rem;
    overflow-wrap: anywhere;
}

.hero-slide-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 8px;
    margin: 0;
    font-size: 0.72rem;
    color: var(--pc-muted);
}

.hero-slide-card__meta span {
    padding: 2px 6px;
    border-radius: 6px;
    background: var(--pc-surface);
}

.hero-slide-card__body p {
    margin: 0;
    overflow-wrap: anywhere;
}

.hero-slide-card__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

@media (max-width: 820px) {
    .hero-editor__preview-wrap,
    .hero-editor__slides {
        padding: 16px;
    }

    .hero-editor__records-hint {
        padding-inline: 16px;
    }

    .hero-preview {
        min-block-size: 220px;
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .hero-editor__slides {
        grid-template-columns: minmax(0, 1fr);
    }

    .hero-preview {
        min-block-size: 200px;
        padding: 16px;
    }

    .hero-slide-card__actions {
        align-items: stretch;
    }
}
</style>
