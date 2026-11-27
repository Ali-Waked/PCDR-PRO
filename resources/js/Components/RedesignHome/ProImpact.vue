<script setup>
import { computed } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const content = useHomepageContent(() => props.sections)
const impactSection = computed(() => content.section('impact'))

const heading = computed(() => content.text(impactSection.value, 'title'))
const caveat = computed(() => content.text(impactSection.value, 'body'))

function displayValue(item) {
    return content.text(item, 'display')
        || (item?.value !== null && item?.value !== undefined ? String(item.value) : '')
}

const stats = computed(() => content.items('impact', 'stat-').map((item, index) => ({
    key: content.text(item, 'key', `stat-${index + 1}`),
    title: content.text(item, 'title'),
    body: content.text(item, 'body'),
    display: displayValue(item),
    unit: content.text(item, 'unit'),
    url: content.safeUrl(content.text(item, 'url')),
})))

const targets = computed(() => content.items('impact', 'target-').map((item, index) => ({
    key: content.text(item, 'key', `target-${index + 1}`),
    display: content.text(item, 'display')
        || (item?.value !== null && item?.value !== undefined ? String(item.value) : ''),
    label: content.text(item, 'body') || content.text(item, 'title'),
})).filter((target) => target.display || target.label))
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
                <h2 v-if="heading">{{ heading }}</h2>
            </div>

            <div v-if="stats.length" class="stats">
                <component
                    :is="stat.url ? 'a' : 'div'"
                    v-for="stat in stats"
                    :key="stat.key"
                    class="stat"
                    :href="stat.url || undefined"
                >
                    <h3 v-if="stat.title">{{ stat.title }} <span class="arr"><svg class="directional-flip" aria-hidden="true"><use href="#i-arrow" /></svg></span></h3>
                    <p v-if="stat.body">{{ stat.body }}</p>
                    <div class="num"><b>{{ stat.display }}</b><span v-if="stat.unit">{{ stat.unit }}</span></div>
                </component>
            </div>

            <div v-if="targets.length" class="targets" id="targets">
                <div v-for="target in targets" :key="target.key" class="tg">
                    <b>{{ target.display }}</b>
                    <span>{{ target.label }}</span>
                </div>
            </div>
            <p v-if="caveat" class="fine">{{ caveat }}</p>
        </div>
    </section>
</template>
