<script setup>
import { ref } from 'vue'

defineProps({
    title: { type: String, default: '' },
    text: { type: String, required: true },
})

const open = ref(false)

function toggle() {
    open.value = !open.value
}

function close() {
    open.value = false
}
</script>

<template>
    <span class="relative inline-flex align-middle">
        <button
            type="button"
            :aria-expanded="open"
            :title="title"
            aria-label="More information"
            @click.stop.prevent="toggle"
            class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500 text-xs font-bold text-white shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300"
        >?</button>

        <!-- Click-catcher to dismiss the popup when clicking elsewhere -->
        <span v-if="open" class="fixed inset-0 z-40" @click.stop.prevent="close"></span>

        <div
            v-if="open"
            class="absolute left-6 top-0 z-50 w-72 rounded-md border border-slate-200 bg-white p-4 text-left shadow-lg"
            @click.stop
        >
            <p v-if="title" class="mb-2 text-sm font-semibold text-slate-800">{{ title }}</p>
            <p class="text-xs leading-relaxed text-slate-600">{{ text }}</p>
        </div>
    </span>
</template>
