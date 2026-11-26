<script setup>
import { computed, ref, useId } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    name: { type: String, default: 'password' },
    autocomplete: { type: String, default: 'current-password' },
    showLabel: { type: String, required: true },
    hideLabel: { type: String, required: true },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const generatedId = useId()
const inputId = computed(() => `auth-password-${props.name}-${generatedId}`)
const errorId = computed(() => `${inputId.value}-error`)
const revealed = ref(false)
const inputType = computed(() => revealed.value ? 'text' : 'password')
</script>

<template>
    <div class="auth-password">
        <label class="pcdr-label" :for="inputId">
            {{ label }}
            <span v-if="required" aria-hidden="true">*</span>
        </label>
        <div class="auth-password__control">
            <input
                :id="inputId"
                :name="name"
                :type="inputType"
                :value="modelValue"
                :required="required"
                :autocomplete="autocomplete"
                :disabled="disabled"
                class="pcdr-input disabled:opacity-60"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="error ? errorId : undefined"
                @input="emit('update:modelValue', $event.target.value)"
            >
            <button
                class="auth-password__toggle"
                type="button"
                :aria-label="revealed ? hideLabel : showLabel"
                :aria-pressed="String(revealed)"
                @click="revealed = !revealed"
            >
                {{ revealed ? hideLabel : showLabel }}
            </button>
        </div>
        <p v-if="error" :id="errorId" class="mt-1.5 text-sm text-danger" role="alert">{{ error }}</p>
    </div>
</template>
