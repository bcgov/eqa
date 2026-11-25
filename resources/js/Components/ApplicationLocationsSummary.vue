<script setup>
import CampusLocations from './CampusLocations.vue'

// Read-only "Locations, Address for Service and Previous Names" block of a submitted application.
const props = defineProps({
    application: { type: Object, required: true },
    campusLocations: { type: Array, default: () => [] },
    previousNames: { type: Array, default: () => [] },
})

const serviceAddress = () => {
    const a = props.application
    return [a.service_street1, a.service_street2, a.service_city, a.service_province, a.service_postal_code, a.service_country].filter(Boolean).join(', ')
}
const confirmed = () => [true, 1, '1', 't'].includes(props.application.service_address_confirmed)
</script>

<template>
    <div class="space-y-5 text-sm">
        <div>
            <h3 class="mb-2 font-medium text-slate-700">Campus Locations</h3>
            <CampusLocations :campuses="campusLocations" />
        </div>
        <div>
            <h3 class="mb-2 font-medium text-slate-700">Address for Service</h3>
            <p class="text-slate-800">{{ serviceAddress() || '—' }}</p>
            <p class="mt-1 text-xs" :class="confirmed() ? 'text-green-700' : 'text-slate-500'">{{ confirmed() ? 'Confirmed by the institution' : 'Not confirmed' }}</p>
        </div>
        <div>
            <h3 class="mb-2 font-medium text-slate-700">Previous Operating Names (last 5 years)</h3>
            <table v-if="previousNames.length" class="min-w-full divide-y divide-slate-100 rounded-md border border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Used From</th><th class="px-3 py-2">Used To</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(n, i) in previousNames" :key="i">
                        <td class="px-3 py-2 text-slate-800">{{ n.name }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ n.used_from }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ n.used_to || 'Present' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-slate-500">None declared.</p>
        </div>
        <div>
            <h3 class="mb-2 font-medium text-slate-700">BC Registry Services</h3>
            <p class="text-slate-800">Annual report filed with BC Registry Services: <span class="font-medium">{{ [true, 1, '1', 't'].includes(application.annual_report_filed) ? 'Yes' : 'No' }}</span></p>
        </div>
    </div>
</template>
