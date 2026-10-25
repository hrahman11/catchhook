<script setup lang="ts">
import MethodBadge from '@/components/MethodBadge.vue';
import { useNow } from '@/composables/useNow';
import { formatBytes, timeAgo } from '@/lib/format';
import type { CapturedRequestSummary } from '@/types';

withDefaults(
    defineProps<{
        requests: CapturedRequestSummary[];
        selectedId: string | null;
        compareId?: string | null;
        freshIds?: Set<string>;
    }>(),
    {
        compareId: null,
        freshIds: () => new Set(),
    },
);
defineEmits<{ select: [id: string] }>();

const now = useNow();
</script>

<template>
    <TransitionGroup tag="ul" name="request" class="divide-y divide-zinc-800/80">
        <li v-for="request in requests" :key="request.id">
            <button
                type="button"
                :aria-current="request.id === selectedId"
                :class="[
                    request.id === selectedId ? 'bg-zinc-800/80 shadow-[inset_2px_0_0] shadow-brand-500' : 'hover:bg-zinc-900',
                    freshIds.has(request.id) && request.id !== selectedId ? 'bg-brand-500/10' : '',
                ]"
                class="flex w-full flex-col gap-1.5 px-4 py-3 text-left transition-colors duration-700"
                @click="$emit('select', request.id)"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <MethodBadge :method="request.method" />
                    <span class="truncate font-mono text-xs text-zinc-200">
                        {{ request.path }}<span v-if="request.query_string" class="text-zinc-500">?{{ request.query_string }}</span>
                    </span>
                    <span
                        v-if="freshIds.has(request.id)"
                        class="ml-auto shrink-0 rounded bg-brand-500/20 px-1.5 text-[10px] font-semibold text-brand-300 uppercase"
                        >New</span
                    >
                    <template v-if="compareId">
                        <span
                            v-if="request.id === selectedId"
                            class="ml-auto shrink-0 rounded bg-rose-500/20 px-1.5 text-[10px] font-semibold text-rose-300"
                            >A</span
                        >
                        <span
                            v-else-if="request.id === compareId"
                            class="ml-auto shrink-0 rounded bg-emerald-500/20 px-1.5 text-[10px] font-semibold text-emerald-300"
                            >B</span
                        >
                    </template>
                </span>
                <span class="flex items-center justify-between gap-2 text-[11px] text-zinc-500">
                    <span class="truncate">{{ request.ip ?? 'unknown' }} · {{ formatBytes(request.size) }}</span>
                    <time :datetime="request.created_at" class="shrink-0">{{ timeAgo(request.created_at, now) }}</time>
                </span>
            </button>
        </li>
    </TransitionGroup>
</template>

<style scoped>
.request-enter-active {
    transition:
        opacity 300ms ease,
        transform 300ms ease;
}

.request-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
