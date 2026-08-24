<script setup>
import { onMounted, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const el = ref(null)

// Populate the editor from the model without clobbering the caret while the
// user is actively typing (only write when the value actually differs).
const syncFromModel = () => {
    if (el.value && el.value.innerHTML !== (props.modelValue || '')) {
        el.value.innerHTML = props.modelValue || ''
    }
}

onMounted(syncFromModel)
watch(() => props.modelValue, syncFromModel)

const onInput = () => {
    if (el.value) {
        emit('update:modelValue', el.value.innerHTML)
    }
}

const exec = (command, value = null) => {
    el.value?.focus()
    document.execCommand(command, false, value)
    onInput()
}

const insertLink = () => {
    const url = window.prompt('Link URL', 'https://')
    if (url) {
        exec('createLink', url)
    }
}
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-1 rounded-t-md border border-b-0 border-slate-300 bg-slate-50 px-2 py-1">
            <button type="button" title="Bold" @click="exec('bold')" class="h-7 w-7 rounded font-bold text-slate-700 hover:bg-slate-200">B</button>
            <button type="button" title="Italic" @click="exec('italic')" class="h-7 w-7 rounded italic text-slate-700 hover:bg-slate-200">I</button>
            <button type="button" title="Underline" @click="exec('underline')" class="h-7 w-7 rounded text-slate-700 underline hover:bg-slate-200">U</button>
            <span class="mx-1 h-5 w-px bg-slate-300"></span>
            <button type="button" title="Bulleted list" @click="exec('insertUnorderedList')" class="h-7 w-7 rounded text-slate-700 hover:bg-slate-200">•</button>
            <button type="button" title="Numbered list" @click="exec('insertOrderedList')" class="h-7 w-7 rounded text-xs text-slate-700 hover:bg-slate-200">1.</button>
            <button type="button" title="Insert link" @click="insertLink" class="h-7 w-7 rounded text-slate-700 hover:bg-slate-200">🔗</button>
            <button type="button" title="Remove link" @click="exec('unlink')" class="h-7 rounded px-1.5 text-xs text-slate-700 hover:bg-slate-200">unlink</button>
            <span class="mx-1 h-5 w-px bg-slate-300"></span>
            <button type="button" title="Clear formatting" @click="exec('removeFormat')" class="h-7 rounded px-1.5 text-xs text-slate-700 hover:bg-slate-200">clear</button>
        </div>
        <div
            ref="el"
            contenteditable="true"
            @input="onInput"
            class="prose prose-sm min-h-[12rem] max-w-none rounded-b-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-500 focus:outline-none"
        ></div>
    </div>
</template>
