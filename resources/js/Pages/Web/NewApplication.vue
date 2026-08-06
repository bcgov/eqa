<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    institution: { type: Object, default: null },
})

const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const form = useForm({
    total_enrolment: null,
    in_person_students: null,
    intl_students_permit: null,
    intl_students_other: null,
    enrolment_type: 'FTE',
})

function submit() {
    form.post('/web/applications')
}
</script>

<template>
    <Head title="New Application" />

    <div class="mb-4">
        <Link href="/web/applications" class="text-sm text-indigo-600 hover:underline">← Applications</Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-800">Submit New EQA Application</h1>
    <p v-if="institution" class="mt-1 text-sm text-slate-500">for {{ institution.name }} <span class="font-mono text-slate-400">{{ institution.institution_number }}</span></p>

    <form @submit.prevent="submit" class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-700">Student Enrolment Information</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block"><span class="text-sm text-slate-600">Total Institution Enrolment</span>
                <input v-model="form.total_enrolment" type="number" min="0" :class="inputClass" />
            </label>
            <label class="block"><span class="text-sm text-slate-600">Total Number of In-Person Students</span>
                <input v-model="form.in_person_students" type="number" min="0" :class="inputClass" />
            </label>
            <label class="block"><span class="text-sm text-slate-600">International Students - Study Permit</span>
                <input v-model="form.intl_students_permit" type="number" min="0" :class="inputClass" />
            </label>
            <label class="block"><span class="text-sm text-slate-600">International Students - Other</span>
                <input v-model="form.intl_students_other" type="number" min="0" :class="inputClass" />
            </label>
            <label class="block"><span class="text-sm text-slate-600">Enrolment Type</span>
                <select v-model="form.enrolment_type" :class="inputClass">
                    <option>FTE</option>
                    <option>Enrolment</option>
                </select>
            </label>
        </div>
        <div class="mt-6 flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Submit Application</button>
            <Link href="/web/applications" class="rounded-md border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</Link>
        </div>
    </form>
</template>