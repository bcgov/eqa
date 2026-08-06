<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    institution: { type: Object, default: null },
    dbas: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})

function address(d) {
    return [d.street1, d.street2, d.street3, d.city, d.province, d.postal_code].filter(Boolean).join(', ')
}

function toggle(d) {
    router.patch(`/web/dbas/${d.crm_id}/toggle`)
}

function remove(d) {
    if (confirm(`Remove ${d.name || 'this DBA'}? This cannot be undone.`)) {
        router.delete(`/web/dbas/${d.crm_id}`)
    }
}
</script>

<template>
    <Head title="DBAs" />

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">DBAs
            <span v-if="institution" class="text-base font-normal text-slate-500">for {{ institution.name }}</span>
        </h1>
        <div class="flex items-center gap-3">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">{{ dbas.length }} total</span>
            <Link href="/web/dbas/new" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add DBA</Link>
        </div>
    </div>

    <p class="mt-1 text-sm text-slate-500">Registered &ldquo;Doing Business As&rdquo; names for this institution.</p>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">DBA Name</th>
                    <th class="px-3 py-2.5">Address</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="d in dbas" :key="d.crm_id" class="align-top hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">
                        {{ d.name || '—' }}
                        <a v-if="d.website" :href="d.website" target="_blank" rel="noopener" class="mt-0.5 block text-xs font-normal text-indigo-600 hover:underline">{{ d.website }}</a>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ address(d) || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ d.email || '—' }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="d.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">{{ d.status || '—' }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2 whitespace-nowrap text-xs">
                            <Link :href="`/web/dbas/${d.crm_id}/edit`" class="text-indigo-600 hover:underline">Edit</Link>
                            <button type="button" @click="toggle(d)" class="text-amber-600 hover:underline">{{ d.status === 'Active' ? 'Deactivate' : 'Reactivate' }}</button>
                            <button type="button" @click="remove(d)" class="text-red-600 hover:underline">Remove</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="dbas.length === 0"><td colspan="5" class="px-3 py-8 text-center text-sm text-slate-400">No DBAs recorded.</td></tr>
            </tbody>
        </table>
    </div>
</template>