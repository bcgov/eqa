<script setup>
import { Head } from '@inertiajs/vue3'
import { defineAsyncComponent, computed } from 'vue'

const props = defineProps({
    module: { type: String, required: true },
    page: { type: String, required: true },
})

const modulePages = import.meta.glob('../../../../Modules/*/resources/js/Pages/**/*.vue')
const target = computed(() =>
    defineAsyncComponent(
        modulePages[`../../../../Modules/${props.module}/resources/js/Pages/${props.page}.vue`],
    ),
)
</script>

<template>
    <Head :title="page" />
    <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
        Raw migrated page: <strong>{{ module }} / {{ page }}</strong>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-1">
        <component :is="target" />
    </div>
</template>