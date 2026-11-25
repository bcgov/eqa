<script setup>
// Read-only list of the campus locations entered on an application, styled like the institution's
// Campuses & Locations page. Application locations have no status.
defineProps({
    campuses: { type: Array, default: () => [] },
})

const address = (c) => [c.street1, c.street2, c.city, c.province, c.postal_code, c.country].filter(Boolean).join(', ')
const isPrimary = (c) => [true, 1, '1', 't'].includes(c.primary_location)
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Location Name</th>
                    <th class="px-3 py-2.5">Address</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">Primary</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="(c, i) in campuses" :key="i" class="align-top hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">
                        {{ c.location_name || '—' }}
                        <a v-if="c.website" :href="c.website" target="_blank" rel="noopener" class="mt-0.5 block text-xs font-normal text-indigo-600 hover:underline">{{ c.website }}</a>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ address(c) || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ c.email || '—' }}</td>
                    <td class="px-3 py-2">
                        <span v-if="isPrimary(c)" class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">Primary</span>
                        <span v-else class="text-xs text-slate-400">—</span>
                    </td>
                </tr>
                <tr v-if="campuses.length === 0"><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-400">No campus locations entered.</td></tr>
            </tbody>
        </table>
    </div>
</template>
