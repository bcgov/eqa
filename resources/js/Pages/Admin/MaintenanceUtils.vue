<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import MaintenanceMenu from '../../Components/MaintenanceMenu.vue'
import UtilSettings from '../../Components/UtilSettings.vue'

defineProps({
    categories: { type: Array, default: () => [] },
    results: { type: Object, default: () => ({}) },
})

const flash = computed(() => usePage().props.flash || {})
</script>

<template>
    <Head title="Maintenance · Utils" />

    <MaintenanceMenu>
        <div v-if="flash.success" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ flash.success }}</div>
        <div v-if="flash.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ flash.error }}</div>

        <h1 class="text-2xl font-bold text-slate-800">Utils</h1>
        <p class="mb-6 mt-1 text-sm text-slate-500">
            Application settings used across the portal. Changes take effect immediately. An inactive setting falls back to its system default.
        </p>

        <UtilSettings :categories="categories" :results="results" />
    </MaintenanceMenu>
</template>
