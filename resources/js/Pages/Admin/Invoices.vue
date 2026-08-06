<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    invoices: { type: Array, default: () => [] },
    q: { type: String, default: '' },
})

const search = ref(props.q)
const money = (v) => '$' + Number(v ?? 0).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function statusClass(s) {
    if (s === 'Paid') return 'bg-green-100 text-green-700'
    if (s === 'Unpaid') return 'bg-amber-100 text-amber-700'
    if (s === 'Refund Issued') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}

function submitSearch() {
    router.get('/admin/invoices', { q: search.value }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Invoices" />
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Invoices</h1>
            <p class="text-sm text-slate-500">{{ invoices.length }} financial summaries · migrated from Dynamics</p>
        </div>
        <input v-model="search" @keyup.enter="submitSearch" type="search" placeholder="Search invoice #, institution, application…" class="w-72 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none" />
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Invoice #</th>
                    <th class="px-3 py-2.5">Invoice Date</th>
                    <th class="px-3 py-2.5">Institution</th>
                    <th class="px-3 py-2.5">Application</th>
                    <th class="px-3 py-2.5 text-right">Total</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="inv in invoices" :key="inv.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-mono text-xs">
                        <Link :href="`/admin/invoices/${inv.crm_id}`" class="text-indigo-600 hover:underline">{{ inv.invoice_number }}</Link>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ inv.invoice_date || '—' }}</td>
                    <td class="px-3 py-2">
                        <Link v-if="inv.institution_crm_id" :href="`/admin/institutions/${inv.institution_crm_id}`" class="text-indigo-600 hover:underline">{{ inv.institution_name || '—' }}</Link>
                        <span v-else class="text-slate-700">{{ inv.institution_name || '—' }}</span>
                    </td>
                    <td class="px-3 py-2 font-mono text-xs">
                        <Link v-if="inv.application_crm_id" :href="`/admin/applications/${inv.application_crm_id}`" class="text-indigo-600 hover:underline">{{ inv.application_reference || '—' }}</Link>
                        <span v-else class="text-slate-500">{{ inv.application_reference || '—' }}</span>
                    </td>
                    <td class="px-3 py-2 text-right text-slate-700">{{ money(inv.total_charges) }}</td>
                    <td class="px-3 py-2"><span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusClass(inv.invoice_status)">{{ inv.invoice_status || '—' }}</span></td>
                    <td class="px-3 py-2"><a :href="`/admin/invoices/${inv.crm_id}/receipt`" class="text-xs text-indigo-600 hover:underline">Receipt</a></td>
                </tr>
                <tr v-if="invoices.length === 0"><td colspan="7" class="px-3 py-8 text-center text-sm text-slate-400">No invoices found.</td></tr>
            </tbody>
        </table>
    </div>
</template>