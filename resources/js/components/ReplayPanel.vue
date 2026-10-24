<script setup lang="ts">
import HeaderEditor, { type EditableHeader } from '@/components/HeaderEditor.vue';
import ReplayResponse from '@/components/ReplayResponse.vue';
import SidePanel from '@/components/SidePanel.vue';
import { postJson, type ValidationErrors } from '@/lib/api';
import { HTTP_METHODS, REPLAY_DROPPED_HEADERS, headerRows } from '@/lib/http';
import type { CapturedRequest, ReplayResult } from '@/types';
import { ref } from 'vue';

const props = defineProps<{ request: CapturedRequest }>();
const emit = defineEmits<{ close: [] }>();

const TARGET_KEY = 'catchhook.replay.target';

const targetUrl = ref(readStoredTarget());
const method = ref(props.request.method);
const headers = ref<EditableHeader[]>(
    headerRows(props.request.headers)
        .filter(([name]) => !REPLAY_DROPPED_HEADERS.includes(name.toLowerCase()))
        .map(([name, value]) => ({ enabled: true, name, value })),
);
const body = ref(props.request.is_binary ? '' : (props.request.body ?? ''));

const sending = ref(false);
const result = ref<ReplayResult | null>(null);
const error = ref<string | null>(null);

async function send() {
    sending.value = true;
    error.value = null;
    result.value = null;
    storeTarget(targetUrl.value);

    try {
        const response = await postJson<ReplayResult | ValidationErrors>(`/requests/${props.request.id}/replay`, {
            target_url: targetUrl.value,
            method: method.value,
            headers: headers.value.filter((header) => header.enabled && header.name.trim()).map(({ name, value }) => ({ name: name.trim(), value })),
            // Binary bodies can't be edited as text, so the server re-sends the original bytes.
            ...(props.request.is_binary ? {} : { body: body.value }),
        });

        if (response.status === 200) {
            result.value = response.data as ReplayResult;
        } else if (response.status === 422) {
            const { errors, message } = response.data as ValidationErrors;
            error.value = Object.values(errors ?? {})[0]?.[0] ?? message;
        } else if (response.status === 429) {
            error.value = 'Too many replays. Wait a minute and try again.';
        } else {
            error.value = `Replay failed with status ${response.status}.`;
        }
    } catch {
        error.value = 'Could not reach Catchhook. Check your connection.';
    } finally {
        sending.value = false;
    }
}

function readStoredTarget(): string {
    try {
        return localStorage.getItem(TARGET_KEY) ?? '';
    } catch {
        return '';
    }
}

function storeTarget(value: string) {
    try {
        localStorage.setItem(TARGET_KEY, value);
    } catch {
        // Storage can be unavailable (private mode); remembering the URL is only a convenience.
    }
}
</script>

<template>
    <SidePanel title="Replay request" description="Re-send this request to any URL, edited or as-is." @close="emit('close')">
        <form class="flex-1 space-y-5 overflow-y-auto p-5" @submit.prevent="send">
            <div class="flex gap-2">
                <label class="sr-only" for="replay-method">Method</label>
                <select
                    id="replay-method"
                    v-model="method"
                    class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 font-mono text-sm text-zinc-100 focus:border-brand-500 focus:outline-none"
                >
                    <option v-for="option in HTTP_METHODS" :key="option" :value="option">{{ option }}</option>
                </select>
                <label class="sr-only" for="replay-target">Target URL</label>
                <input
                    id="replay-target"
                    v-model="targetUrl"
                    type="url"
                    required
                    placeholder="https://your-app.example.com/webhooks"
                    class="min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-sm text-zinc-100 placeholder:text-zinc-600 focus:border-brand-500 focus:outline-none"
                />
                <button
                    type="submit"
                    :disabled="sending"
                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-400 disabled:opacity-60"
                >
                    {{ sending ? 'Sending…' : 'Send' }}
                </button>
            </div>

            <p v-if="error" role="alert" class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">{{ error }}</p>

            <ReplayResponse v-if="result" :result="result" />

            <HeaderEditor v-model="headers">
                <template #hint>
                    <p class="mt-2 text-[11px] text-zinc-500">Host and Content-Length are set automatically. An <code class="font-mono">X-Catchhook-Replay</code> header is added.</p>
                </template>
            </HeaderEditor>

            <div>
                <label for="replay-body" class="mb-2 block text-xs font-semibold tracking-wider text-zinc-400 uppercase">Body</label>
                <p v-if="request.is_binary" class="rounded-lg border border-dashed border-zinc-800 px-3 py-4 text-center text-xs text-zinc-500">
                    Binary body. The original bytes are re-sent unchanged.
                </p>
                <textarea
                    v-else
                    id="replay-body"
                    v-model="body"
                    rows="10"
                    spellcheck="false"
                    class="w-full rounded-lg border border-zinc-800 bg-zinc-900 p-3 font-mono text-xs leading-relaxed text-zinc-100 focus:border-brand-500 focus:outline-none"
                />
            </div>
        </form>
    </SidePanel>
</template>
