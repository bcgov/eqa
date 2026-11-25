<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, nextTick, ref } from 'vue'
import MaintenanceMenu from '../../Components/MaintenanceMenu.vue'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    appliesTo: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash || {})
const inputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'
const appliesLabel = (v) => props.appliesTo.find((a) => a.value === v)?.label ?? v
const base = '/admin/maintenance/website-compliance'

function move(kind, row, direction) {
    router.post(`${base}/${kind}/${row.id}/move`, { direction }, { preserveScroll: true })
}

// One dialog at a time: a section or an item, new (id null) or existing.
const dialog = ref(null)
const dialogEl = ref(null)
const sectionForm = useForm({ title: '', intro: '', is_active: true })
const itemForm = useForm({ section_id: null, label: '', help_text: '', applies_to: 'all', is_active: true })

async function open(kind, row = null, sectionId = null) {
    const form = kind === 'section' ? sectionForm : itemForm
    form.clearErrors()
    form.defaults(kind === 'section'
        ? { title: row?.title ?? '', intro: row?.intro ?? '', is_active: row ? !!row.is_active : true }
        : { section_id: row?.section_id ?? sectionId, label: row?.label ?? '', help_text: row?.help_text ?? '', applies_to: row?.applies_to ?? 'all', is_active: row ? !!row.is_active : true })
    form.reset()
    dialog.value = { kind, id: row?.id ?? null }
    await nextTick()
    dialogEl.value?.querySelector('[data-autofocus]')?.focus()
}
function close() {
    dialog.value = null
}
function save() {
    const { kind, id } = dialog.value
    const form = kind === 'section' ? sectionForm : itemForm
    const url = `${base}/${kind}s${id ? `/${id}` : ''}`
    form[id ? 'put' : 'post'](url, { preserveScroll: true, onSuccess: () => close() })
}
</script>

<template>
    <Head title="Maintenance · Website Compliance" />

    <MaintenanceMenu>
        <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Website Compliance</h1>
                <p class="mb-6 mt-1 max-w-3xl text-sm text-slate-500">
                    The checklist institutions answer on the application: a link and/or a comment for each item. Changes apply to draft
                    applications; a submitted application keeps its own copy of the checklist as it was when it was saved.
                </p>
            </div>
            <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="open('section')">Add Section</button>
        </div>

        <section v-for="(section, s) in sections" :key="section.id" class="mb-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" :aria-label="section.title">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">{{ section.title }}
                        <span v-if="!section.is_active" class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Inactive</span>
                    </h2>
                    <p v-if="section.intro" class="mt-0.5 text-xs text-slate-500">{{ section.intro }}</p>
                </div>
                <div class="flex items-center gap-1 text-xs">
                    <button type="button" class="rounded border border-slate-300 px-2 py-1 text-slate-600 hover:bg-white disabled:opacity-40" :disabled="s === 0"
                        :aria-label="`Move section ${section.title} up`" @click="move('sections', section, 'up')">↑</button>
                    <button type="button" class="rounded border border-slate-300 px-2 py-1 text-slate-600 hover:bg-white disabled:opacity-40" :disabled="s === sections.length - 1"
                        :aria-label="`Move section ${section.title} down`" @click="move('sections', section, 'down')">↓</button>
                    <button type="button" class="ml-1 rounded border border-slate-300 px-2 py-1 font-medium text-indigo-600 hover:bg-white" @click="open('section', section)">Edit Section</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="w-20 px-4 py-2">Order</th>
                            <th scope="col" class="px-4 py-2">Item</th>
                            <th scope="col" class="px-4 py-2">Applies To</th>
                            <th scope="col" class="px-4 py-2">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(item, i) in section.items" :key="item.id" class="align-top hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 text-xs">
                                <button type="button" class="rounded border border-slate-300 px-1.5 py-0.5 text-slate-600 hover:bg-white disabled:opacity-40" :disabled="i === 0"
                                    :aria-label="`Move ${item.label} up`" @click="move('items', item, 'up')">↑</button>
                                <button type="button" class="ml-1 rounded border border-slate-300 px-1.5 py-0.5 text-slate-600 hover:bg-white disabled:opacity-40" :disabled="i === section.items.length - 1"
                                    :aria-label="`Move ${item.label} down`" @click="move('items', item, 'down')">↓</button>
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" class="text-left font-medium text-indigo-600 hover:underline" @click="open('item', item)">{{ item.label }}</button>
                                <p v-if="item.help_text" class="mt-0.5 text-xs text-slate-500">Explanation: {{ item.help_text }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ appliesLabel(item.applies_to) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ item.is_active ? 'True' : 'False' }}</span>
                            </td>
                        </tr>
                        <tr v-if="section.items.length === 0"><td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">No items in this section.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-2.5">
                <button type="button" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100" @click="open('item', null, section.id)">+ Add Item</button>
            </div>
        </section>
        <p v-if="sections.length === 0" class="text-sm text-slate-500">No sections yet. Add a section to start the checklist.</p>

        <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.esc="close">
            <div ref="dialogEl" role="dialog" aria-modal="true" aria-labelledby="wc-dialog-title" class="w-full max-w-lg rounded-lg bg-white shadow-xl">
                <form @submit.prevent="save">
                    <div class="border-b border-slate-200 px-5 py-3">
                        <h2 id="wc-dialog-title" class="text-base font-semibold text-slate-800">{{ dialog.id ? 'Edit' : 'Add' }} {{ dialog.kind === 'section' ? 'Section' : 'Item' }}</h2>
                    </div>

                    <div v-if="dialog.kind === 'section'" class="space-y-4 px-5 py-4">
                        <label class="block text-sm text-slate-600">Title
                            <input v-model="sectionForm.title" data-autofocus type="text" :class="inputClass" />
                            <span v-if="sectionForm.errors.title" class="mt-1 block text-xs text-red-600">{{ sectionForm.errors.title }}</span>
                        </label>
                        <label class="block text-sm text-slate-600">Introduction (shown under the title)
                            <textarea v-model="sectionForm.intro" rows="3" :class="inputClass"></textarea>
                            <span v-if="sectionForm.errors.intro" class="mt-1 block text-xs text-red-600">{{ sectionForm.errors.intro }}</span>
                        </label>
                        <label class="block text-sm text-slate-600">Active
                            <select v-model="sectionForm.is_active" :class="inputClass">
                                <option :value="true">True</option>
                                <option :value="false">False (hidden on new and draft applications)</option>
                            </select>
                        </label>
                    </div>

                    <div v-else class="space-y-4 px-5 py-4">
                        <label class="block text-sm text-slate-600">Section
                            <select v-model="itemForm.section_id" :class="inputClass">
                                <option v-for="sec in sections" :key="sec.id" :value="sec.id">{{ sec.title }}</option>
                            </select>
                            <span v-if="itemForm.errors.section_id" class="mt-1 block text-xs text-red-600">{{ itemForm.errors.section_id }}</span>
                        </label>
                        <label class="block text-sm text-slate-600">Item text
                            <textarea v-model="itemForm.label" data-autofocus rows="3" :class="inputClass"></textarea>
                            <span v-if="itemForm.errors.label" class="mt-1 block text-xs text-red-600">{{ itemForm.errors.label }}</span>
                        </label>
                        <label class="block text-sm text-slate-600">Explanation bubble (optional, shown in the "?" next to the item)
                            <textarea v-model="itemForm.help_text" rows="3" :class="inputClass"></textarea>
                            <span v-if="itemForm.errors.help_text" class="mt-1 block text-xs text-red-600">{{ itemForm.errors.help_text }}</span>
                        </label>
                        <label class="block text-sm text-slate-600">Applies to
                            <select v-model="itemForm.applies_to" :class="inputClass">
                                <option v-for="a in appliesTo" :key="a.value" :value="a.value">{{ a.label }}</option>
                            </select>
                        </label>
                        <label class="block text-sm text-slate-600">Active
                            <select v-model="itemForm.is_active" :class="inputClass">
                                <option :value="true">True</option>
                                <option :value="false">False (hidden on new and draft applications)</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                        <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="close">Cancel</button>
                        <button type="submit" :disabled="(dialog.kind === 'section' ? sectionForm : itemForm).processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{{ dialog.id ? 'Update' : 'Add' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </MaintenanceMenu>
</template>
