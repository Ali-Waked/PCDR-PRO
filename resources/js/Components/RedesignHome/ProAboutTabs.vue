<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    mission: { type: Object, default: null },
    vision: { type: Object, default: null },
    objectives: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
    labels: { type: Object, required: true },
})

const tabs = computed(() => [
    { id: 'mission', label: props.labels.mission, visible: Boolean(props.mission) },
    { id: 'objectives', label: props.labels.objectives, visible: props.objectives.length > 0 },
    { id: 'vision', label: props.labels.vision, visible: Boolean(props.vision) || props.values.length > 0 },
].filter((tab) => tab.visible))

const active = ref(tabs.value[0]?.id || '')
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
</script>

<template>
    <div v-if="tabs.length" class="pcdr-pro-home pro-about">
        <div class="pro-tabs" role="tablist" :aria-label="labels.group">
            <button
                v-for="(tab, index) in tabs"
                :key="tab.id"
                ref="buttons"
                type="button"
                role="tab"
                :id="`pro-tab-${tab.id}`"
                :aria-controls="`pro-panel-${tab.id}`"
                :aria-selected="active === tab.id ? 'true' : 'false'"
                :tabindex="active === tab.id ? 0 : -1"
                @click="select(index)"
                @keydown="onKeydown($event, index)"
            >{{ tab.label }}</button>
        </div>

        <div
            v-if="mission"
            id="pro-panel-mission"
            role="tabpanel"
            :aria-labelledby="'pro-tab-mission'"
            :hidden="active !== 'mission'"
            class="pro-panel"
        >
            <h4 v-if="mission.title">{{ mission.title }}</h4>
            <p>{{ mission.body }}</p>
        </div>

        <div
            v-if="objectives.length"
            id="pro-panel-objectives"
            role="tabpanel"
            aria-labelledby="pro-tab-objectives"
            :hidden="active !== 'objectives'"
            class="pro-panel"
        >
            <ol class="pro-goals">
                <li v-for="objective in objectives" :key="objective.key || objective.title">{{ objective.title }}</li>
            </ol>
        </div>

        <div
            v-if="vision || values.length"
            id="pro-panel-vision"
            role="tabpanel"
            aria-labelledby="pro-tab-vision"
            :hidden="active !== 'vision'"
            class="pro-panel"
        >
            <h4 v-if="vision?.title">{{ vision.title }}</h4>
            <p v-if="vision?.body">{{ vision.body }}</p>
            <ul v-if="values.length" class="pro-values" :aria-label="labels.values">
                <li v-for="value in values" :key="value.key || value.title">{{ value.title }}</li>
            </ul>
        </div>
    </div>
</template>
