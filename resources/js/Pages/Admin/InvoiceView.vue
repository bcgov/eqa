<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
    invoice: { type: Object, default: () => ({}) },
    transactions: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})
const errors = computed(() => usePage().props.errors || {})
const manual = ref([])
const underReview = computed(() => props.transactions.filter((t) => t.status === 'review' || manual.value.includes(t.order_number)))
const canOverride = (t) => ['declined', 'abandoned', 'cancelled'].includes(t.status) && props.invoice.invoice_status !== 'Paid'
const accepting = reactive(Object.fromEntries(props.transactions.map((t) => [t.order_number, { trn_id: '', auth_code: '' }])))

function accept(t) {
    router.post(`/admin/invoices/${props.invoice.crm_id}/transactions/${t.order_number}/accept`, accepting[t.order_number], { preserveScroll: true })
}
function dismiss(t) {
    if (confirm(`Dismiss order ${t.order_number}? Only do this after confirming in Worldline that it was not charged or has been refunded.`)) {
        router.post(`/admin/invoices/${props.invoice.crm_id}/transactions/${t.order_number}/dismiss`, {}, { preserveScroll: true })
    }
}

const money = (v) => '$' + Number(v ?? 0).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
function txnClass(s) {
    if (s === 'approved') return 'bg-green-100 text-green-700'
    if (s === 'pending') return 'bg-blue-100 text-blue-700'
    if (s === 'review') return 'bg-red-100 text-red-700'
    return 'bg-slate-100 text-slate-500'
}
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
            <a v-if="invoice.invoice_status === 'Paid'" :href="`/admin/invoices/${invoice.crm_id}/receipt`" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Download Order Receipt</a>
        </div>
    </div>

    <div v-if="flash.success" class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
    <div v-if="flash.error" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

    <div v-if="underReview.length" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-semibold">{{ underReview.length }} online payment(s) to resolve</p>
        <p class="mt-1">Express Pay could not confirm these attempts automatically, and the institution cannot pay online again until they are resolved. Search the Worldline transaction report for each order number. Accept a charge to apply it to this invoice. Refund any duplicate charges in Worldline, then dismiss them here.</p>
        <div v-for="t in underReview" :key="t.order_number" class="mt-3 flex flex-wrap items-end gap-2">
            <span class="font-mono text-xs">Order {{ t.order_number }} · {{ money(t.amount) }}</span>
            <input v-model="accepting[t.order_number].trn_id" placeholder="Worldline Txn ID" class="w-36 rounded-md border border-slate-300 px-2 py-1 text-xs" />
            <input v-model="accepting[t.order_number].auth_code" placeholder="Auth code" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-xs" />
            <button type="button" :disabled="!accepting[t.order_number].trn_id" @click="accept(t)" class="rounded-md bg-green-600 px-3 py-1 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50">Accept Payment</button>
            <button v-if="t.status === 'review'" type="button" @click="dismiss(t)" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Dismiss</button>
            <button v-else type="button" @click="manual.splice(manual.indexOf(t.order_number), 1)" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
        </div>
        <p v-if="errors.trn_id" class="mt-2 text-xs text-red-700">{{ errors.trn_id }}</p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">General</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Invoice Number</dt><dd class="font-mono text-slate-800">{{ invoice.invoice_number }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Invoice Date</dt><dd class="text-slate-800">{{ invoice.invoice_date || '—' }}</dd></div>
                <div v-if="invoice.description" class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Description</dt><dd class="whitespace-pre-line text-right text-slate-800">{{ invoice.description }}</dd></div>
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
            <div v-if="invoice.invoice_status === 'Paid'" class="flex flex-wrap items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <span class="text-slate-400">&#128206;</span>
                <a :href="`/admin/invoices/${invoice.crm_id}/receipt`" class="text-indigo-600 hover:underline">EQA_Order_Receipt.docx</a>
                <span class="text-xs text-slate-400">— generated from the migrated Dynamics Word template</span>
            </div>
            <p v-else class="text-sm text-slate-400">The order receipt is available once the invoice is paid.</p>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">BC Express Pay Transactions</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Order #</th>
                        <th class="px-3 py-2">Started</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Worldline Txn</th>
                        <th class="px-3 py-2">Auth Code</th>
                        <th class="px-3 py-2">Card</th>
                        <th class="px-3 py-2">Message</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="t in transactions" :key="t.order_number">
                        <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ t.order_number }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ t.created_at }}</td>
                        <td class="px-3 py-2 text-right text-slate-800">{{ money(t.amount) }}</td>
                        <td class="px-3 py-2"><span class="rounded px-2 py-0.5 text-xs font-medium capitalize" :class="txnClass(t.status)">{{ t.status }}</span></td>
                        <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ t.trn_id || '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ t.auth_code || '—' }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ t.card_type || '—' }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ t.message_text || '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <button v-if="canOverride(t) && !manual.includes(t.order_number)" type="button" @click="manual.push(t.order_number)" class="whitespace-nowrap text-xs text-indigo-600 hover:underline" title="Use only if the Worldline report shows this order as approved.">Accept payment…</button>
                        </td>
                    </tr>
                    <tr v-if="transactions.length === 0"><td colspan="9" class="px-3 py-6 text-center text-sm text-slate-400">No online payment attempts.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>