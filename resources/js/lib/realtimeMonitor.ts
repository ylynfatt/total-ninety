import type { ConnectionStatus } from 'laravel-echo';

export interface RealtimeState {
    status: ConnectionStatus;
    stale: boolean;
    syncing: boolean;
}

export function createRealtimeMonitor(
    resync: () => Promise<void>,
    notify: (state: RealtimeState) => void,
) {
    const state: RealtimeState = {
        status: 'connecting',
        stale: true,
        syncing: false,
    };
    let revision = 0;
    let disposed = false;

    function publish(): void {
        if (!disposed) {
            notify({ ...state });
        }
    }

    async function refresh(): Promise<void> {
        if (disposed || state.syncing) {
            return;
        }

        const startedAt = revision;
        state.syncing = true;
        publish();

        try {
            await resync();
            state.stale =
                state.status !== 'connected' || revision !== startedAt;
        } catch {
            state.stale = true;
        } finally {
            state.syncing = false;
            publish();

            if (
                !disposed &&
                state.status === 'connected' &&
                revision !== startedAt
            ) {
                void refresh();
            }
        }
    }

    function setStatus(status: ConnectionStatus): void {
        if (disposed || status === state.status) {
            return;
        }

        revision++;
        state.status = status;
        state.stale = true;
        publish();

        if (status === 'connected') {
            void refresh();
        }
    }

    return {
        refresh,
        setStatus,
        dispose: (): void => {
            disposed = true;
        },
    };
}
