<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    alerts: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <Head title="Alerts" />

    <div class="mx-auto w-full max-w-4xl px-4 py-6">
        <h1 class="mb-4 text-xl font-semibold text-gray-900">Alerts</h1>

        <div v-if="props.alerts.length === 0" class="rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500">
            No alerts to display.
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="(alert, index) in props.alerts"
                :key="alert.id ?? index"
                class="flex items-start gap-3 rounded-md border p-4 shadow-sm"
                :class="{
                    'border-red-300 bg-red-50 text-red-800': alert.type === 'danger' || alert.type === 'error',
                    'border-yellow-300 bg-yellow-50 text-yellow-800': alert.type === 'warning',
                    'border-green-300 bg-green-50 text-green-800': alert.type === 'success',
                    'border-blue-300 bg-blue-50 text-blue-800': !alert.type || alert.type === 'info',
                }"
            >
                <div class="flex-1">
                    <p class="text-sm font-medium">{{ alert.message }}</p>
                    <p v-if="alert.description" class="mt-1 text-sm opacity-80">{{ alert.description }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>