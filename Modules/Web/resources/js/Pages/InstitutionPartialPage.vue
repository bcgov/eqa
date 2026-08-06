<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    institution: {
        type: Object,
        default: () => ({}),
    },
})
</script>

<template>
    <Head title="Institution" />

    <div class="mx-auto max-w-4xl px-4 py-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold text-gray-900">
                {{ institution?.name ?? 'Institution' }}
            </h1>

            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-if="institution?.code">
                    <dt class="text-sm font-medium text-gray-500">Code</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ institution.code }}</dd>
                </div>
                <div v-if="institution?.address">
                    <dt class="text-sm font-medium text-gray-500">Address</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ institution.address }}</dd>
                </div>
                <div v-if="institution?.city">
                    <dt class="text-sm font-medium text-gray-500">City</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ institution.city }}</dd>
                </div>
                <div v-if="institution?.state">
                    <dt class="text-sm font-medium text-gray-500">State</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ institution.state }}</dd>
                </div>
                <div v-if="institution?.zip">
                    <dt class="text-sm font-medium text-gray-500">Zip</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ institution.zip }}</dd>
                </div>
                <div v-if="institution?.phone">
                    <dt class="text-sm font-medium text-gray-500">Phone</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ institution.phone }}</dd>
                </div>
            </dl>

            <div v-if="!institution || Object.keys(institution).length === 0" class="mt-4 text-sm text-gray-500">
                No institution information available.
            </div>
        </div>
    </div>
</template>