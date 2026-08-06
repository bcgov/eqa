<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    institution: { type: Object, default: null },
    application: { type: Object, default: () => ({}) },
})
</script>

<template>
    <Head :title="application.reference || 'Application'" />

    <div class="mb-4">
        <Link href="/web/applications" class="text-sm text-indigo-600 hover:underline">← Applications</Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-800">View Application for Reference: <span class="font-mono">{{ application.reference }}</span></h1>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Institution Details</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Institution Name</dt><dd class="text-slate-800">{{ application.institution_name || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Application Date</dt><dd class="text-slate-800">{{ application.application_date || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Application Status</dt><dd class="text-slate-800">{{ application.status || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Business Owner Name</dt><dd class="text-slate-800">{{ application.owner_name || '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Student Enrolment Information</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Total Institution Enrolment</dt><dd class="text-slate-800">{{ application.total_enrolment ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">International Students - Study Permit</dt><dd class="text-slate-800">{{ application.intl_students_permit ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">International Students - Other</dt><dd class="text-slate-800">{{ application.intl_students_other ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Total Number of In-Person Students</dt><dd class="text-slate-800">{{ application.in_person_students ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Enrolment Type</dt><dd class="text-slate-800">{{ application.enrolment_type || '—' }}</dd></div>
            </dl>
        </div>
    </div>
</template>