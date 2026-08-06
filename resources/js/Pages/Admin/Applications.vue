<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    applications: { type: Array, default: () => [] },
})

const search = ref('')
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.applications
    return props.applications.filter((a) =>
        [a.reference, a.institution_name, a.status].some((v) => (v || '').toLowerCase().includes(q)),
    )
})

function statusClass(s) {
    if (s === 'Approved') return 'bg-green-100 text-green-700'
    if (s === 'Not Approved') return 'bg-red-100 text-red-700'
    if (s === 'Under Review' || s === 'Suitability Review') return 'bg-amber-100 text-amber-700'
    if (s === 'Pending Review') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}
function money(v) {
    return v != null ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'
}
</script>

<template>
    <Head title="Applications" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Applications</h1>
            <p class="text-sm text-slate-500">{{ filtered.length }} of {{ applications.length }} applications · migrated from Dynamics</p>
        </div>
        <input
            v-model="search"
            type="search"
            placeholder="Search reference or institution…"
            class="w-72 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none"
        />
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Reference</th>
                    <th class="px-3 py-2.5">Institution</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5">Application Date</th>
                    <th class="px-3 py-2.5">Approved Date</th>
                    <th class="px-3 py-2.5 text-right">Total Due</th>
                    <th class="px-3 py-2.5">Invoice #</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="a in filtered" :key="a.crm_id" class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-600">{{ a.reference }}</td>
                    <td class="px-3 py-2">
                        <Link v-if="a.institution_crm_id" :href="`/admin/institutions/${a.institution_crm_id}`" class="text-indigo-600 hover:underline">{{ a.institution_name || '—' }}</Link>
                        <span v-else class="text-slate-800">{{ a.institution_name || '—' }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusClass(a.status)">{{ a.status || '—' }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ a.application_date || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ a.approved_date || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-slate-600">{{ money(a.total_due) }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">
                        <Link v-if="a.invoice_crm_id" :href="`/admin/invoices/${a.invoice_crm_id}`" class="text-indigo-600 hover:underline">{{ a.invoice_number || '—' }}</Link>
                        <span v-else class="text-slate-500">{{ a.invoice_number || '—' }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <Link :href="`/admin/applications/${a.crm_id}`" class="font-medium text-indigo-600 hover:underline">Review →</Link>
                    </td>
                </tr>
                <tr v-if="filtered.length === 0">
                    <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-400">No applications.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>