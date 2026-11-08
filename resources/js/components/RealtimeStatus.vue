<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { RealtimeState } from '@/lib/realtimeMonitor';

const props = defineProps<{ state: RealtimeState }>();
defineEmits<{ retry: [] }>();

const message = computed(() => {
    if (props.state.syncing) {
        return 'Refreshing the latest match data…';
    }

    if (props.state.status === 'connecting') {
        return 'Connecting to live updates…';
    }

    if (props.state.status !== 'connected') {
        return 'Live updates disconnected. Scores may be out of date. We’ll refresh when the connection returns.';
    }

    return props.state.stale
        ? 'Live connection restored, but the data refresh failed. Try again.'
        : 'Connected · Live updates active';
});
</script>

<template>
    <div
        role="status"
        aria-live="polite"
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 px-4 py-3 text-sm"
    >
        <p
            class="min-w-0 flex-1"
            :class="state.stale ? 'text-muted-foreground' : 'text-foreground'"
        >
            {{ message }}
        </p>
        <Button
            v-if="state.stale"
            variant="outline"
            size="sm"
            :disabled="state.syncing"
            @click="$emit('retry')"
            >{{ state.syncing ? 'Refreshing…' : 'Refresh snapshot' }}</Button
        >
    </div>
</template>
