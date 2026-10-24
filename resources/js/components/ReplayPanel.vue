<script setup lang="ts">
import ReplayResponse from '@/components/ReplayResponse.vue';
import { postJson, type ValidationErrors } from '@/lib/api';
import { HTTP_METHODS, REPLAY_DROPPED_HEADERS, headerRows } from '@/lib/http';
import type { CapturedRequest, ReplayResult } from '@/types';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ request: CapturedRequest }>();
const emit = defineEmits<{ close: [] }>();

interface EditableHeader {
    enabled: boolean;
    name: string;
    value: string;
}

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

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="fixed inset-0 z-50 flex justify-end bg-black/60 backdrop-blur-sm" @click.self="emit('close')">
        <div role="dialog" aria-modal="true" aria-labelledby="replay-title" class="flex h-full w-full max-w-2xl flex-col border-l border-zinc-800 bg-zinc-950 shadow-2xl">
            <header class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                <div>
                    <h2 id="replay-title" class="font-semibold text-zinc-100">Replay request</h2>
                    <p class="text-xs text-zinc-500">Re-send this request to any URL, edited or as-is.</p>
                </div>
                <button type="button" class="rounded-md p-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100" aria-label="Close" @click="emit('close')">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round" />
                    </svg>
                </button>
            </header>

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

                <fieldset>
                    <legend class="mb-2 flex w-full items-center justify-between text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                        Headers
                        <button
                            type="button"
                            class="rounded px-2 py-0.5 text-xs font-medium tracking-normal text-brand-300 normal-case hover:bg-zinc-800"
                            @click="headers.push({ enabled: true, name: '', value: '' })"
                        >
                            + Add header
                        </button>
                    </legend>
                    <div class="space-y-1.5">
                        <div v-for="(header, index) in headers" :key="index" class="flex items-center gap-2">
                            <input v-model="header.enabled" type="checkbox" class="accent-brand-500" :aria-label="`Send ${header.name || 'header'}`" />
                            <input
                                v-model="header.name"
                                placeholder="Name"
                                aria-label="Header name"
                                :class="header.enabled ? '' : 'opacity-40'"
                                class="w-2/5 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-1 font-mono text-xs text-zinc-300 focus:border-brand-500 focus:outline-none"
                            />
                            <input
                                v-model="header.value"
                                placeholder="Value"
                                aria-label="Header value"
                                :class="header.enabled ? '' : 'opacity-40'"
                                class="min-w-0 flex-1 rounded-md border border-zinc-800 bg-zinc-900 px-2 py-1 font-mono text-xs text-zinc-100 focus:border-brand-500 focus:outline-none"
                            />
                            <button type="button" class="text-zinc-500 hover:text-rose-300" aria-label="Remove header" @click="headers.splice(index, 1)">
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-zinc-500">Host and Content-Length are set automatically. An <code class="font-mono">X-Catchhook-Replay</code> header is added.</p>
                </fieldset>

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
        </div>
    </div>
</template>
