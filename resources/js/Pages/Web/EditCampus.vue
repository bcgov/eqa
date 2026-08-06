<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    institution: { type: Object, default: null },
    campus: { type: Object, default: null },
    submitUrl: { type: String, default: '' },
    method: { type: String, default: '' },
    cancelUrl: { type: String, default: '/web/campuses' },
})

const isEdit = !!(props.campus && props.campus.crm_id)
const action = props.submitUrl || (isEdit ? `/web/campuses/${props.campus.crm_id}` : '/web/campuses')
const httpMethod = (props.method || (isEdit ? 'put' : 'post')).toLowerCase()
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const form = useForm({
    location_name: props.campus?.location_name ?? props.campus?.name ?? '',
    description: props.campus?.description ?? '',
    email: props.campus?.email ?? '',
    website: props.campus?.website ?? '',
    street1: props.campus?.street1 ?? '',
    street2: props.campus?.street2 ?? '',
    street3: props.campus?.street3 ?? '',
    city: props.campus?.city ?? '',
    province: props.campus?.province ?? '',
    postal_code: props.campus?.postal_code ?? '',
    country: props.campus?.country ?? '',
    primary_location: props.campus?.primary_location ?? false,
    status: props.campus?.status ?? 'Active',
})

function submit() {
    form.submit(httpMethod, action)
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Campus' : 'Add Campus'" />

    <div class="mb-4">
        <Link :href="cancelUrl" class="text-sm text-indigo-600 hover:underline">← Back</Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-800">{{ isEdit ? 'Edit Campus' : 'Add Campus' }}</h1>
    <p v-if="institution" class="mt-1 text-sm text-slate-500">for {{ institution.name }}</p>

    <form @submit.prevent="submit" class="mt-6 max-w-4xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Location Details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Location Name</span>
                    <input v-model="form.location_name" type="text" :class="inputClass" />
                    <span v-if="form.errors.location_name" class="mt-1 block text-xs text-red-600">{{ form.errors.location_name }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Email</span>
                    <input v-model="form.email" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Website</span>
                    <input v-model="form.website" type="text" :class="inputClass" />
                </label>
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Description</span>
                    <textarea v-model="form.description" rows="2" :class="inputClass"></textarea>
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
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Status</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="text-sm text-slate-600">Status</span>
                    <select v-model="form.status" :class="inputClass">
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 pt-6">
                    <input v-model="form.primary_location" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    <span class="text-sm text-slate-600">Primary location for this institution</span>
                </label>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ isEdit ? 'Save Changes' : 'Add Campus' }}</button>
            <Link :href="cancelUrl" class="rounded-md border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</Link>
        </div>
    </form>
</template>