<script setup lang="ts">
import BodyViewer from '@/components/BodyViewer.vue';
import CopyButton from '@/components/CopyButton.vue';
import KeyValueTable from '@/components/KeyValueTable.vue';
import MethodBadge from '@/components/MethodBadge.vue';
import { formatBytes, formatDateTime } from '@/lib/format';
import { headerRows, parseQuery } from '@/lib/http';
import type { CapturedRequest } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ request: CapturedRequest; binUrl: string }>();

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
        </header>

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
    </article>
</template>
