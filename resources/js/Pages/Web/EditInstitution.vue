<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    institution: { type: Object, default: null },
    qaOptions: { type: Array, default: () => [] },
    enrolmentTypes: { type: Array, default: () => [] },
    canEditDli: { type: Boolean, default: false },
    submitUrl: { type: String, default: '/web/institution' },
    cancelUrl: { type: String, default: '/web/institution' },
})

const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const form = useForm({
    name: props.institution?.name ?? '',
    legal_name: props.institution?.legal_name ?? '',
    bc_incorporation_number: props.institution?.bc_incorporation_number ?? '',
    dli_number: props.institution?.dli_number ?? '',
    qa_met_through: props.institution?.qa_met_through ?? '',
    website: props.institution?.website ?? '',
    street1: props.institution?.street1 ?? '',
    street2: props.institution?.street2 ?? '',
    city: props.institution?.city ?? '',
    province: props.institution?.province ?? '',
    postal_code: props.institution?.postal_code ?? '',
    country: props.institution?.country ?? '',
    total_enrolment: props.institution?.total_enrolment ?? 0,
    intl_students_permit: props.institution?.intl_students_permit ?? 0,
    intl_students_other: props.institution?.intl_students_other ?? 0,
    in_person_students: props.institution?.in_person_students ?? 0,
    online_students: props.institution?.online_students ?? 0,
    enrolment_type: props.institution?.enrolment_type ?? 'FTE',
})

function submit() {
    form.put(props.submitUrl)
}
</script>

<template>
    <Head title="Edit Institution" />

    <div class="mb-4">
        <Link :href="cancelUrl" class="text-sm text-indigo-600 hover:underline">← Back</Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-800">Edit Institution Details</h1>
    <p v-if="institution" class="mt-1 text-sm text-slate-500">{{ institution.name }} <span class="font-mono text-slate-400">{{ institution.institution_number }}</span></p>

    <form @submit.prevent="submit" class="mt-6 max-w-4xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Institution Details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Institution Name</span>
                    <input v-model="form.name" type="text" :class="inputClass" />
                    <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Legal Name</span>
                    <input v-model="form.legal_name" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">BC Incorporation Number</span>
                    <input v-model="form.bc_incorporation_number" type="text" :class="inputClass" />
                </label>
                <label v-if="canEditDli" class="block"><span class="text-sm text-slate-600">DLI Number</span>
                    <input v-model="form.dli_number" type="text" :class="inputClass" placeholder="e.g. O19023456789" />
                    <span class="mt-1 block text-xs text-slate-400">Designated Learning Institution number (managed by the Ministry).</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Quality Assurance Met Through</span>
                    <select v-model="form.qa_met_through" :class="inputClass">
                        <option value="">—</option>
                        <option v-for="o in qaOptions" :key="o" :value="o">{{ o }}</option>
                    </select>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Web URL</span>
                    <input v-model="form.website" type="text" :class="inputClass" />
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Address</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Street 1</span>
                    <input v-model="form.street1" type="text" :class="inputClass" />
                </label>
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Street 2</span>
                    <input v-model="form.street2" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">City</span>
                    <input v-model="form.city" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Province</span>
                    <input v-model="form.province" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Postal Code</span>
                    <input v-model="form.postal_code" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Country</span>
                    <input v-model="form.country" type="text" :class="inputClass" />
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Student Enrolment Information</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="text-sm text-slate-600">Total Institution Enrolment</span>
                    <input v-model="form.total_enrolment" type="number" min="0" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Enrolment Type</span>
                    <select v-model="form.enrolment_type" :class="inputClass">
                        <option v-for="t in enrolmentTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Number of International Students - Study Permit</span>
                    <input v-model="form.intl_students_permit" type="number" min="0" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Number of International Students - Other</span>
                    <input v-model="form.intl_students_other" type="number" min="0" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Total Number of In-Person Students</span>
                    <input v-model="form.in_person_students" type="number" min="0" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Total Number of Online Students</span>
                    <input v-model="form.online_students" type="number" min="0" :class="inputClass" />
                </label>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Save Changes</button>
            <Link :href="cancelUrl" class="rounded-md border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</Link>
        </div>
    </form>
</template>