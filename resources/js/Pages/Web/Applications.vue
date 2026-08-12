<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    institution: { type: Object, default: null },
    applications: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})

const activeStatuses = ['Pending Review', 'Under Review', 'Suitability Review']
const hasActiveApplication = computed(() =>
    ((usePage().props.applications) || []).some((a) => activeStatuses.includes(a.status))
)

function statusClass(s) {
    if (s === 'Approved') return 'bg-green-100 text-green-700'
    if (s === 'Not Approved') return 'bg-red-100 text-red-700'
    if (s === 'Under Review' || s === 'Suitability Review') return 'bg-amber-100 text-amber-700'
    if (s === 'Pending Review') return 'bg-blue-100 text-blue-700'
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
        <span v-else class="cursor-not-allowed rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-400" title="You already have an application that is submitted or under review.">Submit New Application</span>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Reference #</th>
                    <th class="px-3 py-2.5">Application Date</th>
                    <th class="px-3 py-2.5">Approval Date</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="a in applications" :key="a.crm_id" class="hover:bg-slate-50">
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