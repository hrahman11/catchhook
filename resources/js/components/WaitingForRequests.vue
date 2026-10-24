<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import { computed } from 'vue';

const props = defineProps<{ url: string }>();

const example = computed(() => `curl -X POST "${props.url}/demo?source=cli" \\\n  -H "Content-Type: application/json" \\\n  -d '{"event":"user.created","id":42}'`);
</script>

<template>
    <div class="mx-auto flex max-w-xl flex-col items-center py-16 text-center">
        <span class="relative mb-6 flex size-3">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-500 opacity-60" />
            <span class="relative inline-flex size-3 rounded-full bg-brand-500" />
        </span>
        <h2 class="text-lg font-semibold text-zinc-100">Waiting for the first request…</h2>
        <p class="mt-2 text-sm text-zinc-400">Send any HTTP request to your bin URL, with any method, path or payload.</p>

        <div class="mt-6 w-full overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900/60 text-left">
            <div class="flex items-center justify-between border-b border-zinc-800 px-3 py-2">
                <span class="text-xs text-zinc-500">Try it from your terminal</span>
                <CopyButton :value="example" />
            </div>
            <pre class="overflow-x-auto p-3 font-mono text-xs leading-relaxed text-zinc-300"><code>{{ example }}</code></pre>
        </div>
    </div>
</template>
