<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    institution: { type: Object, default: null },
    users: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    currentUserId: { type: Number, default: null },
})

const flash = computed(() => usePage().props.flash || {})

const roleClass = (active) =>
    active ? 'bg-green-600 text-white' : 'bg-white text-green-700 hover:bg-green-50'

const canSwitch = (u) => props.canManage && !!u.user_id && u.user_id !== props.currentUserId

function switchRole(u, role) {
    if (!canSwitch(u) || u.access_type === role) return
    if (!confirm(`Switch ${u.full_name || u.email}'s role to: ${role}?`)) return
    router.put(`/web/users/${u.user_id}/role`, { role }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Manage Users" />

    <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Manage Users
            <span v-if="institution" class="text-base font-normal text-slate-500">for {{ institution.name }}</span>
        </h1>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">{{ users.length }} total</span>
    </div>

    <p class="mt-1 text-sm text-slate-500">
        Staff authorised to access the EQA portal on behalf of this institution.
        <span v-if="canManage">As an Institution Admin you can switch a staff member's role.</span>
        <span v-else>Contact an Institution Admin to change access roles.</span>
    </p>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Name</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">User ID</th>
                    <th class="px-3 py-2.5">GUID</th>
                    <th class="px-3 py-2.5">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="u in users" :key="u.crm_id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">{{ u.full_name || '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">
                        <a v-if="u.email" :href="`mailto:${u.email}`" class="text-indigo-600 hover:underline">{{ u.email }}</a>
                        <span v-else>—</span>
                    </td>
                    <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ u.bceid_username || '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs uppercase text-slate-500">{{ u.bceid_user_guid || '—' }}</td>
                    <td class="px-3 py-2">
                        <span v-if="!u.user_id" class="text-xs text-slate-400">No account</span>
                        <div
                            v-else-if="canSwitch(u)"
                            class="inline-flex overflow-hidden rounded-md border border-green-600 text-xs font-medium"
                            role="group"
                            aria-label="Toggle staff role"
                        >
                            <button
                                v-for="r in ['Admin', 'User', 'Guest']"
                                :key="r"
                                type="button"
                                class="border-l border-green-600 px-3 py-1.5 first:border-l-0 transition"
                                :class="roleClass(u.access_type === r)"
                                @click="switchRole(u, r)"
                            >{{ r }}</button>
                        </div>
                        <span
                            v-else
                            class="rounded px-2 py-0.5 text-xs font-medium"
                            :class="u.access_type ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                        >{{ u.access_type || '—' }}</span>
                    </td>
                </tr>
                <tr v-if="users.length === 0"><td colspan="5" class="px-3 py-8 text-center text-sm text-slate-400">No users recorded.</td></tr>
            </tbody>
        </table>
    </div>
</template>