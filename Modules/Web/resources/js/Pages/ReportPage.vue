<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    report: {
        type: Object,
        default: () => ({}),
    },
})
</script>

<template>
    <Head title="Report" />

    <div class="min-h-screen bg-gray-50 p-6">
        <div class="mx-auto max-w-5xl">
            <header class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900">Report</h1>
                <p class="mt-1 text-sm text-gray-500">
                    View and review report details migrated from the legacy EQA Web Report controller.
                </p>
            </header>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div v-if="report && Object.keys(report).length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="(value, key) in report" :key="key" class="flex flex-col">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ key }}</span>
                        <span class="mt-1 text-sm text-gray-900">{{ value }}</span>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-sm text-gray-500">No report data is available.</p>
                </div>
            </div>
        </div>
    </div>
</template>