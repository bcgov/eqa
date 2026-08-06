<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })
</script>

<template>
    <Head title="Disabled" />

    <div class="mx-auto max-w-2xl px-4 py-12">
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <h1 class="text-xl font-semibold text-gray-900">Disabled</h1>
            <p class="mt-4 text-sm text-gray-600">
                This page is currently disabled.
            </p>
        </div>
    </div>
</template>