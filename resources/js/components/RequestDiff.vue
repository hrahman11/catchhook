<script setup lang="ts">
import MethodBadge from '@/components/MethodBadge.vue';
import { collapse, diffHeaders, diffLines } from '@/lib/diff';
import { formatBytes, formatDateTime } from '@/lib/format';
import { detectFormat, prettyJson } from '@/lib/http';
import type { CapturedRequest } from '@/types';
import { computed, ref } from 'vue';

const props = defineProps<{ left: CapturedRequest; right: CapturedRequest }>();
const emit = defineEmits<{ close: []; swap: [] }>();

const showUnchanged = ref(false);

const overview = computed(() =>
    (
        [
            ['Method', props.left.method, props.right.method],
            ['Path', props.left.path, props.right.path],
            ['Query', props.left.query_string ?? '', props.right.query_string ?? ''],
            ['Content type', props.left.content_type ?? '', props.right.content_type ?? ''],
            ['Size', formatBytes(props.left.size), formatBytes(props.right.size)],
            ['From', props.left.ip ?? '', props.right.ip ?? ''],
        ] as const
    ).map(([label, a, b]) => ({ label, a, b, changed: a !== b })),
);

const headers = computed(() => diffHeaders(props.left.headers, props.right.headers));
const changedHeaders = computed(() => headers.value.filter((row) => row.status !== 'same'));
const visibleHeaders = computed(() => (showUnchanged.value ? headers.value : changedHeaders.value));

const body = computed(() => {
    const { left, right } = props;

    if (left.is_binary || right.is_binary) {
        return { kind: 'binary' as const, identical: left.body === right.body && left.is_binary === right.is_binary };
    }

    if (left.body === right.body) {
        return { kind: 'identical' as const };
    }

    // Pretty-print JSON first so changes line up per field instead of on one long line.
    const lines = (request: CapturedRequest) => {
        const text = request.body ?? '';
        return (detectFormat(request.content_type, text, false) === 'json' ? prettyJson(text) : text).split('\n');
    };

    const ops = diffLines(lines(left), lines(right));

    return ops ? { kind: 'diff' as const, lines: collapse(ops) } : { kind: 'too-large' as const };
});

const statusStyles = {
    changed: 'bg-amber-500/10 text-amber-200',
    added: 'bg-emerald-500/10 text-emerald-200',
    removed: 'bg-rose-500/10 text-rose-200',
    same: 'text-zinc-400',
};
</script>

<template>
    <article class="space-y-6">
        <header class="flex flex-wrap items-center gap-3">
            <h2 class="text-sm font-semibold text-zinc-100">Comparing requests</h2>
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2 text-xs">
                <span class="flex min-w-0 items-center gap-1.5 rounded-md border border-zinc-800 px-2 py-1">
                    <span class="font-semibold text-rose-300">A</span>
                    <MethodBadge :method="left.method" />
                    <span class="truncate font-mono text-zinc-300">{{ left.path }}</span>
                    <time class="text-zinc-500" :datetime="left.created_at">{{ formatDateTime(left.created_at) }}</time>
                </span>
                <button
                    type="button"
                    class="rounded p-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
                    title="Swap A and B"
                    aria-label="Swap A and B"
                    @click="emit('swap')"
                >
                    ⇄
                </button>
                <span class="flex min-w-0 items-center gap-1.5 rounded-md border border-zinc-800 px-2 py-1">
                    <span class="font-semibold text-emerald-300">B</span>
                    <MethodBadge :method="right.method" />
                    <span class="truncate font-mono text-zinc-300">{{ right.path }}</span>
                    <time class="text-zinc-500" :datetime="right.created_at">{{ formatDateTime(right.created_at) }}</time>
                </span>
            </div>
            <button
                type="button"
                class="rounded-md border border-zinc-700 px-2.5 py-1 text-xs text-zinc-300 hover:bg-zinc-800"
                @click="emit('close')"
            >
                Exit compare
            </button>
        </header>

        <section>
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Overview</h3>
            <div class="overflow-hidden rounded-lg border border-zinc-800">
                <table class="w-full table-fixed text-left font-mono text-xs">
                    <thead class="bg-zinc-900 text-[10px] text-zinc-500 uppercase">
                        <tr>
                            <th class="w-1/5 px-3 py-1.5 font-medium">Field</th>
                            <th class="px-3 py-1.5 font-medium">A</th>
                            <th class="px-3 py-1.5 font-medium">B</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <tr v-for="row in overview" :key="row.label" :class="row.changed ? statusStyles.changed : 'text-zinc-300'">
                            <th scope="row" class="px-3 py-2 font-medium text-zinc-400">{{ row.label }}</th>
                            <td class="px-3 py-2 break-all">{{ row.a || '—' }}</td>
                            <td class="px-3 py-2 break-all">{{ row.b || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                    Headers
                    <span class="font-normal text-zinc-500 normal-case">({{ changedHeaders.length }} different)</span>
                </h3>
                <label class="flex items-center gap-1.5 text-xs text-zinc-400">
                    <input v-model="showUnchanged" type="checkbox" class="accent-brand-500" />
                    Show unchanged
                </label>
            </div>
            <div class="overflow-hidden rounded-lg border border-zinc-800">
                <table v-if="visibleHeaders.length" class="w-full table-fixed text-left font-mono text-xs">
                    <tbody class="divide-y divide-zinc-800">
                        <tr v-for="row in visibleHeaders" :key="row.name" :class="statusStyles[row.status]" class="align-top">
                            <th scope="row" class="w-1/5 px-3 py-2 font-medium break-all">{{ row.name }}</th>
                            <td class="px-3 py-2 break-all">{{ row.left ?? '—' }}</td>
                            <td class="px-3 py-2 break-all">{{ row.right ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="px-3 py-4 text-center text-xs text-zinc-500">The headers are identical.</p>
            </div>
        </section>

        <section>
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Body</h3>

            <p v-if="body.kind === 'identical'" class="rounded-lg border border-dashed border-zinc-800 px-3 py-4 text-center text-xs text-zinc-500">
                The bodies are identical.
            </p>
            <p v-else-if="body.kind === 'binary'" class="rounded-lg border border-dashed border-zinc-800 px-3 py-4 text-center text-xs text-zinc-500">
                Binary bodies are {{ body.identical ? 'identical' : 'different' }}.
            </p>
            <p
                v-else-if="body.kind === 'too-large'"
                class="rounded-lg border border-dashed border-zinc-800 px-3 py-4 text-center text-xs text-zinc-500"
            >
                The bodies are too different to show a line-by-line diff.
            </p>
            <div v-else class="max-h-[32rem] overflow-auto rounded-lg border border-zinc-800 bg-zinc-900/60 py-2 font-mono text-xs leading-relaxed">
                <template v-for="(line, index) in body.lines" :key="index">
                    <div v-if="line.type === 'skip'" class="my-1 bg-zinc-800/50 px-3 py-0.5 text-center text-[10px] text-zinc-500">
                        {{ line.count }} unchanged {{ line.count === 1 ? 'line' : 'lines' }}
                    </div>
                    <div
                        v-else
                        :class="{
                            'bg-rose-500/10 text-rose-200': line.type === 'delete',
                            'bg-emerald-500/10 text-emerald-200': line.type === 'insert',
                            'text-zinc-400': line.type === 'equal',
                        }"
                        class="flex px-3 whitespace-pre-wrap"
                    >
                        <span class="w-4 shrink-0 select-none" aria-hidden="true">{{
                            line.type === 'delete' ? '−' : line.type === 'insert' ? '+' : ' '
                        }}</span>
                        <span class="sr-only">{{ line.type === 'delete' ? 'Only in A:' : line.type === 'insert' ? 'Only in B:' : '' }}</span>
                        <span class="min-w-0 break-all">{{ line.text }}</span>
                    </div>
                </template>
            </div>
        </section>
    </article>
</template>
