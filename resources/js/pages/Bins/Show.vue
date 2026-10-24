<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import CopyButton from '@/components/CopyButton.vue';
import LiveIndicator from '@/components/LiveIndicator.vue';
import RequestDetail from '@/components/RequestDetail.vue';
import RequestDiff from '@/components/RequestDiff.vue';
import RequestFilters from '@/components/RequestFilters.vue';
import RequestList from '@/components/RequestList.vue';
import ResponseSettingsPanel from '@/components/ResponseSettingsPanel.vue';
import WaitingForRequests from '@/components/WaitingForRequests.vue';
import { useLiveRequests } from '@/composables/useLiveRequests';
import { useNow } from '@/composables/useNow';
import { timeAgo } from '@/lib/format';
import type { Bin, CapturedRequest, CapturedRequestSummary } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { useConnectionStatus } from '@laravel/echo-vue';
import { computed, ref } from 'vue';

/** Must match BinController::LIST_LIMIT. */
const LIST_LIMIT = 100;

const props = defineProps<{
    bin: Bin;
    filters: { q: string; method: string | null };
    requests: CapturedRequestSummary[];
    total: number;
    /** Number of requests matching the filters, or null when not filtering. */
    matches: number | null;
    selected: CapturedRequest | null;
    compare: CapturedRequest | null;
}>();

const now = useNow(30_000);
const expiresIn = computed(() => timeAgo(props.bin.expires_at, now.value));
const connection = useConnectionStatus();
const editingResponse = ref(false);
const pickingCompare = ref(false);

const isFiltering = computed(() => props.matches !== null);

const { requests: liveRequests, total: liveTotal, freshIds } = useLiveRequests({
    binId: props.bin.id,
    initial: () => props.requests,
    initialTotal: () => props.total,
    limit: LIST_LIMIT,
    maxTotal: props.bin.max_requests,
    isFiltering: () => isFiltering.value,
    // Open the first request automatically, but never pull the user away from one they're reading.
    onCaptured: (request) => {
        if (!props.selected) {
            select(request.id);
        }
    },
});

type Params = { q: string | null; method: string | null; r: string | null; c: string | null };

/**
 * Navigate within the dashboard, keeping filters, selection and comparison in
 * the URL so the current view survives a refresh and can be shared.
 */
function visit(changes: Partial<Params>, only: string[]) {
    const params: Params = {
        q: props.filters.q || null,
        method: props.filters.method,
        r: props.selected?.id ?? null,
        c: props.compare?.id ?? null,
        ...changes,
    };

    const query = Object.fromEntries(Object.entries(params).filter(([, value]) => value));

    router.get(`/bins/${props.bin.id}`, query, { only, preserveState: true, preserveScroll: true, replace: true });
}

function select(id: string) {
    if (pickingCompare.value) {
        pickingCompare.value = false;

        if (id !== props.selected?.id) {
            visit({ c: id }, ['compare']);
        }

        return;
    }

    visit({ r: id, c: null }, ['selected', 'compare']);
}

function filter({ q, method }: { q: string; method: string | null }) {
    visit({ q: q || null, method }, ['filters', 'requests', 'matches']);
}

function swapCompared() {
    visit({ r: props.compare?.id ?? null, c: props.selected?.id ?? null }, ['selected', 'compare']);
}

function refresh() {
    router.reload({ only: ['requests', 'total', 'matches', 'selected', 'compare'] });
}

function clearAll() {
    if (confirm('Delete every captured request in this bin?')) {
        router.delete(`/bins/${props.bin.id}/requests`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Bin ${bin.id}`" />

    <div class="flex h-full flex-col">
        <header class="border-b border-zinc-800 bg-zinc-950/80 backdrop-blur">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
                <AppLogo />

                <div class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900 py-1 pr-1 pl-3">
                    <code class="min-w-0 flex-1 truncate font-mono text-sm text-brand-300">{{ bin.url }}</code>
                    <CopyButton :value="bin.url" />
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <span v-if="bin.expired" class="rounded-md bg-rose-500/15 px-2 py-1 text-rose-300">Expired</span>
                    <template v-else>
                        <span class="text-zinc-500">Expires {{ expiresIn }}</span>
                        <LiveIndicator />
                    </template>
                    <button
                        v-if="connection !== 'connected'"
                        type="button"
                        class="rounded-md border border-zinc-700 px-2.5 py-1 text-zinc-300 hover:bg-zinc-800"
                        @click="refresh"
                    >
                        Refresh
                    </button>
                    <button
                        type="button"
                        :title="bin.response ? 'This bin sends a custom response' : 'This bin sends the default response'"
                        class="inline-flex items-center gap-1.5 rounded-md border border-zinc-700 px-2.5 py-1 text-zinc-300 hover:bg-zinc-800"
                        @click="editingResponse = true"
                    >
                        Response
                        <span
                            :class="bin.response ? 'bg-brand-500/20 text-brand-200' : 'bg-zinc-800 text-zinc-400'"
                            class="rounded px-1 font-mono text-[10px]"
                        >
                            {{ bin.response ? bin.response.status : 'default' }}
                        </span>
                        <span v-if="bin.response?.delay_ms" class="font-mono text-[10px] text-zinc-500">+{{ (bin.response.delay_ms / 1000).toFixed(1) }}s</span>
                    </button>
                    <button
                        type="button"
                        :disabled="liveTotal === 0"
                        class="rounded-md border border-zinc-700 px-2.5 py-1 text-zinc-300 hover:border-rose-500/50 hover:text-rose-300 disabled:opacity-40 disabled:hover:border-zinc-700 disabled:hover:text-zinc-300"
                        @click="clearAll"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </header>

        <ResponseSettingsPanel v-if="editingResponse" :bin="bin" @close="editingResponse = false" />

        <WaitingForRequests v-if="liveTotal === 0" :url="bin.url" />

        <div v-else class="flex min-h-0 flex-1 flex-col md:flex-row">
            <aside class="flex max-h-96 shrink-0 flex-col border-b border-zinc-800 md:max-h-none md:w-96 md:border-r md:border-b-0">
                <RequestFilters :q="filters.q" :method="filters.method" @change="filter" />

                <div v-if="pickingCompare" class="flex items-center justify-between gap-2 border-b border-brand-500/30 bg-brand-500/10 px-4 py-2 text-xs text-brand-200">
                    Pick a request to compare with A
                    <button type="button" class="text-brand-300 underline-offset-2 hover:underline" @click="pickingCompare = false">Cancel</button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <p class="sticky top-0 z-10 border-b border-zinc-800 bg-zinc-950 px-4 py-2 text-xs text-zinc-500">
                        <template v-if="isFiltering">{{ matches }} of {{ liveTotal }} {{ liveTotal === 1 ? 'request' : 'requests' }} match</template>
                        <template v-else>{{ liveTotal }} {{ liveTotal === 1 ? 'request' : 'requests' }}</template>
                        <span v-if="(matches ?? liveTotal) > liveRequests.length">· showing latest {{ liveRequests.length }}</span>
                        <span v-if="liveTotal >= bin.max_requests" :title="`Bins keep their ${bin.max_requests} most recent requests`">
                            · oldest discarded
                        </span>
                    </p>
                    <p v-if="liveRequests.length === 0" class="px-4 py-10 text-center text-xs text-zinc-500">No requests match your search.</p>
                    <RequestList
                        v-else
                        :requests="liveRequests"
                        :selected-id="selected?.id ?? null"
                        :compare-id="compare?.id ?? null"
                        :fresh-ids="freshIds"
                        @select="select"
                    />
                </div>
            </aside>

            <main class="min-w-0 flex-1 overflow-y-auto p-6">
                <RequestDiff v-if="selected && compare" :left="selected" :right="compare" @swap="swapCompared" @close="visit({ c: null }, ['compare'])" />
                <RequestDetail v-else-if="selected" :request="selected" :bin-url="bin.url" @compare="pickingCompare = true" />
                <p v-else class="py-16 text-center text-sm text-zinc-500">Select a request to inspect it.</p>
            </main>
        </div>
    </div>
</template>
