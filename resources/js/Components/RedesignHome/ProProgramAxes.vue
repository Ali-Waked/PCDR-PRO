<script setup>
import { computed } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const content = useHomepageContent(() => props.sections)
const programSection = computed(() => content.section('programs'))

const eyebrow = computed(() => content.text(programSection.value, 'eyebrow'))
const numbering = computed(() => (/^\d+$/.test(eyebrow.value) ? eyebrow.value : ''))
const eyebrowLabel = computed(() => (numbering.value
    ? content.text(programSection.value, 'body')
    : eyebrow.value))
const title = computed(() => content.text(programSection.value, 'title'))

const axes = computed(() => content.items('programs').map((item, index) => ({
    key: content.text(item, 'key', `axis-${index + 1}`),
    title: content.text(item, 'title'),
    body: content.text(item, 'body'),
})).filter((axis) => axis.title || axis.body))
</script>

<template>
    <section class="sec" id="programs" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div v-if="numbering || eyebrowLabel" class="eyebrow">
                    <b v-if="numbering">{{ numbering }}</b>
                    <span v-if="eyebrowLabel">{{ eyebrowLabel }}</span>
                </div>
                <h2 v-if="title" class="h2">{{ title }}</h2>
            </div>
            <ul v-if="axes.length" class="axes">
                <li v-for="(axis, index) in axes" :key="axis.key" class="axis">
                    <b>{{ String(index + 1).padStart(2, '0') }}</b>
                    <h3 v-if="axis.title">{{ axis.title }}</h3>
                    <p v-if="axis.body">{{ axis.body }}</p>
                </li>
            </ul>
        </div>
    </section>
</template>
