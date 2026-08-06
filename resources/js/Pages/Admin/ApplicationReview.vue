<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    application: { type: Object, default: () => ({}) },
    invoice: { type: Object, default: null },
})

const page = usePage()
const flash = computed(() => page.props.flash || {})

const stage = computed(() => props.application.workflow_stage || 'draft')

const stageIndex = {
    draft: 0, pending_review: 1, fees_payment: 2, under_review: 3,
    application_approval: 4, not_approved: 4, non_approval_reasons: 4, suitability_review: 4, end: 5,
}
const activeIndex = computed(() => stageIndex[stage.value] ?? 0)
const decisionLabel = computed(() => {
    if (['not_approved', 'non_approval_reasons'].includes(stage.value)) return 'Not Approved'
    if (stage.value === 'suitability_review') return 'Suitability Review'
    if (stage.value === 'application_approval') return 'Application Approval'
    if (stage.value === 'end') return props.application.status === 'Approved' ? 'Application Approval' : 'Not Approved'
    return 'Decision'
})
const steps = computed(() => ['Draft Application', 'Pending Review', 'Fees Payment', 'Under Review', decisionLabel.value, 'End Process'])

const notApproved = computed(() => props.application.status === 'Not Approved')

const eqaGood = ref(props.application.eqa_good_standing ?? true)
const ptibGood = ref(props.application.ptib_good_standing ?? true)
const reasons = ref(props.application.non_approval_reasons || '')
const busy = ref(false)

const truthy = (v) => v === true || v === 't' || v === 1 || v === '1'
const money = (v) => (v === null || v === undefined || v === '') ? '$0.00' : '$' + Number(v).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const locked = computed(() => stage.value === 'end')
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none'

const a = props.application
const form = useForm({
    receipt_confirmation: truthy(a.receipt_confirmation),
    fees_payment_received: truthy(a.fees_payment_received),
    ready_for_review: truthy(a.ready_for_review),
    sabc_designation: truthy(a.sabc_designation),
    eligibility: a.eligibility ?? '',
    review_completion_date: a.review_completion_date ?? '',
    eqa_good_standing: truthy(a.eqa_good_standing),
    ptib_good_standing: truthy(a.ptib_good_standing),
    need_additional_details: truthy(a.need_additional_details),
    suitability_review_date: a.suitability_review_date ?? '',
    resubmission_date: a.resubmission_date ?? '',
    approved_date: a.approved_date ?? '',
    designation_expiry: a.designation_expiry ?? '',
    not_approved_date: a.not_approved_date ?? '',
    appeal_successful: truthy(a.appeal_successful),
    reason_incomplete: truthy(a.reason_incomplete),
    reason_non_payment: truthy(a.reason_non_payment),
    reason_withdrawn: truthy(a.reason_withdrawn),
    reason_not_good_standing: truthy(a.reason_not_good_standing),
    reason_not_meet_eligibility: truthy(a.reason_not_meet_eligibility),
    program_associate_degree: truthy(a.program_associate_degree),
    program_university_transfer: truthy(a.program_university_transfer),
    program_bachelors_degree: truthy(a.program_bachelors_degree),
    program_graduate_degree: truthy(a.program_graduate_degree),
    program_career_training: truthy(a.program_career_training),
    program_language_training: truthy(a.program_language_training),
    program_theological_education: truthy(a.program_theological_education),
    program_trades_apprenticeship: truthy(a.program_trades_apprenticeship),
    media_pamphlet: truthy(a.media_pamphlet),
    media_website: truthy(a.media_website),
    media_brochure: truthy(a.media_brochure),
    media_poster: truthy(a.media_poster),
    media_banner: truthy(a.media_banner),
    media_billboard: truthy(a.media_billboard),
    brand_usage_plan: a.brand_usage_plan ?? '',
    other_logos_trademarks: a.other_logos_trademarks ?? '',
    affiliates_partners: a.affiliates_partners ?? '',
    affirm_policy_manual: truthy(a.affirm_policy_manual),
    affirm_website_compliance: truthy(a.affirm_website_compliance),
    affirm_written_permission: truthy(a.affirm_written_permission),
    affirm_branding_guide: truthy(a.affirm_branding_guide),
    affirm_understands_comply: truthy(a.affirm_understands_comply),
    affirm_authorized: truthy(a.affirm_authorized),
    representative_signature: a.representative_signature ?? '',
    application_fee: a.application_fee ?? '',
    annual_designation_fee: a.annual_designation_fee ?? '',
})

const dateFields = ['review_completion_date', 'suitability_review_date', 'resubmission_date', 'approved_date', 'designation_expiry', 'not_approved_date', 'eligibility', 'application_fee', 'annual_designation_fee']

function save() {
    form.transform((data) => {
        const out = { ...data }
        for (const k of dateFields) { if (out[k] === '') out[k] = null }
        return out
    }).put(`/admin/applications/${a.crm_id}`, { preserveScroll: true })
}

function advance(action, extra = {}) {
    busy.value = true
    router.post(`/admin/applications/${props.application.crm_id}/advance`, { action, ...extra }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false },
    })
}

function yesNo(v) {
    if (v === true || v === 1) return 'Yes'
    if (v === false || v === 0) return 'No'
    return '—'
}

const statusClass = computed(() => {
    if (notApproved.value) return 'bg-red-100 text-red-700'
    if (props.application.status === 'Approved') return 'bg-green-100 text-green-700'
    return 'bg-amber-100 text-amber-700'
})
const btn = 'rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm disabled:opacity-50'
</script>

<template>
    <Head :title="application.reference || 'Application'" />

    <div class="mb-4">
        <Link href="/admin/applications" class="text-sm text-indigo-600 hover:underline">← Applications</Link>
    </div>

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-mono text-2xl font-bold text-slate-800">{{ application.reference }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ application.institution_name }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="statusClass">{{ application.status || '—' }}</span>
    </div>

    <!-- Approval process (Business Process Flow) -->
    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Application Approval Process</h2>
        <ol class="flex flex-wrap items-center gap-2">
            <li v-for="(label, idx) in steps" :key="idx" class="flex items-center gap-2">
                <span class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium"
                    :class="idx < activeIndex ? 'bg-green-600 text-white' : idx === activeIndex ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-400'">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full text-[10px]" :class="idx < activeIndex ? 'bg-white/30' : 'bg-black/10'">{{ idx + 1 }}</span>
                    {{ label }}
                </span>
                <span v-if="idx < steps.length - 1" class="text-slate-300">→</span>
            </li>
        </ol>
    </div>

    <div v-if="invoice" class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Invoice</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <Link :href="`/admin/invoices/${invoice.crm_id}`" class="font-mono text-indigo-600 hover:underline">{{ invoice.invoice_number }}</Link>
                    · {{ money(invoice.total_charges) }} ·
                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="invoice.invoice_status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">{{ invoice.invoice_status }}</span>
                </p>
            </div>
            <a :href="`/admin/invoices/${invoice.crm_id}/receipt`" class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">Download Order Receipt</a>
        </div>
    </div>

    <!-- Action panel: advance the workflow -->
    <div class="mt-6 rounded-lg border-2 border-indigo-100 bg-indigo-50/40 p-5">
        <h2 class="mb-3 text-sm font-semibold text-indigo-900">Next step</h2>

        <div v-if="stage === 'draft'">
            <p class="mb-3 text-sm text-slate-600">The application is a draft. Submit it to begin the review process.</p>
            <button :disabled="busy" @click="advance('submit')" :class="[btn, 'bg-indigo-600 hover:bg-indigo-700']">Submit for Review →</button>
        </div>

        <div v-else-if="stage === 'pending_review'">
            <p class="mb-3 text-sm text-slate-600">Awaiting the application fee before review can begin.</p>
            <button :disabled="busy" @click="advance('record_payment')" :class="[btn, 'bg-indigo-600 hover:bg-indigo-700']">Record Fees Payment →</button>
        </div>

        <div v-else-if="stage === 'fees_payment'">
            <p class="mb-3 text-sm text-slate-600">Fees received. Begin the eligibility review.</p>
            <button :disabled="busy" @click="advance('begin_review')" :class="[btn, 'bg-indigo-600 hover:bg-indigo-700']">Begin Review →</button>
        </div>

        <div v-else-if="stage === 'under_review'">
            <p class="mb-3 text-sm text-slate-600">Record the standing checks, then decide the eligibility outcome.</p>
            <div class="mb-4 flex flex-wrap gap-5">
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" v-model="eqaGood" class="rounded"> EQA in Good Standing</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" v-model="ptibGood" class="rounded"> PTIB in Good Standing</label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button :disabled="busy" @click="advance('eligible', { eqa_good_standing: eqaGood, ptib_good_standing: ptibGood })" :class="[btn, 'bg-green-600 hover:bg-green-700']">Eligibility Met — Approve</button>
                <button :disabled="busy" @click="advance('ineligible', { eqa_good_standing: eqaGood, ptib_good_standing: ptibGood })" :class="[btn, 'bg-red-600 hover:bg-red-700']">Eligibility Not Met</button>
                <button :disabled="busy" @click="advance('refer_suitability')" :class="[btn, 'bg-amber-500 hover:bg-amber-600']">Refer to Suitability Review</button>
            </div>
        </div>

        <div v-else-if="stage === 'application_approval'">
            <p class="mb-3 text-sm text-slate-600">Eligibility met. Finalize the approval and set the designation.</p>
            <button :disabled="busy" @click="advance('approve')" :class="[btn, 'bg-green-600 hover:bg-green-700']">Finalize Approval →</button>
        </div>

        <div v-else-if="stage === 'not_approved'">
            <p class="mb-3 text-sm text-slate-600">Eligibility not met. Record the reasons for non-approval.</p>
            <textarea v-model="reasons" rows="3" placeholder="Reasons for non-approval…" class="mb-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"></textarea>
            <button :disabled="busy" @click="advance('record_reasons', { reasons })" :class="[btn, 'bg-indigo-600 hover:bg-indigo-700']">Record Reasons →</button>
        </div>

        <div v-else-if="stage === 'non_approval_reasons'">
            <p class="mb-3 text-sm text-slate-600">Finalize the non-approval decision.</p>
            <button :disabled="busy" @click="advance('finalize')" :class="[btn, 'bg-red-600 hover:bg-red-700']">Finalize Non-Approval →</button>
        </div>

        <div v-else-if="stage === 'suitability_review'">
            <p class="mb-3 text-sm text-slate-600">Complete the suitability review and record the outcome.</p>
            <div class="flex flex-wrap gap-2">
                <button :disabled="busy" @click="advance('pass')" :class="[btn, 'bg-green-600 hover:bg-green-700']">Suitability Passed — Approve</button>
                <button :disabled="busy" @click="advance('fail')" :class="[btn, 'bg-red-600 hover:bg-red-700']">Suitability Failed — Not Approve</button>
            </div>
        </div>

        <div v-else class="flex items-center gap-2 text-sm font-medium text-slate-600">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-green-600 text-xs text-white">✓</span>
            Process complete — {{ application.status }}.
        </div>
    </div>

    <!-- Editable application details (ministry edits the fields while pending approval) -->
    <form @submit.prevent="save" class="mt-6 space-y-6">
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">General</h2>
                <div class="space-y-2 text-sm text-slate-700">
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.receipt_confirmation" :disabled="locked" class="rounded"> Receipt Confirmation?</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.fees_payment_received" :disabled="locked" class="rounded"> Fees Payment Received?</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.ready_for_review" :disabled="locked" class="rounded"> Ready to Review?</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.sabc_designation" :disabled="locked" class="rounded"> Institution has SABC Designation</label>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Review Stages</h2>
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <label class="block sm:col-span-2"><span class="text-slate-500">Eligibility Requirement</span>
                        <select v-model="form.eligibility" :disabled="locked" :class="inputClass">
                            <option value="">—</option>
                            <option>Requirements Met</option>
                            <option>Requirements Not Met</option>
                            <option>Suitability Review Needed</option>
                        </select>
                    </label>
                    <label class="block"><span class="text-slate-500">Review Completion Date</span>
                        <input type="date" v-model="form.review_completion_date" :disabled="locked" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-slate-500">Suitability Review Date</span>
                        <input type="date" v-model="form.suitability_review_date" :disabled="locked" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-slate-500">Resubmission Date</span>
                        <input type="date" v-model="form.resubmission_date" :disabled="locked" :class="inputClass" />
                    </label>
                    <div class="flex flex-col gap-2 pt-1 sm:col-span-2">
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.eqa_good_standing" :disabled="locked" class="rounded"> EQA in Good Standing</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.ptib_good_standing" :disabled="locked" class="rounded"> PTIB in Good Standing</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.need_additional_details" :disabled="locked" class="rounded"> Need Additional Details</label>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Designation Decision</h2>
                <p class="mb-3 text-sm">Current decision:
                    <span class="font-medium" :class="notApproved ? 'text-red-600' : (application.status === 'Approved' ? 'text-green-700' : 'text-slate-500')">{{ notApproved ? 'Not Approved' : (application.status === 'Approved' ? 'Approved' : '—') }}</span>
                </p>
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <label class="block"><span class="text-slate-500">Approved Date</span>
                        <input type="date" v-model="form.approved_date" :disabled="locked" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-slate-500">Designation Expiry Date</span>
                        <input type="date" v-model="form.designation_expiry" :disabled="locked" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-slate-500">Not Approved Date</span>
                        <input type="date" v-model="form.not_approved_date" :disabled="locked" :class="inputClass" />
                    </label>
                    <label class="flex items-center gap-2 pt-6"><input type="checkbox" v-model="form.appeal_successful" :disabled="locked" class="rounded"> Appeal Successful</label>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Non-Approval Reasons</h2>
                <div class="space-y-2 text-sm text-slate-700">
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.reason_incomplete" :disabled="locked" class="rounded"> Incomplete Application</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.reason_non_payment" :disabled="locked" class="rounded"> Non-Payment of Fees</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.reason_withdrawn" :disabled="locked" class="rounded"> Withdrawn</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.reason_not_good_standing" :disabled="locked" class="rounded"> Not in Good Standing</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.reason_not_meet_eligibility" :disabled="locked" class="rounded"> Does Not Meet Eligibility Requirements</label>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Financials</h2>
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <label class="block"><span class="text-slate-500">Application Fee</span>
                        <input type="number" step="0.01" min="0" v-model="form.application_fee" :disabled="locked" :class="inputClass" />
                    </label>
                    <label class="block"><span class="text-slate-500">Annual Designation Fee</span>
                        <input type="number" step="0.01" min="0" v-model="form.annual_designation_fee" :disabled="locked" :class="inputClass" />
                    </label>
                    <div class="flex justify-between border-t border-slate-100 pt-2 sm:col-span-2"><span class="text-slate-500">Total Amount</span><span class="font-medium text-slate-800">{{ money(application.total_due) }}</span></div>
                    <div class="flex justify-between sm:col-span-2"><span class="text-slate-500">Invoice Number</span><span class="text-slate-800">{{ application.invoice_number || '—' }}</span></div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Type of Education Offered</h2>
                <div class="grid grid-cols-1 gap-2 text-sm text-slate-700 sm:grid-cols-2">
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_associate_degree" :disabled="locked" class="rounded"> Associate Degree</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_career_training" :disabled="locked" class="rounded"> Career Training</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_university_transfer" :disabled="locked" class="rounded"> University Transfer</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_language_training" :disabled="locked" class="rounded"> Language Training</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_bachelors_degree" :disabled="locked" class="rounded"> Bachelors Degree</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_theological_education" :disabled="locked" class="rounded"> Theological Education</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_graduate_degree" :disabled="locked" class="rounded"> Graduate Degree</label>
                    <label class="flex items-center gap-2"><input type="checkbox" v-model="form.program_trades_apprenticeship" :disabled="locked" class="rounded"> Trades / Apprenticeship Training</label>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">EQA Branding</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Media Placements Planned</p>
                    <div class="grid grid-cols-2 gap-2 text-sm text-slate-700">
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.media_pamphlet" :disabled="locked" class="rounded"> Pamphlet</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.media_poster" :disabled="locked" class="rounded"> Poster</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.media_website" :disabled="locked" class="rounded"> Website</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.media_banner" :disabled="locked" class="rounded"> Banner</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.media_brochure" :disabled="locked" class="rounded"> Brochure</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.media_billboard" :disabled="locked" class="rounded"> Billboard / Signage</label>
                    </div>
                </div>
                <div class="space-y-3 text-sm">
                    <label class="block"><span class="text-slate-500">EQA Brand Usage Plan</span>
                        <textarea v-model="form.brand_usage_plan" rows="2" :disabled="locked" :class="inputClass"></textarea>
                    </label>
                    <label class="block"><span class="text-slate-500">Other Logos or Symbols and Trademarks</span>
                        <textarea v-model="form.other_logos_trademarks" rows="2" :disabled="locked" :class="inputClass"></textarea>
                    </label>
                    <label class="block"><span class="text-slate-500">Affiliates or Partnership Institutions</span>
                        <textarea v-model="form.affiliates_partners" rows="2" :disabled="locked" :class="inputClass"></textarea>
                    </label>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Affirmations</h2>
            <div class="space-y-2 text-sm text-slate-700">
                <label class="flex items-start gap-2"><input type="checkbox" v-model="form.affirm_policy_manual" :disabled="locked" class="mt-0.5 rounded"> Confirm reading the EQA Policy and Procedures Manual</label>
                <label class="flex items-start gap-2"><input type="checkbox" v-model="form.affirm_website_compliance" :disabled="locked" class="mt-0.5 rounded"> Institution's website is in compliance</label>
                <label class="flex items-start gap-2"><input type="checkbox" v-model="form.affirm_written_permission" :disabled="locked" class="mt-0.5 rounded"> Institutions require the written permission of the Ministry to use the EQA brand</label>
                <label class="flex items-start gap-2"><input type="checkbox" v-model="form.affirm_branding_guide" :disabled="locked" class="mt-0.5 rounded"> Use and misuse of the EQA brand are set out in the Education Quality Assurance branding guide</label>
                <label class="flex items-start gap-2"><input type="checkbox" v-model="form.affirm_understands_comply" :disabled="locked" class="mt-0.5 rounded"> The institutional representative understands what use of the EQA brand entails, and agrees to comply</label>
                <label class="flex items-start gap-2"><input type="checkbox" v-model="form.affirm_authorized" :disabled="locked" class="mt-0.5 rounded"> I am the person assigned by my organization, authorized to submit the application</label>
            </div>
            <label class="mt-3 block text-sm sm:max-w-sm"><span class="text-slate-500">Institution Representative Signature</span>
                <input type="text" v-model="form.representative_signature" :disabled="locked" :class="inputClass" />
            </label>
        </div>

        <div v-if="!locked" class="flex items-center gap-3">
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Save Application Details</button>
            <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Saved.</span>
        </div>
        <p v-else class="text-sm text-slate-400">This application's process is complete — fields are locked.</p>
    </form>
</template>