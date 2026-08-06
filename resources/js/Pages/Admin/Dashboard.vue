<script setup>
import { Head } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Admin" />
    <h1 class="text-2xl font-bold text-slate-800">Ministry Admin Portal</h1>
    <p class="mt-2 text-sm text-slate-500">
        Ministry (IDIR) portal. Institutions, applications and the approval process
        will be generated here from the Dynamics migration.
    </p>
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Institutions</div>
            <div class="mt-1 text-2xl font-semibold text-slate-800">—</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Applications</div>
            <div class="mt-1 text-2xl font-semibold text-slate-800">—</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Pending review</div>
            <div class="mt-1 text-2xl font-semibold text-slate-800">—</div>
        </div>
    </div>
</template>