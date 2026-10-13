<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineProps({
    institution: { type: Object, default: null },
    invoices: { type: Array, default: () => [] },
    canPayOnline: { type: Boolean, default: false },
})

const flash = computed(() => usePage().props.flash || {})
const payingId = ref(null)

const money = (v) => Number(v ?? 0).toLocaleString('en-CA', { style: 'currency', currency: 'CAD' })

function statusClass(s) {
    if (s === 'Paid') return 'bg-green-100 text-green-700'
    if (s === 'Unpaid') return 'bg-amber-100 text-amber-700'
    if (s === 'Refund Issued') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}

function pay(invoice) {
    payingId.value = invoice.crm_id
    router.post(`/web/invoices/${invoice.crm_id}/pay`, {}, {
        preserveScroll: true,
        onFinish: () => { payingId.value = null },
    })
}
</script>

<template>
    <Head title="Invoices" />

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

    <h1 class="text-2xl font-bold text-slate-800">Invoices
        <span v-if="institution" class="text-base font-normal text-slate-500">for {{ institution.name }}</span>
    </h1>
    <p class="mt-1 text-sm text-slate-500">
        Online payments are processed by BC Express Pay. You will be taken to a secure payment page and returned here when you are done.
    </p>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Invoice #</th>
                    <th class="px-3 py-2.5">Application</th>
                    <th class="px-3 py-2.5">Invoice Date</th>
                    <th class="px-3 py-2.5 text-right">Total</th>
                    <th class="px-3 py-2.5 text-right">Balance Due</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="i in invoices" :key="i.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ i.invoice_number || '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ i.application_reference || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ i.invoice_date || '—' }}</td>
                    <td class="px-3 py-2 text-right text-slate-600">{{ money(i.total_charges) }}</td>
                    <td class="px-3 py-2 text-right font-medium text-slate-800">{{ money(i.amount_due) }}</td>
                    <td class="px-3 py-2"><span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusClass(i.invoice_status)">{{ i.invoice_status || '—' }}</span></td>
                    <td class="px-3 py-2 text-right">
                        <button
                            v-if="i.payable && canPayOnline"
                            type="button"
                            :disabled="payingId !== null"
                            @click="pay(i)"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60"
                        >{{ payingId === i.crm_id ? 'Redirecting…' : 'Pay Online' }}</button>
                    </td>
                </tr>
                <tr v-if="invoices.length === 0"><td colspan="7" class="px-3 py-8 text-center text-sm text-slate-400">No invoices yet.</td></tr>
            </tbody>
        </table>
    </div>
</template>
