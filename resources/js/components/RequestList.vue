<script setup lang="ts">
import MethodBadge from '@/components/MethodBadge.vue';
import { useNow } from '@/composables/useNow';
import { formatBytes, timeAgo } from '@/lib/format';
import type { CapturedRequestSummary } from '@/types';

defineProps<{ requests: CapturedRequestSummary[]; selectedId: string | null }>();
defineEmits<{ select: [id: string] }>();

const now = useNow();
</script>

<template>
    <ul class="divide-y divide-zinc-800/80">
        <li v-for="request in requests" :key="request.id">
            <button
                type="button"
                :aria-current="request.id === selectedId"
                :class="request.id === selectedId ? 'bg-zinc-800/80 shadow-[inset_2px_0_0] shadow-brand-500' : 'hover:bg-zinc-900'"
                class="flex w-full flex-col gap-1.5 px-4 py-3 text-left transition"
                @click="$emit('select', request.id)"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <MethodBadge :method="request.method" />
                    <span class="truncate font-mono text-xs text-zinc-200">
                        {{ request.path }}<span v-if="request.query_string" class="text-zinc-500">?{{ request.query_string }}</span>
                    </span>
                </span>
                <span class="flex items-center justify-between gap-2 text-[11px] text-zinc-500">
                    <span class="truncate">{{ request.ip ?? 'unknown' }} · {{ formatBytes(request.size) }}</span>
                    <time :datetime="request.created_at" class="shrink-0">{{ timeAgo(request.created_at, now) }}</time>
                </span>
            </button>
        </li>
    </ul>
</template>
