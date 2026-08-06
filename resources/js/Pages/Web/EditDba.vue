<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    institution: { type: Object, default: null },
    dba: { type: Object, default: null },
    submitUrl: { type: String, default: '' },
    method: { type: String, default: '' },
    cancelUrl: { type: String, default: '/web/dbas' },
})

const isEdit = !!(props.dba && props.dba.crm_id)
const action = props.submitUrl || (isEdit ? `/web/dbas/${props.dba.crm_id}` : '/web/dbas')
const httpMethod = (props.method || (isEdit ? 'put' : 'post')).toLowerCase()
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const form = useForm({
    name: props.dba?.name ?? '',
    email: props.dba?.email ?? '',
    website: props.dba?.website ?? '',
    description: props.dba?.description ?? '',
    street1: props.dba?.street1 ?? '',
    street2: props.dba?.street2 ?? '',
    street3: props.dba?.street3 ?? '',
    city: props.dba?.city ?? '',
    province: props.dba?.province ?? '',
    postal_code: props.dba?.postal_code ?? '',
    country: props.dba?.country ?? '',
    status: props.dba?.status ?? 'Active',
})

function submit() {
    form.submit(httpMethod, action)
}
</script>

<template>
    <Head :title="isEdit ? 'Edit DBA' : 'Add DBA'" />

    <div class="mb-4">
        <Link :href="cancelUrl" class="text-sm text-indigo-600 hover:underline">← Back</Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-800">{{ isEdit ? 'Edit DBA' : 'Add DBA' }}</h1>
    <p v-if="institution" class="mt-1 text-sm text-slate-500">for {{ institution.name }}</p>

    <form @submit.prevent="submit" class="mt-6 max-w-4xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">DBA Details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">DBA Name</span>
                    <input v-model="form.name" type="text" :class="inputClass" />
                    <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Email</span>
                    <input v-model="form.email" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Website URL</span>
                    <input v-model="form.website" type="text" :class="inputClass" />
                </label>
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Description</span>
                    <textarea v-model="form.description" rows="2" :class="inputClass"></textarea>
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">DBA Address</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2"><span class="text-sm text-slate-600">Street 1</span>
                    <input v-model="form.street1" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Street 2</span>
                    <input v-model="form.street2" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Street 3</span>
                    <input v-model="form.street3" type="text" :class="inputClass" />
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
            <label class="block sm:max-w-xs"><span class="text-sm text-slate-600">Status</span>
                <select v-model="form.status" :class="inputClass">
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </label>
        </section>

        <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ isEdit ? 'Save Changes' : 'Add DBA' }}</button>
            <Link :href="cancelUrl" class="rounded-md border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</Link>
        </div>
    </form>
</template>