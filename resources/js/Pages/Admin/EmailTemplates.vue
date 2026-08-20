<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    templates: { type: Array, default: () => [] },
    sendingEnabled: { type: Boolean, default: false },
    testingEmail: { type: String, default: '' },
})

const page = usePage()
const flash = computed(() => page.props.flash || {})

// Master switch — toggling posts immediately.
const toggleSending = () => {
    router.patch('/admin/email-templates/toggle', { sending_enabled: !props.sendingEnabled }, {
        preserveScroll: true,
    })
}

// Testing-email redirect form.
const settingsForm = useForm({
    testing_email: props.testingEmail || '',
})

const saveTestingEmail = () => {
    settingsForm.patch('/admin/email-templates/settings', {
        preserveScroll: true,
    })
}

// Inline editor state.
const editingId = ref(null)
const form = useForm({
    subject: '',
    body: '',
    recipients: '',
    is_active: true,
})

const startEdit = (t) => {
    editingId.value = t.id
    form.clearErrors()
    form.subject = t.subject || ''
    form.body = t.body || ''
    form.recipients = t.recipients || ''
    form.is_active = !!t.is_active
}

const cancelEdit = () => {
    editingId.value = null
    form.reset()
    form.clearErrors()
}

const save = (t) => {
    form.put(`/admin/email-templates/${t.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null },
    })
}

const placeholderList = (variables) =>
    (variables || '').split(',').map((s) => s.trim()).filter(Boolean)

const placeholder = (name) => `{{${name}}}`

</script>

<template>
    <Head title="Email Templates" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Email Templates</h1>
            <p class="text-sm text-slate-500">Notification emails migrated from the legacy CRM · {{ templates.length }} templates</p>
        </div>
    </div>

    <div v-if="flash.success" class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
    <div v-if="flash.error" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

    <!-- Master switch -->
    <div class="mt-4 rounded-lg border shadow-sm" :class="sendingEnabled ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'">
        <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-slate-800">Email sending</h2>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-semibold"
                        :class="sendingEnabled ? 'bg-green-600 text-white' : 'bg-amber-500 text-white'"
                    >{{ sendingEnabled ? 'ON' : 'OFF' }}</span>
                </div>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Master switch for all notification emails. When <b>OFF</b> (the default), no emails are sent
                    regardless of individual template status. Turn this on only when you are ready for the system
                    to send live notifications.
                </p>
            </div>
            <button
                type="button"
                role="switch"
                :aria-checked="sendingEnabled"
                @click="toggleSending"
                class="relative inline-flex h-7 w-12 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                :class="sendingEnabled ? 'bg-green-600 focus:ring-green-500' : 'bg-slate-300 focus:ring-slate-400'"
            >
                <span
                    class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                    :class="sendingEnabled ? 'translate-x-6' : 'translate-x-1'"
                />
            </button>
        </div>
    </div>

    <!-- Testing email redirect -->
    <div class="mt-4 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="px-4 py-4">
            <h2 class="text-base font-semibold text-slate-800">Testing email</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">
                While email sending is <b>OFF</b>, if an address is set here, <b>all</b> notifications are
                still generated but redirected to this address instead of the real recipient on the template
                or process — a safe way to preview live emails. Leave blank to send nothing while OFF.
                When sending is <b>ON</b>, this field is ignored and emails go to their normal recipients.
            </p>
            <form @submit.prevent="saveTestingEmail" class="mt-3 flex flex-wrap items-start gap-2">
                <div class="min-w-[16rem] flex-1">
                    <input
                        v-model="settingsForm.testing_email"
                        type="email"
                        placeholder="tester@gov.bc.ca"
                        class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-500 focus:outline-none"
                    />
                    <p v-if="settingsForm.errors.testing_email" class="mt-1 text-xs text-red-600">{{ settingsForm.errors.testing_email }}</p>
                    <p v-if="!sendingEnabled && settingsForm.testing_email" class="mt-1 text-xs text-amber-700">
                        All notifications are currently being redirected to this address.
                    </p>
                </div>
                <button
                    type="submit"
                    :disabled="settingsForm.processing"
                    class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >Save</button>
            </form>
        </div>
    </div>

    <!-- Templates -->
    <div class="mt-6 space-y-4">
        <div v-for="t in templates" :key="t.id" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-800">{{ t.name }}</h3>
                        <span v-if="t.category" class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ t.category }}</span>
                        <span
                            class="rounded px-2 py-0.5 text-xs font-medium"
                            :class="t.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                        >{{ t.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <p v-if="t.description" class="mt-1 max-w-3xl text-xs text-slate-500">{{ t.description }}</p>
                </div>
                <button
                    v-if="editingId !== t.id"
                    type="button"
                    @click="startEdit(t)"
                    class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >Edit</button>
            </div>

            <!-- Read-only summary -->
            <div v-if="editingId !== t.id" class="grid gap-3 px-4 py-3 text-sm sm:grid-cols-2">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Subject</div>
                    <div class="text-slate-700">{{ t.subject }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Recipients</div>
                    <div class="text-slate-700">{{ t.recipients || '—' }}</div>
                </div>
                <div class="sm:col-span-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Body</div>
                    <div class="mt-1 rounded border border-slate-100 bg-slate-50 p-3 text-slate-700" v-html="t.body"></div>
                </div>
                <div v-if="t.variables" class="sm:col-span-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Available placeholders</div>
                    <div class="text-slate-600">
                        <code v-for="v in placeholderList(t.variables)" :key="v" class="mr-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ placeholder(v) }}</code>
                    </div>
                </div>
            </div>

            <!-- Edit form -->
            <form v-else @submit.prevent="save(t)" class="space-y-3 px-4 py-3 text-sm">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</label>
                    <input v-model="form.subject" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 focus:border-slate-500 focus:outline-none" />
                    <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Recipients</label>
                    <input v-model="form.recipients" type="text" placeholder="name@gov.bc.ca" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 focus:border-slate-500 focus:outline-none" />
                    <p v-if="form.errors.recipients" class="mt-1 text-xs text-red-600">{{ form.errors.recipients }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Body (HTML)</label>
                    <textarea v-model="form.body" rows="8" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs focus:border-slate-500 focus:outline-none"></textarea>
                    <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</p>
                    <p v-if="t.variables" class="mt-1 text-xs text-slate-500">
                        Placeholders:
                        <code v-for="v in placeholderList(t.variables)" :key="v" class="mr-1 rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ placeholder(v) }}</code>
                    </p>
                </div>
                <label class="flex items-center gap-2 text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Active (send this notification when triggered)
                </label>
                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Save</button>
                    <button type="button" @click="cancelEdit" class="rounded-md border border-slate-300 px-4 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                </div>
            </form>
        </div>

        <div v-if="templates.length === 0" class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-400">
            No email templates found.
        </div>
    </div>
</template>
