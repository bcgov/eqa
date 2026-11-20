<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import BcFooter from '../../Components/BCDesign/BcFooter.vue'

defineOptions({ layout: null })

const props = defineProps({
    filters: { type: Object, required: true },
    cities: { type: Array, default: () => [] },
    institutions: { type: Object, default: null },
    institution: { type: Object, default: null },
})

const BASE = '/institution-directory'
const PER_PAGE = [10, 25, 50, 100]

const search = reactive({ q: props.filters.q, city: props.filters.city })
watch(() => props.filters, (f) => { search.q = f.q; search.city = f.city })

function query(overrides = {}) {
    const params = { status: props.filters.status, q: search.q, city: search.city, per_page: props.filters.per_page, ...overrides }
    return Object.fromEntries(Object.entries(params).filter(([k, v]) =>
        v !== '' && v !== null && v !== undefined && !(k === 'status' && v === 'designated') && !(k === 'per_page' && v === 10)))
}

function go(overrides = {}) {
    router.get(BASE, query(overrides), { preserveState: true, preserveScroll: true, replace: true })
}

let timer = null
function onKeywords() {
    clearTimeout(timer)
    timer = setTimeout(() => go(), 350)
}
function reset() {
    clearTimeout(timer)
    search.q = ''
    search.city = ''
    router.get(BASE, query({ q: '', city: '' }))
}

const resultsUrl = computed(() => {
    const qs = new URLSearchParams(query()).toString()
    return qs ? `${BASE}?${qs}` : BASE
})
const detailUrl = (i) => {
    const qs = new URLSearchParams(query()).toString()
    return `${BASE}/${i.crm_id}${qs ? `?${qs}` : ''}`
}

const isPrevious = computed(() => props.filters.status === 'previous')
const infoOpen = ref(false)

// Detail view
const tab = ref('locations')
const openLocations = ref(new Set([0]))
watch(() => props.institution?.crm_id, () => { tab.value = 'locations'; openLocations.value = new Set([0]) })
const toggleLocation = (index) => {
    const next = new Set(openLocations.value)
    next.has(index) ? next.delete(index) : next.add(index)
    openLocations.value = next
}
const expandAll = () => { openLocations.value = new Set(props.institution.locations.map((_, i) => i)) }
const collapseAll = () => { openLocations.value = new Set() }

const tabs = computed(() => {
    if (!props.institution) return []
    const list = [
        { key: 'locations', label: `${props.institution.locations.length} Location${props.institution.locations.length === 1 ? '' : 's'}` },
        { key: 'contact', label: 'Contact Details' },
    ]
    if (props.institution.dbas.length) list.push({ key: 'dbas', label: 'Doing Business As' })
    return list
})

const websiteHref = (url) => (/^https?:\/\//i.test(url) ? url : `http://${url}`)

const pageRange = computed(() => {
    const p = props.institutions
    if (!p || p.total === 0) return ''
    return `Showing ${p.from}–${p.to} of ${p.total}`
})
</script>

<template>
    <Head title="EQA Institution Directory" />
    <div class="flex min-h-screen flex-col bg-white">
        <header class="border-b-4" :style="{ backgroundColor: '#013366', borderColor: '#fcba19' }">
            <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-4">
                <span class="text-lg font-bold text-white">British Columbia</span>
                <span class="text-sm text-slate-200">Education Quality Assurance</span>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section aria-labelledby="directory-title">
                    <h1 id="directory-title" class="text-3xl font-semibold text-slate-700">EQA Institution Directory</h1>
                    <h2 class="mt-4 text-xl font-semibold text-slate-700">
                        {{ isPrevious ? 'Previously Designated Institutions' : 'Designated Institutions' }}
                    </h2>
                    <p class="mt-1 text-slate-700">
                        <template v-if="isPrevious">
                            These institutions no longer hold the Education Quality Assurance (EQA) designation and may not use the EQA brand.
                        </template>
                        <template v-else>
                            These post-secondary institutions hold the Education Quality Assurance (EQA) designation. Select an institution to see its locations and contact details.
                        </template>
                    </p>

                    <div class="mt-4 bg-slate-100">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-3 py-4 text-left text-slate-700"
                            :aria-expanded="infoOpen"
                            aria-controls="eqa-info"
                            @click="infoOpen = !infoOpen"
                        >
                            <span>For more information about EQA designation click arrow to expand this information box.</span>
                            <svg class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': infoOpen }" :style="{ color: '#38598a' }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </button>
                        <div v-show="infoOpen" id="eqa-info" class="space-y-2 px-3 pb-4 text-sm text-slate-700">
                            <p>
                                The EQA designation is the Government of British Columbia's brand of quality for post-secondary education.
                                Designated institutions have met or exceeded provincial quality assurance standards.
                            </p>
                            <p>
                                <a class="text-[#255a90] underline hover:no-underline" href="https://www2.gov.bc.ca/gov/content/education-training/post-secondary-education/institution-resources-administration/education-quality-assurance">Learn more about Education Quality Assurance</a>
                                ·
                                <a class="text-[#255a90] underline hover:no-underline" href="/admin/report/institutions-eqa-registry">Download the registry (PDF)</a>
                            </p>
                        </div>
                    </div>

                    <!-- Results list -->
                    <template v-if="institutions">
                        <div class="mt-4 flex flex-wrap items-end justify-between gap-2">
                            <label class="text-slate-700">Results per page:
                                <select
                                    class="ml-1 rounded border border-slate-400 px-1 py-0.5"
                                    :value="filters.per_page"
                                    @change="go({ per_page: Number($event.target.value), page: undefined })"
                                >
                                    <option v-for="n in PER_PAGE" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </label>
                            <nav aria-label="Designation status" class="text-[#255a90]">
                                <Link :href="BASE" :data="query({ status: 'designated' })" :class="isPrevious ? 'hover:underline' : 'font-semibold underline'" :aria-current="isPrevious ? undefined : 'page'" preserve-scroll>Designated</Link>
                                <span class="mx-1 text-slate-500">|</span>
                                <Link :href="BASE" :data="query({ status: 'previous' })" :class="isPrevious ? 'font-semibold underline' : 'hover:underline'" :aria-current="isPrevious ? 'page' : undefined" preserve-scroll>Previously Designated</Link>
                            </nav>
                        </div>

                        <div class="mt-1 overflow-x-auto">
                            <table class="w-full border border-slate-200 text-left">
                                <caption class="sr-only">{{ isPrevious ? 'Previously designated institutions' : 'Designated institutions' }}</caption>
                                <thead class="text-sm text-white" :style="{ backgroundColor: '#38598a' }">
                                    <tr>
                                        <th scope="col" class="w-32 px-2 py-2 font-semibold">Institution ID</th>
                                        <th scope="col" class="px-2 py-2 font-semibold">Institution</th>
                                        <th scope="col" class="w-36 px-2 py-2 font-semibold">City</th>
                                        <th scope="col" class="w-36 px-2 py-2 font-semibold">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="align-top text-slate-700">
                                    <tr v-for="i in institutions.data" :key="i.crm_id" class="border-t border-slate-200 hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-2 py-3">{{ i.institution_number || '—' }}</td>
                                        <td class="px-2 py-3">
                                            <Link :href="detailUrl(i)" class="text-[#255a90] hover:underline">{{ i.name }}</Link>
                                            <div v-if="i.street">{{ i.street }}</div>
                                        </td>
                                        <td class="px-2 py-3">{{ i.city || '—' }}</td>
                                        <td class="px-2 py-3">{{ i.status }}</td>
                                    </tr>
                                    <tr v-if="institutions.data.length === 0">
                                        <td colspan="4" class="px-2 py-8 text-center text-slate-500">No institutions match your search.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="institutions.total > 0" class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-700">
                            <span aria-live="polite">{{ pageRange }}</span>
                            <nav v-if="institutions.last_page > 1" aria-label="Pagination" class="flex flex-wrap gap-1">
                                <template v-for="(link, idx) in institutions.links" :key="idx">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        preserve-scroll
                                        class="min-w-8 rounded border px-2 py-1 text-center"
                                        :class="link.active ? 'border-[#38598a] bg-[#38598a] text-white' : 'border-slate-300 text-[#255a90] hover:bg-slate-100'"
                                        :aria-current="link.active ? 'page' : undefined"
                                    ><span v-html="link.label" /></Link>
                                    <span v-else class="min-w-8 rounded border border-slate-200 px-2 py-1 text-center text-slate-400" v-html="link.label" />
                                </template>
                            </nav>
                        </div>
                    </template>

                    <!-- Institution detail -->
                    <template v-else-if="institution">
                        <Link :href="resultsUrl" class="mt-4 inline-block text-[#255a90] hover:underline">Back to results</Link>
                        <div class="mt-1 flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-white" :style="{ backgroundColor: '#38598a' }">
                            <h2 class="text-2xl">{{ institution.name }}</h2>
                            <span class="text-2xl">{{ institution.institution_number }}</span>
                        </div>

                        <div class="border border-t-0 border-slate-200">
                            <div role="tablist" aria-label="Institution details" class="flex flex-wrap gap-1 bg-slate-100 px-1 pt-1">
                                <button
                                    v-for="t in tabs"
                                    :id="`tab-${t.key}`"
                                    :key="t.key"
                                    type="button"
                                    role="tab"
                                    :aria-selected="tab === t.key"
                                    :aria-controls="`panel-${t.key}`"
                                    class="px-4 py-2 text-sm"
                                    :class="tab === t.key ? 'bg-white text-slate-800' : 'bg-slate-200 text-slate-600 hover:bg-slate-300'"
                                    @click="tab = t.key"
                                >{{ t.label }}</button>
                            </div>

                            <div v-if="tab === 'locations'" id="panel-locations" role="tabpanel" aria-labelledby="tab-locations" class="p-4">
                                <p v-if="institution.locations.length === 0" class="text-slate-600">No active locations are listed for this institution.</p>
                                <template v-else>
                                    <div class="mb-3 inline-flex bg-slate-100 px-3 py-2 text-sm text-slate-700">
                                        <button type="button" class="hover:underline" @click="expandAll">Expand All</button>
                                        <span class="mx-1">|</span>
                                        <button type="button" class="hover:underline" @click="collapseAll">Collapse All</button>
                                    </div>
                                    <div v-for="(loc, index) in institution.locations" :key="index" class="mb-2 bg-slate-100">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-4 px-3 py-3 text-left"
                                            :aria-expanded="openLocations.has(index)"
                                            :aria-controls="`location-${index}`"
                                            @click="toggleLocation(index)"
                                        >
                                            <span>
                                                <span class="block text-sm font-bold text-slate-800">{{ loc.name }}</span>
                                                <span v-if="loc.city" class="mt-1 block text-xs text-slate-700">Location: {{ loc.city }}</span>
                                            </span>
                                            <svg class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': openLocations.has(index) }" :style="{ color: '#38598a' }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                        </button>
                                        <div v-show="openLocations.has(index)" :id="`location-${index}`" class="bg-white p-3">
                                            <table class="w-full border border-slate-400 text-sm text-slate-700">
                                                <tbody>
                                                    <tr class="border-b border-slate-400">
                                                        <th scope="row" class="w-40 border-r border-slate-400 p-2 text-left align-top font-semibold">Address</th>
                                                        <td class="p-2"><div v-for="line in loc.address" :key="line">{{ line }}</div><span v-if="!loc.address.length">—</span></td>
                                                    </tr>
                                                    <tr class="border-b border-slate-400">
                                                        <th scope="row" class="border-r border-slate-400 p-2 text-left align-top font-semibold">Email</th>
                                                        <td class="p-2"><a v-if="loc.email" :href="`mailto:${loc.email}`" class="text-[#255a90] hover:underline">{{ loc.email }}</a><span v-else>—</span></td>
                                                    </tr>
                                                    <tr>
                                                        <th scope="row" class="border-r border-slate-400 p-2 text-left align-top font-semibold">Website</th>
                                                        <td class="break-all p-2"><a v-if="loc.website" :href="websiteHref(loc.website)" class="text-[#255a90] hover:underline">{{ loc.website }}</a><span v-else>—</span></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div v-else-if="tab === 'contact'" id="panel-contact" role="tabpanel" aria-labelledby="tab-contact" class="p-4">
                                <table class="w-full border border-slate-400 text-sm text-slate-700">
                                    <tbody>
                                        <tr class="border-b border-slate-400">
                                            <th scope="row" class="w-40 border-r border-slate-400 p-2 text-left align-top font-semibold">Mailing Address</th>
                                            <td class="p-2"><div v-for="line in institution.address" :key="line">{{ line }}</div><span v-if="!institution.address.length">—</span></td>
                                        </tr>
                                        <tr class="border-b border-slate-400">
                                            <th scope="row" class="border-r border-slate-400 p-2 text-left align-top font-semibold">Website</th>
                                            <td class="break-all p-2"><a v-if="institution.website" :href="websiteHref(institution.website)" class="text-[#255a90] hover:underline">{{ institution.website }}</a><span v-else>—</span></td>
                                        </tr>
                                        <tr class="border-b border-slate-400">
                                            <th scope="row" class="border-r border-slate-400 p-2 text-left align-top font-semibold">EQA Status</th>
                                            <td class="p-2">{{ institution.status }}</td>
                                        </tr>
                                        <tr :class="{ 'border-b border-slate-400': institution.de_designated_date }">
                                            <th scope="row" class="border-r border-slate-400 p-2 text-left align-top font-semibold">Designation Date</th>
                                            <td class="p-2">{{ institution.designation_date || '—' }}</td>
                                        </tr>
                                        <tr v-if="institution.de_designated_date">
                                            <th scope="row" class="border-r border-slate-400 p-2 text-left align-top font-semibold">De-Designation Date</th>
                                            <td class="p-2">{{ institution.de_designated_date }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-else-if="tab === 'dbas'" id="panel-dbas" role="tabpanel" aria-labelledby="tab-dbas" class="p-4">
                                <p class="mb-3 text-sm text-slate-600">Other names this institution operates under.</p>
                                <table class="w-full border border-slate-400 text-sm text-slate-700">
                                    <thead class="bg-slate-100">
                                        <tr class="border-b border-slate-400">
                                            <th scope="col" class="border-r border-slate-400 p-2 text-left font-semibold">Name</th>
                                            <th scope="col" class="border-r border-slate-400 p-2 text-left font-semibold">Address</th>
                                            <th scope="col" class="p-2 text-left font-semibold">Website</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(dba, index) in institution.dbas" :key="index" class="border-b border-slate-400 last:border-b-0 align-top">
                                            <td class="border-r border-slate-400 p-2">{{ dba.name }}</td>
                                            <td class="border-r border-slate-400 p-2"><div v-for="line in dba.address" :key="line">{{ line }}</div><span v-if="!dba.address.length">—</span></td>
                                            <td class="break-all p-2"><a v-if="dba.website" :href="websiteHref(dba.website)" class="text-[#255a90] hover:underline">{{ dba.website }}</a><span v-else>—</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </section>

                <aside aria-labelledby="search-title" class="self-start bg-slate-100">
                    <h2 id="search-title" class="px-4 py-2 text-2xl text-white" :style="{ backgroundColor: '#38598a' }">Search For</h2>
                    <form class="space-y-4 p-4" role="search" @submit.prevent="go({ page: undefined })">
                        <label class="block font-semibold text-slate-700">Keywords (Optional)
                            <input
                                v-model="search.q"
                                type="search"
                                maxlength="100"
                                class="mt-1 block w-full rounded border border-slate-400 bg-white px-2 py-1 font-normal"
                                @input="onKeywords"
                            />
                            <span class="mt-1 block text-xs font-normal text-slate-500">Institution name, ID or business name</span>
                        </label>
                        <label class="block font-semibold text-slate-700">City
                            <select
                                v-model="search.city"
                                class="mt-1 block w-full rounded border border-slate-400 bg-white px-1 py-1 font-normal"
                                @change="go({ page: undefined })"
                            >
                                <option value="">All</option>
                                <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="w-full rounded border border-slate-700 py-2 text-white shadow hover:opacity-90"
                            :style="{ backgroundColor: '#38598a' }"
                            @click="reset"
                        >Reset</button>
                    </form>
                </aside>
            </div>
        </main>

        <BcFooter />
    </div>
</template>
