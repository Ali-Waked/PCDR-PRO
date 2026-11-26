<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)

const TAB_KEYS = ['news', 'publications', 'projects']

const FALLBACK = {
    eyebrow: { ar: 'الأخبار', en: 'News' },
    title: { ar: 'آخر الأخبار', en: 'Latest news' },
    group: { ar: 'الأخبار والإصدارات والمشاريع', en: 'News, publications and projects' },
    tabs: {
        news: {
            label: { ar: 'أخبار وأنشطة', en: 'News and activities' },
            entries: [
                {
                    motif: 'm-sun', gradient: 'g1', featured: true,
                    title: { ar: 'الخطة الاستراتيجية والتشغيلية 2027–2029: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة', en: 'Strategic and operational plan 2027–2029: from humanitarian response to recovery and sustainable development' },
                    date: { ar: 'الإطار الزمني 2027 – 2029', en: 'Time frame 2027 – 2029' },
                    body: { ar: 'تؤسس الجمعية للانتقال من التدخلات الإغاثية الموسمية إلى نموذج مؤسسي متكامل يجمع بين الاستجابة الإنسانية الفورية والحماية الاجتماعية والتنمية الاقتصادية والتعافي المبكر…', en: 'The association is laying the groundwork for moving from seasonal relief interventions to an integrated institutional model that combines immediate humanitarian response, social protection, economic development and early recovery…' },
                    cta: { ar: 'أكمل القراءة', en: 'Read more' },
                },
                {
                    motif: 'm-people', gradient: 'g2',
                    title: { ar: 'المرحلة الأولى: التأسيس المؤسسي وبناء الجاهزية الإدارية والفنية', en: 'Phase one: institutional foundation and building administrative and technical readiness' },
                    date: { ar: 'يناير – مارس 2027', en: 'January – March 2027' },
                },
                {
                    motif: 'm-wheat', gradient: 'g3',
                    title: { ar: 'إطلاق البرامج الميدانية: المساعدات الغذائية والدعم النفسي والاجتماعي', en: 'Launch of field programmes: food assistance and psychosocial support' },
                    date: { ar: 'أبريل – يونيو 2027', en: 'April – June 2027' },
                },
                {
                    motif: 'm-home', gradient: 'g4',
                    title: { ar: 'التوسع في الشراكات وبرامج رعاية الأيتام والأشخاص ذوي الإعاقة', en: 'Expanding partnerships and care programmes for orphans and persons with disabilities' },
                    date: { ar: 'يوليو – سبتمبر 2027', en: 'July – September 2027' },
                },
            ],
        },
        publications: {
            label: { ar: 'الإصدارات', en: 'Publications' },
            entries: [
                {
                    motif: 'm-doc', gradient: 'g6', featured: true,
                    title: { ar: 'الخطة الاستراتيجية والتشغيلية الشاملة للجمعية', en: 'The association’s comprehensive strategic and operational plan' },
                    date: { ar: '2027 – 2029', en: '2027 – 2029' },
                    body: { ar: 'الإطار المؤسسي والتوجيهي لعمل الجمعية: الرؤية والرسالة والقيم، والمحاور الاستراتيجية الأحد عشر، والهيكل التنظيمي، والخطة التشغيلية للسنة الأولى، ومؤشرات الأداء والموازنة…', en: 'The institutional and guiding framework for the association’s work: vision, mission and values, the eleven strategic axes, the organisational structure, the first-year operational plan, performance indicators and budget…' },
                    cta: { ar: 'تحميل الإصدار', en: 'Download the publication' },
                },
                {
                    motif: 'm-book', gradient: 'g1',
                    title: { ar: 'الملف التعريفي للجمعية (عربي / إنجليزي)', en: 'Association profile (Arabic / English)' },
                    date: { ar: 'مرحلة التأسيس', en: 'Foundation stage' },
                },
                {
                    motif: 'm-doc', gradient: 'g5',
                    title: { ar: 'دليل استخدام الشعار والهوية البصرية', en: 'Logo and visual identity guidelines' },
                    date: { ar: 'مرحلة التأسيس', en: 'Foundation stage' },
                },
                {
                    motif: 'm-bars', gradient: 'g2',
                    title: { ar: 'تقرير الأداء نصف السنوي', en: 'Semi-annual performance report' },
                    date: { ar: 'يوليو – سبتمبر 2027', en: 'July – September 2027' },
                },
            ],
        },
        projects: {
            label: { ar: 'المشاريع', en: 'Projects' },
            entries: [
                {
                    motif: 'm-wheat', gradient: 'g2', featured: true,
                    title: { ar: 'مشروع الأمن الغذائي والمساعدات النقدية', en: 'Food security and cash assistance project' },
                    date: { ar: 'أولوية تنفيذية عاجلة', en: 'Urgent implementation priority' },
                    body: { ar: 'توزيع الطرود الغذائية والكوبونات وتوفير المساعدات النقدية والعينية للأسر الأكثر احتياجاً، وفق معايير استحقاق معلنة وشفافة وبما يضمن عدم الازدواجية…', en: 'Distributing food parcels and vouchers and providing cash and in-kind assistance to the most needy families, based on published and transparent eligibility criteria and avoiding duplication…' },
                    cta: { ar: 'المزيد عن المشروع', en: 'More about the project' },
                },
                {
                    motif: 'm-people', gradient: 'g5',
                    title: { ar: 'برنامج متكامل لرعاية الأيتام والأسر الأكثر هشاشة', en: 'Integrated programme for the care of orphans and the most vulnerable families' },
                    date: { ar: 'أولوية تنفيذية عاجلة', en: 'Urgent implementation priority' },
                },
                {
                    motif: 'm-bars', gradient: 'g3',
                    title: { ar: 'التمكين الاقتصادي والمشروعات الصغيرة', en: 'Economic empowerment and small projects' },
                    date: { ar: 'أولوية تنفيذية عاجلة', en: 'Urgent implementation priority' },
                },
                {
                    motif: 'm-home', gradient: 'g4',
                    title: { ar: 'الدعم النفسي والاجتماعي للأطفال والأسر', en: 'Psychosocial support for children and families' },
                    date: { ar: 'أولوية تنفيذية عاجلة', en: 'Urgent implementation priority' },
                },
            ],
        },
    },
}

const arabic = computed(() => locale.value === 'ar')
const section = computed(() => content.section('news'))

const label = (value) => (arabic.value ? value.ar : value.en)

function tabLabel(id) {
    const labels = section.value?.tabLabels
    if (labels && typeof labels === 'object' && typeof labels[id] === 'string' && labels[id].trim()) {
        return labels[id]
    }

    return label(FALLBACK.tabs[id].label)
}

const tabs = computed(() => TAB_KEYS.map((id) => ({ id, label: tabLabel(id) })))

const eyebrowText = computed(() => content.text(section.value, 'eyebrow') || label(FALLBACK.eyebrow))
const heading = computed(() => content.text(section.value, 'title') || label(FALLBACK.title))

const panels = computed(() => Object.fromEntries(TAB_KEYS.map((tab) => {
    const fromCms = content.itemsByTab('news', tab)
    const entries = FALLBACK.tabs[tab].entries.map((fallback, index) => {
        const item = fromCms[index]
        const isFeatured = item
            ? (item.featured === true || item.featured === 1 || item.featured === '1')
            : fallback.featured === true

        return {
            key: content.text(item, 'key', `${tab}-${index + 1}`),
            title: content.text(item, 'title') || label(fallback.title),
            dateLabel: content.text(item, 'dateLabel') || label(fallback.date),
            body: content.text(item, 'body') || label(fallback.body || { ar: '', en: '' }),
            ctaLabel: content.text(item, 'ctaLabel') || label(fallback.cta || { ar: '', en: '' }),
            motifKey: content.text(item, 'motifKey', fallback.motif),
            gradient: fallback.gradient,
            url: content.safeUrl(content.text(item, 'url')),
            featured: isFeatured,
        }
    })

    const featuredIndex = Math.max(entries.findIndex((entry) => entry.featured), 0)

    return [tab, {
        featured: entries[featuredIndex],
        list: entries.filter((_, index) => index !== featuredIndex).slice(0, 3),
    }]
})))

const active = ref('news')
const buttons = ref([])

function select(index, focus = false) {
    const tab = tabs.value[index]
    if (!tab) {
        return
    }
    active.value = tab.id
    if (focus) {
        buttons.value[index]?.focus()
    }
}

function onKeydown(event, index) {
    let next = null
    if (event.key === 'ArrowLeft') {
        next = (index + 1) % tabs.value.length
    } else if (event.key === 'ArrowRight') {
        next = (index - 1 + tabs.value.length) % tabs.value.length
    } else if (event.key === 'Home') {
        next = 0
    } else if (event.key === 'End') {
        next = tabs.value.length - 1
    }
    if (next !== null) {
        event.preventDefault()
        select(next, true)
    }
}

function onDeepLink(event) {
    const requested = event.detail?.tab
    const index = tabs.value.findIndex((tab) => tab.id === requested)
    if (index >= 0) {
        select(index)
    }
}

onMounted(() => window.addEventListener('pcdr:open-tab', onDeepLink))
onBeforeUnmount(() => window.removeEventListener('pcdr:open-tab', onDeepLink))
</script>

<template>
    <section class="sec" id="news" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div class="eyebrow"><b>02</b><span>{{ eyebrowText }}</span></div>
                <h2 class="h2">{{ heading }}</h2>
            </div>

            <div data-tabs>
                <div class="tabs" role="tablist" :aria-label="label(FALLBACK.group)">
                    <button
                        v-for="(tab, index) in tabs"
                        :key="tab.id"
                        ref="buttons"
                        class="tab"
                        role="tab"
                        :id="`tab-${tab.id}`"
                        :aria-controls="`p-${tab.id}`"
                        :aria-selected="active === tab.id ? 'true' : 'false'"
                        :tabindex="active === tab.id ? 0 : -1"
                        type="button"
                        @click="select(index)"
                        @keydown="onKeydown($event, index)"
                    >{{ tab.label }}</button>
                </div>

                <div
                    v-for="tab in tabs"
                    :key="`panel-${tab.id}`"
                    class="panel"
                    role="tabpanel"
                    :id="`p-${tab.id}`"
                    :aria-labelledby="`tab-${tab.id}`"
                    :hidden="active !== tab.id"
                >
                    <div class="news">
                        <article class="feat">
                            <div class="thumb" :class="panels[tab.id].featured.gradient">
                                <svg aria-hidden="true"><use :href="`#${panels[tab.id].featured.motifKey}`" /></svg>
                            </div>
                            <h3>{{ panels[tab.id].featured.title }}</h3>
                            <div class="date"><svg class="ico" aria-hidden="true"><use href="#i-cal" /></svg>{{ panels[tab.id].featured.dateLabel }}</div>
                            <p>
                                {{ panels[tab.id].featured.body }}
                                <a
                                    v-if="panels[tab.id].featured.url"
                                    class="more"
                                    :href="panels[tab.id].featured.url"
                                >{{ panels[tab.id].featured.ctaLabel }}</a>
                                <span v-else-if="panels[tab.id].featured.ctaLabel" class="more">{{ panels[tab.id].featured.ctaLabel }}</span>
                            </p>
                        </article>
                        <ul class="list">
                            <li v-for="item in panels[tab.id].list" :key="item.key" class="li">
                                <div class="thumb" :class="item.gradient"><svg aria-hidden="true"><use :href="`#${item.motifKey}`" /></svg></div>
                                <div>
                                    <h4>
                                        <a v-if="item.url" :href="item.url">{{ item.title }}</a>
                                        <span v-else>{{ item.title }}</span>
                                    </h4>
                                    <div class="date"><svg class="ico" aria-hidden="true"><use href="#i-cal" /></svg>{{ item.dateLabel }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
