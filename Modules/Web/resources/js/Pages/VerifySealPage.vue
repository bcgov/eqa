<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    apply: {
        type: Object,
        default: () => ({}),
    },
})

const form = useForm({
    sealNumber: props.apply?.sealNumber ?? '',
})

const submit = () => {
    form.post(route('web.apply.verify-seal.store'))
}
</script>

<template>
    <Head title="Verify Seal" />

    <div class="min-h-screen bg-gray-50 py-10">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h1 class="text-lg font-semibold text-gray-900">Verify Seal</h1>
                </div>

                <form class="space-y-6 px-6 py-6" @submit.prevent="submit">
                    <div>
                        <label for="sealNumber" class="block text-sm font-medium text-gray-700">
                            Seal Number
                        </label>
                        <input
                            id="sealNumber"
                            v-model="form.sealNumber"
                            type="text"
                            name="sealNumber"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            :class="{ 'border-red-500': form.errors.sealNumber }"
                        />
                        <p v-if="form.errors.sealNumber" class="mt-1 text-sm text-red-600">
                            {{ form.errors.sealNumber }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Link
                            href="route('web.apply.index')"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            Verify
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>