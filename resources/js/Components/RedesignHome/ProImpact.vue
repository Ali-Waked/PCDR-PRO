<script setup>
import { computed } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)

const FALLBACK = {
    heading: { ar: 'بصمتنا في المجتمع', en: 'Our imprint in the community' },
    caveat: {
        ar: 'مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.',
        en: 'Preliminary planning targets for 2027, subject to review once available funding and implementation capacity have been determined.',
    },
    stats: [
        {
            ar: { title: 'مجالات عملنا', body: 'حسب أهدافنا الاستراتيجية', unit: 'محوراً' },
            en: { title: 'Our areas of work', body: 'According to our strategic objectives', unit: 'axes' },
            display: '11',
            url: '#programs',
        },
        {
            ar: { title: 'مستهدفنا لعام 2027', body: 'أسرة مستفيدة من التدخلات الإنسانية', unit: 'أسرة' },
            en: { title: 'Our 2027 target', body: 'Families benefiting from humanitarian interventions', unit: 'families' },
            display: '3,000',
            url: '#targets',
        },
    ],
    targets: [
        { display: '1,500', ar: 'أسرة من برامج الأمن الغذائي', en: 'families from food security programmes' },
        { display: '50', ar: 'مشروعاً صغيراً مدعوماً', en: 'supported small projects' },
        { display: '100', ar: 'يتيم من برامج الرعاية', en: 'orphans from care programmes' },
        { display: '100', ar: 'شخص من ذوي الإعاقة', en: 'persons with disabilities' },
        { display: '500', ar: 'مستفيد من الدعم النفسي والاجتماعي', en: 'beneficiaries of psychosocial support' },
        { display: '200', ar: 'شاب وشابة في التدريب', en: 'young men and women in training' },
        { display: '100', ar: 'متطوع مسجل', en: 'registered volunteers' },
        { display: '10', ar: 'لجان مجتمعية نشطة', en: 'active community committees' },
    ],
}

const arabic = computed(() => locale.value === 'ar')

const heading = computed(() => content.text(content.section('impact'), 'title')
    || (arabic.value ? FALLBACK.heading.ar : FALLBACK.heading.en))

const caveat = computed(() => content.text(content.section('impact'), 'body')
    || (arabic.value ? FALLBACK.caveat.ar : FALLBACK.caveat.en))

const stats = computed(() => {
    const fromCms = content.items('impact', 'stat-')
    return FALLBACK.stats.map((fallback, index) => {
        const item = fromCms[index]
        return {
            key: content.text(item, 'key', `stat-${index + 1}`),
            title: content.text(item, 'title') || (arabic.value ? fallback.ar.title : fallback.en.title),
            body: content.text(item, 'body') || (arabic.value ? fallback.ar.body : fallback.en.body),
            display: content.text(item, 'display')
                || (item?.value !== null && item?.value !== undefined ? String(item.value) : fallback.display),
            unit: content.text(item, 'unit') || (arabic.value ? fallback.ar.unit : fallback.en.unit),
            url: content.safeUrl(content.text(item, 'url')) || fallback.url,
        }
    })
})

const targets = computed(() => {
    const fromCms = content.items('impact', 'target-')
    return FALLBACK.targets.map((fallback, index) => {
        const item = fromCms[index]
        return {
            key: content.text(item, 'key', `target-${index + 1}`),
            display: content.text(item, 'display')
                || (item?.value !== null && item?.value !== undefined ? String(item.value) : fallback.display),
            label: content.text(item, 'body')
                || content.text(item, 'title')
                || (arabic.value ? fallback.ar : fallback.en),
        }
    })
})
</script>

<template>
    <section class="sec" id="impact">
        <div class="wrap">
            <div class="imprint-head">
                <svg class="rings" viewBox="0 0 160 184" fill="none" stroke-width="3" aria-hidden="true">
                    <ellipse cx="80" cy="92" rx="72" ry="86" class="ra" stroke-dasharray="46 7 14 7" />
                    <ellipse cx="80" cy="92" rx="63" ry="76" class="rb" stroke-dasharray="20 6" />
                    <ellipse cx="80" cy="92" rx="55" ry="66" class="ra" stroke-dasharray="60 8 10 8" />
                    <ellipse cx="80" cy="92" rx="47" ry="57" class="rb" stroke-dasharray="26 6 8 6" />
                    <ellipse cx="80" cy="92" rx="39" ry="48" class="ra" stroke-dasharray="34 7" />
                    <ellipse cx="80" cy="92" rx="31" ry="39" class="rb" stroke-dasharray="16 6 30 6" />
                    <ellipse cx="80" cy="92" rx="23" ry="30" class="ra" stroke-dasharray="40 6" />
                    <ellipse cx="80" cy="92" rx="15" ry="20" class="rb" />
                    <ellipse cx="80" cy="92" rx="7" ry="10" class="ra" />
                </svg>
                <h2>{{ heading }}</h2>
            </div>

            <div class="stats">
                <a v-for="stat in stats" :key="stat.key" class="stat" :href="stat.url">
                    <h3>{{ stat.title }} <span class="arr"><svg aria-hidden="true"><use href="#i-arrow" /></svg></span></h3>
                    <p>{{ stat.body }}</p>
                    <div class="num"><b>{{ stat.display }}</b><span>{{ stat.unit }}</span></div>
                </a>
            </div>

            <div class="targets" id="targets">
                <div v-for="target in targets" :key="target.key" class="tg">
                    <b>{{ target.display }}</b>
                    <span>{{ target.label }}</span>
                </div>
            </div>
            <p class="fine">{{ caveat }}</p>
        </div>
    </section>
</template>
