<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import SortHeader from '../../Components/SortHeader.vue'

const props = defineProps({
    applications: { type: Object, default: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }) },
    filters: { type: Object, default: () => ({ search: '', sort: 'application_date', direction: 'desc' }) },
})

const rows = computed(() => props.applications.data || [])
const search = ref(props.filters.search || '')

let debounce = null
function reload(params) {
    router.get('/admin/applications', {
        search: search.value || undefined,
        sort: props.filters.sort,
        direction: props.filters.direction,
        ...params,
    }, { preserveState: true, preserveScroll: true, replace: true })
}
watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => reload({}), 300)
})
function sortBy(column) {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc'
    reload({ sort: column, direction })
}

function statusClass(s) {
    if (s === 'Approved') return 'bg-green-100 text-green-700'
    if (s === 'Not Approved') return 'bg-red-100 text-red-700'
    if (s === 'Under Review' || s === 'Suitability Review') return 'bg-amber-100 text-amber-700'
    if (s === 'Pending Review') return 'bg-blue-100 text-blue-700'
    return 'bg-slate-100 text-slate-500'
}
function money(v) {
    return v != null ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'
}
</script>

<template>
    <Head title="Applications" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Applications</h1>
            <p class="text-sm text-slate-500">
                <template v-if="applications.total">Showing {{ applications.from }}–{{ applications.to }} of {{ applications.total }} applications</template>
                <template v-else>No matching applications</template>
                ·
            </p>
        </div>
        <input
            v-model="search"
            type="search"
            placeholder="Search reference or institution…"
            class="w-72 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none"
        />
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5"><SortHeader label="Reference" column="reference" :sort="filters.sort" :direction="filters.direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Institution" column="institution" :sort="filters.sort" :direction="filters.direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Status" column="status" :sort="filters.sort" :direction="filters.direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Application Date" column="application_date" :sort="filters.sort" :direction="filters.direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5"><SortHeader label="Approved Date" column="approved_date" :sort="filters.sort" :direction="filters.direction" @sort="sortBy" /></th>
                    <th class="px-3 py-2.5 text-right">Total Due</th>
                    <th class="px-3 py-2.5">Invoice #</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="a in rows" :key="a.crm_id" class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">
                        <Link :href="`/admin/applications/${a.crm_id}`" class="text-indigo-600 hover:underline">{{ a.reference }}</Link>
                    </td>
                    <td class="px-3 py-2">
                        <Link v-if="a.institution_crm_id" :href="`/admin/institutions/${a.institution_crm_id}`" class="text-indigo-600 hover:underline">{{ a.institution_name || '—' }}</Link>
                        <span v-else class="text-slate-800">{{ a.institution_name || '—' }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusClass(a.status)">{{ a.status || '—' }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ a.application_date || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ a.approved_date || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-slate-600">{{ money(a.total_due) }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">
                        <Link v-if="a.invoice_crm_id" :href="`/admin/invoices/${a.invoice_crm_id}`" class="text-indigo-600 hover:underline">{{ a.invoice_number || '—' }}</Link>
                        <span v-else class="text-slate-500">{{ a.invoice_number || '—' }}</span>
                    </td>
                </tr>
                <tr v-if="rows.length === 0">
                    <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-400">No applications.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-if="applications.links && applications.links.length > 3" class="mt-4 flex flex-wrap items-center justify-center gap-1">
        <template v-for="(link, i) in applications.links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                preserve-state
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'"
                v-html="link.label"
            />
            <span
                v-else
                class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-300"
                v-html="link.label"
            />
        </template>
    </div>
</template>