<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    institution: { type: Object, default: null },
    applications: { type: Array, default: () => [] },
})

function statusClass(s) {
    if (s === 'Approved') return 'bg-green-100 text-green-700'
    if (s === 'Not Approved') return 'bg-red-100 text-red-700'
    if (s === 'Under Review' || s === 'Suitability Review') return 'bg-amber-100 text-amber-700'
    if (s === 'Pending Review') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}
</script>

<template>
    <Head title="Home" />

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">EQA Designation Application Portal</h1>
            <p v-if="institution" class="mt-1 text-sm text-slate-500">Welcome, {{ institution.name }} <span class="font-mono text-slate-400">{{ institution.institution_number }}</span></p>
        </div>
        <Link href="/web/applications/new" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Submit New Application</Link>
    </div>

    <div v-if="institution" class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Institution</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Institution Name</dt><dd class="text-slate-800">{{ institution.name }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Institution Number</dt><dd class="font-mono text-slate-800">{{ institution.institution_number }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">EQA Status</dt><dd class="text-slate-800">{{ institution.eqa_status || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Designation Start Date</dt><dd class="text-slate-800">{{ institution.designation_start || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Designation Expiry Date</dt><dd class="text-slate-800">{{ institution.designation_expiry || '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Recent Applications</h2>
                <Link href="/web/applications" class="text-xs font-medium text-indigo-600 hover:underline">View all →</Link>
            </div>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr><th class="py-1.5">Reference</th><th class="py-1.5">Date</th><th class="py-1.5">Status</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="a in applications" :key="a.crm_id">
                        <td class="py-2 font-mono text-xs text-slate-600">{{ a.reference }}</td>
                        <td class="py-2 text-slate-600">{{ a.application_date || '—' }}</td>
                        <td class="py-2"><span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusClass(a.status)">{{ a.status }}</span></td>
                        <td class="py-2 text-right"><Link :href="`/web/applications/${a.crm_id}`" class="text-xs text-indigo-600 hover:underline">View</Link></td>
                    </tr>
                    <tr v-if="applications.length === 0"><td colspan="4" class="py-4 text-center text-xs text-slate-400">No applications yet.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-else class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-700">
        No institution is bound to this session. Load data with <code>php artisan migration:migrate-data</code>, then sign in as an Institution again.
    </div>
</template>