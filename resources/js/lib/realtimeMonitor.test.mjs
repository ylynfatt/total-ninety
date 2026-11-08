import assert from 'node:assert/strict';
import test from 'node:test';
import { setImmediate } from 'node:timers/promises';
import { createRealtimeMonitor } from './realtimeMonitor.ts';

test('refreshes the initial snapshot and resyncs once after a dropped connection', async () => {
    let refreshes = 0;
    let state;
    const monitor = createRealtimeMonitor(
        async () => {
            refreshes++;
        },
        (next) => {
            state = next;
        },
    );
    monitor.setStatus('connected');
    await setImmediate();
    assert.equal(refreshes, 1);
    assert.equal(state.stale, false);
    monitor.setStatus('connected');
    assert.equal(refreshes, 1);
    monitor.setStatus('disconnected');
    assert.equal(state.stale, true);
    monitor.setStatus('reconnecting');
    monitor.setStatus('connected');
    await setImmediate();
    assert.equal(refreshes, 2);
    assert.equal(state.stale, false);
});

test('keeps failed refreshes visibly stale and supports a manual retry', async () => {
    let fail = true;
    let state;
    const monitor = createRealtimeMonitor(
        async () => {
            if (fail) {
                throw new Error('Offline');
            }
        },
        (next) => {
            state = next;
        },
    );
    monitor.setStatus('connected');
    await setImmediate();
    assert.equal(state.stale, true);
    assert.equal(state.syncing, false);
    fail = false;
    await monitor.refresh();
    assert.equal(state.stale, false);
});

test('resyncs again when the connection drops during an outstanding snapshot request', async () => {
    let resolveFirst;
    let refreshes = 0;
    let state;
    const monitor = createRealtimeMonitor(
        () => {
            refreshes++;

            return refreshes === 1
                ? new Promise((resolve) => {
                      resolveFirst = resolve;
                  })
                : Promise.resolve();
        },
        (next) => {
            state = next;
        },
    );
    monitor.setStatus('connected');
    monitor.setStatus('disconnected');
    monitor.setStatus('connected');
    assert.equal(refreshes, 1);
    resolveFirst();
    await setImmediate();
    assert.equal(refreshes, 2);
    assert.equal(state.stale, false);
});

test('does not mark a disconnected snapshot current and stops notifying after disposal', async () => {
    let notifications = 0;
    let state;
    const monitor = createRealtimeMonitor(
        async () => {},
        (next) => {
            notifications++;
            state = next;
        },
    );
    monitor.setStatus('disconnected');
    await monitor.refresh();
    assert.equal(state.stale, true);
    monitor.dispose();
    const previous = notifications;
    monitor.setStatus('connected');
    await monitor.refresh();
    assert.equal(notifications, previous);
});
