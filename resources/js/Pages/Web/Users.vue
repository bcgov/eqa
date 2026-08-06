<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    institution: { type: Object, default: null },
    users: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})

function toggle(u) {
    router.patch(`/web/users/${u.crm_id}/toggle`)
}

function remove(u) {
    if (confirm(`Remove ${u.full_name || 'this user'}? This cannot be undone.`)) {
        router.delete(`/web/users/${u.crm_id}`)
    }
}
</script>

<template>
    <Head title="Manage Users" />

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Manage Users
            <span v-if="institution" class="text-base font-normal text-slate-500">for {{ institution.name }}</span>
        </h1>
        <div class="flex items-center gap-3">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">{{ users.length }} total</span>
            <Link href="/web/users/new" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add User</Link>
        </div>
    </div>

    <p class="mt-1 text-sm text-slate-500">Contacts authorised to access the EQA portal on behalf of this institution.</p>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Name</th>
                    <th class="px-3 py-2.5">Job Title</th>
                    <th class="px-3 py-2.5">Role</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">Phone</th>
                    <th class="px-3 py-2.5">Web User</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="u in users" :key="u.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">{{ u.full_name || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ u.job_title || '—' }}</td>
                    <td class="px-3 py-2">
                        <span v-if="u.role" class="rounded px-2 py-0.5 text-xs font-medium"
                            :class="u.role === 'Primary' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'">{{ u.role }}</span>
                        <span v-else class="text-xs text-slate-400">—</span>
                    </td>
                    <td class="px-3 py-2 text-slate-600">
                        <a v-if="u.email" :href="`mailto:${u.email}`" class="text-indigo-600 hover:underline">{{ u.email }}</a>
                        <span v-else>—</span>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ u.phone || u.mobile || '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ u.web_user_name || '—' }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium"
                            :class="u.web_user_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                            {{ u.web_user_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2 whitespace-nowrap text-xs">
                            <Link :href="`/web/users/${u.crm_id}/edit`" class="text-indigo-600 hover:underline">Edit</Link>
                            <button type="button" @click="toggle(u)" class="text-amber-600 hover:underline">{{ u.web_user_active ? 'Deactivate' : 'Reactivate' }}</button>
                            <button type="button" @click="remove(u)" class="text-red-600 hover:underline">Remove</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="users.length === 0"><td colspan="8" class="px-3 py-8 text-center text-sm text-slate-400">No users recorded.</td></tr>
            </tbody>
        </table>
    </div>
</template>