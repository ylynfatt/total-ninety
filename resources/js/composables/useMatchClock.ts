import { onMounted, onUnmounted, readonly, ref } from 'vue';

/**
 * The minute a match clock currently reads. While the game is live and the
 * clock is running it's the stored base minute plus whole minutes elapsed
 * since `clockStartedAt`; otherwise it's the frozen base minute (which may be
 * null for a game that hasn't kicked off).
 */
export function matchClockMinute(now: number, status: string, baseMinute: number | null, clockStartedAt: string | null): number | null {
    if (status !== 'live' || clockStartedAt === null) {
        return baseMinute;
    }

    const elapsedMs = now - new Date(clockStartedAt).getTime();

    return (baseMinute ?? 0) + Math.max(0, Math.floor(elapsedMs / 60000));
}

/**
 * A ref holding the current epoch milliseconds, refreshed on an interval and
 * cleaned up on unmount. Drives the locally-ticking match clock so every
 * viewer (including guests) advances the minute without server round-trips.
 *
 * The interval is started in onMounted so this is safe under server-side
 * rendering — `window`/timers are only touched in the browser, and the server
 * just renders the initial snapshot.
 */
export function useNow(intervalMs = 1000) {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        timer = setInterval(() => {
            now.value = Date.now();
        }, intervalMs);
    });

    onUnmounted(() => {
        if (timer !== undefined) {
            clearInterval(timer);
        }
    });

    return readonly(now);
}
