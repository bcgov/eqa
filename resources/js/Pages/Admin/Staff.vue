<script setup>
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({
    staff: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
})

const roleClass = (active) =>
    active
        ? 'bg-green-600 text-white'
        : 'bg-white text-green-700 hover:bg-green-50'

const hasRole = (row, name) => (row.roles || []).some((r) => r.name === name)

function switchRole(row, role) {
    if (!props.canManage || row.access_type === role) return
    if (!confirm(`Switch ${row.name}'s role to: ${role}?`)) return
    router.put(`/admin/staff/${row.id}/role`, { role }, { preserveScroll: true })
}

function switchStatus(row, disabled) {
    if (!props.canManage || row.disabled === disabled) return
    if (!confirm(`Switch ${row.name}'s status to: ${disabled ? 'Inactive' : 'Active'}?`)) return
    router.put(`/admin/staff/${row.id}/status`, { disabled }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Staff" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Staff Maintenance</h1>
            <p class="text-sm text-slate-500">{{ staff.length }} ministry staff</p>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Email</th>
                    <th class="px-4 py-2.5">Role</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="row in staff" :key="row.id" class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ row.name }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ row.email }}</td>
                    <td class="px-4 py-3">
                        <div class="inline-flex overflow-hidden rounded-md border border-green-600 text-xs font-medium" role="group" aria-label="Toggle staff role">
                            <button
                                v-for="role in ['Admin', 'User', 'Guest']"
                                :key="role"
                                type="button"
                                class="border-l border-green-600 px-3 py-1.5 first:border-l-0 transition disabled:cursor-not-allowed"
                                :class="roleClass(row.access_type === role)"
                                :disabled="!canManage"
                                @click="switchRole(row, role)"
                            >{{ role }}</button>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="inline-flex overflow-hidden rounded-md border border-green-600 text-xs font-medium" role="group" aria-label="Toggle staff status">
                            <button
                                type="button"
                                class="px-3 py-1.5 transition disabled:cursor-not-allowed"
                                :class="roleClass(!row.disabled)"
                                :disabled="!canManage"
                                @click="switchStatus(row, false)"
                            >Active</button>
                            <button
                                type="button"
                                class="border-l border-green-600 px-3 py-1.5 transition disabled:cursor-not-allowed"
                                :class="roleClass(row.disabled)"
                                :disabled="!canManage"
                                @click="switchStatus(row, true)"
                            >Inactive</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="staff.length === 0">
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No ministry staff yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
