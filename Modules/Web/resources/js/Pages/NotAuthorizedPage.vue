<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Not Authorized" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="max-w-md w-full text-center">
            <p class="text-sm font-semibold text-red-600">403</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                Not Authorized
            </h1>
            <p class="mt-4 text-base text-gray-500">
                You do not have permission to access this student data.
            </p>
            <div class="mt-8">
                <Link
                    href="/"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Go back home
                </Link>
            </div>
        </div>
    </div>
</template>