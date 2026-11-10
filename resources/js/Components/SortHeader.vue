<script setup>
const props = defineProps({
    label: { type: String, required: true },
    column: { type: String, required: true },
    sort: { type: String, default: '' },
    direction: { type: String, default: 'desc' },
})
defineEmits(['sort'])
</script>

<template>
    <button type="button" @click="$emit('sort', column)" class="inline-flex items-center gap-1 uppercase tracking-wide hover:text-slate-800" :class="{ 'text-slate-800': sort === column }">
        {{ label }}
        <span v-if="sort === column" aria-hidden="true">{{ direction === 'asc' ? '▲' : '▼' }}</span>
        <span v-else class="text-slate-300" aria-hidden="true">↕</span>
    </button>
</template>
