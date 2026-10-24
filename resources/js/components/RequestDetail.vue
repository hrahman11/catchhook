<script setup lang="ts">
import BodyViewer from '@/components/BodyViewer.vue';
import CodeSnippets from '@/components/CodeSnippets.vue';
import CopyButton from '@/components/CopyButton.vue';
import KeyValueTable from '@/components/KeyValueTable.vue';
import MethodBadge from '@/components/MethodBadge.vue';
import ReplayPanel from '@/components/ReplayPanel.vue';
import { formatBytes, formatDateTime } from '@/lib/format';
import { headerRows, parseQuery } from '@/lib/http';
import type { CapturedRequest } from '@/types';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ request: CapturedRequest; binUrl: string }>();

const replaying = ref(false);

watch(
    () => props.request.id,
    () => (replaying.value = false),
);

const fullUrl = computed(() => {
    const path = props.request.path === '/' ? '' : props.request.path;
    const query = props.request.query_string ? `?${props.request.query_string}` : '';

    return `${props.binUrl}${path}${query}`;
});

const overview = computed<[string, string][]>(() => [
    ['Received', formatDateTime(props.request.created_at)],
    ['From', props.request.ip ?? 'unknown'],
    ['Content type', props.request.content_type ?? '—'],
    ['Size', formatBytes(props.request.size)],
    ['Request ID', props.request.id],
]);

const query = computed(() => parseQuery(props.request.query_string));
const headers = computed(() => headerRows(props.request.headers));
</script>

<template>
    <article class="space-y-6">
        <header class="flex min-w-0 items-center gap-3">
            <MethodBadge :method="request.method" />
            <code class="min-w-0 flex-1 truncate font-mono text-sm text-zinc-100" :title="fullUrl">{{ fullUrl }}</code>
            <CopyButton :value="fullUrl" label="Copy URL" />
            <button
                type="button"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-brand-500 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-brand-400"
                @click="replaying = true"
            >
                <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 3-6.7L3 8" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M3 3v5h5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Replay
            </button>
        </header>

        <ReplayPanel v-if="replaying" :key="request.id" :request="request" @close="replaying = false" />

        <section>
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Overview</h3>
            <KeyValueTable :rows="overview" />
        </section>

        <section v-if="query.length">
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Query parameters</h3>
            <KeyValueTable :rows="query" />
        </section>

        <section>
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                Headers <span class="font-normal text-zinc-500">({{ headers.length }})</span>
            </h3>
            <KeyValueTable :rows="headers" empty="No headers." />
        </section>

        <BodyViewer :request="request" />

        <CodeSnippets :request="request" :url="fullUrl" />
    </article>
</template>
