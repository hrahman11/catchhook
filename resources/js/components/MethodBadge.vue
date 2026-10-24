<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ method: string }>();

const colors: Record<string, string> = {
    GET: 'bg-sky-500/15 text-sky-300 ring-sky-500/30',
    POST: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
    PUT: 'bg-amber-500/15 text-amber-300 ring-amber-500/30',
    PATCH: 'bg-violet-500/15 text-violet-300 ring-violet-500/30',
    DELETE: 'bg-rose-500/15 text-rose-300 ring-rose-500/30',
};

const classes = computed(() => colors[props.method] ?? 'bg-zinc-500/15 text-zinc-300 ring-zinc-500/30');
</script>

<template>
    <span :class="classes" class="inline-flex w-16 shrink-0 justify-center rounded px-1.5 py-0.5 font-mono text-[11px] font-medium ring-1 ring-inset">
        {{ method }}
    </span>
</template>
