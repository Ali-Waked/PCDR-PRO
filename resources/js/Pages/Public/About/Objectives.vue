<script setup>
import { computed } from 'vue'
import ProStandalonePage from '../../../Components/RedesignHome/ProStandalonePage.vue'
import { aboutObjectivesPage } from '../../../Support/standaloneChildPage'
import { useI18n } from '../../../composables/useI18n'

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    meta: { type: Object, default: null },
})

const { locale } = useI18n()
const page = computed(() => aboutObjectivesPage(props.content, locale.value))
</script>

<template>
    <ProStandalonePage :page="page" :meta="meta" />
</template>
