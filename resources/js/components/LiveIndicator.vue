<script setup lang="ts">
import { useConnectionStatus } from '@laravel/echo-vue';
import { computed } from 'vue';

const status = useConnectionStatus();

const state = computed(() => {
    switch (status.value) {
        case 'connected':
            return { label: 'Live', dot: 'bg-brand-500', ping: true, text: 'text-brand-300' };
        case 'connecting':
        case 'reconnecting':
            return { label: 'Connecting…', dot: 'bg-amber-400', ping: false, text: 'text-amber-300' };
        default:
            return { label: 'Offline', dot: 'bg-zinc-500', ping: false, text: 'text-zinc-400' };
    }
});
</script>

<template>
    <span
        :class="state.text"
        :title="status === 'connected' ? 'New requests appear automatically' : 'Live updates are unavailable. Use refresh to load new requests'"
        class="inline-flex items-center gap-1.5 rounded-md border border-zinc-800 px-2 py-1 font-medium"
    >
        <span class="relative flex size-2">
            <span v-if="state.ping" :class="state.dot" class="absolute inline-flex size-full animate-ping rounded-full opacity-60" />
            <span :class="state.dot" class="relative inline-flex size-2 rounded-full" />
        </span>
        {{ state.label }}
    </span>
</template>
