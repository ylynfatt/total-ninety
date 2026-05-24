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
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
        role="img"
        aria-label="Total Ninety"
    >
        <text
            x="50%"
            y="52%"
            text-anchor="middle"
            dominant-baseline="central"
            font-size="32"
        >
            ⚽️
        </text>
    </svg>
</template>
