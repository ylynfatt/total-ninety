import { router } from '@inertiajs/vue3';
import { echo, echoIsConfigured } from '@laravel/echo-vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { createRealtimeMonitor } from '@/lib/realtimeMonitor';
import type { RealtimeState } from '@/lib/realtimeMonitor';

export function useRealtimeConnection(only: string[], afterSync?: () => void) {
    const state = ref<RealtimeState>({
        status: 'connecting',
        stale: true,
        syncing: false,
    });
    let unsubscribe: (() => void) | undefined;
    let cancelRequest: (() => void) | undefined;
    const monitor = createRealtimeMonitor(
        () =>
            new Promise<void>((resolve, reject) => {
                router.reload({
                    only,
                    onCancelToken: (token) => {
                        cancelRequest = () => token.cancel();
                    },
                    onSuccess: () => {
                        afterSync?.();
                        resolve();
                    },
                    onError: () =>
                        reject(new Error('Could not refresh live data.')),
                    onCancel: () => reject(new Error('Refresh cancelled.')),
                    onHttpException: () => {
                        reject(new Error('Could not refresh live data.'));

                        return false;
                    },
                    onNetworkError: () => {
                        reject(new Error('Could not connect.'));

                        return false;
                    },
                    onFinish: () => {
                        cancelRequest = undefined;
                    },
                });
            }),
        (next) => {
            state.value = next;
        },
    );

    onMounted(() => {
        try {
            if (!echoIsConfigured()) {
                monitor.setStatus('failed');

                return;
            }

            const connection = echo();
            unsubscribe = connection.connector.onConnectionChange(
                monitor.setStatus,
            );
            monitor.setStatus(connection.connectionStatus());
        } catch {
            monitor.setStatus('failed');
        }
    });

    onBeforeUnmount(() => {
        monitor.dispose();
        unsubscribe?.();
        cancelRequest?.();
    });

    return { state, refresh: monitor.refresh };
}
