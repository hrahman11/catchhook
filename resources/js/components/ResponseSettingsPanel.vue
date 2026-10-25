<script setup lang="ts">
import HeaderEditor, { type EditableHeader } from '@/components/HeaderEditor.vue';
import SidePanel from '@/components/SidePanel.vue';
import type { Bin } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ bin: Bin }>();
const emit = defineEmits<{ close: [] }>();

const MAX_DELAY_MS = 10_000;

const presets = [
    { label: '200 OK', status: 200 },
    { label: '204 No Content', status: 204 },
    { label: '400 Bad Request', status: 400 },
    { label: '401 Unauthorized', status: 401 },
    { label: '429 Too Many Requests', status: 429 },
    { label: '500 Server Error', status: 500 },
    { label: '503 Unavailable', status: 503 },
];

const current = props.bin.response;

const form = useForm({
    status: current?.status ?? 200,
    headers: (current?.headers ?? [{ name: 'content-type', value: 'application/json' }]).map((header): EditableHeader => ({
        enabled: true,
        ...header,
    })),
    body: current?.body ?? '{"ok":true}',
    delay_ms: current?.delay_ms ?? 0,
});

const headerErrors = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return Object.fromEntries(form.headers.map((_, index) => [index, errors[`headers.${index}.name`] ?? errors[`headers.${index}.value`]]));
});

function save() {
    form.transform((data) => ({
        ...data,
        headers: data.headers.filter((header) => header.enabled && header.name.trim()).map(({ name, value }) => ({ name: name.trim(), value })),
    })).put(`/bins/${props.bin.id}/response`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

function reset() {
    router.delete(`/bins/${props.bin.id}/response`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <SidePanel title="Custom response" description="Choose what your bin sends back to whoever calls it." @close="emit('close')">
        <form class="flex flex-1 flex-col overflow-hidden" @submit.prevent="save">
            <div class="flex-1 space-y-6 overflow-y-auto p-5">
                <div>
                    <label for="response-status" class="mb-2 block text-xs font-semibold tracking-wider text-zinc-400 uppercase">Status code</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            id="response-status"
                            v-model.number="form.status"
                            type="number"
                            min="200"
                            max="599"
                            class="w-24 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 font-mono text-sm text-zinc-100 focus:border-brand-500 focus:outline-none"
                        />
                        <button
                            v-for="preset in presets"
                            :key="preset.status"
                            type="button"
                            :class="
                                form.status === preset.status
                                    ? 'border-brand-500 bg-brand-500/15 text-brand-200'
                                    : 'border-zinc-800 text-zinc-400 hover:border-zinc-600 hover:text-zinc-200'
                            "
                            class="rounded-md border px-2 py-1 text-xs"
                            @click="form.status = preset.status"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-rose-300">{{ form.errors.status }}</p>
                </div>

                <HeaderEditor v-model="form.headers" :errors="headerErrors" :max="20">
                    <template #hint>
                        <p class="mt-2 text-[11px] text-zinc-500">
                            Responses are always sandboxed. <code class="font-mono">Set-Cookie</code> and security headers can't be overridden.
                        </p>
                    </template>
                </HeaderEditor>

                <div>
                    <label for="response-body" class="mb-2 block text-xs font-semibold tracking-wider text-zinc-400 uppercase">Body</label>
                    <textarea
                        id="response-body"
                        v-model="form.body"
                        rows="8"
                        spellcheck="false"
                        class="w-full rounded-lg border border-zinc-800 bg-zinc-900 p-3 font-mono text-xs leading-relaxed text-zinc-100 focus:border-brand-500 focus:outline-none"
                    />
                    <p v-if="form.errors.body" class="mt-1 text-xs text-rose-300">{{ form.errors.body }}</p>
                </div>

                <div>
                    <label
                        for="response-delay"
                        class="mb-2 flex items-center justify-between text-xs font-semibold tracking-wider text-zinc-400 uppercase"
                    >
                        Delay
                        <span class="font-mono font-normal tracking-normal text-zinc-300">{{ (form.delay_ms / 1000).toFixed(1) }}s</span>
                    </label>
                    <input
                        id="response-delay"
                        v-model.number="form.delay_ms"
                        type="range"
                        min="0"
                        :max="MAX_DELAY_MS"
                        step="100"
                        class="w-full accent-brand-500"
                    />
                    <p class="mt-1 text-[11px] text-zinc-500">Wait before responding, to test how the sender handles slow endpoints and timeouts.</p>
                    <p v-if="form.errors.delay_ms" class="mt-1 text-xs text-rose-300">{{ form.errors.delay_ms }}</p>
                </div>
            </div>

            <footer class="flex items-center justify-between gap-3 border-t border-zinc-800 px-5 py-4">
                <button v-if="bin.response" type="button" class="text-sm text-zinc-400 hover:text-rose-300" @click="reset">Reset to default</button>
                <span v-else class="text-xs text-zinc-500">Currently sending the default JSON acknowledgement.</span>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-400 disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving…' : 'Save response' }}
                </button>
            </footer>
        </form>
    </SidePanel>
</template>
