<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
})

const form = useForm({
    applicant_name: '',
    email: '',
    phone: '',
    company_name: '',
    description: '',
})

const submit = () => {
    form.post(route('applications.store'))
}
</script>

<template>
    <Head title="Create Application" />

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Create Application</h1>
            <p class="mt-1 text-sm text-gray-500">Fill out the form below to submit a new application.</p>
        </div>

        <form class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <label for="applicant_name" class="block text-sm font-medium text-gray-700">Applicant Name</label>
                <input
                    id="applicant_name"
                    v-model="form.applicant_name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <p v-if="form.errors.applicant_name" class="mt-1 text-sm text-red-600">{{ form.errors.applicant_name }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                <input
                    id="phone"
                    v-model="form.phone"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
            </div>

            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                <input
                    id="company_name"
                    v-model="form.company_name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <p v-if="form.errors.company_name" class="mt-1 text-sm text-red-600">{{ form.errors.company_name }}</p>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                ></textarea>
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                <Link
                    :href="route('applications.index')"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                >
                    Submit Application
                </button>
            </div>
        </form>
    </div>
</template>