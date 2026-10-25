<script setup lang="ts">
export interface EditableHeader {
    enabled: boolean;
    name: string;
    value: string;
}

const headers = defineModel<EditableHeader[]>({ required: true });

withDefaults(defineProps<{ errors?: Record<string, string | undefined>; max?: number }>(), {
    errors: () => ({}),
    max: 100,
});
</script>

<template>
    <fieldset>
        <legend class="mb-2 flex w-full items-center justify-between text-xs font-semibold tracking-wider text-zinc-400 uppercase">
            Headers
            <button
                type="button"
                :disabled="headers.length >= max"
                class="rounded px-2 py-0.5 text-xs font-medium tracking-normal text-brand-300 normal-case hover:bg-zinc-800 disabled:opacity-40"
                @click="headers.push({ enabled: true, name: '', value: '' })"
            >
                + Add header
            </button>
        </legend>

        <p v-if="headers.length === 0" class="rounded-lg border border-dashed border-zinc-800 px-3 py-3 text-center text-xs text-zinc-500">
            No headers.
        </p>

        <div class="space-y-1.5">
            <div v-for="(header, index) in headers" :key="index">
                <div class="flex items-center gap-2">
                    <input v-model="header.enabled" type="checkbox" class="accent-brand-500" :aria-label="`Send ${header.name || 'header'}`" />
                    <input
                        v-model="header.name"
                        placeholder="Name"
                        aria-label="Header name"
                        :class="header.enabled ? '' : 'opacity-40'"
                        class="w-2/5 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-1 font-mono text-xs text-zinc-300 focus:border-brand-500 focus:outline-none"
                    />
                    <input
                        v-model="header.value"
                        placeholder="Value"
                        aria-label="Header value"
                        :class="header.enabled ? '' : 'opacity-40'"
                        class="min-w-0 flex-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-1 font-mono text-xs text-zinc-100 focus:border-brand-500 focus:outline-none"
                    />
                    <button type="button" class="text-zinc-500 hover:text-rose-300" aria-label="Remove header" @click="headers.splice(index, 1)">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
                <p v-if="errors[index]" class="mt-1 pl-6 text-xs text-rose-300">{{ errors[index] }}</p>
            </div>
        </div>

        <slot name="hint" />
    </fieldset>
</template>
