<script setup>
/**
 * AlertCampusPage
 * Migrated from: EQA WEB/Views/Campus/_AlertCampus.cshtml (razor_view, partial)
 * Module: Web
 */
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
    campus: {
        type: Object,
        default: () => ({}),
    },
    alerts: {
        type: Array,
        default: () => [],
    },
})

const hasAlerts = computed(() => props.alerts.length > 0)

const form = useForm({
    campus_id: props.campus?.id ?? null,
})

function acknowledge(alertId) {
    form.post(route('campus.alerts.acknowledge', { alert: alertId }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`Alerts - ${campus?.name ?? 'Campus'}`" />

    <div class="mx-auto max-w-3xl p-4">
        <header class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900">
                {{ campus?.name ?? 'Campus' }} Alerts
            </h1>
        </header>

        <div v-if="!hasAlerts" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
            No active alerts for this campus.
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="alert in alerts"
                :key="alert.id"
                class="flex items-start justify-between gap-4 rounded-lg border border-amber-300 bg-amber-50 p-4"
            >
                <div>
                    <p class="font-medium text-amber-900">{{ alert.title }}</p>
                    <p class="mt-1 text-sm text-amber-800">{{ alert.message }}</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                    :disabled="form.processing"
                    @click="acknowledge(alert.id)"
                >
                    Acknowledge
                </button>
            </li>
        </ul>
    </div>
</template>