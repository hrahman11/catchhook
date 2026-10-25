<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import { SNIPPET_LANGUAGES, buildSnippet, snippetInput, type SnippetLanguage } from '@/lib/snippets';
import type { CapturedRequest } from '@/types';
import { computed, ref } from 'vue';

const props = defineProps<{ request: CapturedRequest; url: string }>();

const language = ref<SnippetLanguage>('curl');
const code = computed(() => buildSnippet(language.value, snippetInput(props.request, props.url)));
</script>

<template>
    <section>
        <div class="mb-2 flex items-center justify-between gap-2">
            <h3 class="text-xs font-semibold tracking-wider text-zinc-400 uppercase">Copy as code</h3>
            <div class="flex items-center gap-2">
                <div role="tablist" class="flex rounded-md border border-zinc-700 p-0.5 text-xs">
                    <button
                        v-for="option in SNIPPET_LANGUAGES"
                        :key="option.id"
                        type="button"
                        role="tab"
                        :aria-selected="language === option.id"
                        :class="language === option.id ? 'bg-zinc-700 text-zinc-100' : 'text-zinc-400 hover:text-zinc-200'"
                        class="rounded px-2 py-0.5"
                        @click="language = option.id"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <CopyButton :value="code" />
            </div>
        </div>
        <pre
            class="max-h-80 overflow-auto rounded-lg border border-zinc-800 bg-zinc-900/60 p-3 font-mono text-xs leading-relaxed text-zinc-200"
        ><code>{{ code }}</code></pre>
    </section>
</template>
