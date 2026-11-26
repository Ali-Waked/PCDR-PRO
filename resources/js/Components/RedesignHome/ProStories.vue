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
        motif: 'm-bars', gradient: 'g3',
        title: { ar: 'مشروع منزلي يعيد الدخل لأسرة', en: 'A home-based project restoring income to a family' },
        body: { ar: 'برنامج التمكين الاقتصادي. تُنشر القصة بعد موافقة المستفيد وبما يحمي خصوصيته.', en: 'Economic empowerment programme. The story is published with the beneficiary’s consent and in a way that protects their privacy.' },
    },
    {
        motif: 'm-wheat', gradient: 'g2',
        title: { ar: 'مطبخ مجتمعي يخدم الحي', en: 'A community kitchen serving the neighbourhood' },
        body: { ar: 'برنامج الأمن الغذائي. تُنشر القصة بعد موافقة المستفيد وبما يحمي خصوصيته.', en: 'Food security programme. The story is published with the beneficiary’s consent and in a way that protects their privacy.' },
    },
    {
        motif: 'm-home', gradient: 'g4',
        title: { ar: 'دعم نفسي واجتماعي للأطفال', en: 'Psychosocial support for children' },
        body: { ar: 'برنامج الحماية والرعاية. تُنشر القصة بعد موافقة ولي الأمر وبما يحمي خصوصية الطفل.', en: 'Protection and care programme. The story is published with the guardian’s consent and in a way that protects the child’s privacy.' },
    },
]

const arabic = computed(() => locale.value === 'ar')
const label = (value) => (arabic.value ? value.ar : value.en)
const section = computed(() => content.section('stories'))

const stories = computed(() => {
    const fromCms = content.items('stories')
    return FALLBACK.map((fallback, index) => {
        const item = fromCms[index]
        return {
            key: content.text(item, 'key', `story-${index + 1}`),
            title: content.text(item, 'title') || label(fallback.title),
            body: content.text(item, 'body') || label(fallback.body),
            motifKey: content.text(item, 'motifKey', fallback.motif),
            gradient: fallback.gradient,
        }
    })
})
</script>

<template>
    <section class="sec" id="stories" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div class="eyebrow"><b>05</b><span>{{ content.text(section, 'eyebrow') || (arabic ? 'الأثر' : 'Impact') }}</span></div>
                <h2 class="h2">{{ content.text(section, 'title') || (arabic ? 'قصص نجاح' : 'Success stories') }}</h2>
            </div>
            <ul class="stories">
                <li v-for="story in stories" :key="story.key" class="story">
                    <div class="thumb" :class="story.gradient"><svg aria-hidden="true"><use :href="`#${story.motifKey}`" /></svg></div>
                    <div>
                        <h3>{{ story.title }}</h3>
                        <p>{{ story.body }}</p>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
