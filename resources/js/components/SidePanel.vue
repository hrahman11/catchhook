<script setup lang="ts">
import { onBeforeUnmount, onMounted, useId } from 'vue';

defineProps<{ title: string; description?: string }>();
const emit = defineEmits<{ close: [] }>();

const titleId = useId();

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="fixed inset-0 z-50 flex justify-end bg-black/60 backdrop-blur-sm" @click.self="emit('close')">
        <div role="dialog" aria-modal="true" :aria-labelledby="titleId" class="flex h-full w-full max-w-2xl flex-col border-l border-zinc-800 bg-zinc-950 shadow-2xl">
            <header class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                <div>
                    <h2 :id="titleId" class="font-semibold text-zinc-100">{{ title }}</h2>
                    <p v-if="description" class="text-xs text-zinc-500">{{ description }}</p>
                </div>
                <button type="button" class="rounded-md p-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100" aria-label="Close" @click="emit('close')">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round" />
                    </svg>
                </button>
            </header>

            <slot />
        </div>
    </div>
</template>
