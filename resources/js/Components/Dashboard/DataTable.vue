<script setup>
defineProps({
    columns: { type: Array, required: true },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: '' },
    emptyMessage: { type: String, default: '' },
    dense: { type: Boolean, default: false },
})
</script>

<template>
    <div class="pc-table-wrap">
        <table class="pc-table">
            <thead>
                <tr>
                    <th v-for="column in columns" :key="column.key" :class="column.align === 'end' ? 'is-end' : ''">
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <slot />
                <tr v-if="empty && $slots.empty">
                    <td :colspan="columns.length">
                        <slot name="empty" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
