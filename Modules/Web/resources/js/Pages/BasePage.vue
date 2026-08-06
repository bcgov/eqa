<script setup>
import { Head } from '@inertiajs/vue3'

defineOptions({ name: 'BasePage' })

defineProps({
    title: {
        type: String,
        default: 'Base',
    },
})
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ title }}
                </h1>

                <div class="mt-4">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>