<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    dbas: { type: Array, default: () => [] },
})

const search = ref('')
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.dbas
    return props.dbas.filter((d) => [d.name, d.institution_name, d.city].some((v) => (v || '').toLowerCase().includes(q)))
})
</script>

<template>
    <Head title="DBAs" />
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">DBAs</h1>
            <p class="text-sm text-slate-500">{{ filtered.length }} of {{ dbas.length }} &ldquo;Doing Business As&rdquo; names · migrated from Dynamics</p>
        </div>
        <input v-model="search" type="search" placeholder="Search DBA, institution or city…" class="w-72 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none" />
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">DBA Name</th>
                    <th class="px-3 py-2.5">Institution Name</th>
                    <th class="px-3 py-2.5">Street 1</th>
                    <th class="px-3 py-2.5">City</th>
                    <th class="px-3 py-2.5">Province</th>
                    <th class="px-3 py-2.5">Postal Code</th>
                    <th class="px-3 py-2.5">Website</th>
                    <th class="px-3 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="d in filtered" :key="d.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">{{ d.name || '—' }}</td>
                    <td class="px-3 py-2">
                        <Link v-if="d.institution_crm_id" :href="`/admin/institutions/${d.institution_crm_id}`" class="text-indigo-600 hover:underline">{{ d.institution_name || '—' }}</Link>
                        <span v-else class="text-slate-700">{{ d.institution_name || '—' }}</span>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ d.street1 || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ d.city || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ d.province || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ d.postal_code || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">
                        <a v-if="d.website" :href="d.website" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Link</a>
                        <span v-else>—</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="d.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">{{ d.status || '—' }}</span>
                    </td>
                </tr>
                <tr v-if="filtered.length === 0"><td colspan="8" class="px-3 py-8 text-center text-sm text-slate-400">No DBAs found.</td></tr>
            </tbody>
        </table>
    </div>
</template>