<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import SortHeader from '../../Components/SortHeader.vue'

const props = defineProps({
    institution: { type: Object, default: null },
    applications: { type: Array, default: () => [] },
    hasActiveApplication: { type: Boolean, default: false },
})

const flash = computed(() => usePage().props.flash || {})

// Client-side sorting: an institution's own applications all arrive on one page. Newest first by default.
const sort = ref('application_date')
const direction = ref('desc')
function sortBy(column) {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc'
    } else {
        sort.value = column
        direction.value = column.endsWith('_date') ? 'desc' : 'asc'
    }
}
const ariaSort = (column) => (sort.value === column ? (direction.value === 'asc' ? 'ascending' : 'descending') : 'none')
const sortedApplications = computed(() => {
    const key = sort.value
    const factor = direction.value === 'asc' ? 1 : -1
    return [...props.applications].sort((a, b) => {
        const x = a[key] ?? ''
        const y = b[key] ?? ''
        // Blank values (e.g. no approval date yet) always sort last.
        if (x === '' || y === '') return x === y ? 0 : (x === '' ? 1 : -1)
        return String(x).localeCompare(String(y), 'en-CA', { numeric: true, sensitivity: 'base' }) * factor
    })
})

function statusClass(s) {
    if (s && s.startsWith('Approved')) return 'bg-green-100 text-green-700'
    if (['Not Approved', 'Declined', 'Expired'].includes(s)) return 'bg-red-100 text-red-700'
    if (['Under Review', 'Suitability Review', 'Pending Appeal'].includes(s)) return 'bg-amber-100 text-amber-700'
    if (s === 'Pending Review' || s === 'Fee Payment Received') return 'bg-blue-100 text-blue-700'
    if (s === 'Application Fee Required' || s === 'Additional Information Required') return 'bg-orange-100 text-orange-700'
    if (s === 'Pending Admin Confirm Expiry') return 'bg-red-100 text-red-700'
    return 'bg-slate-100 text-slate-500'
}
</script>

<template>
    <Head title="Applications" />

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Applications
            <span v-if="institution" class="text-base font-normal text-slate-500">for {{ institution.name }}</span>
        </h1>
        <Link v-if="!hasActiveApplication" href="/web/applications/new" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Submit New Application</Link>
        <span v-else class="cursor-not-allowed rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-400" title="You already have an open application.">Submit New Application</span>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5" :aria-sort="ariaSort('reference')"><SortHeader label="Reference #" column="reference" :sort="sort" :direction="direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5" :aria-sort="ariaSort('application_date')"><SortHeader label="Application Date" column="application_date" :sort="sort" :direction="direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5" :aria-sort="ariaSort('approved_date')"><SortHeader label="Approval Date" column="approved_date" :sort="sort" :direction="direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5" :aria-sort="ariaSort('status')"><SortHeader label="Status" column="status" :sort="sort" :direction="direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="a in sortedApplications" :key="a.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ a.reference }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ a.application_date || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ a.approved_date || '—' }}</td>
                    <td class="px-3 py-2"><span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusClass(a.status)">{{ a.status }}</span></td>
                    <td class="px-3 py-2"><Link :href="`/web/applications/${a.crm_id}`" class="text-indigo-600 hover:underline">View</Link></td>
                </tr>
                <tr v-if="applications.length === 0"><td colspan="5" class="px-3 py-8 text-center text-sm text-slate-400">No applications yet.</td></tr>
            </tbody>
        </table>
    </div>
</template>