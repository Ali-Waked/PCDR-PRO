<script setup>
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Phase5Shell from '../../../Components/Phase5/Phase5Shell.vue'
import Phase5Section from '../../../Components/Phase5/Phase5Section.vue'
import Phase5Table from '../../../Components/Phase5/Phase5Table.vue'
import Phase5Pagination from '../../../Components/Phase5/Phase5Pagination.vue'
import SelectionControl from '../../../Components/Selection/SelectionControl.vue'
import Badge from '../../../Components/Ui/Badge.vue'
import { useI18n } from '../../../composables/useI18n'
import { label, phase5Url, rowsOf, shortText, toneFor } from '../../../Components/Phase5/phase5'

const props = defineProps({
    definitions: { type: Object, default: () => ({}) },
    executions: { type: Object, default: () => ({}) },
    triggers: { type: Array, default: () => [] },
    operators: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
    fields: { type: Object, default: () => ({}) },
    gates: { type: Object, default: () => ({}) },
})

const { locale, formatDate } = useI18n()

const title = computed(() => label('automations', locale.value))
const definitionRows = computed(() => rowsOf(props.definitions))
const executionRows = computed(() => rowsOf(props.executions))
const fieldKeys = computed(() => Object.keys(props.fields ?? {}))
const operatorKeys = computed(() => Object.keys(props.operators ?? {}))

const triggerOptions = computed(() => props.triggers.map((trigger) => ({
    value: trigger,
    label: label(trigger, locale.value, trigger),
})))
const conditionFieldOptions = computed(() => fieldKeys.value.map((key) => ({
    value: key,
    label: label(key, locale.value, key),
})))
const operatorOptions = computed(() => operatorKeys.value.map((key) => ({
    value: key,
    label: label(key, locale.value, key),
})))
const actionOptions = computed(() => props.actions.map((action) => ({
    value: action,
    label: label(action, locale.value, action),
})))

const ACTION_FIELDS = {
    create_notification: ['category', 'priority', 'title_key', 'preview_key'],
    create_task: ['title', 'priority', 'due_days'],
    add_internal_comment: ['body'],
    log_audit: ['action'],
}

const actionFields = computed(() => ACTION_FIELDS[form.actions[0]?.action] ?? [])

const form = useForm({
    key: '',
    category: 'generic',
    trigger_event: '',
    conditions: [{ field: '', operator: '', value: '' }],
    actions: [{ action: '', params: {} }],
    retry_limit: 3,
    timeout_seconds: 20,
})

const definitionColumns = computed(() => [
    { key: 'key' },
    { key: 'category' },
    { key: 'trigger_event' },
    { key: 'enabled', boolean: true },
    { key: 'version' },
    { key: 'conditions' },
    { key: 'actions_list', label: label('actions', locale.value) },
    { key: 'last_executed_at', format: 'date' },
    { key: 'actions_column', label: locale.value === 'ar' ? 'تبديل' : 'Toggle' },
])

const executionColumns = computed(() => [
    { key: 'id' },
    { key: 'trigger_event' },
    { key: 'status', badge: true },
    { key: 'attempts' },
    { key: 'created_at', format: 'date' },
    { key: 'finished_at', format: 'date' },
    { key: 'action' },
])

function serialize(value) {
    if (value === null || value === undefined) {
        return '—'
    }
    if (Array.isArray(value) || typeof value === 'object') {
        return shortText(JSON.stringify(value), 100)
    }

    return String(value)
}

function toggle(definition) {
    router.post(
        phase5Url('automations.toggle', { automation: definition.id }, locale.value),
        { enabled: !definition.enabled },
        { preserveScroll: true },
    )
}

function retry(execution) {
    router.post(
        phase5Url('automations.executions.retry', { execution: execution.id }, locale.value),
        {},
        { preserveScroll: true },
    )
}

function submit() {
    form.transform((data) => ({
        key: data.key,
        category: data.category,
        trigger_event: data.trigger_event,
        conditions: data.conditions
            .filter((item) => item.field !== '' && item.operator !== '')
            .map((item) => ({ field: item.field, operator: item.operator, value: item.value || null })),
        actions: data.actions
            .filter((item) => item.action !== '')
            .map((item) => ({
                action: item.action,
                ...Object.fromEntries(
                    Object.entries(item.params ?? {}).filter(([, value]) => value !== '' && value !== null),
                ),
            })),
        retry_limit: Number(data.retry_limit),
        timeout_seconds: Number(data.timeout_seconds),
    })).post(phase5Url('automations.store', {}, locale.value), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Phase5Shell :title="title">
        <div v-if="!props.gates?.external_channels_enabled" class="notice" role="note">
            {{ locale === 'ar'
                ? 'القنوات الخارجية معطّلة؛ تعمل الأتمتة داخل النظام فقط وتُسجّل بأمان.'
                : 'External channels are disabled; automations remain internal and are logged safely.' }}
        </div>

        <Phase5Section :title="label('definitions', locale)" flush>
            <Phase5Table :columns="definitionColumns" :rows="definitionRows" compact>
                <template #cell-conditions="{ value }">{{ serialize(value) }}</template>
                <template #cell-actions_list="{ value }">{{ serialize(value) }}</template>
                <template #cell-actions_column="{ row }">
                    <button type="button" class="btn btn-ghost btn-small" @click="toggle(row)">
                        {{ row.enabled ? label('disabled_label', locale) : label('enabled_label', locale) }}
                    </button>
                </template>
            </Phase5Table>
            <div style="padding: 0 1.25rem 1.25rem">
                <Phase5Pagination :paginator="props.definitions" page-key="definitionPage" />
            </div>
        </Phase5Section>

        <Phase5Section :title="label('executions', locale)" flush>
            <Phase5Table :columns="executionColumns" :rows="executionRows" compact>
                <template #cell-action="{ row }">
                    <button
                        v-if="row.status === 'failed'"
                        type="button"
                        class="btn btn-secondary btn-small"
                        @click="retry(row)"
                    >
                        {{ label('retry', locale) }}
                    </button>
                    <span v-else>—</span>
                </template>
            </Phase5Table>
            <div style="padding: 0 1.25rem 1.25rem">
                <Phase5Pagination :paginator="props.executions" page-key="executionPage" />
            </div>
        </Phase5Section>

        <Phase5Section :title="label('create', locale) + ' · ' + label('automations', locale)">
            <form style="padding: 1rem 1.25rem 1.25rem" @submit.prevent="submit">
                <div class="phase5-form__grid">
                    <div class="field">
                        <label for="phase5-automation-key">{{ label('key', locale) }}</label>
                        <input id="phase5-automation-key" v-model="form.key" class="input" required />
                    </div>
                    <div class="field">
                        <label for="phase5-automation-category">{{ label('category', locale) }}</label>
                        <input id="phase5-automation-category" v-model="form.category" class="input" />
                    </div>
                    <div class="field">
                        <SelectionControl
                            v-model="form.trigger_event"
                            :label="label('triggers', locale)"
                            :options="triggerOptions"
                            :placeholder="label('choose', locale)"
                            :error="form.errors.trigger_event || ''"
                            :searchable="triggerOptions.length > 6"
                            required
                        />
                    </div>
                    <div class="field">
                        <label for="phase5-automation-retry">{{ label('attempts', locale) }}</label>
                        <input id="phase5-automation-retry" v-model="form.retry_limit" class="input" type="number" min="0" max="3" />
                    </div>
                </div>

                <fieldset style="margin: 1.25rem 0 0; border: 1px solid var(--pcdr-border); border-radius: 0.5rem; padding: 1rem">
                    <legend>{{ label('conditions', locale) }}</legend>
                    <div class="phase5-form__grid">
                        <div class="field">
                            <SelectionControl
                                v-model="form.conditions[0].field"
                                :label="label('fields', locale)"
                                :options="conditionFieldOptions"
                                :placeholder="label('choose', locale)"
                                :searchable="conditionFieldOptions.length > 6"
                            />
                        </div>
                        <div class="field">
                            <SelectionControl
                                v-model="form.conditions[0].operator"
                                :label="label('operator', locale)"
                                :options="operatorOptions"
                                :placeholder="label('choose', locale)"
                                :searchable="operatorOptions.length > 6"
                            />
                        </div>
                        <div class="field">
                            <label for="phase5-condition-value">{{ label('value', locale) }}</label>
                            <input id="phase5-condition-value" v-model="form.conditions[0].value" class="input" />
                        </div>
                    </div>
                </fieldset>

                <fieldset style="margin: 1.25rem 0 0; border: 1px solid var(--pcdr-border); border-radius: 0.5rem; padding: 1rem">
                    <legend>{{ label('actions', locale) }}</legend>
                    <div class="phase5-form__grid">
                        <div class="field">
                            <SelectionControl
                                v-model="form.actions[0].action"
                                :label="label('actions', locale)"
                                :options="actionOptions"
                                :placeholder="label('choose', locale)"
                                :searchable="actionOptions.length > 6"
                            />
                        </div>
                        <div v-for="key in actionFields" :key="key" class="field">
                            <label :for="`phase5-action-${key}`">{{ label(key, locale) }}</label>
                            <input :id="`phase5-action-${key}`" v-model="form.actions[0].params[key]" class="input" />
                        </div>
                    </div>
                </fieldset>

                <div v-if="Object.keys(form.errors).length" class="notice" role="alert">
                    <span v-for="(message, field) in form.errors" :key="field">{{ message }}</span>
                </div>

                <div class="phase5-form__actions">
                    <button type="submit" class="btn btn-primary" :disabled="form.processing" :aria-busy="form.processing">
                        {{ label('create', locale) }}
                    </button>
                </div>
            </form>
        </Phase5Section>
    </Phase5Shell>
</template>
