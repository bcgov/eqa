<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    application: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
})

const form = useForm({
    id: props.application?.id ?? null,
    applicant_name: props.application?.applicant_name ?? '',
    company_name: props.application?.company_name ?? '',
    email: props.application?.email ?? '',
    phone: props.application?.phone ?? '',
    address: props.application?.address ?? '',
    city: props.application?.city ?? '',
    state: props.application?.state ?? '',
    postal_code: props.application?.postal_code ?? '',
    country: props.application?.country ?? '',
    scope_of_certification: props.application?.scope_of_certification ?? '',
    status: props.application?.status ?? '',
    notes: props.application?.notes ?? '',
})

const pageTitle = computed(() => 'Edit Application')

function submit() {
    form.put(route('web.application.update', { application: form.id }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-900">{{ pageTitle }}</h1>
            <Link
                :href="route('web.application.index')"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
            >
                Back to Applications
            </Link>
        </div>

        <form class="space-y-6 rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label for="applicant_name" class="block text-sm font-medium text-gray-700">
                        Applicant Name
                    </label>
                    <input
                        id="applicant_name"
                        v-model="form.applicant_name"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.applicant_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.applicant_name }}
                    </p>
                </div>

                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-700">
                        Company Name
                    </label>
                    <input
                        id="company_name"
                        v-model="form.company_name"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.company_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.company_name }}
                    </p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">
                        Phone
                    </label>
                    <input
                        id="phone"
                        v-model="form.phone"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                        {{ form.errors.phone }}
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700">
                        Address
                    </label>
                    <input
                        id="address"
                        v-model="form.address"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">
                        {{ form.errors.address }}
                    </p>
                </div>

                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">
                        City
                    </label>
                    <input
                        id="city"
                        v-model="form.city"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.city" class="mt-1 text-sm text-red-600">
                        {{ form.errors.city }}
                    </p>
                </div>

                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700">
                        State / Province
                    </label>
                    <input
                        id="state"
                        v-model="form.state"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.state" class="mt-1 text-sm text-red-600">
                        {{ form.errors.state }}
                    </p>
                </div>

                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700">
                        Postal Code
                    </label>
                    <input
                        id="postal_code"
                        v-model="form.postal_code"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">
                        {{ form.errors.postal_code }}
                    </p>
                </div>

                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700">
                        Country
                    </label>
                    <input
                        id="country"
                        v-model="form.country"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <p v-if="form.errors.country" class="mt-1 text-sm text-red-600">
                        {{ form.errors.country }}
                    </p>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">
                        Status
                    </label>
                    <select
                        id="status"
                        v-model="form.status"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                        {{ form.errors.status }}
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label for="scope_of_certification" class="block text-sm font-medium text-gray-700">
                        Scope of Certification
                    </label>
                    <textarea
                        id="scope_of_certification"
                        v-model="form.scope_of_certification"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    ></textarea>
                    <p v-if="form.errors.scope_of_certification" class="mt-1 text-sm text-red-600">
                        {{ form.errors.scope_of_certification }}
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700">
                        Notes
                    </label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    ></textarea>
                    <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                        {{ form.errors.notes }}
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link
                    :href="route('web.application.index')"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</template>