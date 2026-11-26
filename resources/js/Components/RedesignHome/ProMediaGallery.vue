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
        motif: 'm-people', gradient: 'g1',
        caption: { ar: 'الاستجابة الطارئة وتوزيع المساعدات على الأسر المتضررة', en: 'Emergency response and distribution of assistance to affected families' },
    },
    {
        motif: 'm-wheat', gradient: 'g2',
        caption: { ar: 'توزيع الطرود الغذائية', en: 'Distribution of food parcels' },
    },
    {
        motif: 'm-home', gradient: 'g3',
        caption: { ar: 'الدعم النفسي والاجتماعي للأطفال', en: 'Psychosocial support for children' },
    },
    {
        motif: 'm-drop', gradient: 'g4',
        caption: { ar: 'نظافة مراكز الإيواء', en: 'Cleanliness of shelter centres' },
    },
    {
        motif: 'm-bars', gradient: 'g5',
        caption: { ar: 'دعم المشروعات الصغيرة', en: 'Support for small projects' },
    },
]

const arabic = computed(() => locale.value === 'ar')
const section = computed(() => content.section('albums'))

const heading = computed(() => content.text(section.value, 'title')
    || (arabic.value ? 'ألبوم الصور' : 'Photo album'))

const albums = computed(() => {
    const fromCms = content.items('albums')
    return FALLBACK.map((fallback, index) => {
        const item = fromCms[index]
        const caption = arabic.value ? fallback.caption.ar : fallback.caption.en
        return {
            key: content.text(item, 'key', `album-${index + 1}`),
            caption: content.text(item, 'caption') || content.text(item, 'title') || caption,
            motifKey: content.text(item, 'motifKey', fallback.motif),
            gradient: fallback.gradient,
            media: content.mediaUrl(item),
        }
    })
})

function photoStyle(album) {
    return album.media ? { '--photo': `url("${album.media.replace(/"/g, '%22')}")` } : null
}
</script>

<template>
    <section class="sec" id="albums" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div class="eyebrow"><b>03</b><span>{{ arabic ? 'الوسائط' : 'Media' }}</span></div>
                <h2 class="h2">{{ heading }}</h2>
            </div>
            <div class="album">
                <figure
                    v-for="(album, index) in albums"
                    :key="album.key"
                    class="ph"
                    :class="[{ big: index === 0 }, album.gradient]"
                    :style="photoStyle(album)"
                >
                    <svg aria-hidden="true"><use :href="`#${album.motifKey}`" /></svg>
                    <figcaption>{{ album.caption }}</figcaption>
                </figure>
            </div>
        </div>
    </section>
</template>
