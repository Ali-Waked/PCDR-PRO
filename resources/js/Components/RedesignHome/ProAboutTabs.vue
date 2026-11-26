<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)
const logo = '/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg'

const FALLBACK = {
    eyebrow: { ar: 'من نحن', en: 'About us' },
    title: { ar: 'رسالتنا ورؤيتنا', en: 'Our mission and vision' },
    group: { ar: 'رسالتنا وأهدافنا ورؤيتنا', en: 'Our mission, objectives and vision' },
    valuesLabel: { ar: 'قيمنا المؤسسية', en: 'Our institutional values' },
    mission: {
        ar: 'تنسيق الجهود الإنسانية وتعزيز الشراكات المحلية والدولية، وتقديم استجابات إغاثية وتنموية متكاملة، وتمكين الفئات الأكثر هشاشة، وتحسين الظروف المعيشية للأسر المتضررة والفقيرة، من خلال برامج مهنية ومستدامة تقوم على المشاركة المجتمعية والشفافية والمساءلة.',
        en: 'Coordinating humanitarian efforts and strengthening local and international partnerships, providing integrated relief and development responses, empowering the most vulnerable groups, and improving the living conditions of affected and poor families through professional and sustainable programmes grounded in community participation, transparency and accountability.',
    },
    vision: {
        ar: 'الريادة في العمل الإنساني والتنموي التشاركي، والمساهمة في بناء مجتمع فلسطيني أكثر قدرة على الصمود والتعافي، يتمتع فيه الأفراد والأسر بالكرامة والعدالة وتكافؤ الفرص.',
        en: 'Leadership in participatory humanitarian and development action, contributing to building a more resilient and recovering Palestinian society in which individuals and families enjoy dignity, justice and equal opportunities.',
    },
    objectives: [
        { ar: 'تطوير منظومة متكاملة للاستجابة الإنسانية وإدارة الأزمات والكوارث.', en: 'Develop an integrated system for humanitarian response and crisis and disaster management.' },
        { ar: 'تعزيز الأمن الغذائي وتحسين الظروف المعيشية للأسر الفقيرة والمتضررة.', en: 'Enhance food security and improve living conditions for poor and affected families.' },
        { ar: 'تمكين الأسر اقتصادياً وتعزيز فرص العمل والإنتاج ومكافحة الفقر.', en: 'Empower families economically and promote work and production opportunities and combat poverty.' },
        { ar: 'توفير خدمات الحماية الاجتماعية والرعاية النفسية والاجتماعية للفئات الأكثر هشاشة.', en: 'Provide social protection and psychosocial care services for the most vulnerable groups.' },
        { ar: 'تحسين الصحة العامة والمياه والإصحاح البيئي.', en: 'Improve public health, water and environmental sanitation.' },
        { ar: 'دعم التعليم وتنمية قدرات الأطفال والشباب.', en: 'Support education and develop the capacities of children and young people.' },
        { ar: 'المساهمة في التعافي المبكر وتحسين ظروف السكن وإعادة تأهيل المرافق المجتمعية.', en: 'Contribute to early recovery, improve housing conditions and rehabilitate community facilities.' },
        { ar: 'تعزيز المشاركة المجتمعية والتطوع والعمل التشاركي.', en: 'Promote community participation, volunteering and participatory action.' },
        { ar: 'تطوير الشراكات المحلية والدولية واستقطاب التمويل.', en: 'Develop local and international partnerships and attract funding.' },
        { ar: 'بناء منظومة إعلام واتصال ومناصرة مجتمعية فاعلة.', en: 'Build an effective media, communication and community advocacy system.' },
        { ar: 'تعزيز البحث والدراسات وتقييم الاحتياجات وقياس أثر التدخلات.', en: 'Promote research, studies, needs assessment and measurement of intervention impact.' },
    ],
    values: [
        { ar: 'الإنسانية وصون الكرامة', en: 'Humanity and preservation of dignity' },
        { ar: 'العدالة وعدم التمييز', en: 'Justice and non-discrimination' },
        { ar: 'النزاهة والشفافية والمساءلة', en: 'Integrity, transparency and accountability' },
        { ar: 'المشاركة المجتمعية والتطوع', en: 'Community participation and volunteering' },
        { ar: 'المهنية والكفاءة والاستدامة', en: 'Professionalism, efficiency and sustainability' },
        { ar: 'الشراكة والتكامل', en: 'Partnership and integration' },
        { ar: 'الاستجابة السريعة والمرونة', en: 'Rapid response and flexibility' },
        { ar: 'حماية الفئات الأكثر هشاشة', en: 'Protection of the most vulnerable groups' },
        { ar: 'احترام الخصوصية وحماية البيانات', en: 'Respect for privacy and data protection' },
        { ar: 'التعلم والتحسين المستمر', en: 'Continuous learning and improvement' },
    ],
}

const arabic = computed(() => locale.value === 'ar')
const label = (value) => (arabic.value ? value.ar : value.en)
const section = computed(() => content.section('about'))
const allItems = computed(() => content.items('about'))

function itemFor(prefix, field = 'body') {
    return allItems.value.find((item) => String(item.key || '').toLowerCase().startsWith(prefix)
        && content.text(item, field))
}

function tabLabel(id, fallback) {
    const labels = section.value?.tabLabels
    if (labels && typeof labels === 'object' && typeof labels[id] === 'string' && labels[id].trim()) {
        return labels[id]
    }

    return label(fallback)
}

const tabs = computed(() => [
    { id: 'mission', domId: 'mission', label: tabLabel('mission', { ar: 'رسالتنا', en: 'Our mission' }) },
    { id: 'objectives', domId: 'goals', label: tabLabel('objectives', { ar: 'أهدافنا', en: 'Our objectives' }) },
    { id: 'vision', domId: 'vision', label: tabLabel('vision', { ar: 'رؤيتنا', en: 'Our vision' }) },
])

const mission = computed(() => content.text(itemFor('mission'), 'body') || label(FALLBACK.mission))
const vision = computed(() => content.text(itemFor('vision'), 'body') || label(FALLBACK.vision))

const objectives = computed(() => {
    const fromCms = content.items('about', 'objective-')
    return FALLBACK.objectives.map((fallback, index) => ({
        key: content.text(fromCms[index], 'key', `objective-${index + 1}`),
        title: content.text(fromCms[index], 'title') || label(fallback),
    }))
})

const values = computed(() => {
    const fromCms = content.items('about', 'value-')
    return FALLBACK.values.map((fallback, index) => ({
        key: content.text(fromCms[index], 'key', `value-${index + 1}`),
        title: content.text(fromCms[index], 'title') || label(fallback),
    }))
})

const active = ref('mission')
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
    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        next = (index - 1 + tabs.value.length) % tabs.value.length
    } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        next = (index + 1) % tabs.value.length
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
    const requested = event.detail?.tab === 'goals' ? 'objectives' : event.detail?.tab
    const index = tabs.value.findIndex((tab) => tab.id === requested)
    if (index >= 0) {
        select(index)
    }
}

onMounted(() => window.addEventListener('pcdr:open-tab', onDeepLink))
onBeforeUnmount(() => window.removeEventListener('pcdr:open-tab', onDeepLink))
</script>

<template>
    <section class="sec" id="about" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div class="eyebrow"><b>04</b><span>{{ content.text(section, 'eyebrow') || label(FALLBACK.eyebrow) }}</span></div>
                <h2 class="h2">{{ content.text(section, 'title') || label(FALLBACK.title) }}</h2>
            </div>

            <div data-tabs>
                <div class="tabs" role="tablist" :aria-label="label(FALLBACK.group)">
                    <button
                        v-for="(tab, index) in tabs"
                        :key="tab.id"
                        ref="buttons"
                        class="tab"
                        role="tab"
                        :id="`about-${tab.domId}-t`"
                        :aria-controls="`about-${tab.domId}`"
                        :aria-selected="active === tab.id ? 'true' : 'false'"
                        :tabindex="active === tab.id ? 0 : -1"
                        type="button"
                        @click="select(index)"
                        @keydown="onKeydown($event, index)"
                    >{{ tab.label }}</button>
                </div>

                <div
                    class="panel"
                    role="tabpanel"
                    id="about-mission"
                    aria-labelledby="about-mission-t"
                    :hidden="active !== 'mission'"
                >
                    <div class="mission">
                        <div class="mark"><img :src="logo" alt="" width="210" height="210"></div>
                        <p>{{ mission }}</p>
                    </div>
                </div>

                <div
                    class="panel"
                    role="tabpanel"
                    id="about-goals"
                    aria-labelledby="about-goals-t"
                    :hidden="active !== 'objectives'"
                >
                    <ol class="goals">
                        <li v-for="objective in objectives" :key="objective.key">{{ objective.title }}</li>
                    </ol>
                </div>

                <div
                    class="panel"
                    role="tabpanel"
                    id="about-vision"
                    aria-labelledby="about-vision-t"
                    :hidden="active !== 'vision'"
                >
                    <div class="mission">
                        <div class="mark"><img :src="logo" alt="" width="210" height="210"></div>
                        <div>
                            <p>{{ vision }}</p>
                            <ul class="vals" :aria-label="label(FALLBACK.valuesLabel)">
                                <li v-for="value in values" :key="value.key">{{ value.title }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
