<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import InfoTooltip from '../../Components/InfoTooltip.vue'

const props = defineProps({
    institution: { type: Object, default: null },
})

const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const form = useForm({
    // Student Enrolment Information
    total_enrolment: null,
    enrolment_type: 'FTE',
    intl_students_permit: null,
    intl_students_other: null,
    in_person_students: null,
    online_students: null,
    // Type of Education Offered
    program_associate_degree: false,
    program_university_transfer: false,
    program_bachelors_degree: false,
    program_graduate_degree: false,
    program_career_training: false,
    program_language_training: false,
    program_theological_education: false,
    program_trades_apprenticeship: false,
    // SABC
    sabc_designation: false,
    // EQA Brand Usage Plan
    media_pamphlet: false,
    media_website: false,
    media_brochure: false,
    media_poster: false,
    media_banner: false,
    media_billboard: false,
    brand_usage_plan: '',
    affiliates_partners: '',
    other_logos_trademarks: '',
    // Conditions of use affirmations
    affirm_policy_manual: false,
    affirm_website_compliance: false,
    affirm_written_permission: false,
    affirm_branding_guide: false,
    affirm_understands_comply: false,
    affirm_authorized: false,
    representative_signature: '',
})

const educationTypes = [
    ['program_associate_degree', 'Associate Degree'],
    ['program_university_transfer', 'University Transfer'],
    ['program_bachelors_degree', 'Bachelors Degree'],
    ['program_graduate_degree', 'Graduate Degree'],
    ['program_career_training', 'Career Training'],
    ['program_language_training', 'Language Training'],
    ['program_theological_education', 'Theological Education'],
    ['program_trades_apprenticeship', 'Trades / Apprenticeship Training'],
]

const mediaPlacements = [
    ['media_pamphlet', 'Pamphlet'],
    ['media_website', 'Website'],
    ['media_brochure', 'Brochure'],
    ['media_poster', 'Poster'],
    ['media_banner', 'Banner'],
    ['media_billboard', 'Billboard/Signage'],
]

const affirmations = [
    ['affirm_policy_manual', 'I confirm that I have read and understood the EQA Policy and Procedures Manual.'],
    ['affirm_website_compliance', "I confirm that my institution's website is in compliance with the EQA Policy and Procedures Manual and that all policies listed in the Manual are readily available to the public on the website."],
    ['affirm_written_permission', 'Institutions require the written permission of the Ministry in order to use the EQA brand. Permission to use the brand will be provided to institutions that have provided the necessary institutional profile information and that have been approved for application or re-application of their EQA designation.'],
    ['affirm_branding_guide', 'Information relating to use and misuse of the EQA brand are set out in the Education Quality Assurance Policy and Procedures Manual and EQA Branding Guide (available through the system). Any Questions relating to the use of the EQA brand should be directed to EQA@gov.bc.ca'],
    ['affirm_understands_comply', 'The institutional representative understands what use of the EQA brand entails, and agrees to comply with all EQA brand use requirements.'],
    ['affirm_authorized', 'I am the person assigned by my organization as the institutional contact, authorized to submit the application.'],
]

function submit() {
    form.post('/web/applications')
}
</script>

<template>
    <Head title="New Application" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-4">
            <Link href="/web/applications" class="text-sm text-indigo-600 hover:underline">← Applications</Link>
        </div>

        <h1 class="text-2xl font-bold text-slate-800">Submit New EQA Application</h1>
        <p v-if="institution" class="mt-1 text-sm text-slate-500">for {{ institution.name }} <span class="font-mono text-slate-400">{{ institution.institution_number }}</span></p>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <!-- Student Enrolment Information -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Student Enrolment Information</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block"><span class="text-sm text-slate-600">Total Institution Enrolment</span>
                        <InfoTooltip class="ml-1" title="Total Institution Enrolment" text="Total Institution Enrolment includes all students (domestic and international) in all programs at the institution over the past 12 months." />
                        <input v-model="form.total_enrolment" type="number" min="0" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-sm text-slate-600">Enrolment Type</span>
                        <select v-model="form.enrolment_type" :class="inputClass">
                            <option>FTE</option>
                            <option>Enrolment</option>
                        </select>
                    </label>
                    <label class="block"><span class="text-sm text-slate-600">International Students - Study Permit</span>
                        <input v-model="form.intl_students_permit" type="number" min="0" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-sm text-slate-600">International Students - Other</span>
                        <input v-model="form.intl_students_other" type="number" min="0" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-sm text-slate-600">Total Number of In-Person Students</span>
                        <InfoTooltip class="ml-1" title="Total Number of In-Person Students" text="Include the number of students enrolled in a program where either 100% of the hours of instruction are delivered in-person or a combination of in-person and online delivery." />
                        <input v-model="form.in_person_students" type="number" min="0" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-sm text-slate-600">Total Number of Online Students</span>
                        <input v-model="form.online_students" type="number" min="0" :class="inputClass" />
                    </label>
                </div>
            </section>

            <!-- Type of Education Offered -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-700">Type of Education Offered</h2>
                <p class="mb-4 text-xs text-slate-400">Select all types of education your institution offers.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label v-for="[field, label] in educationTypes" :key="field" class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form[field]" class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                        {{ label }}
                    </label>
                </div>
            </section>

            <!-- SABC Designation -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Institution has SABC Designation?</h2>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" v-model="form.sabc_designation" class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                    Yes, the institution has an SABC Designation
                </label>
            </section>

            <!-- EQA Brand Usage Plan -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">EQA Brand Usage Plan</h2>

                <h3 class="mb-2 text-sm font-medium text-slate-600">Media Placements Planned</h3>
                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <label v-for="[field, label] in mediaPlacements" :key="field" class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form[field]" class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                        {{ label }}
                    </label>
                </div>

                <label class="mt-2 block"><span class="text-sm text-slate-600">EQA Brand Usage Plan</span>
                    <textarea v-model="form.brand_usage_plan" rows="3" :class="inputClass"></textarea>
                </label>
                <label class="mt-4 block"><span class="text-sm text-slate-600">Affiliates or Partnership Institutions</span>
                    <span class="mb-1 block text-xs italic text-slate-400">List any official affiliations or partnerships the institution has with other institutions.</span>
                    <textarea v-model="form.affiliates_partners" rows="3" :class="inputClass"></textarea>
                </label>
                <label class="mt-4 block"><span class="text-sm text-slate-600">Other Logos or Symbols and Trademarks</span>
                    <span class="mb-1 block text-xs italic text-slate-400">List any other marks, logos, or symbols that may appear in conjunction with the EQA Brand.</span>
                    <textarea v-model="form.other_logos_trademarks" rows="3" :class="inputClass"></textarea>
                </label>
            </section>

            <!-- Conditions of use affirmations -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-700">I have read and understand the conditions of use pertaining to the following:</h2>
                <p class="mb-4 text-xs text-slate-400">All affirmations must be confirmed before submitting.</p>
                <ul class="space-y-3 text-sm">
                    <li v-for="[field, label] in affirmations" :key="field" class="flex items-start gap-2">
                        <input type="checkbox" v-model="form[field]" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600" />
                        <span class="text-slate-700">{{ label }}</span>
                    </li>
                </ul>
                <p v-if="form.errors.affirm_authorized" class="mt-2 text-xs text-red-600">{{ form.errors.affirm_authorized }}</p>

                <label class="mt-4 block"><span class="text-sm text-slate-600">Institutional Representative Signature</span>
                    <input v-model="form.representative_signature" type="text" :class="inputClass" />
                    <span v-if="form.errors.representative_signature" class="mt-1 block text-xs text-red-600">{{ form.errors.representative_signature }}</span>
                </label>
            </section>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Submit Application</button>
                <Link href="/web/applications" class="rounded-md border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</Link>
            </div>
        </form>
    </div>
</template>
