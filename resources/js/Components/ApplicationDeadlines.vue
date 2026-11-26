<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'

const props = defineProps({
    applicationId: { type: String, required: true },
    deadlines: { type: Array, default: () => [] },
})

const page = usePage()
const errors = computed(() => page.props.errors || {})
// Setting deadlines and running the deadline job are limited to Ministry Admins (as the routes are).
const canManage = computed(() => (page.props.auth?.user?.roles ?? []).some((r) => r === 'Super Admin' || r === 'Ministry Admin'))

const now = ref(Date.now())
let timer
onMounted(() => { timer = setInterval(() => { now.value = Date.now() }, 30000) })
onUnmounted(() => clearInterval(timer))

const dates = reactive(Object.fromEntries(props.deadlines.map((d) => [d.key, d.date || ''])))
const busy = ref(false)

const when = (iso) => iso
    ? new Date(iso).toLocaleString('en-CA', { timeZone: 'America/Vancouver', weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) + ' Pacific'
    : '—'

function timeLeft(d) {
    if (!d.applies_at) return { text: 'No date set', tone: 'text-slate-400' }
    const ms = new Date(d.applies_at).getTime() - now.value
    if (ms <= 0) {
        return d.active
            ? { text: d.mode === 'job' ? 'Passed — applied at the next run' : 'Passed', tone: d.mode === 'none' ? 'text-amber-700 font-semibold' : 'text-red-700 font-semibold' }
            : { text: 'Passed', tone: 'text-slate-500' }
    }
    const minutes = Math.floor(ms / 60000)
    const days = Math.floor(minutes / 1440)
    const hours = Math.floor((minutes % 1440) / 60)
    const text = days > 0 ? `${days} day${days === 1 ? '' : 's'} ${hours} h left` : `${hours} h ${minutes % 60} min left`
    return { text, tone: !d.active ? 'text-slate-500' : days < 7 ? 'text-amber-700 font-semibold' : 'text-green-700 font-semibold' }
}

function save(d) {
    busy.value = true
    router.put(`/admin/applications/${props.applicationId}/deadlines`, { deadline: d.key, date: dates[d.key] }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false },
    })
}

function runNow() {
    if (!confirm('Run the daily deadline checks now for every application? This is the same job that runs at 01:00: past deadlines are applied and due reminders are emailed.')) return
    busy.value = true
    router.post('/admin/deadlines/run', {}, { preserveScroll: true, onFinish: () => { busy.value = false } })
}
</script>

<template>
    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="deadlines-heading">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="deadlines-heading" class="text-sm font-semibold text-slate-700">Deadlines</h2>
                <p class="mt-1 text-xs text-slate-500">
                    A deadline is met through its whole due date. Passed deadlines are applied by the daily deadline job the next day at 01:00 Pacific;
                    the appeal window closes as soon as its date has passed.
                </p>
            </div>
            <button v-if="canManage" type="button" :disabled="busy" @click="runNow"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                Run Deadline Checks Now
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="py-2 pr-4">Deadline</th>
                        <th scope="col" class="py-2 pr-4">Due date</th>
                        <th scope="col" class="py-2 pr-4">Time left</th>
                        <th scope="col" class="py-2 pr-4">When it passes</th>
                        <th v-if="canManage" scope="col" class="py-2">Change (testing)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 align-top">
                    <tr v-for="d in deadlines" :key="d.key" :class="d.active ? '' : 'text-slate-400'">
                        <td class="py-2 pr-4">
                            <span class="font-medium" :class="d.active ? 'text-slate-800' : ''">{{ d.label }}</span>
                            <span v-if="d.active" class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700">Running</span>
                            <span v-else class="ml-1 text-xs">(not running at this stage)</span>
                            <p v-if="d.note" class="mt-0.5 text-xs" :class="d.active ? 'text-slate-500' : ''">{{ d.note }}</p>
                        </td>
                        <td class="whitespace-nowrap py-2 pr-4 font-mono text-xs" :class="d.active ? 'text-slate-700' : ''">{{ d.date || '—' }}</td>
                        <td class="whitespace-nowrap py-2 pr-4 text-xs" :class="timeLeft(d).tone">{{ timeLeft(d).text }}</td>
                        <td class="py-2 pr-4 text-xs" :class="d.active ? 'text-slate-600' : ''">
                            {{ d.outcome }}
                            <span class="block" :class="d.active ? 'text-slate-500' : ''">{{ d.applied_by }}<template v-if="d.applies_at && d.mode === 'job'">: {{ when(d.applies_at) }}</template></span>
                        </td>
                        <td v-if="canManage" class="whitespace-nowrap py-2">
                            <form v-if="d.editable" class="flex items-center gap-2" @submit.prevent="save(d)">
                                <label :for="`deadline-${d.key}`" class="sr-only">New date for {{ d.label }}</label>
                                <input :id="`deadline-${d.key}`" v-model="dates[d.key]" type="date" required
                                    class="rounded-md border border-slate-300 px-2 py-1 text-xs focus:border-slate-500 focus:outline-none" />
                                <button type="submit" :disabled="busy || !dates[d.key] || dates[d.key] === d.date"
                                    class="rounded-md bg-slate-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-50">Set</button>
                            </form>
                            <span v-else class="text-xs">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="errors.date" class="mt-2 text-xs text-red-600" role="alert">{{ errors.date }}</p>
        <p v-if="canManage" class="mt-2 text-xs text-slate-500">Changing a date here sends no email. A past date is allowed so you can check the outcome straight away with Run Deadline Checks Now.</p>
    </section>
</template>
