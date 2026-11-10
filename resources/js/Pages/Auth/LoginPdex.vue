<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    hasAccess: { type: Boolean, default: false },
    status: { type: String, default: '' },
    pdexLoginUrl: { type: String, default: '' },
})
</script>

<template>
    <Head title="Sign in" />
    <div class="flex min-h-screen flex-col bg-slate-100">
        <header class="border-b-4" :style="{ backgroundColor: '#013366', borderColor: '#fcba19' }">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-4">
                <span class="text-lg font-bold text-white">British Columbia</span>
                <span class="text-sm text-slate-200">Education Quality Assurance</span>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 items-center justify-center px-4 py-12">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <h1 class="text-xl font-bold text-slate-800">EQA Designation Application Portal</h1>

                <p v-if="status" class="mt-4 text-sm text-slate-600">{{ status }}</p>
                <p v-else-if="!hasAccess" class="mt-4 text-sm text-slate-600">
                    Your account is not yet authorized to access this portal. Please contact the ministry
                    administrator.
                </p>

                <a
                    v-if="pdexLoginUrl"
                    :href="pdexLoginUrl"
                    class="mt-6 inline-block rounded-lg px-5 py-2.5 text-sm font-medium text-white"
                    :style="{ backgroundColor: '#013366' }"
                >
                    Sign in again
                </a>
            </div>
        </main>
    </div>
</template>
