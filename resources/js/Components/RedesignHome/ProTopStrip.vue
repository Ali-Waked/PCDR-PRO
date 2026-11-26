<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from '../../composables/useI18n'

const { locale } = useI18n()

const isArabic = () => locale.value === 'ar'
const dateText = ref(isArabic() ? 'قطاع غزة – فلسطين' : 'Gaza Strip – Palestine')
const clockText = ref('')

function format(tag, options) {
    try {
        return new Intl.DateTimeFormat(tag, options).format(new Date())
    } catch {
        return ''
    }
}

function update() {
    const arabic = isArabic()

    const gregorian = format(
        arabic ? 'ar-u-ca-gregory-nu-arab' : 'en-u-ca-gregory',
        { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
    )
    const hijri = format(
        arabic ? 'ar-u-ca-islamic-umalqura-nu-arab' : 'en-u-ca-islamic-umalqura',
        { day: 'numeric', month: 'long', year: 'numeric' },
    )
    const time = format(
        arabic ? 'ar-u-nu-arab' : 'en-GB',
        { hour: '2-digit', minute: '2-digit', hour12: false },
    )

    dateText.value = gregorian + (hijri ? ` - ${hijri}` : '')
    clockText.value = time
}

let timer = null

onMounted(() => {
    update()
    timer = setInterval(update, 30000)
})
onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer)
        timer = null
    }
})
</script>

<template>
    <div class="topbar">
        <div class="wrap">
            <span>
                <svg class="ico" aria-hidden="true"><use href="#i-cal" /></svg>
                <span>{{ dateText }}</span>
            </span>
            <span>
                <svg class="ico" aria-hidden="true"><use href="#i-clock" /></svg>
                <span id="clock">{{ clockText }}</span>
            </span>
        </div>
    </div>
</template>
