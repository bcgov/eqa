<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Login" />
    <div class="flex min-h-screen flex-col bg-slate-100">
        <header class="border-b-4" :style="{ backgroundColor: '#013366', borderColor: '#fcba19' }">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-4">
                <span class="text-lg font-bold text-white">British Columbia</span>
                <span class="text-sm text-slate-200">Education Quality Assurance</span>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 items-center justify-center px-4 py-12">
            <div class="w-full max-w-2xl">
                <h1 class="text-center text-2xl font-bold text-slate-800">
                    EQA Designation Application Portal
                </h1>
                <p class="mt-2 text-center text-sm text-slate-500">Select how you sign in.</p>

                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <Link
                        href="/login/bceid"
                        method="post"
                        as="button"
                        class="group rounded-xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:shadow-md"
                    >
                        <div class="text-lg font-semibold text-slate-800">Institution</div>
                        <p class="mt-1 text-sm text-slate-500">Sign in with <strong>BCeID</strong> to submit and track EQA applications.</p>
                        <span class="mt-4 inline-block text-sm font-medium" :style="{ color: '#013366' }">Continue to Web portal →</span>
                    </Link>

                    <Link
                        href="/login/idir"
                        method="post"
                        as="button"
                        class="group rounded-xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:shadow-md"
                    >
                        <div class="text-lg font-semibold text-slate-800">Ministry Staff</div>
                        <p class="mt-1 text-sm text-slate-500">Sign in with <strong>IDIR</strong> to review institutions and approve applications.</p>
                        <span class="mt-4 inline-block text-sm font-medium" :style="{ color: '#013366' }">Continue to Admin portal →</span>
                    </Link>
                </div>
            </div>
        </main>
    </div>
</template>