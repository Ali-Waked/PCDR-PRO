<script setup>
import { computed } from 'vue'
import { useI18n } from '../../composables/useI18n'

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 18 },
    strokeWidth: { type: [Number, String], default: 1.8 },
})

const paths = {
    menu: ['M4 7h16', 'M4 12h16', 'M4 17h16'],
    search: ['M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z', 'm21 21-4.3-4.3'],
    chevronDown: ['m6 9 6 6 6-6'],
    chevronRight: ['m9 6 6 6-6 6'],
    chevronLeft: ['m15 18-6-6 6-6'],
    panel: ['M3 4h18v16H3z', 'M9 4v16'],
    layout: ['M3 4h18v16H3z', 'M3 9h18', 'M9 9v11'],
    file: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6', 'M8 13h8', 'M8 17h6'],
    home: ['m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z', 'M9 21V12h6v9'],
    layers: ['m12 2 9 5-9 5-9-5 9-5Z', 'm3 12 9 5 9-5', 'm3 17 9 5 9-5'],
    newspaper: ['M4 4h16v16H4z', 'M8 8h8', 'M8 12h8', 'M8 16h5'],
    folder: ['M3 6h7l2 2h9v11a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z'],
    image: ['M3 4h18v16H3z', 'm8 16 3-3 2 2 3-4 4 5', 'M8.5 9h.01'],
    mail: ['M3 5h18v14H3z', 'm3 6 9 7 9-7'],
    template: ['M4 3h16v18H4z', 'M4 8h16', 'M9 8v13'],
    users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
    shield: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
    settings: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.94 19.3a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.57 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.7 8.94a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.57a1.7 1.7 0 0 0 1.03-1.56V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.06 4.7a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.43 9c.2.62.78 1.03 1.48 1.03H21a2 2 0 1 1 0 4h-.09A1.7 1.7 0 0 0 19.4 15Z'],
    history: ['M3 12a9 9 0 1 0 3-6.7L3 8', 'M3 3v5h5', 'M12 7v5l4 2'],
    globe: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M2 12h20', 'M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20Z'],
    lock: ['M5 11h14v10H5z', 'M8 11V7a4 4 0 0 1 8 0v4'],
    plus: ['M12 5v14', 'M5 12h14'],
    edit: ['M12 20h9', 'M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z'],
    trash: ['M3 6h18', 'M8 6V4h8v2', 'M19 6l-1 14H6L5 6', 'M10 11v6', 'M14 11v6'],
    eye: ['M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
    check: ['m5 12 5 5L20 7'],
    close: ['M18 6 6 18', 'm6 6 12 12'],
    alert: ['M12 3 2 21h20L12 3Z', 'M12 9v5', 'M12 18h.01'],
    info: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M12 11v6', 'M12 7h.01'],
    inbox: ['M4 4h16v16H4z', 'M4 13h5l1 3h4l1-3h5'],
    clock: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M12 6v6l4 2'],
    spark: ['m12 2 2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8Z'],
    upload: ['M12 16V4', 'm7 9 5-5 5 5', 'M4 16v4h16v-4'],
    download: ['M12 4v12', 'm7 11 5 5 5-5', 'M4 20h16'],
    link: ['M10 13a5 5 0 0 0 7.5.5l2-2A5 5 0 0 0 12.4 4.5l-1.2 1.2', 'M14 11a5 5 0 0 0-7.5-.5l-2 2A5 5 0 0 0 11.6 19.5l1.2-1.2'],
    logout: ['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'm16 17 5-5-5-5', 'M21 12H9'],
    eyeOff: ['m3 3 18 18', 'M10.6 10.6a3 3 0 0 0 4.2 4.2', 'M9.4 5.2A9.8 9.8 0 0 1 12 5c6.4 0 10 7 10 7a18 18 0 0 1-2.2 3.1', 'M6.2 6.2A18 18 0 0 0 2 12s3.6 7 10 7a9.7 9.7 0 0 0 3.2-.5'],
    arrowRight: ['M5 12h14', 'm13 6 6 6-6 6'],
    arrowLeft: ['M19 12H5', 'm11 6-6 6 6 6'],
    refresh: ['M21 12a9 9 0 1 1-3-6.7L21 8', 'M21 3v5h-5'],
    copy: ['M9 9h10v11H9z', 'M5 15V4h10'],
    monitor: ['M3 4h18v13H3z', 'M8 21h8', 'M12 17v4'],
    sun: ['M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z', 'M12 1v3', 'M12 20v3', 'M4.2 4.2l2.1 2.1', 'M17.7 17.7l2.1 2.1', 'M1 12h3', 'M20 12h3', 'M4.2 19.8l2.1-2.1', 'M17.7 6.3l2.1-2.1'],
    moon: ['M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z'],
    phone: ['M7 3h10v18H7z', 'M11 18h2'],
    book: ['M4 4h7a3 3 0 0 1 3 3v13a3 3 0 0 0-3-3H4z', 'M20 4h-3a3 3 0 0 0-3 3v13a3 3 0 0 1 3-3h3z'],
}

const { isRtl } = useI18n()

const directionAliases = {
    arrowForward: (rtl) => rtl ? 'arrowLeft' : 'arrowRight',
    arrowBack: (rtl) => rtl ? 'arrowRight' : 'arrowLeft',
    chevronForward: (rtl) => rtl ? 'chevronLeft' : 'chevronRight',
    chevronBack: (rtl) => rtl ? 'chevronRight' : 'chevronLeft',
}

const resolvedName = computed(() => directionAliases[props.name]?.(isRtl.value) ?? props.name)
const drawnPaths = computed(() => paths[resolvedName.value] ?? paths.info)
const pixelSize = computed(() => Number(props.size))
</script>

<template>
    <svg
        class="pc-icon"
        :width="pixelSize"
        :height="pixelSize"
        viewBox="0 0 24 24"
        fill="none"
        :stroke-width="strokeWidth"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <path v-for="(path, index) in drawnPaths" :key="index" :d="path" />
    </svg>
</template>
