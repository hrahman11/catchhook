<script setup lang="ts">
import { HTTP_METHODS } from '@/lib/http';
import { ref, watch } from 'vue';

const props = defineProps<{ q: string; method: string | null }>();
const emit = defineEmits<{ change: [filters: { q: string; method: string | null }] }>();

const term = ref(props.q);
let timer: ReturnType<typeof setTimeout> | undefined;

// Debounce typing so the server is queried once the user pauses.
watch(term, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => emit('change', { q: value.trim(), method: props.method }), 300);
});

watch(
    () => props.q,
    (value) => {
        if (value !== term.value.trim()) term.value = value;
    },
);

function toggleMethod(method: string) {
    emit('change', { q: term.value.trim(), method: props.method === method ? null : method });
}
</script>

<template>
    <div class="space-y-2 border-b border-zinc-800 px-4 py-3">
        <label class="relative block">
            <span class="sr-only">Search requests</span>
            <svg
                viewBox="0 0 24 24"
                class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-zinc-500"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-3.5-3.5" stroke-linecap="round" />
            </svg>
            <input
                v-model="term"
                type="search"
                placeholder="Search path, headers or body…"
                class="w-full rounded-md border border-zinc-800 bg-zinc-900 py-1.5 pr-2 pl-8 text-xs text-zinc-100 placeholder:text-zinc-600 focus:border-brand-500 focus:outline-none"
            />
        </label>
        <div class="flex flex-wrap gap-1" role="group" aria-label="Filter by method">
            <button
                v-for="option in HTTP_METHODS"
                :key="option"
                type="button"
                :aria-pressed="method === option"
                :class="
                    method === option
                        ? 'border-brand-500 bg-brand-500/15 text-brand-200'
                        : 'border-zinc-800 text-zinc-500 hover:border-zinc-600 hover:text-zinc-300'
                "
                class="rounded border px-1.5 py-0.5 font-mono text-[10px]"
                @click="toggleMethod(option)"
            >
                {{ option }}
            </button>
        </div>
    </div>
</template>
