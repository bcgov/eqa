<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    institution: { type: Object, default: null },
    campusCount: { type: Number, default: 0 },
    userCount: { type: Number, default: 0 },
})

const flash = computed(() => usePage().props.flash || {})

// PTIRU Standing is only relevant when QA is met through the Private Training
// designation. Match both the legacy "(PTIB)" label and the renamed "(PTIRU)" one.
const showPtiruStanding = computed(() => {
    const qa = props.institution?.qa_met_through
    return typeof qa === 'string' && (qa.includes('(PTIB)') || qa.includes('(PTIRU)'))
})
</script>

<template>
    <Head title="Institution" />

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>

    <div v-if="institution">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">{{ institution.name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Institution ID <span class="font-mono text-slate-600">{{ institution.institution_number }}</span></p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-sm font-semibold"
                    :class="institution.eqa_status === 'Designated' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'">
                    {{ institution.eqa_status || 'Not Designated' }}
                </span>
                <Link href="/web/institution/edit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Edit Institution Details</Link>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Designation Information</h2>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between py-2"><dt class="text-slate-500">EQA Status</dt><dd class="text-slate-800">{{ institution.eqa_status || '—' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-slate-500">EQA Standing</dt><dd class="text-slate-800">{{ institution.eqa_standing || '—' }}</dd></div>
                    <div v-if="showPtiruStanding" class="flex justify-between py-2"><dt class="text-slate-500">PTIRU Standing</dt><dd class="text-slate-800">{{ institution.ptib_standing || '—' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-slate-500">Designation Start Date</dt><dd class="text-slate-800">{{ institution.designation_start || '—' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-slate-500">Designation Expiry Date</dt><dd class="text-slate-800">{{ institution.designation_expiry || '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Institution Details</h2>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Legal Name</dt><dd class="text-right text-slate-800">{{ institution.legal_name || '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">DLI Number</dt><dd class="text-right text-slate-800">{{ institution.dli_number || '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">QA Met Through</dt><dd class="text-right text-slate-800">{{ institution.qa_met_through || '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Business Owner</dt><dd class="text-right text-slate-800">{{ institution.business_owner || '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Primary Contact</dt><dd class="text-right text-slate-800">{{ institution.primary_contact || '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">City</dt><dd class="text-right text-slate-800">{{ institution.city || '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Province</dt><dd class="text-right text-slate-800">{{ institution.province || '—' }}</dd></div>
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Website</dt><dd class="max-w-[16rem] truncate text-right text-slate-800"><a v-if="institution.website" :href="institution.website" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">{{ institution.website }}</a><span v-else>—</span></dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Student Enrolment Information</h2>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between py-2"><dt class="text-slate-500">Total Institution Enrolment</dt><dd class="text-slate-800">{{ institution.total_enrolment ?? '—' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-slate-500">International Students - Study Permit</dt><dd class="text-slate-800">{{ institution.intl_students_permit ?? '—' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-slate-500">International Students - Other</dt><dd class="text-slate-800">{{ institution.intl_students_other ?? '—' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-slate-500">Total Number of In-Person Students</dt><dd class="text-slate-800">{{ institution.in_person_students ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Related Records</h2>
                <div class="grid grid-cols-2 gap-4">
                    <Link href="/web/campuses" class="rounded-lg border border-slate-200 p-4 text-center transition hover:border-indigo-300 hover:bg-indigo-50">
                        <div class="text-2xl font-bold text-slate-800">{{ campusCount }}</div>
                        <div class="mt-1 text-xs font-medium text-slate-500">Campuses / Locations →</div>
                    </Link>
                    <Link href="/web/users" class="rounded-lg border border-slate-200 p-4 text-center transition hover:border-indigo-300 hover:bg-indigo-50">
                        <div class="text-2xl font-bold text-slate-800">{{ userCount }}</div>
                        <div class="mt-1 text-xs font-medium text-slate-500">Portal Users →</div>
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-700">
        No institution is bound to this session. Load data with <code>php artisan migration:migrate-data</code>, then sign in as an Institution again.
    </div>
</template>