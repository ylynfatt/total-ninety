<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span
            class="truncate font-display text-base font-bold uppercase leading-tight tracking-[0.14em]"
            >Total <span class="text-sidebar-primary">Ninety</span></span
        >
    </div>
</template>
