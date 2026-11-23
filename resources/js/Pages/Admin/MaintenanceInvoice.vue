<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import MaintenanceMenu from '../../Components/MaintenanceMenu.vue'
import UtilSettings from '../../Components/UtilSettings.vue'

defineProps({
    categories: { type: Array, default: () => [] },
    results: { type: Object, default: () => ({}) },
    placeholders: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})
const token = (name) => `{${name}}`
</script>

<template>
    <Head title="Maintenance · Invoice" />

    <MaintenanceMenu>
        <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
        <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Invoice</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    Wording printed on the invoices institutions download. The issuer block (ministry, branch, address and email) and the fee amounts are under
                    <a href="/admin/maintenance/utils" class="text-indigo-600 hover:underline">Utils</a>.
                </p>
            </div>
            <a href="/admin/maintenance/invoice/preview" target="_blank" rel="noopener" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Preview sample invoice (PDF)</a>
        </div>

        <UtilSettings :categories="categories" :results="results" :show-category-headings="false">
            <template #value-help>
                <p class="text-xs text-slate-500">
                    Plain text. You can use:
                    <code v-for="p in placeholders" :key="p" class="mr-1 rounded bg-slate-100 px-1 py-0.5 text-slate-700">{{ token(p) }}</code>
                </p>
            </template>
        </UtilSettings>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <h2 class="mb-1 font-semibold text-slate-700">Placeholders</h2>
            <p>These are replaced with the invoice's own values when the PDF is created:</p>
            <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                <li v-for="p in placeholders" :key="p"><code class="rounded bg-white px-1 py-0.5 text-slate-700">{{ token(p) }}</code></li>
            </ul>
        </div>
    </MaintenanceMenu>
</template>
