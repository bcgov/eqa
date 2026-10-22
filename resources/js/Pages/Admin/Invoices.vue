<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import SortHeader from '../../Components/SortHeader.vue'

const props = defineProps({
    invoices: { type: Object, default: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }) },
    q: { type: String, default: '' },
    filters: { type: Object, default: () => ({ sort: 'invoice_date', direction: 'desc' }) },
})

const rows = computed(() => props.invoices?.data || [])
const search = ref(props.q)
const money = (v) => '$' + Number(v ?? 0).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function statusClass(s) {
    if (s === 'Paid') return 'bg-green-100 text-green-700'
    if (s === 'Unpaid') return 'bg-amber-100 text-amber-700'
    if (s === 'Refund Issued') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}

let debounce = null
function reload(params) {
    router.get('/admin/invoices', {
        q: search.value || undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
        ...params,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => reload({}), 300)
})
function sortBy(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc'
    reload({ sort: column, direction })
}

const feeTypes = {
    application_new: { label: 'New Application Fee', class: 'bg-sky-100 text-sky-700' },
    application_renewal: { label: 'Renewal Application Fee', class: 'bg-teal-100 text-teal-700' },
    admin_variable: { label: 'Administrative Fee', class: 'bg-purple-100 text-purple-700' },
    appeal: { label: 'Appeal Fee', class: 'bg-rose-100 text-rose-700' },
}

const isOnline = (inv) => /express\s*pay/i.test(inv.payment_method || '')
const isCheque = (inv) => /cheque|check/i.test(inv.payment_method || '') || !!inv.cheque_number
const methodLabel = (inv) => (isOnline(inv) ? 'Online (Express Pay)' : isCheque(inv) ? 'Cheque' : inv.payment_method)
function methodClass(inv) {
    if (isOnline(inv)) return 'bg-indigo-100 text-indigo-700'
    if (isCheque(inv)) return 'bg-amber-100 text-amber-800'
    return 'bg-slate-100 text-slate-600'
}
</script>

<template>
    <Head title="Invoices" />
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Invoices</h1>
            <p class="text-sm text-slate-500">
                <template v-if="invoices?.total">Showing {{ invoices.from }}–{{ invoices.to }} of {{ invoices.total }} invoices</template>
                <template v-else>No matching invoices</template>
            </p>
        </div>
        <input v-model="search" type="search" placeholder="Search invoice #, institution, application…" class="w-72 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none" />
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5"><SortHeader label="Invoice #" column="invoice_number" v-bind="filters" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Invoice Type" column="invoice_type" v-bind="filters" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Invoice Date" column="invoice_date" v-bind="filters" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Institution" column="institution" v-bind="filters" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Application" column="application" v-bind="filters" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5 text-right">Total</th>
                    <th class="px-3 py-2.5"><SortHeader label="Status" column="status" v-bind="filters" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5">Payment Method</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="inv in rows" :key="inv.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-mono text-xs">
                        <Link :href="`/admin/invoices/${inv.crm_id}`" class="text-indigo-600 hover:underline">{{ inv.invoice_number }}</Link>
                    </td>
                    <td class="px-3 py-2">
                        <span v-if="feeTypes[inv.fee_type]" class="rounded px-2 py-0.5 text-xs font-medium" :class="feeTypes[inv.fee_type].class">{{ feeTypes[inv.fee_type].label }}</span>
                        <span v-else class="text-xs text-slate-400">Fees (legacy)</span>
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
                    <td class="px-3 py-2 text-slate-600">
                        <span v-if="inv.payment_method" class="rounded px-2 py-0.5 text-xs font-medium" :class="methodClass(inv)">{{ methodLabel(inv) }}</span>
                        <span v-else>—</span>
                        <span v-if="inv.cheque_number" class="block text-xs text-slate-400">Cheque #{{ inv.cheque_number }}</span>
                        <span v-else-if="inv.transaction_reference" class="block font-mono text-xs text-slate-400">Ref {{ inv.transaction_reference }}</span>
                    </td>
                    <td class="px-3 py-2"><a v-if="inv.invoice_status === 'Paid'" :href="`/admin/invoices/${inv.crm_id}/receipt`" class="text-xs text-indigo-600 hover:underline">Receipt</a></td>
                </tr>
                <tr v-if="rows.length === 0"><td colspan="9" class="px-3 py-8 text-center text-sm text-slate-400">No invoices found.</td></tr>
            </tbody>
        </table>
    </div>

    <div v-if="invoices?.links && invoices.links.length > 3" class="mt-4 flex flex-wrap items-center justify-center gap-1">
        <template v-for="(link, i) in invoices.links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                preserve-state
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                v-html="link.label"
            />
            <span v-else class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-300" v-html="link.label" />
        </template>
    </div>
</template>