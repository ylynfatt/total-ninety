<script setup lang="ts">
import { ref, watch } from 'vue';

/**
 * A score number that pops and flashes whenever its value changes — used by
 * the gamecast header and the live scoreboard so a goal landing over the
 * realtime channel is obvious rather than a silent digit swap.
 *
 * The animation only fires on a change (never on the initial render), and the
 * flash colour is themeable so it stays legible on both the dark pitch header
 * and the light scoreboard cards.
 */
const props = withDefaults(
    defineProps<{ value: number | null; flash?: string }>(),
    { flash: 'var(--volt)' },
);

const popping = ref(false);

watch(
    () => props.value,
    () => {
        // Drop the class, then re-add it next frame so a second goal in quick
        // succession restarts the animation instead of being ignored.
        popping.value = false;
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                popping.value = true;
            });
        });
    },
);

function onAnimationEnd(): void {
    popping.value = false;
}
</script>

<template>
    <span
        class="animated-score"
        :class="{ 'animated-score--pop': popping }"
        :style="{ '--score-flash': flash }"
        @animationend="onAnimationEnd"
        >{{ value ?? '–' }}</span
    >
</template>

<style scoped>
.animated-score {
    display: inline-block;
}

.animated-score--pop {
    animation: score-pop 0.6s ease-out;
}

@keyframes score-pop {
    0% {
        transform: scale(1);
    }
    30% {
        transform: scale(1.4);
        color: var(--score-flash);
    }
    100% {
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .animated-score--pop {
        animation: none;
    }
}
</style>
