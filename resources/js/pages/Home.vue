<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({});

const features = [
    { title: 'Any request, captured', text: 'Every method, path, header and body, stored byte-for-byte, binary payloads included.' },
    { title: 'Live inspection', text: 'Requests appear in your dashboard the moment they arrive, without refreshing.' },
    { title: 'Replay & debug', text: 'Re-send a captured request to your local server or any URL, edited or as-is.' },
];
</script>

<template>
    <Head title="Inspect webhooks in real time" />

    <div class="flex min-h-full flex-col">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
            <AppLogo />
            <a href="https://github.com/hrahman11/catchhook" class="text-sm text-zinc-400 transition hover:text-zinc-100">GitHub</a>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center px-6 pt-16 pb-24 text-center">
            <p class="rounded-full border border-brand-500/30 bg-brand-500/10 px-3 py-1 text-xs font-medium text-brand-300">
                No sign-up. Your URL is ready in one click.
            </p>

            <h1 class="mt-6 max-w-3xl text-4xl font-semibold tracking-tight text-zinc-50 sm:text-6xl">
                See exactly what your webhooks send.
            </h1>
            <p class="mt-6 max-w-2xl text-lg text-zinc-400">
                Catchhook gives you a unique URL that captures every HTTP request sent to it, so you can inspect headers and payloads and debug
                integrations in seconds.
            </p>

            <form class="mt-10" @submit.prevent="form.post('/bins')">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-brand-500 px-6 py-3 font-semibold text-white shadow-lg shadow-brand-500/25 transition hover:bg-brand-400 disabled:opacity-60"
                >
                    {{ form.processing ? 'Creating…' : 'Create a webhook URL' }}
                </button>
            </form>

            <div class="mt-24 grid w-full gap-4 text-left sm:grid-cols-3">
                <div v-for="feature in features" :key="feature.title" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-5">
                    <h2 class="font-medium text-zinc-100">{{ feature.title }}</h2>
                    <p class="mt-2 text-sm text-zinc-400">{{ feature.text }}</p>
                </div>
            </div>
        </main>
    </div>
</template>
