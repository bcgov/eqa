<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const role = computed(() => page.props.auth?.role ?? 'institution')
const user = computed(() => page.props.auth?.user ?? null)
const logoutUrl = computed(() => page.props.logoutUrl ?? '/logout')
const isMinistryAdmin = computed(() =>
    (user.value?.roles ?? []).some((r) => r === 'Super Admin' || r === 'Ministry Admin'),
)

const institutionNav = [
    { label: 'Home', href: '/web' },
    { label: 'Applications', href: '/web/applications' },
    { label: 'Institution', href: '/web/institution' },
    { label: 'Campuses', href: '/web/campuses' },
    { label: 'DBAs', href: '/web/dbas' },
    { label: 'Manage Users', href: '/web/users' },
]
const ministryNav = computed(() => [
    { label: 'Dashboard', href: '/admin' },
    { label: 'Institutions', href: '/admin/institutions' },
    { label: 'DBAs', href: '/admin/dbas' },
    { label: 'Applications', href: '/admin/applications' },
    { label: 'Invoices', href: '/admin/invoices' },
    ...(isMinistryAdmin.value
        ? [
            { label: 'Email Templates', href: '/admin/email-templates' },
            { label: 'Staff', href: '/admin/staff' },
        ]
        : []),
])
const nav = computed(() => (role.value === 'ministry' ? ministryNav.value : institutionNav))
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100">
        <header class="border-b-4" :style="{ backgroundColor: '#013366', borderColor: '#fcba19' }">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <Link :href="role === 'ministry' ? '/admin' : '/web'" class="flex items-center gap-3">
                    <span class="text-lg font-bold text-white">British Columbia</span>
                    <span class="hidden text-sm text-slate-200 sm:inline">Education Quality Assurance</span>
                </Link>
                <div class="flex items-center gap-4">
                    <span v-if="user" class="hidden text-sm text-slate-200 sm:inline">{{ user.name }}</span>
                    <a :href="logoutUrl" class="rounded bg-white/10 px-3 py-1.5 text-sm font-medium text-white hover:bg-white/20">
                        Log off
                    </a>
                </div>
            </div>
            <nav class="bg-white/5">
                <div class="mx-auto flex max-w-7xl flex-wrap gap-1 px-2">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="px-3 py-2 text-sm font-medium text-slate-100 hover:bg-white/10"
                    >{{ item.label }}</Link>
                </div>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-4 text-xs text-slate-400">
                Migrated from the legacy Microsoft estate · Served by bcmtol-webserver-dest
            </div>
        </footer>
    </div>
</template>