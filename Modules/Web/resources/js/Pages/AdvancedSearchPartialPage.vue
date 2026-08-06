<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    campuses: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const form = ref({
    campusName: props.filters?.campusName ?? '',
    campusCode: props.filters?.campusCode ?? '',
    city: props.filters?.city ?? '',
    state: props.filters?.state ?? '',
    isActive: props.filters?.isActive ?? '',
})

const submitting = ref(false)

function submit() {
    submitting.value = true

    router.get(route('web.dba-campus.index'), { ...form.value }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false
        },
    })
}

function reset() {
    form.value = {
        campusName: '',
        campusCode: '',
        city: '',
        state: '',
        isActive: '',
    }
    submit()
}
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-gray-800">Advanced Search</h2>

        <form class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="submit">
            <div>
                <label for="campusName" class="mb-1 block text-sm font-medium text-gray-700">Campus Name</label>
                <input
                    id="campusName"
                    v-model="form.campusName"
                    type="text"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="Enter campus name"
                />
            </div>

            <div>
                <label for="campusCode" class="mb-1 block text-sm font-medium text-gray-700">Campus Code</label>
                <input
                    id="campusCode"
                    v-model="form.campusCode"
                    type="text"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="Enter campus code"
                />
            </div>

            <div>
                <label for="city" class="mb-1 block text-sm font-medium text-gray-700">City</label>
                <input
                    id="city"
                    v-model="form.city"
                    type="text"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="Enter city"
                />
            </div>

            <div>
                <label for="state" class="mb-1 block text-sm font-medium text-gray-700">State</label>
                <input
                    id="state"
                    v-model="form.state"
                    type="text"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="Enter state"
                />
            </div>

            <div>
                <label for="isActive" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select
                    id="isActive"
                    v-model="form.isActive"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    :disabled="submitting"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Search
                </button>
                <button
                    type="button"
                    :disabled="submitting"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="reset"
                >
                    Reset
                </button>
            </div>
        </form>
    </div>
</template>