<script setup lang="ts">
defineProps<{ rows: [string, string][]; empty?: string }>();
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-zinc-800">
        <table v-if="rows.length" class="w-full table-fixed text-left font-mono text-xs">
            <tbody class="divide-y divide-zinc-800">
                <tr v-for="([key, value], index) in rows" :key="`${key}-${index}`" class="align-top hover:bg-zinc-900/60">
                    <th scope="row" class="w-1/3 px-3 py-2 font-medium break-all text-zinc-400">{{ key }}</th>
                    <td class="px-3 py-2 break-all whitespace-pre-wrap text-zinc-200">{{ value }}</td>
                </tr>
            </tbody>
        </table>
        <p v-else class="px-3 py-4 text-center text-xs text-zinc-500">{{ empty ?? 'Nothing here.' }}</p>
    </div>
</template>
