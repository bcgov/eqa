<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    institution: { type: Object, default: null },
    campuses: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})

function address(c) {
    return [c.street1, c.street2, c.street3, c.city, c.province, c.postal_code].filter(Boolean).join(', ')
}

function toggle(c) {
    router.patch(`/web/campuses/${c.crm_id}/toggle`)
}

function remove(c) {
    if (confirm(`Remove ${c.location_name || c.name || 'this campus'}? This cannot be undone.`)) {
        router.delete(`/web/campuses/${c.crm_id}`)
    }
}
</script>

<template>
    <Head title="Campuses" />

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Campuses &amp; Locations
            <span v-if="institution" class="text-base font-normal text-slate-500">for {{ institution.name }}</span>
        </h1>
        <div class="flex items-center gap-3">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">{{ campuses.length }} total</span>
            <Link href="/web/campuses/new" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add Campus</Link>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Location Name</th>
                    <th class="px-3 py-2.5">Address</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">Primary</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="c in campuses" :key="c.crm_id" class="align-top hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">
                        {{ c.location_name || c.name || '—' }}
                        <a v-if="c.website" :href="c.website" target="_blank" rel="noopener" class="mt-0.5 block text-xs font-normal text-indigo-600 hover:underline">{{ c.website }}</a>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ address(c) || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ c.email || '—' }}</td>
                    <td class="px-3 py-2">
                        <span v-if="c.primary_location" class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">Primary</span>
                        <span v-else class="text-xs text-slate-400">—</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium"
                            :class="c.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                            {{ c.status || '—' }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2 whitespace-nowrap text-xs">
                            <Link :href="`/web/campuses/${c.crm_id}/edit`" class="text-indigo-600 hover:underline">Edit</Link>
                            <button type="button" @click="toggle(c)" class="text-amber-600 hover:underline">{{ c.status === 'Active' ? 'Deactivate' : 'Reactivate' }}</button>
                            <button type="button" @click="remove(c)" class="text-red-600 hover:underline">Remove</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="campuses.length === 0"><td colspan="6" class="px-3 py-8 text-center text-sm text-slate-400">No campuses recorded.</td></tr>
            </tbody>
        </table>
    </div>
</template>