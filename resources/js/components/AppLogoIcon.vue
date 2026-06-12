<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        :class="className"
        v-bind="$attrs"
        role="img"
        aria-label="Total Ninety"
    >
        <circle cx="24" cy="24" r="20.5" stroke="currentColor" stroke-width="3" />
        <path
            d="M24 15.2 32.4 21.3 29.2 31.2H18.8L15.6 21.3 24 15.2Z"
            fill="currentColor"
        />
        <g stroke="currentColor" stroke-width="3" stroke-linecap="round">
            <path d="M24 15.2V5.5" />
            <path d="M32.4 21.3 41.5 18.4" />
            <path d="M29.2 31.2 34.8 39" />
            <path d="M18.8 31.2 13.2 39" />
            <path d="M15.6 21.3 6.5 18.4" />
        </g>
    </svg>
</template>
