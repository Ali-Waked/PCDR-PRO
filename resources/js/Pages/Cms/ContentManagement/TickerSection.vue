<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import CmsDashboardLayout from '../../../Layouts/CmsDashboardLayout.vue'
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
const activeLocale = computed(() => locales.includes(props.locale) ? props.locale : 'ar')
const isArticle = computed(() => form.item_type === 'article')
const publishedSources = computed(() => props.sources.filter((source) => {
    if (source.status !== 'published') {
        return false
    }

    return ! source.publishedAt || new Date(source.publishedAt).getTime() <= Date.now()
}))

const copy = computed(() => props.locale === 'ar' ? {
    title: 'شريط الإعلانات',
    subtitle: 'إدارة الإعلانات المختصرة والروابط إلى المقالات المنشورة.',
    hint: 'إخفاء الإعلان يحفظ بياناته. الروابط المتاحة هنا من المقالات المنشورة فقط.',
    presentation: 'عنوان الشريط',
    presentationHint: 'يظهر هذا العنوان بجانب الإعلانات في الموقع العام.',
    tickerLabel: 'نص العنوان',
    saveLabel: 'حفظ العنوان',
    add: 'إضافة إعلان',
    edit: 'تعديل الإعلان',
    cancelEdit: 'إلغاء التعديل',
    unsaved: 'تعديلات غير محفوظة',
    save: 'حفظ الإعلان',
    remove: 'حذف',
    cancel: 'إلغاء',
    hideTitle: 'إخفاء هذا الإعلان؟',
    hideMessage: 'سيختفي الإعلان من الموقع العام وتبقى بياناته محفوظة.',
    deleteTitle: 'حذف هذا الإعلان؟',
    deleteMessage: 'سيتم حذف الإعلان وترجماته. استخدم الإخفاء للاحتفاظ به.',
    confirmHide: 'إخفاء',
    confirmDelete: 'حذف',
    moveUp: 'تحريك الإعلان للأعلى',
    moveDown: 'تحريك الإعلان للأسفل',
    itemType: 'نوع الإعلان',
    inlineType: 'إعلان نصي',
    articleType: 'رابط مقال منشور',
    articleSource: 'المقال المنشور',
    noSources: 'لا توجد مقالات منشورة متاحة للربط.',
    titleField: 'نص الإعلان',
    urlField: 'رابط الإعلان',
    articleHint: 'عنوان الإعلان ورابطه يُؤخذان من المقال المختار.',
    sort: 'الترتيب',
    startAt: 'بداية العرض',
    endAt: 'نهاية العرض',
    scheduleHelp: 'اترك التاريخ فارغاً ليبدأ العرض من اليوم أو يستمر دون تاريخ انتهاء.',
    scheduleTitle: 'جدول العرض',
    visibility: 'ظاهر',
    always: 'عرض مستمر',
    from: 'من',
    until: 'إلى',
    preview: 'معاينة الشريط',
    previewHint: 'تعكس المعاينة الإعلان الحالي والإعلانات المحفوظة قبل الحفظ.',
    noPreview: 'لا توجد إعلانات لعرضها بعد.',
    records: 'الإعلانات',
    empty: 'لا توجد إعلانات في هذا القسم بعد.',
} : {
    title: 'Announcement ticker',
    subtitle: 'Manage short announcements and links to published articles.',
    hint: 'Hiding an announcement preserves it. Only published articles can be linked.',
    presentation: 'Ticker label',
    presentationHint: 'This label appears beside the announcements on the public website.',
    tickerLabel: 'Label text',
    saveLabel: 'Save label',
    add: 'Add announcement',
    edit: 'Edit announcement',
    cancelEdit: 'Cancel edit',
    unsaved: 'Unsaved changes',
    save: 'Save announcement',
    remove: 'Delete',
    cancel: 'Cancel',
    hideTitle: 'Hide this announcement?',
    hideMessage: 'The announcement will disappear from the public website while its data is preserved.',
    deleteTitle: 'Delete this announcement?',
    deleteMessage: 'The announcement and its translations will be deleted. Use hide if you want to keep it.',
    confirmHide: 'Hide',
    confirmDelete: 'Delete',
    moveUp: 'Move announcement up',
    moveDown: 'Move announcement down',
    itemType: 'Announcement type',
    inlineType: 'Text announcement',
    articleType: 'Published article link',
    articleSource: 'Published article',
    noSources: 'No published articles are available to link.',
    titleField: 'Announcement text',
    urlField: 'Announcement URL',
    articleHint: 'The announcement title and URL are taken from the selected article.',
    sort: 'Order',
    startAt: 'Display from',
    endAt: 'Display until',
    scheduleHelp: 'Leave a date empty to start today or continue without an end date.',
    scheduleTitle: 'Display schedule',
    visibility: 'Visible',
    always: 'Always displayed',
    from: 'From',
    until: 'Until',
    preview: 'Ticker preview',
    previewHint: 'The preview reflects the current announcement and saved announcements before saving.',
    noPreview: 'No announcements to preview yet.',
    records: 'Announcements',
    empty: 'No announcements in this section yet.',
})

function emptyTranslation() {
    return { title: '', url: '' }
}

function emptyValues() {
    return {
        item_type: 'inline',
        item_id: '',
        is_published: true,
        sort_order: props.items.length,
        display_start_at: '',
        display_end_at: '',
        translations: { ar: emptyTranslation(), en: emptyTranslation() },
    }
}

function presentationValues() {
    return {
        translations: {
            ar: { ticker_label: props.section.presentation?.translations?.ar?.tickerLabel ?? '' },
            en: { ticker_label: props.section.presentation?.translations?.en?.tickerLabel ?? '' },
        },
    }
}

const form = useForm(emptyValues())
const presentationForm = useForm(presentationValues())

function setFormValues(values) {
    form.assign(values)
    form.defaults(values)
    form.reset()
}

function setPresentationValues(values) {
    presentationForm.assign(values)
    presentationForm.defaults(values)
    presentationForm.reset()
}

setPresentationValues(presentationValues())

function dateValue(value) {
    return value ? String(value).slice(0, 10) : ''
}

function translation(item, code) {
    return item?.translations?.[code] ?? {}
}

function editItem(item) {
    editing.value = item.id
    setFormValues({
        item_type: item.itemType === 'article' ? 'article' : 'inline',
        item_id: item.itemId ?? '',
        is_published: item.enabled !== false,
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

function submitPresentation() {
    presentationForm.put(localPath(`cms/content/${props.section.key}/presentation`), {
        preserveScroll: true,
        onSuccess: () => setPresentationValues(JSON.parse(JSON.stringify(presentationForm.data()))),
    })
}

function submitItem() {
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

async function destroyItem(item) {
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

async function toggleItem(item, event) {
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

function moveItem(index, direction) {
    const target = index + direction
    if (! canUpdate.value || target < 0 || target >= props.items.length) {
        return
    }

    const reordered = [...props.items]
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

const previewItems = computed(() => {
    const current = form.translations[activeLocale.value] ?? {}
    const currentTitle = isArticle.value
        ? publishedSources.value.find((source) => String(source.id) === String(form.item_id))?.title || ''
        : current.title || ''
    const saved = props.items
        .filter((item) => item.id !== editing.value)
        .map((item) => item.translations?.[activeLocale.value]?.title || item.title || '')

    return [currentTitle, ...saved].filter((title) => title !== '').slice(0, 5)
})
</script>

<template>
    <CmsDashboardLayout :title="copy.title" :subtitle="copy.subtitle" :hint="copy.hint">
        <div class="pc-stack ticker-editor">
            <section class="pc-card">
                <header class="pc-card__head"><h2 class="pc-card__title">{{ copy.presentation }}</h2></header>
                <form class="pc-form-grid" @submit.prevent="submitPresentation">
                    <label v-for="code in locales" :key="code" class="pc-field">
                        <span>{{ code.toUpperCase() }} — {{ copy.tickerLabel }}</span>
                        <input v-model="presentationForm.translations[code].ticker_label" type="text" maxlength="120">
                    </label>
                    <div class="pc-fieldset">
                        <p class="pc-hint">{{ copy.presentationHint }}</p>
                        <span v-if="presentationForm.isDirty" class="pc-hint" role="status">{{ copy.unsaved }}</span>
                        <div class="pc-form-actions">
                            <button class="pc-btn pc-btn--secondary" type="submit" :disabled="!canUpdate || presentationForm.processing">
                                {{ copy.saveLabel }}
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="pc-card">
                <header class="pc-card__head">
                    <h2 class="pc-card__title">{{ editing === null ? copy.add : copy.edit }}</h2>
                    <span v-if="form.isDirty" class="pc-hint" role="status">{{ copy.unsaved }}</span>
                    <button v-if="editing !== null" class="pc-btn pc-btn--ghost pc-btn--sm" type="button" @click="cancelEdit">
                        {{ copy.cancelEdit }}
                    </button>
                </header>
                <form class="pc-form-grid" @submit.prevent="submitItem">
                    <div class="pc-fieldset">
                        <label class="pc-field">
                            <span>{{ copy.itemType }}</span>
                            <select v-model="form.item_type">
                                <option value="inline">{{ copy.inlineType }}</option>
                                <option value="article">{{ copy.articleType }}</option>
                            </select>
                            <small v-if="form.errors.item_type" class="pc-error">{{ form.errors.item_type }}</small>
                        </label>
                        <label v-if="isArticle" class="pc-field">
                            <span>{{ copy.articleSource }}</span>
                            <select v-model="form.item_id">
                                <option value="">—</option>
                                <option v-for="source in publishedSources" :key="source.id" :value="source.id">{{ source.title }} (#{{ source.id }})</option>
                            </select>
                            <small v-if="form.errors.item_id" class="pc-error">{{ form.errors.item_id }}</small>
                            <small v-if="!publishedSources.length" class="pc-hint">{{ copy.noSources }}</small>
                        </label>
                        <p v-if="isArticle" class="pc-hint" role="note">{{ copy.articleHint }}</p>
                        <label class="pc-field">
                            <span>{{ copy.sort }}</span>
                            <input v-model.number="form.sort_order" type="number" min="0" max="999">
                        </label>
                        <fieldset class="ticker-editor__schedule">
                            <legend>{{ copy.scheduleTitle }}</legend>
                            <label class="pc-field">
                                <span>{{ copy.startAt }}</span>
                                <input v-model="form.display_start_at" type="date" aria-describedby="ticker-schedule-help">
                            </label>
                            <label class="pc-field">
                                <span>{{ copy.endAt }}</span>
                                <input v-model="form.display_end_at" type="date" aria-describedby="ticker-schedule-help">
                                <small v-if="form.errors.display_end_at" class="pc-error">{{ form.errors.display_end_at }}</small>
                            </label>
                            <p id="ticker-schedule-help" class="pc-hint" role="note">{{ copy.scheduleHelp }}</p>
                        </fieldset>
                        <label class="pc-check">
                            <input v-model="form.is_published" type="checkbox">
                            <span>{{ copy.visibility }}</span>
                        </label>
                    </div>

                    <template v-if="!isArticle">
                        <div v-for="code in locales" :key="code" class="pc-fieldset">
                            <span class="pc-eyebrow">{{ code.toUpperCase() }}</span>
                            <label class="pc-field">
                                <span>{{ copy.titleField }}</span>
                                <input v-model="form.translations[code].title" type="text" maxlength="255">
                            </label>
                            <label class="pc-field">
                                <span>{{ copy.urlField }}</span>
                                <input v-model="form.translations[code].url" type="text" maxlength="255">
                                <small v-if="form.errors[`translations.${code}.url`]" class="pc-error">{{ form.errors[`translations.${code}.url`] }}</small>
                            </label>
                        </div>
                    </template>

                    <div class="pc-fieldset">
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
                <div class="ticker-editor__preview-wrap">
                    <div v-if="previewItems.length" class="ticker-preview">
                        <span class="ticker-preview__label">{{ presentationForm.translations[activeLocale].ticker_label }}</span>
                        <div class="ticker-preview__items">
                            <span v-for="(title, index) in previewItems" :key="`${title}-${index}`">{{ title }}</span>
                        </div>
                    </div>
                    <p v-else class="pc-empty">{{ copy.noPreview }}</p>
                    <p class="pc-hint">{{ copy.previewHint }}</p>
                </div>
            </section>

            <section class="pc-card">
                <header class="pc-card__head"><h2 class="pc-card__title">{{ copy.records }} ({{ items.length }})</h2></header>
                <p v-if="!items.length" class="pc-empty">{{ copy.empty }}</p>
                <div v-else class="ticker-editor__items">
                    <article v-for="(item, index) in items" :key="item.id" class="ticker-item-card">
                        <div class="ticker-item-card__body">
                            <span class="pc-eyebrow">#{{ index + 1 }} · {{ item.itemKey || `${item.itemType}-${item.itemId || item.id}` }}</span>
                            <h3>{{ item.translations?.[activeLocale]?.title || item.title || `#${item.id}` }}</h3>
                            <p v-if="item.itemType === 'inline' && item.translations?.[activeLocale]?.url" class="pc-hint">{{ item.translations[activeLocale].url }}</p>
                            <p class="pc-hint">{{ scheduleSummary(item) }}</p>
                            <div class="ticker-item-card__actions">
                                <button class="pc-btn pc-btn--secondary pc-btn--sm" type="button" :disabled="!canUpdate" @click="editItem(item)">{{ copy.edit }}</button>
                                <button class="pc-btn pc-btn--ghost pc-btn--xs" type="button" :disabled="!canUpdate || index === 0" :aria-label="copy.moveUp" @click="moveItem(index, -1)">↑</button>
                                <button class="pc-btn pc-btn--ghost pc-btn--xs" type="button" :disabled="!canUpdate || index === items.length - 1" :aria-label="copy.moveDown" @click="moveItem(index, 1)">↓</button>
                                <label class="pc-toggle">
                                    <input type="checkbox" :checked="item.enabled" :disabled="!canUpdate" @change="toggleItem(item, $event)">
                                    <span>{{ item.enabled ? copy.visibility : copy.confirmHide }}</span>
                                </label>
                                <button class="pc-btn pc-btn--danger pc-btn--sm" type="button" :disabled="!canUpdate" @click="destroyItem(item)">{{ copy.remove }}</button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </CmsDashboardLayout>
</template>

<style scoped>
.ticker-editor__schedule {
    display: grid;
    gap: 12px;
    min-inline-size: 0;
    padding: 14px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm);
}

.ticker-editor__schedule legend {
    padding-inline: 4px;
    color: var(--pc-muted);
    font-size: 0.78rem;
    font-weight: 650;
}

.ticker-editor__preview-wrap {
    padding: 20px;
}

.ticker-preview {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 18px;
    min-inline-size: 0;
    padding: 12px 16px;
    border-inline-start: 4px solid var(--pc-teal);
    border-radius: var(--pc-radius-sm);
    background: var(--pc-surface-2);
}

.ticker-preview__label {
    flex: 0 0 auto;
    color: var(--pc-teal);
    font-size: 0.78rem;
    font-weight: 700;
}

.ticker-preview__items {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    min-inline-size: 0;
}

.ticker-preview__items span {
    overflow-wrap: anywhere;
}

.ticker-editor__items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
    gap: 14px;
    padding: 20px;
}

.ticker-item-card {
    min-inline-size: 0;
    padding: 14px;
    border: 1px solid var(--pc-border);
    border-radius: var(--pc-radius-sm);
    background: var(--pc-surface-2);
}

.ticker-item-card__body {
    display: grid;
    gap: 8px;
    min-inline-size: 0;
}

.ticker-item-card__body h3,
.ticker-item-card__body p {
    margin: 0;
    overflow-wrap: anywhere;
}

.ticker-item-card__body h3 {
    font-size: 0.95rem;
}

.ticker-item-card__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

@media (max-width: 820px) {
    .ticker-editor__preview-wrap,
    .ticker-editor__items {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .ticker-editor__items {
        grid-template-columns: minmax(0, 1fr);
    }

    .ticker-preview {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
