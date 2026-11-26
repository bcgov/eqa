<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import InfoTooltip from '../../Components/InfoTooltip.vue'
import { affirmations } from '../../applicationAffirmations'

const props = defineProps({
    institution: { type: Object, default: null },
    application: { type: Object, default: null },
    campusLocations: { type: Array, default: () => [] },
    previousNames: { type: Array, default: () => [] },
    websiteCompliance: { type: Array, default: () => [] },
})

// "?" bubble text, maintained under Maintenance > Utils (Application Help Text).
const help = computed(() => usePage().props.settings?.help ?? {})

const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

function emptyLocation() {
    return { location_name: '', email: '', website: '', street1: '', street2: '', city: '', province: '', postal_code: '', country: '', primary_location: false }
}

const form = useForm({
    // Student Enrolment Information
    total_enrolment: null,
    intl_students_permit: null,
    // Enrolment Type and International Students - Other are retired: not asked, never written.
    in_person_students: null,
    online_students: null,
    // Campus locations are entered on each application (never prefilled from the institution's campuses).
    campus_locations: props.campusLocations.length
        ? props.campusLocations.map((l) => ({ ...emptyLocation(), ...l, primary_location: !!l.primary_location }))
        : [emptyLocation()],
    // Address for service: prefilled from the institution profile (a saved draft keeps its own).
    service_street1: props.institution?.street1 ?? '',
    service_street2: props.institution?.street2 ?? '',
    service_city: props.institution?.city ?? '',
    service_province: props.institution?.province ?? '',
    service_postal_code: props.institution?.postal_code ?? '',
    service_country: props.institution?.country ?? '',
    service_address_confirmed: false,
    // Operating names used in the last 5 years: the draft's own, or the institution's for a new application.
    previous_names: props.previousNames.map((n) => ({ name: n.name, used_from: n.used_from, used_to: n.used_to ?? '' })),
    annual_report_filed: false,
    per_student_fee_enrolment: null,
    // Website Compliance: a link and/or a comment per checklist item, keyed by item.
    website_compliance: Object.fromEntries(props.websiteCompliance.flatMap((s) => s.items)
        .map((item) => [item.key, { url: item.url ?? '', comment: item.comment ?? '' }])),
    // Type of Education Offered
    program_associate_degree: false,
    program_university_transfer: false,
    program_bachelors_degree: false,
    program_graduate_degree: false,
    program_career_training: false,
    program_language_training: false,
    program_theological_education: false,
    program_trades_apprenticeship: false,
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
    affirm_authorized: false,
    representative_signature: '',
})

// Editing a draft: start from the saved values.
if (props.application) {
    for (const key of Object.keys(form.data())) {
        const value = props.application[key]
        if (value !== null && value !== undefined && value !== '') {
            form[key] = typeof form[key] === 'boolean' ? (value === true || value === 1 || value === '1' || value === 't') : value
        }
    }
    form.defaults()
}

const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10)
function addPreviousName() {
    form.previous_names.push({ name: '', used_from: '', used_to: '' })
}
function removePreviousName(index) {
    form.previous_names.splice(index, 1)
}
const nameError = (i, field) => form.errors[`previous_names.${i}.${field}`] || (field === 'used_to' ? form.errors[`previous_names.${i}`] : '')

function addLocation() {
    form.campus_locations.push(emptyLocation())
}
function removeLocation(index) {
    form.campus_locations.splice(index, 1)
}
// Only one location can be primary: ticking one clears the others.
function setPrimary(index, checked) {
    form.campus_locations.forEach((l, i) => { l.primary_location = checked && i === index })
}
const locationError = (i, field) => form.errors[`campus_locations.${i}.${field}`]
const complianceError = (key, field) => form.errors[`website_compliance.${key}.${field}`]
const locationFields = [
    ['location_name', 'Location Name', true, 'sm:col-span-2'],
    ['email', 'Email', false, ''],
    ['website', 'Website', false, ''],
    ['street1', 'Street 1', true, 'sm:col-span-2'],
    ['street2', 'Street 2', false, 'sm:col-span-2'],
    ['city', 'City', true, ''],
    ['province', 'Province', true, ''],
    ['postal_code', 'Postal Code', true, ''],
    ['country', 'Country', false, ''],
]

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

// Steps: free navigation between them; all data stays in this one form until it is saved.
// `fields` are the form keys (prefixes) each step owns, used to flag the steps with errors.
const steps = [
    { title: 'Enrolment', fields: ['total_enrolment', 'intl_students_permit', 'in_person_students', 'online_students'] },
    { title: 'Locations & Organization', fields: ['campus_locations', 'service_', 'previous_names', 'annual_report_filed'] },
    { title: 'Programs & Partnerships', fields: ['program_', 'affiliates_partners'] },
    { title: 'EQA Brand Usage', fields: ['media_', 'brand_usage_plan', 'other_logos_trademarks'] },
    { title: 'Website Compliance', fields: ['website_compliance'] },
    { title: 'Fees & Declarations', fields: ['per_student_fee_enrolment', 'affirm_', 'representative_signature'] },
]
const stepOf = (key) => steps.findIndex((s) => s.fields.some((f) => key.startsWith(f)))
const stepErrorCounts = computed(() => {
    const counts = steps.map(() => 0)
    for (const key of Object.keys(form.errors)) {
        const i = stepOf(key)
        if (i >= 0) counts[i]++
    }
    return counts
})
const firstErrorStep = () => stepErrorCounts.value.findIndex((n) => n > 0) + 1

const filled = (v) => v !== null && v !== undefined && String(v).trim() !== ''
const stepComplete = computed(() => [
    true,
    form.campus_locations.length > 0
        && form.campus_locations.every((l) => ['location_name', 'street1', 'city', 'province', 'postal_code'].every((f) => filled(l[f])))
        && ['service_street1', 'service_city', 'service_province', 'service_postal_code', 'service_country'].every((f) => filled(form[f]))
        && !!form.service_address_confirmed
        && !!form.annual_report_filed
        && form.previous_names.every((n) => filled(n.name) && filled(n.used_from)),
    true,
    true,
    Object.values(form.website_compliance).every((a) => filled(a.url) || filled(a.comment)),
    filled(form.per_student_fee_enrolment) && affirmations.every(([f]) => form[f]) && filled(form.representative_signature),
])

// Submitting from the application page sends incomplete applications back here with the errors.
const pageErrors = usePage().props.errors || {}
if (Object.keys(pageErrors).length) form.setError(pageErrors)

const requestedStep = Number(new URLSearchParams(window.location.search).get('step'))
const step = ref(Object.keys(form.errors).length ? firstErrorStep() : (requestedStep >= 1 && requestedStep <= steps.length ? requestedStep : 1))
// An existing application has been through every step; a new one marks steps as they are visited.
const visited = ref(new Set(props.application ? steps.map((_, i) => i + 1) : [step.value]))
const stepper = ref(null)

function goTo(n) {
    step.value = n
    visited.value.add(n)
    stepper.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
function stepStatus(n) {
    if (stepErrorCounts.value[n - 1] > 0) return 'error'
    if (visited.value.has(n) && stepComplete.value[n - 1]) return 'complete'
    return 'incomplete'
}

const flash = computed(() => usePage().props.flash || {})
const resubmitting = computed(() => props.application?.workflow_stage === 'changes_requested')

// Save Draft keeps whatever has been entered; Submit also requires every step to be complete.
// Untouched blank location / previous-name rows are left out so they don't block saving a draft.
const blankRow = (row, ignore = []) => Object.entries(row).every(([k, v]) => ignore.includes(k) || !filled(v))
function save(intent) {
    form.transform((data) => ({
        ...data,
        campus_locations: data.campus_locations.filter((l) => !blankRow(l, ['primary_location'])),
        previous_names: data.previous_names.filter((n) => !blankRow(n)),
        intent,
        step: step.value,
    }))
    const options = {
        preserveScroll: true,
        onError: () => { if (firstErrorStep() > 0) goTo(firstErrorStep()) },
    }
    if (props.application) {
        form.put(`/web/applications/${props.application.crm_id}`, options)
    } else {
        form.post('/web/applications', options)
    }
}
</script>

<template>
    <Head :title="application ? 'Edit Application' : 'New Application'" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-4">
            <Link :href="application ? `/web/applications/${application.crm_id}` : '/web/applications'" class="text-sm text-indigo-600 hover:underline">← {{ application ? application.reference : 'Applications' }}</Link>
        </div>

        <h1 class="text-2xl font-bold text-slate-800">{{ application ? 'Edit EQA Application' : 'New EQA Application' }}</h1>
        <p v-if="institution" class="mt-1 text-sm text-slate-500">for {{ institution.name }} <span class="font-mono text-slate-400">{{ institution.institution_number }}</span></p>
        <p class="mt-1 text-sm text-slate-500">Move between the steps in any order. Select <span class="font-medium">Save Draft</span> at any time to keep your progress, and <span class="font-medium">{{ resubmitting ? 'Resubmit' : 'Submit' }} Application</span> on the last step when every step is complete. Fields marked <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">with an asterisk</span> are required before you can submit.</p>

        <div v-if="flash.success" class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700" role="status">{{ flash.success }}</div>
        <div v-if="flash.error" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ flash.error }}</div>
        <div v-else-if="Object.keys(form.errors).length" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">
            Some information is missing or needs correcting. Steps marked <span aria-hidden="true">!</span><span class="sr-only">needs attention</span> need attention.
        </div>

        <!-- Step indicator -->
        <nav ref="stepper" aria-label="Application steps" class="mt-6 scroll-mt-4">
            <ol class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                <li v-for="(s, i) in steps" :key="s.title">
                    <button type="button" @click="goTo(i + 1)" :aria-current="step === i + 1 ? 'step' : undefined"
                        class="flex h-full w-full items-start gap-2 rounded-md border px-2.5 py-2 text-left text-xs transition"
                        :class="step === i + 1 ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-slate-200 bg-white hover:border-indigo-300'">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                            :class="{
                                'bg-red-600 text-white': stepStatus(i + 1) === 'error',
                                'bg-green-600 text-white': stepStatus(i + 1) === 'complete',
                                'bg-slate-200 text-slate-600': stepStatus(i + 1) === 'incomplete',
                            }" aria-hidden="true">{{ stepStatus(i + 1) === 'error' ? '!' : (stepStatus(i + 1) === 'complete' ? '✓' : i + 1) }}</span>
                        <span>
                            <span class="block font-semibold" :class="step === i + 1 ? 'text-indigo-800' : 'text-slate-700'">{{ s.title }}</span>
                            <span class="sr-only">Step {{ i + 1 }} of {{ steps.length }}, </span>
                            <span v-if="stepStatus(i + 1) === 'error'" class="block text-red-700">Needs attention</span>
                            <span v-else-if="stepStatus(i + 1) === 'complete'" class="block text-green-700">Complete</span>
                            <span v-else class="sr-only">not complete</span>
                        </span>
                    </button>
                </li>
            </ol>
        </nav>

        <form @submit.prevent="save('save')" class="mt-6 space-y-6">
            <h2 class="text-lg font-semibold text-slate-800">Step {{ step }} of {{ steps.length }}: {{ steps[step - 1].title }}</h2>

            <div v-show="step === 1" class="space-y-6">
            <!-- Student Enrolment Information -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Student Enrolment Information</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="block"><label for="app-total_enrolment" class="text-sm text-slate-600">Total Institution Enrolment</label>
                        <InfoTooltip class="ml-1" title="Total Institution Enrolment" :text="help.total_enrolment" />
                        <input id="app-total_enrolment" v-model="form.total_enrolment" type="number" min="0" :class="inputClass" />
                    </div>
                    <div class="block"><label for="app-intl_students_permit" class="text-sm text-slate-600">International Students - Study Permit</label>
                        <InfoTooltip class="ml-1" title="International Students - Study Permit" :text="help.intl_students_permit" />
                        <input id="app-intl_students_permit" v-model="form.intl_students_permit" type="number" min="0" :class="inputClass" />
                    </div>
                    <div class="block"><label for="app-in_person_students" class="text-sm text-slate-600">Total Number of In-Person Students</label>
                        <InfoTooltip class="ml-1" title="Total Number of In-Person Students" :text="help.in_person_students" />
                        <input id="app-in_person_students" v-model="form.in_person_students" type="number" min="0" :class="inputClass" />
                    </div>
                    <div class="block"><label for="app-online_students" class="text-sm text-slate-600">Total Number of Online Students</label>
                        <InfoTooltip class="ml-1" title="Total Number of Online Students" :text="help.online_students" />
                        <input id="app-online_students" v-model="form.online_students" type="number" min="0" :class="inputClass" />
                    </div>
                </div>
            </section>
            </div>

            <div v-show="step === 2" class="space-y-6">
            <!-- Locations, Address for Service and Previous Names -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Locations, Address for Service and Previous Names</h2>

                <fieldset>
                    <legend class="text-sm font-medium text-slate-600">Campus Locations <span class="text-red-600">*</span></legend>
                    <p class="mb-2 text-xs text-slate-500">List each campus location for this application with its address. These are entered for this application only and do not change the campuses set up under your institution.</p>
                    <div v-for="(l, i) in form.campus_locations" :key="i" class="mb-3 rounded-md border border-slate-200 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-700">Location {{ i + 1 }}</p>
                            <button v-if="form.campus_locations.length > 1" type="button" @click="removeLocation(i)" class="rounded-md border border-slate-300 px-3 py-1 text-xs text-slate-600 hover:bg-slate-50"
                                :aria-label="`Remove location ${l.location_name || i + 1}`">Remove</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label v-for="[field, label, required, span] in locationFields" :key="field" class="block" :class="span">
                                <span class="text-sm text-slate-600">{{ label }}<span v-if="required" class="text-red-600"> *</span></span>
                                <input v-model="l[field]" :aria-required="required ? 'true' : undefined" :type="field === 'email' ? 'email' : 'text'" :class="inputClass" />
                                <span v-if="locationError(i, field)" class="mt-1 block text-xs text-red-600">{{ locationError(i, field) }}</span>
                            </label>
                        </div>
                        <label class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" :checked="l.primary_location" @change="setPrimary(i, $event.target.checked)" class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                            Primary location
                        </label>
                    </div>
                    <p v-if="form.errors.campus_locations" class="mb-2 text-xs text-red-600">{{ form.errors.campus_locations }}</p>
                    <button type="button" @click="addLocation" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100">+ Add campus location</button>
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="text-sm font-medium text-slate-600">Address for Service</legend>
                    <p class="mb-2 text-xs text-slate-500">Prefilled from your institution profile. Update it here if this application's address for service is different.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Street Address <span class="text-red-600">*</span></span>
                            <input v-model="form.service_street1" aria-required="true" type="text" autocomplete="address-line1" :class="inputClass" />
                            <span v-if="form.errors.service_street1" class="mt-1 block text-xs text-red-600">{{ form.errors.service_street1 }}</span>
                        </label>
                        <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Street Address Line 2</span>
                            <input v-model="form.service_street2" type="text" autocomplete="address-line2" :class="inputClass" />
                        </label>
                        <label class="block"><span class="text-sm text-slate-600">City <span class="text-red-600">*</span></span>
                            <input v-model="form.service_city" aria-required="true" type="text" autocomplete="address-level2" :class="inputClass" />
                            <span v-if="form.errors.service_city" class="mt-1 block text-xs text-red-600">{{ form.errors.service_city }}</span>
                        </label>
                        <label class="block"><span class="text-sm text-slate-600">Province <span class="text-red-600">*</span></span>
                            <input v-model="form.service_province" aria-required="true" type="text" autocomplete="address-level1" :class="inputClass" />
                            <span v-if="form.errors.service_province" class="mt-1 block text-xs text-red-600">{{ form.errors.service_province }}</span>
                        </label>
                        <label class="block"><span class="text-sm text-slate-600">Postal Code <span class="text-red-600">*</span></span>
                            <input v-model="form.service_postal_code" aria-required="true" type="text" autocomplete="postal-code" :class="inputClass" />
                            <span v-if="form.errors.service_postal_code" class="mt-1 block text-xs text-red-600">{{ form.errors.service_postal_code }}</span>
                        </label>
                        <label class="block"><span class="text-sm text-slate-600">Country <span class="text-red-600">*</span></span>
                            <input v-model="form.service_country" aria-required="true" type="text" autocomplete="country-name" :class="inputClass" />
                            <span v-if="form.errors.service_country" class="mt-1 block text-xs text-red-600">{{ form.errors.service_country }}</span>
                        </label>
                    </div>
                    <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form.service_address_confirmed" aria-required="true" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600" />
                        <span>I confirm this is the institution's address for service. <span class="text-red-600">*</span></span>
                    </label>
                    <p v-if="form.errors.service_address_confirmed" class="mt-1 text-xs text-red-600">{{ form.errors.service_address_confirmed }}</p>
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="text-sm font-medium text-slate-600">Previous Operating Names (last 5 years)</legend>
                    <p class="mb-2 text-xs text-slate-500">List any other names the institution operated under in the last 5 years and the dates each was used. Leave "Used To" blank if the name is still in use.</p>
                    <div v-for="(n, i) in form.previous_names" :key="i" class="mb-3 rounded-md border border-slate-200 p-3">
                        <div class="grid gap-3 sm:grid-cols-[1fr_10rem_10rem_auto] sm:items-start">
                            <label class="block"><span class="text-sm text-slate-600">Name<span class="text-red-600"> *</span></span>
                                <input v-model="n.name" aria-required="true" type="text" :class="inputClass" />
                                <span v-if="nameError(i, 'name')" class="mt-1 block text-xs text-red-600">{{ nameError(i, 'name') }}</span>
                            </label>
                            <label class="block"><span class="text-sm text-slate-600">Used From<span class="text-red-600"> *</span></span>
                                <input v-model="n.used_from" aria-required="true" type="date" :max="today" :class="inputClass" />
                                <span v-if="nameError(i, 'used_from')" class="mt-1 block text-xs text-red-600">{{ nameError(i, 'used_from') }}</span>
                            </label>
                            <label class="block"><span class="text-sm text-slate-600">Used To</span>
                                <input v-model="n.used_to" type="date" :min="n.used_from || undefined" :max="today" :class="inputClass" />
                                <span v-if="nameError(i, 'used_to')" class="mt-1 block text-xs text-red-600">{{ nameError(i, 'used_to') }}</span>
                            </label>
                            <button type="button" @click="removePreviousName(i)" class="self-end rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"
                                :aria-label="`Remove previous name ${n.name || i + 1}`">Remove</button>
                        </div>
                    </div>
                    <p v-if="!form.previous_names.length" class="mb-2 text-sm text-slate-500">No previous operating names.</p>
                    <button type="button" @click="addPreviousName" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100">+ Add previous name</button>
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="text-sm font-medium text-slate-600">BC Registry Services</legend>
                    <label class="mt-2 flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form.annual_report_filed" aria-required="true" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600" />
                        <span>Institution is currently in compliance with BC Registry Services and up to date on filing of annual reports.<span class="text-red-600"> *</span></span>
                    </label>
                    <p v-if="form.errors.annual_report_filed" class="ml-6 mt-1 text-xs text-red-600">{{ form.errors.annual_report_filed }}</p>
                </fieldset>
            </section>
            </div>

            <div v-show="step === 3" class="space-y-6">
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

            <!-- Collaborations and Partnerships -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-slate-700">Collaborations and Partnerships</h2>
                <label class="block"><span class="text-sm text-slate-600">Affiliates or Partnership Institutions</span>
                    <span class="mb-1 block text-xs italic text-slate-400">List any official affiliations or partnerships the institution has with other institutions.</span>
                    <textarea v-model="form.affiliates_partners" rows="3" :class="inputClass"></textarea>
                </label>
            </section>
            </div>

            <div v-show="step === 4" class="space-y-6">
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
                <label class="mt-4 block"><span class="text-sm text-slate-600">Other Logos or Symbols and Trademarks</span>
                    <span class="mb-1 block text-xs italic text-slate-400">List any other marks, logos, or symbols that may appear in conjunction with the EQA Brand.</span>
                    <textarea v-model="form.other_logos_trademarks" rows="3" :class="inputClass"></textarea>
                </label>
            </section>
            </div>

            <div v-show="step === 5" class="space-y-6">
            <!-- Website Compliance -->
            <section v-if="websiteCompliance.length" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-700">Website Compliance</h2>
                <p class="mb-4 text-xs text-slate-500">Provide a link and/or a comment for each item. At least one is required.</p>
                <fieldset v-for="(section, s) in websiteCompliance" :key="s" :class="s > 0 ? 'mt-6' : ''">
                    <legend class="text-sm font-medium text-slate-600">{{ section.title }}</legend>
                    <p v-if="section.intro" class="mb-2 text-xs text-slate-500">{{ section.intro }}</p>
                    <div v-for="item in section.items" :key="item.key" class="mb-3 rounded-md border border-slate-200 p-3">
                        <p class="text-sm font-medium text-slate-700">{{ item.label }} <span class="text-red-600">*</span>
                            <InfoTooltip v-if="item.help_text" class="ml-1" :title="item.label" :text="item.help_text" />
                        </p>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <label class="block"><span class="text-sm text-slate-600">Link</span>
                                <input v-model="form.website_compliance[item.key].url" type="url" inputmode="url" placeholder="https://" :class="inputClass" />
                                <span v-if="complianceError(item.key, 'url')" class="mt-1 block text-xs text-red-600">{{ complianceError(item.key, 'url') }}</span>
                            </label>
                            <label class="block"><span class="text-sm text-slate-600">Comment</span>
                                <textarea v-model="form.website_compliance[item.key].comment" rows="2" :class="inputClass"></textarea>
                                <span v-if="complianceError(item.key, 'comment')" class="mt-1 block text-xs text-red-600">{{ complianceError(item.key, 'comment') }}</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </section>
            <p v-if="!websiteCompliance.length" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">No website compliance information is required.</p>
            </div>

            <div v-show="step === 6" class="space-y-6">
            <!-- Per Student Fee Payment Enrollment -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="block sm:max-w-sm"><label for="app-per_student_fee_enrolment" class="text-sm font-semibold text-slate-700">Per Student Fee Payment Enrollment <span class="text-red-600">*</span></label>
                    <InfoTooltip class="ml-1" title="Per Student Fee Payment Enrollment" :text="help.per_student_fee_enrolment" />
                    <input id="app-per_student_fee_enrolment" aria-required="true" v-model="form.per_student_fee_enrolment" type="number" min="0" step="1" inputmode="numeric" :class="inputClass" />
                    <span v-if="form.errors.per_student_fee_enrolment" class="mt-1 block text-xs text-red-600">{{ form.errors.per_student_fee_enrolment }}</span>
                </div>
            </section>

            <!-- Conditions of use affirmations -->
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold text-slate-700">I have read and understand the conditions of use pertaining to the following:</h2>
                <p class="mb-4 text-xs text-slate-400">All affirmations must be confirmed before submitting.</p>
                <ul class="space-y-3 text-sm">
                    <li v-for="[field, parts] in affirmations" :key="field">
                        <label class="flex items-start gap-2">
                            <input type="checkbox" v-model="form[field]" aria-required="true" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600" />
                            <span class="text-slate-700"><template v-for="([text, italic], k) in parts" :key="k"><em v-if="italic">{{ text }}</em><template v-else>{{ text }}</template></template><span class="text-red-600"> *</span></span>
                        </label>
                        <p v-if="form.errors[field]" class="ml-6 mt-1 text-xs text-red-600">{{ form.errors[field] }}</p>
                    </li>
                </ul>

                <label class="mt-4 block"><span class="text-sm text-slate-600">Institutional Representative Signature<span class="text-red-600"> *</span></span>
                    <input v-model="form.representative_signature" aria-required="true" type="text" :class="inputClass" />
                    <span v-if="form.errors.representative_signature" class="mt-1 block text-xs text-red-600">{{ form.errors.representative_signature }}</span>
                </label>
            </section>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-4">
                <div class="flex gap-2">
                    <button v-if="step > 1" type="button" @click="goTo(step - 1)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">← Back</button>
                    <Link href="/web/applications" class="rounded-md px-4 py-2 text-sm font-medium text-slate-600 hover:underline">Cancel</Link>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" :disabled="form.processing" class="rounded-md border border-indigo-300 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50">Save Draft</button>
                    <button v-if="step < steps.length" type="button" @click="goTo(step + 1)" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Next →</button>
                    <button v-else type="button" :disabled="form.processing" @click="save('submit')" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">{{ resubmitting ? 'Resubmit' : 'Submit' }} Application</button>
                </div>
            </div>
        </form>
    </div>
</template>
