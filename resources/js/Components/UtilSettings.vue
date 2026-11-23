<script setup>
import { useForm } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'

// Settings cards (one per category) with an edit dialog, shared by Maintenance > Utils and > Invoice.
defineProps({
    categories: { type: Array, default: () => [] },
    results: { type: Object, default: () => ({}) },
    showCategoryHeadings: { type: Boolean, default: true },
})

const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

const money = (v) => Number(v ?? 0).toLocaleString('en-CA', { style: 'currency', currency: 'CAD' })
function display(row) {
    if (row.field_format === 'money') return money(row.field_name)
    if (row.field_format === 'integer') return `${row.field_name} days`
    return row.field_name || '—'
}

const editing = ref(null)
const form = useForm({ field_name: '', field_description: '', active_flag: true })
const dialog = ref(null)

async function edit(row) {
    editing.value = row
    form.clearErrors()
    form.defaults({ field_name: row.field_name ?? '', field_description: row.field_description ?? '', active_flag: !!row.active_flag })
    form.reset()
    await nextTick()
    dialog.value?.querySelector('[data-autofocus]')?.focus()
}
function close() {
    editing.value = null
}
function save() {
    form.put(`/admin/maintenance/utils/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => close(),
    })
}
const multiline = (row) => ['multiline', 'note'].includes(row.field_format)
</script>

<template>
    <section v-for="cat in categories" :key="cat" class="mb-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" :aria-label="cat">
        <h2 v-if="showCategoryHeadings" class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700">{{ cat }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-2">Setting</th>
                        <th scope="col" class="px-4 py-2">Value</th>
                        <th scope="col" class="px-4 py-2">Description</th>
                        <th scope="col" class="px-4 py-2">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in results[cat]" :key="row.id" class="align-top hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3">
                            <button type="button" class="font-medium text-indigo-600 hover:underline" @click="edit(row)">{{ row.field_label }}</button>
                        </td>
                        <td class="whitespace-pre-line px-4 py-3 text-slate-800">{{ display(row) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ row.field_description || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="row.active_flag ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ row.active_flag ? 'True' : 'False' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    <p v-if="categories.length === 0" class="text-sm text-slate-500">No settings found. Run the database migrations to create them.</p>

    <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.esc="close">
        <div ref="dialog" role="dialog" aria-modal="true" aria-labelledby="edit-util-title" class="w-full max-w-lg rounded-lg bg-white shadow-xl">
            <form @submit.prevent="save">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 id="edit-util-title" class="text-base font-semibold text-slate-800">Edit {{ editing.field_label }}</h2>
                    <p class="text-xs text-slate-500">{{ editing.field_type }}</p>
                </div>
                <div class="space-y-4 px-5 py-4">
                    <label class="block text-sm text-slate-600">Value
                        <textarea v-if="multiline(editing)" v-model="form.field_name" data-autofocus rows="4" :class="inputClass"></textarea>
                        <input v-else v-model="form.field_name" data-autofocus
                            :type="editing.field_format === 'email' ? 'email' : (['money', 'integer'].includes(editing.field_format) ? 'number' : 'text')"
                            :step="editing.field_format === 'money' ? '0.01' : (editing.field_format === 'integer' ? '1' : undefined)"
                            :min="['money', 'integer'].includes(editing.field_format) ? (editing.field_format === 'integer' ? 1 : 0) : undefined"
                            :class="inputClass" />
                        <span v-if="form.errors.field_name" class="mt-1 block text-xs text-red-600">{{ form.errors.field_name }}</span>
                    </label>
                    <slot name="value-help" :row="editing" />
                    <label class="block text-sm text-slate-600">Description
                        <textarea v-model="form.field_description" rows="3" placeholder="Describe where this setting is used in the application" :class="inputClass"></textarea>
                        <span v-if="form.errors.field_description" class="mt-1 block text-xs text-red-600">{{ form.errors.field_description }}</span>
                    </label>
                    <label class="block text-sm text-slate-600">Active
                        <select v-model="form.active_flag" :class="inputClass">
                            <option :value="true">True</option>
                            <option :value="false">False (use system default)</option>
                        </select>
                    </label>
                    <p v-if="editing.field_type === 'Fees'" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Fee changes apply to invoices created from now on. Existing invoices keep their amounts.
                    </p>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="close">Cancel</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Update</button>
                </div>
            </form>
        </div>
    </div>
</template>
