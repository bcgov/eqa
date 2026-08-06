<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: undefined })

const props = defineProps({
    applications: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <Head title="Applications" />

    <div class="p-4">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-lg font-semibold text-gray-900">Applications</h2>
            </div>

            <div v-if="props.applications.length === 0" class="p-6 text-center text-sm text-gray-500">
                No applications found.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                v-for="header in ['Name', 'Description', 'Status', 'Actions']"
                                :key="header"
                                scope="col"
                                class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{ header }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="application in props.applications"
                            :key="application.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-4 py-2 text-sm font-medium text-gray-900">
                                {{ application.name }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                {{ application.description }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                                {{ application.status }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-sm">
                                <Link
                                    :href="route('web.applications.show', application.id)"
                                    class="text-indigo-600 hover:text-indigo-900"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>