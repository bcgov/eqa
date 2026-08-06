<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

defineProps({
    invoice: {
        type: Object,
        default: () => ({}),
    },
})
</script>

<template>
    <Head title="Invoice" />

    <div class="mx-auto max-w-4xl p-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-900">
                Invoice
            </h1>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-sm font-medium text-gray-500">
                        Invoice Number
                    </div>
                    <div class="text-sm text-gray-900">
                        {{ invoice.number ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm font-medium text-gray-500">
                        Date
                    </div>
                    <div class="text-sm text-gray-900">
                        {{ invoice.date ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm font-medium text-gray-500">
                        Customer
                    </div>
                    <div class="text-sm text-gray-900">
                        {{ invoice.customer_name ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm font-medium text-gray-500">
                        Total
                    </div>
                    <div class="text-sm text-gray-900">
                        {{ invoice.total ?? '—' }}
                    </div>
                </div>
            </div>

            <div class="mt-6" v-if="invoice.lines && invoice.lines.length">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Description
                            </th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                Qty
                            </th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(line, index) in invoice.lines" :key="index">
                            <td class="px-3 py-2 text-sm text-gray-900">
                                {{ line.description }}
                            </td>
                            <td class="px-3 py-2 text-right text-sm text-gray-900">
                                {{ line.quantity }}
                            </td>
                            <td class="px-3 py-2 text-right text-sm text-gray-900">
                                {{ line.amount }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="mt-6 text-sm text-gray-500">
                No invoice line items available.
            </div>
        </div>
    </div>
</template>