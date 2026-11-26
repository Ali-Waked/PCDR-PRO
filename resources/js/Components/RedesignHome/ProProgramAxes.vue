<script setup>
import { computed } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)

const FALLBACK = [
    {
        ar: { title: 'الاستجابة الإنسانية والإغاثية وإدارة الكوارث', body: 'منظومة استجابة سريعة ومنظمة للأزمات، وتلبية الاحتياجات الأساسية للأسر المتضررة.' },
        en: { title: 'Humanitarian response, relief and disaster management', body: 'A rapid, organised response system for crises, meeting the essential needs of affected families.' },
    },
    {
        ar: { title: 'الأمن الغذائي والتغذية', body: 'وصول عادل ومنتظم للمساعدات الغذائية، ومطابخ مجتمعية وحدائق منزلية.' },
        en: { title: 'Food security and nutrition', body: 'Fair and regular access to food assistance, community kitchens and home gardens.' },
    },
    {
        ar: { title: 'التنمية الاقتصادية وسبل العيش', body: 'الانتقال بالأسر من الاعتماد على المساعدات إلى الإنتاج والدخل المستدام.' },
        en: { title: 'Economic development and livelihoods', body: 'Moving families from dependence on aid to production and sustainable income.' },
    },
    {
        ar: { title: 'الحماية الاجتماعية والرعاية النفسية', body: 'رعاية الأيتام والأشخاص ذوي الإعاقة والنساء المعيلات وكبار السن.' },
        en: { title: 'Social protection and psychological care', body: 'Care for orphans, persons with disabilities, female-headed households and older people.' },
    },
    {
        ar: { title: 'الصحة العامة والمياه والإصحاح البيئي', body: 'مياه شرب آمنة، وحملات نظافة وتوعية، وتحسين المرافق في مراكز الإيواء.' },
        en: { title: 'Public health, water and environmental sanitation', body: 'Safe drinking water, cleanliness and awareness campaigns, and improved facilities in shelters.' },
    },
    {
        ar: { title: 'التعليم وبناء القدرات وتنمية الطفولة والشباب', body: 'الحد من الآثار التعليمية للأزمات وتنمية مهارات الأطفال والشباب.' },
        en: { title: 'Education, capacity building and child and youth development', body: 'Reducing the educational impact of crises and developing children’s and young people’s skills.' },
    },
    {
        ar: { title: 'الإسكان والتعافي المبكر وإعادة الإعمار المجتمعي', body: 'ترميم المساكن وإعادة تأهيل المرافق المجتمعية الصغيرة.' },
        en: { title: 'Housing, early recovery and community reconstruction', body: 'Rehabilitating homes and small community facilities.' },
    },
    {
        ar: { title: 'المشاركة المجتمعية والتطوع والمسؤولية الاجتماعية', body: 'لجان عمل تشاركي وفرق تطوعية وشراكة مع القطاع الخاص.' },
        en: { title: 'Community participation, volunteering and social responsibility', body: 'Participatory action committees, volunteer teams and partnership with the private sector.' },
    },
    {
        ar: { title: 'الشراكات المحلية والدولية واستقطاب التمويل', body: 'علاقات مؤسسية مع الجهات المحلية والدولية ووحدة لتطوير المشاريع.' },
        en: { title: 'Local and international partnerships and fundraising', body: 'Institutional relations with local and international bodies and a project development unit.' },
    },
    {
        ar: { title: 'الإعلام والاتصال والمناصرة المجتمعية', body: 'توثيق الاحتياجات ونتائج التدخلات والتواصل مع الجمهور والشركاء.' },
        en: { title: 'Media, communication and community advocacy', body: 'Documenting needs and intervention results and communicating with the public and partners.' },
    },
    {
        ar: { title: 'البحث والدراسات وتقييم الاحتياجات', body: 'مسوح وخرائط احتياجات وتقييم أثر المشاريع لبناء قرارات موثوقة.' },
        en: { title: 'Research, studies and needs assessment', body: 'Surveys, needs maps and project impact assessments to support reliable decisions.' },
    },
]

const arabic = computed(() => locale.value === 'ar')
const section = computed(() => content.section('programs'))

const numbering = computed(() => {
    const value = content.text(section.value, 'eyebrow')
    return /^\d+$/.test(value) ? value : '01'
})

const eyebrow = computed(() => {
    const value = content.text(section.value, 'eyebrow')
    return /^\d+$/.test(value)
        ? (arabic.value ? 'برامجنا' : 'Our programmes')
        : (value || (arabic.value ? 'برامجنا' : 'Our programmes'))
})

const title = computed(() => content.text(section.value, 'title')
    || (arabic.value ? 'محاور عملنا' : 'Our areas of work'))

const axes = computed(() => {
    const fromCms = content.items('programs')
    return FALLBACK.map((fallback, index) => {
        const item = fromCms[index]
        return {
            key: content.text(item, 'key', `axis-${index + 1}`),
            title: content.text(item, 'title') || (arabic.value ? fallback.ar.title : fallback.en.title),
            body: content.text(item, 'body') || (arabic.value ? fallback.ar.body : fallback.en.body),
        }
    })
})
</script>

<template>
    <section class="sec" id="programs" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div class="eyebrow"><b>{{ numbering }}</b><span>{{ eyebrow }}</span></div>
                <h2 class="h2">{{ title }}</h2>
            </div>
            <ul class="axes">
                <li v-for="(axis, index) in axes" :key="axis.key" class="axis">
                    <b>{{ String(index + 1).padStart(2, '0') }}</b>
                    <h3>{{ axis.title }}</h3>
                    <p>{{ axis.body }}</p>
                </li>
            </ul>
        </div>
    </section>
</template>
