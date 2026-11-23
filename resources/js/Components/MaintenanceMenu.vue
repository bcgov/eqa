<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

// Left-hand "Maintenance Menu" shared by the maintenance pages (after bcgov/fsg).
const items = [
    { label: 'Utils', href: '/admin/maintenance/utils' },
    { label: 'Email Templates', href: '/admin/maintenance/email-templates' },
    { label: 'Invoice & Receipt', href: '/admin/maintenance/invoice' },
]
const url = computed(() => (usePage().url || '').split('?')[0])
const isActive = (href) => url.value === href || url.value.startsWith(`${href}/`)
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <nav aria-labelledby="maintenance-menu-title" class="self-start overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <h2 id="maintenance-menu-title" class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700">Maintenance Menu</h2>
            <ul class="divide-y divide-slate-100">
                <li v-for="item in items" :key="item.href">
                    <Link
                        :href="item.href"
                        class="block px-4 py-2.5 text-sm"
                        :class="isActive(item.href) ? 'bg-indigo-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-50'"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                    >{{ item.label }}</Link>
                </li>
            </ul>
        </nav>
        <div class="min-w-0">
            <slot />
        </div>
    </div>
</template>
