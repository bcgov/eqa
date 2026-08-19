<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    institutions: { type: Array, default: () => [] },
})

const columns = [
    { key: 'institution_number', label: 'Institution ID' },
    { key: 'name', label: 'Institution Name' },
    { key: 'qa_met_through', label: 'QA Met Through' },
    { key: 'primary_contact', label: 'Primary Contact' },
    { key: 'eqa_status', label: 'EQA Status' },
    { key: 'eqa_standing', label: 'EQA Standing' },
    { key: 'ptib_standing', label: 'PTIRU Standing' },
    { key: 'designation_start', label: 'Designation Start' },
    { key: 'city', label: 'City' },
    { key: 'province', label: 'Province' },
    { key: 'website', label: 'Website' },
    { key: 'dba_count', label: 'DBAs', align: 'center' },
]

const search = ref('')
const eqaStatusFilter = ref('')
const sortKey = ref('name')
const sortDir = ref('asc')

const eqaStatuses = computed(() =>
    [...new Set(props.institutions.map((i) => i.eqa_status).filter(Boolean))].sort(),
)

function sortBy(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortDir.value = 'asc'
    }
}

function compare(a, b, key) {
    const av = a[key] ?? ''
    const bv = b[key] ?? ''
    if (typeof av === 'number' && typeof bv === 'number') return av - bv
    return String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' })
}

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    let rows = props.institutions.filter((i) => {
        const matchesSearch = !q || [i.name, i.institution_number, i.city].some((v) => (v || '').toLowerCase().includes(q))
        const matchesStatus = !eqaStatusFilter.value || i.eqa_status === eqaStatusFilter.value
        return matchesSearch && matchesStatus
    })
    rows = [...rows].sort((a, b) => compare(a, b, sortKey.value) * (sortDir.value === 'asc' ? 1 : -1))
    return rows
})

function standingClass(s) {
    if (s === 'In Good Standing') return 'bg-green-100 text-green-700'
    if (s === 'Under Review') return 'bg-amber-100 text-amber-700'
    if (s) return 'bg-red-100 text-red-700'
    return 'bg-slate-100 text-slate-400'
}
</script>

<template>
    <Head title="Institutions" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Institutions</h1>
            <p class="text-sm text-slate-500">{{ filtered.length }} of {{ institutions.length }} institutions · migrated from Dynamics</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-500">EQA Status
                <select v-model="eqaStatusFilter" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">All</option>
                    <option v-for="s in eqaStatuses" :key="s" :value="s">{{ s }}</option>
                </select>
            </label>
            <input
                v-model="search"
                type="search"
                placeholder="Search name, ID or city…"
                class="w-64 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none"
            />
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th v-for="col in columns" :key="col.key"
                        class="cursor-pointer select-none px-3 py-2.5 hover:text-slate-700"
                        :class="col.align === 'center' ? 'text-center' : ''"
                        @click="sortBy(col.key)">
                        <span class="inline-flex items-center gap-1">
                            {{ col.label }}
                            <span class="text-slate-400">{{ sortKey === col.key ? (sortDir === 'asc' ? '▲' : '▼') : '' }}</span>
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="i in filtered" :key="i.crm_id" class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-500">{{ i.institution_number }}</td>
                    <td class="px-3 py-2 font-medium">
                        <Link :href="`/admin/institutions/${i.crm_id}`" class="text-indigo-600 hover:underline">{{ i.name }}</Link>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ i.qa_met_through || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ i.primary_contact || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ i.eqa_status || '—' }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="standingClass(i.eqa_standing)">{{ i.eqa_standing || '—' }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="standingClass(i.ptib_standing)">{{ i.ptib_standing || '—' }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ i.designation_start || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ i.city || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ i.province || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">
                        <a v-if="i.website" :href="i.website" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Link</a>
                        <span v-else>—</span>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <Link :href="`/admin/institutions/${i.crm_id}`" class="font-medium text-indigo-600 hover:underline">{{ i.dba_count ?? 0 }}</Link>
                    </td>
                </tr>
                <tr v-if="filtered.length === 0">
                    <td colspan="12" class="px-3 py-8 text-center text-sm text-slate-400">
                        No institutions — run <code>php artisan migration:migrate-data</code> to load them.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>