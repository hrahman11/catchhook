<script setup lang="ts">
import { ref } from 'vue';

const props = withDefaults(defineProps<{ value: string; label?: string }>(), {
    label: 'Copy',
});

const copied = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

async function copy() {
    await navigator.clipboard.writeText(props.value);

    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-300 transition hover:border-zinc-600 hover:bg-zinc-700 hover:text-zinc-100"
        @click="copy"
    >
        <svg v-if="copied" viewBox="0 0 24 24" class="size-3.5 text-brand-400" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <svg v-else viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="9" y="9" width="13" height="13" rx="2" />
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
        </svg>
        {{ copied ? 'Copied' : label }}
    </button>
</template>
