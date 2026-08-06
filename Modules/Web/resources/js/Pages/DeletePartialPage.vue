<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    campus: {
        type: Object,
        required: true,
    },
})

const form = useForm({})

const submit = () => {
    form.delete(route('dba-campus.destroy', { dbaCampus: props.campus.id }))
}
</script>

<template>
    <Head title="Delete Campus" />

    <div class="mx-auto max-w-lg p-6">
        <div class="rounded-lg border border-red-200 bg-white shadow-sm">
            <div class="border-b border-red-200 bg-red-50 px-6 py-4">
                <h1 class="text-lg font-semibold text-red-700">
                    Delete Campus
                </h1>
            </div>

            <div class="px-6 py-5">
                <p class="text-sm text-gray-700">
                    Are you sure you want to delete
                    <span class="font-semibold">{{ campus.name }}</span>?
                    This action cannot be undone.
                </p>

                <dl class="mt-4 grid grid-cols-1 gap-y-2 text-sm text-gray-600">
                    <div class="flex justify-between border-b border-gray-100 py-1">
                        <dt class="font-medium text-gray-500">Campus Id</dt>
                        <dd>{{ campus.id }}</dd>
                    </div>
                    <div
                        v-if="campus.code"
                        class="flex justify-between border-b border-gray-100 py-1"
                    >
                        <dt class="font-medium text-gray-500">Code</dt>
                        <dd>{{ campus.code }}</dd>
                    </div>
                </dl>
            </div>

            <form
                class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4"
                @submit.prevent="submit"
            >
                <Link
                    :href="route('dba-campus.index')"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                >
                    Cancel
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Delete
                </button>
            </form>
        </div>
    </div>
</template>