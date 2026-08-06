<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: undefined })

const props = defineProps({
    institutionSeal: {
        type: Object,
        default: () => ({}),
    },
})
</script>

<template>
    <Head title="Verify Seal - Verify Logo" />

    <div class="min-h-screen bg-gray-50 py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h1 class="text-xl font-semibold text-gray-900">
                        Verify Seal - Verify Logo
                    </h1>
                </div>

                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">
                                Institution
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ institutionSeal?.institutionName ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">
                                Seal Status
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ institutionSeal?.sealStatus ?? '—' }}
                            </dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">
                                Logo
                            </dt>
                            <dd class="mt-2">
                                <img
                                    v-if="institutionSeal?.logoUrl"
                                    :src="institutionSeal.logoUrl"
                                    alt="Institution Logo"
                                    class="max-h-48 rounded border border-gray-200 object-contain"
                                />
                                <p v-else class="text-sm text-gray-400">
                                    No logo available.
                                </p>
                            </dd>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
                    <Link
                        href="/apply"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Back
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>