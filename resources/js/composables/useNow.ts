import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

/**
 * A reactive timestamp that ticks on an interval, for keeping relative times fresh.
 */
export function useNow(intervalMs = 10_000): Ref<number> {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        timer = setInterval(() => (now.value = Date.now()), intervalMs);
    });

    onBeforeUnmount(() => clearInterval(timer));

    return now;
}
