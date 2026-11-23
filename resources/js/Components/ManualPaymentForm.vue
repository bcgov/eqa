<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

// Ministry entry of a payment received outside Express Pay (EFT, cheque, ...).
defineProps({
    label: { type: String, required: true },
    busy: { type: Boolean, default: false },
})
const emit = defineEmits(['submit'])

const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10)
const payment = reactive({ payment_method: 'EFT', payment_date: today, payment_reference: '', payment_notes: '' })
const errors = computed(() => usePage().props.errors || {})
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none'
</script>

<template>
    <form class="space-y-3 rounded-md border border-slate-200 bg-white p-3" @submit.prevent="emit('submit', { ...payment })">
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="block text-sm text-slate-600">Payment method
                <select v-model="payment.payment_method" :class="inputClass">
                    <option>EFT</option>
                    <option>Cheque</option>
                    <option>Other</option>
                </select>
                <span v-if="errors.payment_method" class="mt-1 block text-xs text-red-600">{{ errors.payment_method }}</span>
            </label>
            <label class="block text-sm text-slate-600">Date of payment
                <input v-model="payment.payment_date" type="date" :max="today" required :class="inputClass" />
                <span v-if="errors.payment_date" class="mt-1 block text-xs text-red-600">{{ errors.payment_date }}</span>
            </label>
            <label class="block text-sm text-slate-600">Reference number
                <input v-model="payment.payment_reference" type="text" maxlength="100" placeholder="EFT trace / cheque #" :class="inputClass" />
                <span v-if="errors.payment_reference" class="mt-1 block text-xs text-red-600">{{ errors.payment_reference }}</span>
            </label>
        </div>
        <label class="block text-sm text-slate-600">Notes
            <textarea v-model="payment.payment_notes" rows="2" maxlength="1000" placeholder="Anything else about this payment (bank, payer, remittance details…)" :class="inputClass"></textarea>
            <span v-if="errors.payment_notes" class="mt-1 block text-xs text-red-600">{{ errors.payment_notes }}</span>
        </label>
        <button type="submit" :disabled="busy" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50">{{ label }}</button>
    </form>
</template>
