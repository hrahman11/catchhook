<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link href="/" class="flex items-center gap-2 font-semibold tracking-tight text-zinc-100">
        <svg
            viewBox="0 0 24 24"
            class="size-6 text-brand-500"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M12 3v9a4 4 0 1 1-4 4" />
            <path d="M12 3h4" />
            <circle cx="12" cy="3" r="0.5" fill="currentColor" />
        </svg>
        <span>Catchhook</span>
    </Link>
</template>
