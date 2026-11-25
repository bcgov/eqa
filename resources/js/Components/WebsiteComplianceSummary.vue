<script setup>
// Read-only Website Compliance answers: the application's own copy of the checklist.
defineProps({
    sections: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="sections.length" class="space-y-5 text-sm">
        <div v-for="(section, s) in sections" :key="s">
            <h3 class="font-medium text-slate-700">{{ section.title }}</h3>
            <p v-if="section.intro" class="mb-2 text-xs text-slate-500">{{ section.intro }}</p>
            <ul class="divide-y divide-slate-100 rounded-md border border-slate-200">
                <li v-for="item in section.items" :key="item.key" class="px-3 py-2">
                    <p class="font-medium text-slate-800">{{ item.label }}</p>
                    <p v-if="item.url" class="mt-0.5 break-all">
                        <a :href="item.url" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline hover:text-indigo-800">{{ item.url }}</a>
                    </p>
                    <p v-if="item.comment" class="mt-0.5 whitespace-pre-line text-slate-600">{{ item.comment }}</p>
                    <p v-if="!item.url && !item.comment" class="mt-0.5 text-slate-400">—</p>
                </li>
            </ul>
        </div>
    </div>
    <p v-else class="text-sm text-slate-500">No website compliance information recorded.</p>
</template>
