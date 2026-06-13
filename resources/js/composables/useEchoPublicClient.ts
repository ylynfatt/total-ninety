import { useEchoPublic } from '@laravel/echo-vue';

/**
 * Subscribe to a public Echo channel, but only in the browser.
 *
 * Echo grabs its Pusher client during component setup, which has no meaning
 * under server-side rendering (no `window`, no socket). Skipping the
 * subscription on the server lets these realtime pages render their initial
 * snapshot cleanly; the client wires up the live updates after hydration.
 */
export function useEchoPublicClient<T>(channel: string, event: string, callback: (payload: T) => void): void {
    if (typeof window === 'undefined') {
        return;
    }

    useEchoPublic(channel, event, callback);
}
