<script setup>
import { computed, ref, watch } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)
const logo = '/assets/pcdr-homepage/logo/pcdr-prototype-logo.jpg'

const chrome = computed(() => (locale.value === 'ar' ? {
    group: 'رسالتنا وأهدافنا ورؤيتنا',
    values: 'قيمنا المؤسسية',
} : {
    group: 'Our mission, objectives and vision',
    values: 'Our institutional values',
}))

const section = computed(() => content.section('about'))
const eyebrow = computed(() => content.text(section.value, 'eyebrow'))
const heading = computed(() => content.text(section.value, 'title'))

const tabs = computed(() => [
    { id: 'mission', domId: 'mission', label: content.text(section.value?.tabLabels, 'mission') },
    { id: 'objectives', domId: 'goals', label: content.text(section.value?.tabLabels, 'objectives') },
    { id: 'vision', domId: 'vision', label: content.text(section.value?.tabLabels, 'vision') },
].filter((tab) => tab.label))

const allItems = computed(() => content.items('about'))

function bodyFor(prefix) {
    return content.text(allItems.value.find((item) => String(item.key || '').startsWith(prefix)
        && content.text(item, 'body')), 'body')
}

const mission = computed(() => bodyFor('mission'))
const vision = computed(() => bodyFor('vision'))

const objectives = computed(() => content.items('about', 'objective-')
    .map((item) => ({
        key: content.text(item, 'key'),
        title: content.text(item, 'title') || content.text(item, 'label'),
    }))
    .filter((item) => item.title))

const values = computed(() => content.items('about', 'value-')
    .map((item) => ({
        key: content.text(item, 'key'),
        title: content.text(item, 'title') || content.text(item, 'label'),
    }))
    .filter((item) => item.title))

const active = ref('mission')
const buttons = ref([])

watch(tabs, (value) => {
    if (value.length && !value.some((tab) => tab.id === active.value)) {
        active.value = value[0].id
    }
})

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
    if (next !== null && tabs.value.length) {
        event.preventDefault()
        select(next, true)
    }
}
</script>

<template>
    <section class="sec" id="about" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div v-if="eyebrow" class="eyebrow"><b>{{ eyebrow }}</b></div>
                <h2 v-if="heading" class="h2">{{ heading }}</h2>
            </div>

            <div v-if="tabs.length" data-tabs>
                <div class="tabs" role="tablist" :aria-label="chrome.group">
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
                        <p v-if="mission">{{ mission }}</p>
                    </div>
                </div>

                <div
                    class="panel"
                    role="tabpanel"
                    id="about-goals"
                    aria-labelledby="about-goals-t"
                    :hidden="active !== 'objectives'"
                >
                    <ol v-if="objectives.length" class="goals">
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
                            <p v-if="vision">{{ vision }}</p>
                            <ul v-if="values.length" class="vals" :aria-label="chrome.values">
                                <li v-for="value in values" :key="value.key">{{ value.title }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
