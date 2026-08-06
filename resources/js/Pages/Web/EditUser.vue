<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    institution: { type: Object, default: null },
    user: { type: Object, default: null },
    submitUrl: { type: String, default: '' },
    method: { type: String, default: '' },
    cancelUrl: { type: String, default: '/web/users' },
})

const isEdit = !!(props.user && props.user.crm_id)
const action = props.submitUrl || (isEdit ? `/web/users/${props.user.crm_id}` : '/web/users')
const httpMethod = (props.method || (isEdit ? 'put' : 'post')).toLowerCase()
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const form = useForm({
    first_name: props.user?.first_name ?? '',
    last_name: props.user?.last_name ?? '',
    job_title: props.user?.job_title ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    mobile: props.user?.mobile ?? '',
    role: props.user?.role ?? 'Read Only',
    web_user_name: props.user?.web_user_name ?? '',
    web_user_active: props.user?.web_user_active ?? true,
})

function submit() {
    form.submit(httpMethod, action)
}
</script>

<template>
    <Head :title="isEdit ? 'Edit User' : 'Add User'" />

    <div class="mb-4">
        <Link :href="cancelUrl" class="text-sm text-indigo-600 hover:underline">← Back</Link>
    </div>

    <h1 class="text-2xl font-bold text-slate-800">{{ isEdit ? 'Edit User' : 'Add User' }}</h1>
    <p v-if="institution" class="mt-1 text-sm text-slate-500">for {{ institution.name }}</p>

    <form @submit.prevent="submit" class="mt-6 max-w-3xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Contact Details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="text-sm text-slate-600">First Name</span>
                    <input v-model="form.first_name" type="text" :class="inputClass" />
                    <span v-if="form.errors.first_name" class="mt-1 block text-xs text-red-600">{{ form.errors.first_name }}</span>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Last Name</span>
                    <input v-model="form.last_name" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Job Title</span>
                    <input v-model="form.job_title" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Role</span>
                    <select v-model="form.role" :class="inputClass">
                        <option>Primary</option>
                        <option>Read Only</option>
                    </select>
                </label>
                <label class="block"><span class="text-sm text-slate-600">Email</span>
                    <input v-model="form.email" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Phone</span>
                    <input v-model="form.phone" type="text" :class="inputClass" />
                </label>
                <label class="block"><span class="text-sm text-slate-600">Mobile</span>
                    <input v-model="form.mobile" type="text" :class="inputClass" />
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Portal Access</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="text-sm text-slate-600">Web User Name</span>
                    <input v-model="form.web_user_name" type="text" :class="inputClass" />
                </label>
                <label class="flex items-center gap-2 pt-6">
                    <input v-model="form.web_user_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    <span class="text-sm text-slate-600">Active portal user</span>
                </label>
            </div>
        </section>

        <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ isEdit ? 'Save Changes' : 'Add User' }}</button>
            <Link :href="cancelUrl" class="rounded-md border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</Link>
        </div>
    </form>
</template>