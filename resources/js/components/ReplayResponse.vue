<script setup lang="ts">
import KeyValueTable from '@/components/KeyValueTable.vue';
import { formatBytes } from '@/lib/format';
import { headerRows, hexDump, prettyJson } from '@/lib/http';
import type { ReplayResult } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ result: ReplayResult }>();

const statusClass = computed(() => {
    const status = props.result.status ?? 0;

    if (status >= 500) return 'bg-rose-500/15 text-rose-300 ring-rose-500/30';
    if (status >= 400) return 'bg-amber-500/15 text-amber-300 ring-amber-500/30';
    if (status >= 300) return 'bg-sky-500/15 text-sky-300 ring-sky-500/30';

    return 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30';
});

const body = computed(() => {
    const { body, is_binary } = props.result;

    if (!body) return '';
    if (is_binary) return hexDump(body);

    return prettyJson(body);
});
</script>

<template>
    <section class="space-y-4">
        <div v-if="!result.ok" class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-200">
            <p class="font-medium">The request could not be delivered.</p>
            <p class="mt-1 font-mono text-xs break-all text-rose-300/80">{{ result.error }}</p>
            <p class="mt-2 text-xs text-rose-300/60">After {{ result.duration_ms }} ms</p>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span :class="statusClass" class="rounded px-2 py-0.5 font-mono font-medium ring-1 ring-inset">
                    {{ result.status }} {{ result.reason }}
                </span>
                <span class="text-zinc-400">{{ result.duration_ms }} ms</span>
                <span class="text-zinc-400">{{ formatBytes(result.size) }}</span>
                <span v-if="result.truncated" class="text-xs text-amber-300">Body truncated for display</span>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Response headers</h4>
                <KeyValueTable :rows="headerRows(result.headers)" empty="No headers." />
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Response body</h4>
                <pre
                    v-if="body"
                    class="max-h-72 overflow-auto rounded-lg border border-zinc-800 bg-zinc-900/60 p-3 font-mono text-xs leading-relaxed text-zinc-200"
                ><code>{{ body }}</code></pre>
                <p v-else class="rounded-lg border border-dashed border-zinc-800 px-3 py-4 text-center text-xs text-zinc-500">Empty response body.</p>
            </div>
        </template>
    </section>
</template>
