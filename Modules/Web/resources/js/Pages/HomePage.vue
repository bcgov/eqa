<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
})
</script>

<template>
    <Head title="Home" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    EQA Web
                </h1>
                <nav class="flex items-center gap-4">
                    <Link
                        href="/dashboard"
                        class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                    >
                        Dashboard
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Log out
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Welcome
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    This is the home page for the EQA Web application.
                </p>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(value, key) in stats"
                    :key="key"
                    class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-950"
                >
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ key }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ value }}
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>