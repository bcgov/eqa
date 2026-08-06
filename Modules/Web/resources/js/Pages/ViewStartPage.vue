<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({
    layout: AppLayout,
})
</script>

<template>
    <Head title="Start Page" />

    <div class="flex min-h-[50vh] items-center justify-center p-6">
        <div class="text-center text-sm text-gray-500">
            <p>This view has no content of its own.</p>
        </div>
    </div>
</template>