import { useEchoPublic } from '@laravel/echo-vue';

/**
 * Subscribe to a public Echo channel, but only in the browser.
 *
 * Echo grabs its Pusher client during component setup, which has no meaning
 * under server-side rendering (no `window`, no socket). Skipping the
 * subscription on the server lets these realtime pages render their initial
 * snapshot cleanly; the client wires up the live updates after hydration.
 *
 * Our events broadcast under their `broadcastAs()` short name (e.g.
 * `ScoreUpdated`). Echo's formatter otherwise prepends the `App.Events`
 * namespace and would listen for `App\Events\ScoreUpdated`, which never
 * matches the wire event. A leading dot tells Echo to use the name verbatim,
 * so we add one here — keeping every call site free of that detail.
 */
export function useEchoPublicClient<T>(channel: string, event: string, callback: (payload: T) => void): void {
    if (typeof window === 'undefined') {
        return;
    }

    const exactEvent = event.startsWith('.') || event.startsWith('\\') ? event : `.${event}`;

    useEchoPublic(channel, exactEvent, callback);
}
