<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'

const props = defineProps({
    reports: { type: Array, default: () => [] },
    report: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
})

const filterLabels = { from: 'From', to: 'To' }
const inputClass = 'mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none'

const form = reactive({ from: '', to: '' })
watch(() => props.filters, (f) => {
    form.from = f?.from ?? ''
    form.to = f?.to ?? ''
}, { immediate: true })

const columns = computed(() => Object.entries(props.report?.columns ?? {}))
const rows = computed(() => props.report?.rows ?? [])

function params(source) {
    const p = {}
    for (const name of props.report?.filters ?? []) {
        if (source[name]) p[name] = source[name]
    }
    return p
}

function run() {
    router.get(`/admin/reports/${props.report.key}`, params(form), { preserveScroll: true })
}

// Download what is on screen (the applied filters, not unsaved edits).
const csvUrl = computed(() => {
    const qs = new URLSearchParams(params(props.filters ?? {})).toString()
    return `/admin/reports/${props.report.key}/csv${qs ? `?${qs}` : ''}`
})

function link(key, row) {
    if (key === 'reference' && row.application_crm_id) return `/admin/applications/${row.application_crm_id}`
    if (key === 'name' && row.institution_crm_id) return `/admin/institutions/${row.institution_crm_id}`
    return null
}
</script>

<template>
    <Head :title="report ? report.title : 'Reports'" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">
                <Link v-if="report" href="/admin/reports" class="text-slate-400 hover:text-slate-600">Reports</Link>
                <span v-else>Reports</span>
                <template v-if="report"> <span class="text-slate-300" aria-hidden="true">/</span> {{ report.title }}</template>
            </h1>
            <p class="text-sm text-slate-500">{{ report ? report.description : 'Select a report to view it or download it as CSV.' }}</p>
        </div>
        <a v-if="report" :href="csvUrl" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Download CSV</a>
    </div>

    <div v-if="!report" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
            v-for="(r, n) in reports"
            :key="r.key"
            :href="`/admin/reports/${r.key}`"
            class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-300 hover:shadow"
        >
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Report {{ n + 1 }}</div>
            <div class="mt-1 font-semibold text-indigo-700">{{ r.title }}</div>
            <p class="mt-1 text-sm text-slate-500">{{ r.description }}</p>
        </Link>
    </div>

    <template v-else>
        <nav class="mt-4 flex flex-wrap gap-1 border-b border-slate-200" aria-label="Reports">
            <Link
                v-for="r in reports"
                :key="r.key"
                :href="`/admin/reports/${r.key}`"
                class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
                :class="r.key === report.key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                :aria-current="r.key === report.key ? 'page' : undefined"
            >{{ r.title }}</Link>
        </nav>

        <form v-if="report.filters.length" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="run">
            <label v-for="name in report.filters" :key="name" class="flex flex-col text-sm text-slate-600">
                {{ filterLabels[name] }}
                <input v-model="form[name]" type="date" :class="inputClass" />
                <span v-if="errors[name]" class="mt-1 text-xs text-red-600">{{ errors[name] }}</span>
            </label>
            <button type="submit" class="rounded-md bg-slate-800 px-4 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Run report</button>
            <p v-if="report.note" class="basis-full text-xs text-slate-500">{{ report.note }}</p>
        </form>

        <p class="mt-4 text-sm text-slate-500">{{ rows.length }} row{{ rows.length === 1 ? '' : 's' }}</p>
        <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th v-for="[key, label] in columns" :key="key" scope="col" class="whitespace-nowrap px-3 py-2.5">{{ label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(row, i) in rows" :key="i" class="hover:bg-slate-50">
                        <td v-for="[key] in columns" :key="key" class="px-3 py-2 text-slate-700">
                            <Link v-if="link(key, row)" :href="link(key, row)" class="text-indigo-600 hover:underline">{{ row[key] || '—' }}</Link>
                            <template v-else>{{ row[key] || '—' }}</template>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length" class="px-3 py-8 text-center text-sm text-slate-400">No results.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </template>
</template>
