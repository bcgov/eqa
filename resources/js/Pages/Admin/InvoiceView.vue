<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    invoice: { type: Object, default: () => ({}) },
})

const money = (v) => '$' + Number(v ?? 0).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
function statusClass(s) {
    if (s === 'Paid') return 'bg-green-100 text-green-700'
    if (s === 'Unpaid') return 'bg-amber-100 text-amber-700'
    if (s === 'Refund Issued') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}
</script>

<template>
    <Head :title="invoice.invoice_number || 'Invoice'" />
    <div class="mb-4">
        <Link href="/admin/invoices" class="text-sm text-indigo-600 hover:underline">← Invoices</Link>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-mono text-2xl font-bold text-slate-800">{{ invoice.invoice_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">Financial Summary · {{ invoice.institution_name }}</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="statusClass(invoice.invoice_status)">{{ invoice.invoice_status || '—' }}</span>
            <a :href="`/admin/invoices/${invoice.crm_id}/receipt`" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Download Order Receipt</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">General</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Invoice Number</dt><dd class="font-mono text-slate-800">{{ invoice.invoice_number }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Invoice Date</dt><dd class="text-slate-800">{{ invoice.invoice_date || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Application Reference</dt>
                    <dd>
                        <Link v-if="invoice.application_crm_id" :href="`/admin/applications/${invoice.application_crm_id}`" class="font-mono text-indigo-600 hover:underline">{{ invoice.application_reference || '—' }}</Link>
                        <span v-else class="font-mono text-slate-800">{{ invoice.application_reference || '—' }}</span>
                    </dd>
                </div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Institution Name</dt>
                    <dd>
                        <Link v-if="invoice.institution_crm_id" :href="`/admin/institutions/${invoice.institution_crm_id}`" class="text-indigo-600 hover:underline">{{ invoice.institution_name || '—' }}</Link>
                        <span v-else class="text-slate-800">{{ invoice.institution_name || '—' }}</span>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">References</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Invoice Amount</dt><dd class="text-slate-800">{{ money(invoice.invoice_amount) }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Taxes</dt><dd class="text-slate-800">{{ money(invoice.taxes) }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Total Charges</dt><dd class="font-medium text-slate-800">{{ money(invoice.total_charges) }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Invoice Balance</dt><dd class="text-slate-800">{{ money(invoice.invoice_balance) }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Invoice Status</dt><dd class="text-slate-800">{{ invoice.invoice_status || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Refund Amount</dt><dd class="text-slate-800">{{ invoice.refund_amount != null ? money(invoice.refund_amount) : '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Payment Details</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Transaction Reference</dt><dd class="text-slate-800">{{ invoice.transaction_reference || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Payment Amount</dt><dd class="text-slate-800">{{ invoice.payment_amount != null ? money(invoice.payment_amount) : '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Payment Date</dt><dd class="text-slate-800">{{ invoice.payment_received_date || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Authorization Code</dt><dd class="text-slate-800">{{ invoice.transaction_authorization || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Payment Method</dt><dd class="text-slate-800">{{ invoice.payment_method || '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Notes</h2>
            <div class="flex flex-wrap items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <span class="text-slate-400">&#128206;</span>
                <a :href="`/admin/invoices/${invoice.crm_id}/receipt`" class="text-indigo-600 hover:underline">EQA_Order_Receipt.docx</a>
                <span class="text-xs text-slate-400">— generated from the migrated Dynamics Word template</span>
            </div>
        </div>
    </div>
</template>