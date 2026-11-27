<script setup>
import { computed, ref, watch } from 'vue'
import { useHomepageContent } from '../../composables/useHomepageContent'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    sections: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const content = useHomepageContent(() => props.sections)

// Publications are not a homepage tab; news and projects come from the CMS.
const TAB_KEYS = ['news', 'projects']
const GRADIENTS = ['g1', 'g2', 'g3', 'g4', 'g5', 'g6']

const chrome = computed(() => (locale.value === 'ar' ? {
    group: 'الأخبار والمشاريع',
    readMore: 'أكمل القراءة',
} : {
    group: 'News and projects',
    readMore: 'Read more',
}))

const section = computed(() => content.section('news'))
const eyebrow = computed(() => content.text(section.value, 'eyebrow'))
const heading = computed(() => content.text(section.value, 'title'))

const tabs = computed(() => TAB_KEYS
    .map((id) => ({ id, label: content.text(section.value?.tabLabels, id) }))
    .filter((tab) => tab.label))

function entry(item, index) {
    const url = content.safeUrl(content.text(item, 'url'))
    return {
        key: content.text(item, 'key', `news-${index + 1}`),
        title: content.text(item, 'title'),
        dateLabel: content.text(item, 'dateLabel'),
        body: content.text(item, 'body') || content.text(item, 'caption'),
        ctaLabel: content.text(item, 'ctaLabel') || (url ? chrome.value.readMore : ''),
        motifKey: content.text(item, 'motifKey') || 'm-sun',
        gradient: GRADIENTS[index % GRADIENTS.length],
        url,
    }
}

const panels = computed(() => Object.fromEntries(TAB_KEYS.map((tab) => {
    const fromCms = content.itemsByTab('news', tab)
    const preferred = fromCms.findIndex((item) => item.featured === true
        || item.featured === 1
        || item.featured === '1')
    const featuredIndex = preferred >= 0 ? preferred : 0
    const featuredItem = fromCms[featuredIndex]
    const listItems = fromCms.filter((_, index) => index !== featuredIndex)

    return [tab, {
        featured: featuredItem ? entry(featuredItem, 0) : null,
        list: listItems.map((item, index) => entry(item, index + 1)),
    }]
})))

const active = ref(TAB_KEYS[0])
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
    if (event.key === 'ArrowLeft') {
        next = (index + 1) % tabs.value.length
    } else if (event.key === 'ArrowRight') {
        next = (index - 1 + tabs.value.length) % tabs.value.length
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
    <section class="sec" id="news" style="padding-top:0">
        <div class="wrap">
            <div class="deco" aria-hidden="true"></div>
            <div style="margin-top:44px">
                <div v-if="eyebrow" class="eyebrow"><b>{{ eyebrow }}</b></div>
                <h2 v-if="heading" class="h2">{{ heading }}</h2>
            </div>

            <div data-tabs>
                <div v-if="tabs.length" class="tabs" role="tablist" :aria-label="chrome.group">
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
                    <div v-if="panels[tab.id].featured || panels[tab.id].list.length" class="news">
                        <article v-if="panels[tab.id].featured" class="feat">
                            <div class="thumb" :class="panels[tab.id].featured.gradient">
                                <svg aria-hidden="true"><use :href="`#${panels[tab.id].featured.motifKey}`" /></svg>
                            </div>
                            <h3 v-if="panels[tab.id].featured.title">{{ panels[tab.id].featured.title }}</h3>
                            <div v-if="panels[tab.id].featured.dateLabel" class="date"><svg class="ico" aria-hidden="true"><use href="#i-cal" /></svg>{{ panels[tab.id].featured.dateLabel }}</div>
                            <p v-if="panels[tab.id].featured.body || panels[tab.id].featured.ctaLabel">
                                {{ panels[tab.id].featured.body }}
                                <a
                                    v-if="panels[tab.id].featured.url"
                                    class="more"
                                    :href="panels[tab.id].featured.url"
                                >{{ panels[tab.id].featured.ctaLabel }}</a>
                                <span v-else-if="panels[tab.id].featured.ctaLabel" class="more">{{ panels[tab.id].featured.ctaLabel }}</span>
                            </p>
                        </article>
                        <ul v-if="panels[tab.id].list.length" class="list">
                            <li v-for="item in panels[tab.id].list" :key="item.key" class="li">
                                <div class="thumb" :class="item.gradient"><svg aria-hidden="true"><use :href="`#${item.motifKey}`" /></svg></div>
                                <div>
                                    <h4 v-if="item.title">
                                        <a v-if="item.url" :href="item.url">{{ item.title }}</a>
                                        <span v-else>{{ item.title }}</span>
                                    </h4>
                                    <div v-if="item.dateLabel" class="date"><svg class="ico" aria-hidden="true"><use href="#i-cal" /></svg>{{ item.dateLabel }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
