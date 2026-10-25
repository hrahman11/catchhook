<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import KeyValueTable from '@/components/KeyValueTable.vue';
import { detectFormat, hexDump, parseQuery, prettyJson } from '@/lib/http';
import type { CapturedRequest } from '@/types';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ request: CapturedRequest }>();

const format = computed(() => detectFormat(props.request.content_type, props.request.body, props.request.is_binary));
const mode = ref<'pretty' | 'raw'>('pretty');

watch(
    () => props.request.id,
    () => (mode.value = 'pretty'),
);

const raw = computed(() => props.request.body ?? '');

const pretty = computed(() => {
    switch (format.value) {
        case 'json':
            return prettyJson(raw.value);
        case 'binary':
            return hexDump(raw.value);
        default:
            return raw.value;
    }
});

const formFields = computed(() => (format.value === 'form' ? parseQuery(raw.value) : []));
</script>

<template>
    <section>
        <div class="mb-2 flex items-center justify-between gap-2">
            <h3 class="text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                Body
                <span class="ml-1 rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-[10px] font-medium tracking-normal text-zinc-400 normal-case">{{
                    format
                }}</span>
            </h3>

            <div v-if="format !== 'empty'" class="flex items-center gap-2">
                <div v-if="format !== 'binary' && format !== 'text'" class="flex rounded-md border border-zinc-700 p-0.5 text-xs">
                    <button
                        v-for="option in ['pretty', 'raw'] as const"
                        :key="option"
                        type="button"
                        :class="mode === option ? 'bg-zinc-700 text-zinc-100' : 'text-zinc-400 hover:text-zinc-200'"
                        class="rounded px-2 py-0.5 capitalize"
                        @click="mode = option"
                    >
                        {{ option }}
                    </button>
                </div>
                <CopyButton :value="raw" :label="request.is_binary ? 'Copy base64' : 'Copy'" />
            </div>
        </div>

        <p v-if="format === 'empty'" class="rounded-lg border border-dashed border-zinc-800 px-3 py-4 text-center text-xs text-zinc-500">
            This request has no body.
        </p>

        <KeyValueTable v-else-if="format === 'form' && mode === 'pretty'" :rows="formFields" />

        <pre
            v-else
            class="max-h-[32rem] overflow-auto rounded-lg border border-zinc-800 bg-zinc-900/60 p-3 font-mono text-xs leading-relaxed text-zinc-200"
        ><code>{{ mode === 'raw' ? raw : pretty }}</code></pre>
    </section>
</template>
