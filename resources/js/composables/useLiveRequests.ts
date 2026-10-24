import type { CapturedRequestSummary, RequestCapturedEvent } from '@/types';
import { useConnectionStatus, useEchoPublic } from '@laravel/echo-vue';
import { router } from '@inertiajs/vue3';
import { ref, watch, type Ref } from 'vue';

interface Options {
    binId: string;
    initial: () => CapturedRequestSummary[];
    initialTotal: () => number;
    limit: number;
    onCaptured?: (request: CapturedRequestSummary) => void;
}

/**
 * Keeps a bin's request list in sync with requests broadcast over WebSockets.
 *
 * New requests are prepended locally, so no round trip is needed. If the
 * connection drops, the list is re-synced from the server once it comes back,
 * so requests that arrived while offline are never missed.
 */
export function useLiveRequests({ binId, initial, initialTotal, limit, onCaptured }: Options): {
    requests: Ref<CapturedRequestSummary[]>;
    total: Ref<number>;
    freshIds: Ref<Set<string>>;
} {
    const requests = ref<CapturedRequestSummary[]>([...initial()]);
    const total = ref(initialTotal());
    const freshIds = ref(new Set<string>());

    // Server-side reloads (refresh, clear, resync) replace the local state.
    watch(initial, (value) => (requests.value = [...value]));
    watch(initialTotal, (value) => (total.value = value));

    useEchoPublic<RequestCapturedEvent>(`bins.${binId}`, '.request.captured', ({ request }) => {
        if (requests.value.some((existing) => existing.id === request.id)) {
            return;
        }

        requests.value = [request, ...requests.value].slice(0, limit);
        total.value++;
        markFresh(request.id);
        onCaptured?.(request);
    });

    const status = useConnectionStatus();
    let wasConnected = status.value === 'connected';

    watch(status, (current) => {
        if (current === 'connected' && !wasConnected) {
            router.reload({ only: ['requests', 'total'] });
        }

        wasConnected = current === 'connected';
    });

    function markFresh(id: string) {
        freshIds.value = new Set(freshIds.value).add(id);

        setTimeout(() => {
            const next = new Set(freshIds.value);
            next.delete(id);
            freshIds.value = next;
        }, 2000);
    }

    return { requests, total, freshIds };
}
