<script setup lang="ts">
withDefaults(defineProps<{
    title: string;
    description: string;
    compact?: boolean;
}>(), {
    compact: false,
});
</script>

<template>
    <div class="flex min-w-0 flex-col rounded-lg border border-dashed bg-muted/20 text-sm" :class="compact ? 'gap-2 p-3' : 'gap-3 p-4 sm:p-6'">
        <p class="break-words font-medium">{{ title }}</p>
        <p class="break-words text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.default" class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <slot />
        </div>
    </div>
</template>
