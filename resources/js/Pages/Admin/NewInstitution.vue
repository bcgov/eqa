<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const form = useForm({
    institution_number: '',
    name: '',
    legal_name: '',
    bceid_business_guid: '',
    bc_incorporation_number: '',
    website: '',
    street1: '',
    street2: '',
    city: '',
    province: '',
    postal_code: '',
    country: '',
})

function submit() {
    form.post('/admin/institutions')
}
</script>

<template>
    <Head title="New Institution" />

    <div class="mb-4">
        <Link href="/admin/institutions" class="text-sm text-indigo-600 hover:underline">← Back</Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-800">New Institution</h1>
    <p class="mt-1 text-sm text-slate-500">Fields marked <span class="text-red-600">*</span> are required.</p>

    <form @submit.prevent="submit" class="mt-6 max-w-4xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Institution Details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Institution Name <span class="text-red-600">*</span></span>
                    <input v-model="form.name" type="text" :class="inputClass" />
                    <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Legal Name <span class="text-red-600">*</span></span>
                    <input v-model="form.legal_name" type="text" :class="inputClass" />
                    <span v-if="form.errors.legal_name" class="mt-1 block text-xs text-red-600">{{ form.errors.legal_name }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Institution ID</span>
                    <input v-model="form.institution_number" type="text" :class="inputClass" />
                    <span v-if="form.errors.institution_number" class="mt-1 block text-xs text-red-600">{{ form.errors.institution_number }}</span>
                </label>
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">BCeID Business GUID <span class="text-red-600">*</span></span>
                    <input v-model="form.bceid_business_guid" type="text" placeholder="e.g. 3F2504E0-4F89-11D3-9A0C-0305E82C3301" :class="[inputClass, 'font-mono']" />
                    <span v-if="form.errors.bceid_business_guid" class="mt-1 block text-xs text-red-600">{{ form.errors.bceid_business_guid }}</span>
                    <span v-else class="mt-1 block text-xs text-slate-400">The institution's BCeID business account GUID. Must be unique across institutions.</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">BC Incorporation Number</span>
                    <input v-model="form.bc_incorporation_number" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Web URL</span>
                    <input v-model="form.website" type="text" :class="inputClass" />
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Mailing Address</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Street 1 <span class="text-red-600">*</span></span>
                    <input v-model="form.street1" type="text" :class="inputClass" />
                    <span v-if="form.errors.street1" class="mt-1 block text-xs text-red-600">{{ form.errors.street1 }}</span>
                </label>
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Street 2</span>
                    <input v-model="form.street2" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">City <span class="text-red-600">*</span></span>
                    <input v-model="form.city" type="text" :class="inputClass" />
                    <span v-if="form.errors.city" class="mt-1 block text-xs text-red-600">{{ form.errors.city }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Province <span class="text-red-600">*</span></span>
                    <input v-model="form.province" type="text" :class="inputClass" />
                    <span v-if="form.errors.province" class="mt-1 block text-xs text-red-600">{{ form.errors.province }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Postal Code <span class="text-red-600">*</span></span>
                    <input v-model="form.postal_code" type="text" :class="inputClass" />
                    <span v-if="form.errors.postal_code" class="mt-1 block text-xs text-red-600">{{ form.errors.postal_code }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Country <span class="text-red-600">*</span></span>
                    <input v-model="form.country" type="text" :class="inputClass" />
                    <span v-if="form.errors.country" class="mt-1 block text-xs text-red-600">{{ form.errors.country }}</span>
                </label>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Create Institution</button>
            <Link href="/admin/institutions" class="rounded-md border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</Link>
        </div>
    </form>
</template>
