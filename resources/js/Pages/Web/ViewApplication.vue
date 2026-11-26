<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { affirmations as affirmationLabels } from '../../applicationAffirmations'
import ApplicationLocationsSummary from '../../Components/ApplicationLocationsSummary.vue'
import WebsiteComplianceSummary from '../../Components/WebsiteComplianceSummary.vue'

const props = defineProps({
    institution: { type: Object, default: null },
    application: { type: Object, default: () => ({}) },
    invoices: { type: Array, default: () => [] },
    canPayOnline: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    canAppeal: { type: Boolean, default: false },
    awaitingSubmission: { type: Boolean, default: false },
    campusLocations: { type: Array, default: () => [] },
    previousNames: { type: Array, default: () => [] },
    websiteCompliance: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})
const busy = ref(false)
const feeLabels = { application_new: 'New Application Fee', application_renewal: 'Renewal Application Fee', admin_variable: 'Administrative Fee', appeal: 'Appeal Fee' }
const reopened = computed(() => props.application.workflow_stage === 'changes_requested')
const unpaidFee = computed(() => props.invoices.some((i) => i.payable))
// Started but the application fee not yet paid: the application completion deadline applies.
const completionPending = computed(() => ['draft', 'fee_required'].includes(props.application.workflow_stage))

function post(url) {
    busy.value = true
    router.post(url, {}, { preserveScroll: true, onFinish: () => { busy.value = false } })
}
const pay = (invoice) => post(`/web/invoices/${invoice.crm_id}/pay`)
const submitDraft = () => post(`/web/applications/${props.application.crm_id}/submit`)
const appeal = () => post(`/web/applications/${props.application.crm_id}/appeal`)

const dash = (v) => (v === null || v === undefined || v === '' ? '—' : v)

const currency = (v) => {
    if (v === null || v === undefined || v === '') {
        return '$0.00'
    }
    const n = Number(v)
    if (Number.isNaN(n)) {
        return dash(v)
    }
    return n.toLocaleString('en-CA', { style: 'currency', currency: 'CAD' })
}

const yesNo = (v) => (v === true ? 'Yes' : v === false ? 'No' : '—')

// Type of Education Offered — mirrors the legacy "program_*" flags.
const educationTypes = computed(() => [
    ['Associate Degree', props.application.program_associate_degree],
    ['University Transfer', props.application.program_university_transfer],
    ['Bachelors Degree', props.application.program_bachelors_degree],
    ['Graduate Degree', props.application.program_graduate_degree],
    ['Career Training', props.application.program_career_training],
    ['Language Training', props.application.program_language_training],
    ['Theological Education', props.application.program_theological_education],
    ['Trades / Apprenticeship Training', props.application.program_trades_apprenticeship],
])

// EQA Brand Usage Plan — Media Placements Planned.
const mediaPlacements = computed(() => [
    ['Pamphlet', props.application.media_pamphlet],
    ['Website', props.application.media_website],
    ['Brochure', props.application.media_brochure],
    ['Poster', props.application.media_poster],
    ['Banner', props.application.media_banner],
    ['Billboard/Signage', props.application.media_billboard],
])

// Conditions of use affirmations (same wording as the form).
const affirmations = computed(() => affirmationLabels.map(([field, parts]) => [field, parts, props.application[field]]))
// "?" bubble text, maintained under Maintenance > Utils (Application Help Text).
const help = computed(() => usePage().props.settings?.help ?? {})
</script>

<template>
    <Head :title="application.reference || 'Application'" />

    <div class="mx-auto max-w-5xl">
        <div class="mb-4">
            <Link href="/web/applications" class="text-sm text-indigo-600 hover:underline">← Applications</Link>
        </div>

        <h1 class="text-2xl font-bold text-slate-800">
            View Application for Reference: <span class="font-mono">{{ application.reference }}</span>
        </h1>

        <div v-if="flash.success" class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
        <div v-if="flash.error" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

        <!-- Missed resubmission deadline, waiting for EQA staff to confirm the expiry or extend it -->
        <div v-if="application.workflow_stage === 'pending_expiry'" class="mt-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">The deadline to resubmit this application ({{ dash(application.changes_due_date) }}) has passed.</p>
            <p class="mt-1">EQA staff will review it and either close the application or give you more time. Contact EQA@gov.bc.ca if you need an extension.</p>
        </div>

        <!-- Next steps: fees, changes requested, appeal -->
        <section v-if="invoices.length || canEdit || canAppeal || application.compliance_order" class="mt-6 rounded-lg border-2 border-indigo-100 bg-indigo-50/40 p-5">
            <h2 class="mb-3 text-base font-semibold text-indigo-900">Status: {{ dash(application.status) }}</h2>

            <div v-if="reopened" class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <p class="font-semibold">Additional information required by {{ dash(application.changes_due_date) }}:</p>
                <p class="mt-1 whitespace-pre-line">{{ application.changes_requested }}</p>
            </div>

            <p v-if="application.compliance_order" class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <b>Approved under compliance order:</b> {{ application.compliance_order_notes }}
            </p>

            <table v-if="invoices.length && !awaitingSubmission" class="mb-4 min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="py-2">Fee</th><th class="py-2">Invoice #</th><th class="py-2">Due</th><th class="py-2 text-right">Amount</th><th class="py-2 pl-6">Status</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="i in invoices" :key="i.crm_id">
                        <td class="py-2 text-slate-700">{{ feeLabels[i.fee_type] || 'Fees' }}
                            <p v-if="i.description" class="whitespace-pre-line text-xs text-slate-500">{{ i.description }}</p>
                        </td>
                        <td class="py-2 font-mono text-xs"><a :href="`/web/invoices/${i.crm_id}/download`" class="text-indigo-600 hover:underline" :title="`Download invoice ${i.invoice_number} (PDF)`">{{ i.invoice_number }}</a></td>
                        <td class="py-2 text-slate-600">{{ dash(i.due_date) }}</td>
                        <td class="py-2 text-right text-slate-800">{{ currency(i.total_charges) }}</td>
                        <td class="py-2 pl-6 text-slate-600">{{ i.invoice_status }}</td>
                        <td class="py-2 text-right">
                            <button v-if="i.payable && canPayOnline" type="button" :disabled="busy" @click="pay(i)" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Pay Online</button>
                            <span v-else-if="i.payment_under_review" class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800" title="Do not pay again. EQA staff will confirm this payment.">Payment being confirmed</span>
                            <a v-else-if="i.invoice_status === 'Paid'" :href="`/web/invoices/${i.crm_id}/receipt`" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Download Receipt</a>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="completionPending && application.completion_due_date" class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <b>Complete by {{ application.completion_due_date }}.</b>
                {{ awaitingSubmission ? 'Submit this application and pay the application fee' : 'Pay the application fee' }} by this date, or the application expires and you will need to start a new one.
            </p>

            <p v-if="unpaidFee" class="mb-4 text-sm text-slate-600">Pay online, or pay by cheque or another method and EQA staff will confirm your payment.</p>
            <p v-if="awaitingSubmission" class="mb-4 text-sm text-slate-600">Your application has not been submitted. You can edit it until you submit it; <template v-if="[true, 1, '1', 't'].includes(institution?.fee_exempt)">no application fee applies because your institution is fee exempt.</template><template v-else>the application fee is invoiced on submission.</template></p>

            <div class="flex flex-wrap gap-2">
                <Link v-if="canEdit" :href="`/web/applications/${application.crm_id}/edit`" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit Application</Link>
                <button v-if="reopened" type="button" :disabled="busy" @click="submitDraft" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Resubmit Application</button>
                <button v-else-if="awaitingSubmission" type="button" :disabled="busy" @click="submitDraft" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Submit Application</button>
                <button v-if="canAppeal" type="button" :disabled="busy" @click="appeal" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">Appeal Decision (by {{ application.appeal_deadline }})</button>
            </div>
        </section>

        <!-- Institution Details -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Institution Details</h2>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="font-medium text-slate-500">Institution Name</dt>
                    <dd class="mt-1 text-slate-800">{{ dash(application.institution_name) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Application Date</dt>
                    <dd class="mt-1 text-slate-800">{{ dash(application.application_date) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Application Status</dt>
                    <dd class="mt-1 text-slate-800">{{ dash(application.status) }}</dd>
                </div>
                <div class="sm:col-span-3">
                    <dt class="font-medium text-slate-500">Business Owner Name</dt>
                    <dd class="mt-1 whitespace-pre-line text-slate-800">{{ dash(application.owner_name) }}</dd>
                </div>
            </dl>
        </section>

        <!-- Student Enrolment Information -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Student Enrolment Information</h2>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between border-b border-slate-50 py-1">
                    <dt class="text-slate-500" :title="help.total_enrolment">Total Institution Enrolment</dt>
                    <dd class="text-slate-800">{{ application.total_enrolment ?? '—' }}</dd>
                </div>
                <div class="flex justify-between border-b border-slate-50 py-1">
                    <dt class="text-slate-500" :title="help.intl_students_permit">Number of International Students - Study Permit</dt>
                    <dd class="text-slate-800">{{ application.intl_students_permit ?? '—' }}</dd>
                </div>
                <div class="flex justify-between border-b border-slate-50 py-1">
                    <dt class="text-slate-500" :title="help.in_person_students">Total Number of In-Person Students</dt>
                    <dd class="text-slate-800">{{ application.in_person_students ?? '—' }}</dd>
                </div>
                <div class="flex justify-between border-b border-slate-50 py-1">
                    <dt class="text-slate-500" :title="help.online_students">Total Number of Online Students</dt>
                    <dd class="text-slate-800">{{ application.online_students ?? '—' }}</dd>
                </div>
            </dl>
        </section>

        <!-- Locations, Address for Service and Previous Names -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Locations, Address for Service and Previous Names</h2>
            <ApplicationLocationsSummary :application="application" :campus-locations="campusLocations" :previous-names="previousNames" />
        </section>

        <!-- Type of Education Offered -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Type of Education Offered</h2>
            <ul class="space-y-2 text-sm">
                <li v-for="[label, checked] in educationTypes" :key="label" class="flex items-center gap-2">
                    <input type="checkbox" :checked="!!checked" disabled class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                    <span class="font-semibold text-slate-700">{{ label }}</span>
                </li>
            </ul>
        </section>

        <!-- Quality Assurance met through -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Quality Assurance met through:</h2>
            <p class="text-sm font-semibold text-slate-700">{{ dash(institution && institution.qa_met_through) }}</p>
        </section>

        <!-- SABC Designation: retired question, shown only for older applications that answered Yes -->
        <section v-if="[true, 1, '1', 't'].includes(application.sabc_designation)" class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Institution has SABC Designation?</h2>
            <p class="text-sm font-semibold text-slate-700">Yes</p>
        </section>

        <!-- Collaborations and Partnerships -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Collaborations and Partnerships</h2>
            <p class="text-sm font-medium text-slate-500">Affiliates or Partnership Institutions:</p>
            <p class="text-xs italic text-slate-400">List any official affiliations or partnerships the institution has with other institutions.</p>
            <p class="mt-1 whitespace-pre-line rounded border border-slate-100 bg-slate-50 p-3 text-sm text-slate-800">{{ dash(application.affiliates_partners) }}</p>
        </section>

        <!-- EQA Brand Usage Plan -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">EQA Brand Usage Plan</h2>

            <h3 class="mb-2 text-sm font-semibold text-slate-600">Media Placements Planned</h3>
            <ul class="space-y-2 text-sm">
                <li v-for="[label, checked] in mediaPlacements" :key="label" class="flex items-center gap-2">
                    <input type="checkbox" :checked="!!checked" disabled class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                    <span class="font-semibold text-slate-700">{{ label }}</span>
                </li>
            </ul>

            <div class="mt-4">
                <p class="text-sm font-medium text-slate-500">EQA Brand Usage Plan:</p>
                <p class="mt-1 whitespace-pre-line rounded border border-slate-100 bg-slate-50 p-3 text-sm text-slate-800">{{ dash(application.brand_usage_plan) }}</p>
            </div>

            <div class="mt-4">
                <p class="text-sm font-medium text-slate-500">Other Logos or Symbols and Trademarks</p>
                <p class="text-xs italic text-slate-400">List any other marks, logos, or symbols that may appear in conjunction with the EQA Brand.</p>
                <p class="mt-1 whitespace-pre-line rounded border border-slate-100 bg-slate-50 p-3 text-sm text-slate-800">{{ dash(application.other_logos_trademarks) }}</p>
            </div>
        </section>

        <!-- Website Compliance -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Website Compliance</h2>
            <WebsiteComplianceSummary :sections="websiteCompliance" />
        </section>

        <!-- Financials -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Financials</h2>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="font-medium text-slate-500">Application Fee</dt>
                    <dd class="mt-1 text-slate-800">{{ currency(application.application_fee) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Annual Designation Fee</dt>
                    <dd class="mt-1 text-slate-800">{{ currency(application.annual_designation_fee) }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Total Amount</dt>
                    <dd class="mt-1 text-slate-800">{{ currency(application.total_due) }}</dd>
                </div>
            </dl>
        </section>

        <!-- Per Student Fee Payment Enrollment -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">Per Student Fee Payment Enrollment</h2>
            <p class="text-sm text-slate-800" :title="help.per_student_fee_enrolment">{{ application.per_student_fee_enrolment ?? '—' }}</p>
        </section>

        <!-- Conditions of use -->
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700">I have read and understand the conditions of use pertaining to the following:</h2>
            <ul class="space-y-3 text-sm">
                <li v-for="[field, parts, checked] in affirmations" :key="field" class="flex items-start gap-2">
                    <input type="checkbox" :checked="[true, 1, '1', 't'].includes(checked)" disabled class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600" />
                    <span class="text-slate-700"><template v-for="([text, italic], k) in parts" :key="k"><em v-if="italic">{{ text }}</em><template v-else>{{ text }}</template></template></span>
                </li>
            </ul>

            <div class="mt-4">
                <p class="text-sm font-medium text-slate-500">Institutional Representative Signature:</p>
                <p class="mt-1 rounded border border-slate-100 bg-slate-50 p-2 text-sm text-slate-800">{{ dash(application.representative_signature) }}</p>
            </div>
        </section>

        <div class="mt-6">
            <Link href="/web/applications" class="inline-flex rounded-md bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Cancel
            </Link>
        </div>
    </div>
</template>
