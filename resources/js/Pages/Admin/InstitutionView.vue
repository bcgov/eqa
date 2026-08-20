<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    institution: { type: Object, default: null },
    applications: { type: Array, default: () => [] },
    campuses: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    dbas: { type: Array, default: () => [] },
    sentEmails: { type: Array, default: () => [] },
    designationOptions: { type: Object, default: () => ({ statuses: [], standings: [], ptibRequired: false, ptibQaMetThrough: '' }) },
})

const flash = computed(() => usePage().props.flash || {})
const instId = computed(() => props.institution?.crm_id)

const institutionAddress = computed(() =>
    [props.institution?.street1, props.institution?.street2].filter(Boolean).join(', ')
)

const filterSelectClass = 'rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 focus:border-slate-500 focus:outline-none'

// Applications filter by status, defaults to Show All.
const applicationStatusFilter = ref('')
const applicationStatuses = computed(() =>
    [...new Set(props.applications.map((a) => a.status).filter(Boolean))].sort()
)
const filteredApplications = computed(() =>
    applicationStatusFilter.value
        ? props.applications.filter((a) => a.status === applicationStatusFilter.value)
        : props.applications
)

// Locations / Contacts / DBAs default to Active.
const locationStatusFilter = ref('Active')
const filteredCampuses = computed(() =>
    locationStatusFilter.value === 'all'
        ? props.campuses
        : props.campuses.filter((c) => (c.status || 'Inactive') === locationStatusFilter.value)
)

const contactStatusFilter = ref('all')
const filteredUsers = computed(() =>
    contactStatusFilter.value === 'all'
        ? props.users
        : props.users.filter((u) => (u.account_disabled ? 'Inactive' : 'Active') === contactStatusFilter.value)
)

const dbaStatusFilter = ref('Active')
const filteredDbas = computed(() =>
    dbaStatusFilter.value === 'all'
        ? props.dbas
        : props.dbas.filter((d) => (d.status || 'Inactive') === dbaStatusFilter.value)
)

// Sent-emails audit log: view a single message body in a modal.
const emailModal = ref(null)
const openEmail = (e) => { emailModal.value = e }
const closeEmail = () => { emailModal.value = null }
const emailDateTime = (v) => {
    if (!v) return '—'
    const d = new Date(String(v).replace(' ', 'T'))
    return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleString()
}

// PTIRU Standing is only relevant/required when QA is met through PTIRU Designation.
const ptibRequired = computed(() => props.designationOptions?.ptibRequired === true)
const ptibBlocksDesignation = computed(() =>
    ptibRequired.value && designationForm.ptib_standing !== 'In Good Standing')

const editingDesignation = ref(false)
const designationForm = useForm({
    eqa_status: props.institution?.eqa_status ?? 'Pending',
    eqa_standing: props.institution?.eqa_standing ?? '',
    ptib_standing: props.institution?.ptib_standing ?? '',
    designation_start: props.institution?.designation_start ?? '',
    designation_expiry: props.institution?.designation_expiry ?? '',
})

const selectClass = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none'

function saveDesignation() {
    designationForm
        .transform((data) => {
            const out = { ...data }
            for (const k of ['eqa_standing', 'ptib_standing', 'designation_start', 'designation_expiry']) {
                if (out[k] === '') out[k] = null
            }
            return out
        })
        .put(`/admin/institutions/${instId.value}/designation`, {
            preserveScroll: true,
            onSuccess: () => { editingDesignation.value = false },
        })
}

function cancelDesignation() {
    designationForm.reset()
    designationForm.clearErrors()
    editingDesignation.value = false
}

function address(c) {
    return [c.street1, c.street2, c.street3, c.city, c.province, c.postal_code].filter(Boolean).join(', ')
}

function applicationStatusClass(s) {
    if (s === 'Approved') return 'bg-green-100 text-green-700'
    if (s === 'Not Approved') return 'bg-red-100 text-red-700'
    if (s === 'Under Review' || s === 'Suitability Review') return 'bg-amber-100 text-amber-700'
    if (s === 'Pending Review') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}

function money(v) {
    return v != null ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'
}

function toggleCampus(c) {
    router.patch(`/admin/institutions/${instId.value}/campuses/${c.crm_id}/toggle`)
}
function removeCampus(c) {
    if (confirm(`Remove ${c.location_name || c.name || 'this location'}? This cannot be undone.`)) {
        router.delete(`/admin/institutions/${instId.value}/campuses/${c.crm_id}`)
    }
}

const staffRoleClass = (active) =>
    active ? 'bg-green-600 text-white' : 'bg-white text-green-700 hover:bg-green-50'

function switchStaffRole(u, role) {
    if (!u.user_id || u.access_type === role) return
    if (!confirm(`Switch ${u.full_name || u.email}'s role to: ${role}?`)) return
    router.put(`/admin/institutions/${instId.value}/staff/${u.user_id}/role`, { role }, { preserveScroll: true })
}
function switchStaffStatus(u, disabled) {
    if (!u.user_id || u.account_disabled === disabled) return
    if (!confirm(`Switch ${u.full_name || u.email}'s status to: ${disabled ? 'Inactive' : 'Active'}?`)) return
    router.put(`/admin/institutions/${instId.value}/staff/${u.user_id}/status`, { disabled }, { preserveScroll: true })
}

const fetchingBceid = ref(null)
function fetchBceid(u) {
    if (fetchingBceid.value) return
    if (!u.bceid_username && !u.web_user_name) {
        alert('This contact has no BCeID username to look up.')
        return
    }
    fetchingBceid.value = u.crm_id
    router.post(`/admin/institutions/${instId.value}/staff/${u.crm_id}/fetch-bceid`, {}, {
        preserveScroll: true,
        onFinish: () => { fetchingBceid.value = null },
    })
}

function toggleDba(d) {
    router.patch(`/admin/institutions/${instId.value}/dbas/${d.crm_id}/toggle`)
}
function removeDba(d) {
    if (confirm(`Remove ${d.name || 'this DBA'}? This cannot be undone.`)) {
        router.delete(`/admin/institutions/${instId.value}/dbas/${d.crm_id}`)
    }
}
</script>

<template>
    <Head :title="institution?.name || 'Institution'" />

    <div class="mb-4">
        <Link href="/admin/institutions" class="text-sm text-indigo-600 hover:underline">← Institutions</Link>
    </div>

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ institution?.name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Institution ID <span class="font-mono text-slate-600">{{ institution?.institution_number }}</span></p>
        </div>
        <div class="flex items-center gap-3">
            <span class="rounded-full px-3 py-1 text-sm font-semibold"
                :class="institution?.eqa_status === 'Designated' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'">
                {{ institution?.eqa_status || 'Not Designated' }}
            </span>
            <Link :href="`/admin/institutions/${instId}/edit`" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Edit Institution Details</Link>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Designation Information</h2>
                <button v-if="!editingDesignation" type="button" @click="editingDesignation = true"
                    class="text-xs font-semibold text-indigo-600 hover:underline">Manage</button>
            </div>

            <dl v-if="!editingDesignation" class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">QA Met Through</dt><dd class="text-right text-slate-800">{{ institution?.qa_met_through || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">EQA Status</dt><dd class="text-slate-800">{{ institution?.eqa_status || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">EQA Standing</dt><dd class="text-slate-800">{{ institution?.eqa_standing || '—' }}</dd></div>
                <div v-if="ptibRequired" class="flex justify-between py-2"><dt class="text-slate-500">PTIRU Standing</dt><dd class="text-slate-800">{{ institution?.ptib_standing || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Designation Start</dt><dd class="text-slate-800">{{ institution?.designation_start || '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Designation Expiry</dt><dd class="text-slate-800">{{ institution?.designation_expiry || '—' }}</dd></div>
            </dl>

            <form v-else @submit.prevent="saveDesignation" class="space-y-3 text-sm">
                <label class="block"><span class="text-slate-500">EQA Status</span>
                    <select v-model="designationForm.eqa_status" :class="selectClass">
                        <option v-for="s in designationOptions.statuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <span v-if="designationForm.errors.eqa_status" class="mt-1 block text-xs text-red-600">{{ designationForm.errors.eqa_status }}</span>
                </label>
                <label class="block"><span class="text-slate-500">EQA Standing</span>
                    <select v-model="designationForm.eqa_standing" :class="selectClass">
                        <option value="">—</option>
                        <option v-for="s in designationOptions.standings" :key="s" :value="s">{{ s }}</option>
                    </select>
                </label>
                <label class="block"><span class="text-slate-500">PTIRU Standing<span v-if="ptibRequired" class="text-red-500"> *</span></span>
                    <select v-model="designationForm.ptib_standing" :class="selectClass">
                        <option value="">—</option>
                        <option v-for="s in designationOptions.standings" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <span v-if="designationForm.errors.ptib_standing" class="mt-1 block text-xs text-red-600">{{ designationForm.errors.ptib_standing }}</span>
                    <span v-else-if="!ptibRequired" class="mt-1 block text-xs text-slate-400">Not applicable — QA is not met through PTIRU Designation.</span>
                </label>
                <label class="block"><span class="text-slate-500">Designation Start</span>
                    <input type="date" v-model="designationForm.designation_start" :class="selectClass" />
                </label>
                <label class="block"><span class="text-slate-500">Designation Expiry</span>
                    <input type="date" v-model="designationForm.designation_expiry" :class="selectClass" />
                </label>
                <p v-if="ptibRequired && designationForm.eqa_status === 'Designated' && ptibBlocksDesignation"
                    class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    QA is met through PTIRU Designation, so this institution cannot be <span class="font-medium">Designated</span> unless PTIRU Standing is <span class="font-medium">In Good Standing</span>.
                </p>
                <p class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    Setting EQA Status to <span class="font-medium">Designated</span> stamps the start/expiry dates if empty. Changing a standing updates the locked “in good standing” value on this institution's open applications.<span v-if="ptibRequired"> Because QA is met through PTIRU Designation, PTIRU Standing is required and must be In Good Standing to designate.</span>
                </p>
                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" :disabled="designationForm.processing"
                        class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50">Save</button>
                    <button type="button" @click="cancelDesignation"
                        class="rounded-md border border-slate-300 px-4 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                </div>
            </form>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Institution Details</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Legal Name</dt><dd class="text-right text-slate-800">{{ institution?.legal_name || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">BC Incorporation Number</dt><dd class="text-right text-slate-800">{{ institution?.bc_incorporation_number || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">DLI Number</dt><dd class="text-right text-slate-800">{{ institution?.dli_number || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Business Owner Name</dt><dd class="text-right text-slate-800">{{ institution?.business_owner || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Primary Contact</dt><dd class="text-right text-slate-800">{{ institution?.primary_contact || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Website</dt><dd class="max-w-[16rem] truncate text-right text-slate-800"><a v-if="institution?.website" :href="institution.website" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">{{ institution.website }}</a><span v-else>—</span></dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Address</dt><dd class="text-right text-slate-800">{{ institutionAddress || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">City</dt><dd class="text-right text-slate-800">{{ institution?.city || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Province</dt><dd class="text-right text-slate-800">{{ institution?.province || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Postal Code</dt><dd class="text-right text-slate-800">{{ institution?.postal_code || '—' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Country</dt><dd class="text-right text-slate-800">{{ institution?.country || '—' }}</dd></div>
            </dl>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Student Enrolment</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt class="text-slate-500">Total Enrolment</dt><dd class="text-slate-800">{{ institution?.total_enrolment ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Intl - Study Permit</dt><dd class="text-slate-800">{{ institution?.intl_students_permit ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Intl - Other</dt><dd class="text-slate-800">{{ institution?.intl_students_other ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">In-Person</dt><dd class="text-slate-800">{{ institution?.in_person_students ?? '—' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-slate-500">Online</dt><dd class="text-slate-800">{{ institution?.online_students ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-800">Applications <span class="text-sm font-normal text-slate-500">({{ filteredApplications.length }})</span></h2>
        <label class="flex items-center gap-2 text-xs text-slate-500">Status
            <select v-model="applicationStatusFilter" :class="filterSelectClass">
                <option value="">Show All</option>
                <option v-for="s in applicationStatuses" :key="s" :value="s">{{ s }}</option>
            </select>
        </label>
    </div>
    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Reference</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5">Application Date</th>
                    <th class="px-3 py-2.5">Approved Date</th>
                    <th class="px-3 py-2.5 text-right">Total Due</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="a in filteredApplications" :key="a.crm_id" class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-600">{{ a.reference }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="applicationStatusClass(a.status)">{{ a.status || '—' }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ a.application_date || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ a.approved_date || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-slate-600">{{ money(a.total_due) }}</td>
                    <td class="px-3 py-2">
                        <Link :href="`/admin/applications/${a.crm_id}`" class="font-medium text-indigo-600 hover:underline">Review →</Link>
                    </td>
                </tr>
                <tr v-if="filteredApplications.length === 0"><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-400">No applications recorded.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-800">Locations <span class="text-sm font-normal text-slate-500">({{ filteredCampuses.length }})</span></h2>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-slate-500">Status
                <select v-model="locationStatusFilter" :class="filterSelectClass">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="all">Show All</option>
                </select>
            </label>
            <Link :href="`/admin/institutions/${instId}/campuses/new`" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Add Location</Link>
        </div>
    </div>
    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Location Name</th>
                    <th class="px-3 py-2.5">Address</th>
                    <th class="px-3 py-2.5">Primary</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="c in filteredCampuses" :key="c.crm_id" class="align-top hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">{{ c.location_name || c.name || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ address(c) || '—' }}</td>
                    <td class="px-3 py-2">
                        <span v-if="c.primary_location" class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">Primary</span>
                        <span v-else class="text-xs text-slate-400">—</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="c.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">{{ c.status || '—' }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2 whitespace-nowrap text-xs">
                            <Link :href="`/admin/institutions/${instId}/campuses/${c.crm_id}/edit`" class="text-indigo-600 hover:underline">Edit</Link>
                            <button type="button" @click="toggleCampus(c)" class="text-amber-600 hover:underline">{{ c.status === 'Active' ? 'Deactivate' : 'Reactivate' }}</button>
                            <button type="button" @click="removeCampus(c)" class="text-red-600 hover:underline">Remove</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredCampuses.length === 0"><td colspan="5" class="px-3 py-6 text-center text-sm text-slate-400">No locations recorded.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-800">Institution Staff <span class="text-sm font-normal text-slate-500">— BCeID Accounts ({{ filteredUsers.length }})</span></h2>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-slate-500">Status
                <select v-model="contactStatusFilter" :class="filterSelectClass">
                    <option value="all">Show All</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </label>
            <Link :href="`/admin/institutions/${instId}/users/new`" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Add Contact</Link>
        </div>
    </div>
    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Name</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">User ID</th>
                    <th class="px-3 py-2.5">GUID</th>
                    <th class="px-3 py-2.5">Business (BCeID)</th>
                    <th class="px-3 py-2.5">Role</th>
                    <th class="px-3 py-2.5">Status</th>
                    <!-- <th class="px-3 py-2.5 text-right">BCeID</th> -->
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="u in filteredUsers" :key="u.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">{{ u.full_name || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ u.email || '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ u.bceid_username || '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs uppercase text-slate-500">{{ u.bceid_user_guid || '—' }}</td>
                    <td class="px-3 py-2 text-xs text-slate-600">
                        <div>{{ u.bceid_business_legal_name || '—' }}</div>
                        <div v-if="u.bceid_business_guid" class="font-mono uppercase text-slate-400">{{ u.bceid_business_guid }}</div>
                    </td>
                    <td class="px-3 py-2">
                        <span v-if="!u.user_id" class="text-xs text-slate-400">No account</span>
                        <div v-else class="inline-flex overflow-hidden rounded-md border border-green-600 text-xs font-medium" role="group" aria-label="Toggle staff role">
                            <button
                                v-for="r in ['Admin', 'User', 'Guest']"
                                :key="r"
                                type="button"
                                class="border-l border-green-600 px-3 py-1.5 first:border-l-0 transition"
                                :class="staffRoleClass(u.access_type === r)"
                                @click="switchStaffRole(u, r)"
                            >{{ r }}</button>
                        </div>
                    </td>
                    <td class="px-3 py-2">
                        <span v-if="!u.user_id" class="text-xs text-slate-400">—</span>
                        <div v-else class="inline-flex overflow-hidden rounded-md border border-green-600 text-xs font-medium" role="group" aria-label="Toggle staff status">
                            <button
                                type="button"
                                class="px-3 py-1.5 transition"
                                :class="staffRoleClass(!u.account_disabled)"
                                @click="switchStaffStatus(u, false)"
                            >Active</button>
                            <button
                                type="button"
                                class="border-l border-green-600 px-3 py-1.5 transition"
                                :class="staffRoleClass(u.account_disabled)"
                                @click="switchStaffStatus(u, true)"
                            >Inactive</button>
                        </div>
                    </td>
                    <!-- <td class="px-3 py-2 text-right">
                        <button
                            type="button"
                            class="rounded-md border border-indigo-600 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="fetchingBceid === u.crm_id || (!u.bceid_username && !u.web_user_name)"
                            :title="(!u.bceid_username && !u.web_user_name) ? 'No BCeID username on this contact' : 'Fetch BCeID User GUID, Business GUID and Legal Name'"
                            @click="fetchBceid(u)"
                        >{{ fetchingBceid === u.crm_id ? 'Fetching…' : 'Fetch BCeID' }}</button>
                    </td> -->
                </tr>
                <tr v-if="filteredUsers.length === 0"><td colspan="8" class="px-3 py-6 text-center text-sm text-slate-400">No staff recorded.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-800">DBAs <span class="text-sm font-normal text-slate-500">({{ filteredDbas.length }})</span></h2>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-slate-500">Status
                <select v-model="dbaStatusFilter" :class="filterSelectClass">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="all">Show All</option>
                </select>
            </label>
            <Link :href="`/admin/institutions/${instId}/dbas/new`" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Add DBA</Link>
        </div>
    </div>
    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">DBA Name</th>
                    <th class="px-3 py-2.5">Address</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="d in filteredDbas" :key="d.crm_id" class="align-top hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">{{ d.name || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ address(d) || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ d.email || '—' }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="d.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">{{ d.status || '—' }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2 whitespace-nowrap text-xs">
                            <Link :href="`/admin/institutions/${instId}/dbas/${d.crm_id}/edit`" class="text-indigo-600 hover:underline">Edit</Link>
                            <button type="button" @click="toggleDba(d)" class="text-amber-600 hover:underline">{{ d.status === 'Active' ? 'Deactivate' : 'Reactivate' }}</button>
                            <button type="button" @click="removeDba(d)" class="text-red-600 hover:underline">Remove</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredDbas.length === 0"><td colspan="5" class="px-3 py-6 text-center text-sm text-slate-400">No DBAs recorded.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-800">Emails <span class="text-sm font-normal text-slate-500">({{ sentEmails.length }})</span></h2>
    </div>
    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Sent</th>
                    <th class="px-3 py-2.5">Template</th>
                    <th class="px-3 py-2.5">Subject</th>
                    <th class="px-3 py-2.5">Recipient</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="e in sentEmails" :key="e.id" class="align-top hover:bg-slate-50">
                    <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ emailDateTime(e.sent_at || e.created_at) }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ e.template_name || e.template_key || '—' }}</td>
                    <td class="px-3 py-2 font-medium text-slate-800">{{ e.subject || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">
                        {{ e.recipient || '—' }}
                        <span v-if="e.intended_recipient" class="block text-xs text-amber-600">redirected (intended: {{ e.intended_recipient }})</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="e.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ e.status || '—' }}</span>
                        <span v-if="e.error" class="block text-xs text-red-500">{{ e.error }}</span>
                    </td>
                    <td class="px-3 py-2 text-xs">
                        <button type="button" @click="openEmail(e)" class="text-indigo-600 hover:underline">View</button>
                    </td>
                </tr>
                <tr v-if="sentEmails.length === 0"><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-400">No emails sent.</td></tr>
            </tbody>
        </table>
    </div>

    <div v-if="emailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeEmail">
        <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">{{ emailModal.subject || '(no subject)' }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        To {{ emailModal.recipient || '—' }} · {{ emailDateTime(emailModal.sent_at || emailModal.created_at) }}
                    </p>
                </div>
                <button type="button" @click="closeEmail" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <div class="overflow-y-auto px-4 py-4 text-sm text-slate-700" v-html="emailModal.body || '<em>(empty body)</em>'"></div>
        </div>
    </div>
</template>