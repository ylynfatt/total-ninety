<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { index } from '@/routes/scoreboard';
import type { BreadcrumbItem } from '@/types';

interface ScoreboardGame {
    id: number;
    home_team: { name: string | null; acronym: string | null };
    away_team: { name: string | null; acronym: string | null };
    home_team_score: number | null;
    away_team_score: number | null;
    status: string;
    status_label: string;
    current_minute: number | null;
    league_name: string | null;
}

const props = defineProps<{
    games: ScoreboardGame[];
}>();

const games = ref<ScoreboardGame[]>([...props.games]);

const inProgressStatuses = ['live', 'half_time'];

function isInProgress(status: string): boolean {
    return inProgressStatuses.includes(status);
}

/**
 * A fresh score landed. Patch the matching card in place if we already know
 * about the game; otherwise it just became relevant — refetch to pick up its
 * team names (the broadcast payload doesn't carry them).
 */
useEchoPublic(
    'scoreboard.live',
    'ScoreUpdated',
    (e: { game_id: number; home_team_score: number | null; away_team_score: number | null; current_minute: number | null; status: string }) => {
        const game = games.value.find((g) => g.id === e.game_id);

        if (game) {
            game.home_team_score = e.home_team_score;
            game.away_team_score = e.away_team_score;
            game.current_minute = e.current_minute;
            game.status = e.status;
        } else if (isInProgress(e.status)) {
            reloadGames();
        }
    },
);

/**
 * A lifecycle transition. Drop games that left the live set, update the clock
 * for those still live, and refetch when a brand-new game kicks off.
 */
useEchoPublic(
    'scoreboard.live',
    'GameStatusChanged',
    (e: { game_id: number; status: string; current_minute: number | null }) => {
        const game = games.value.find((g) => g.id === e.game_id);

        if (game) {
            if (isInProgress(e.status)) {
                game.status = e.status;
                game.current_minute = e.current_minute;
            } else {
                games.value = games.value.filter((g) => g.id !== e.game_id);
            }
        } else if (isInProgress(e.status)) {
            reloadGames();
        }
    },
);

function reloadGames(): void {
    router.reload({
        only: ['games'],
        onSuccess: () => {
            games.value = [...props.games];
        },
    });
}

const pageBreadcrumbs: BreadcrumbItem[] = [{ title: 'Scoreboard', href: index().url }];
</script>

<template>
    <Head title="Live Scoreboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <div class="flex items-center gap-2.5">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-live opacity-75" />
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-live" />
                </span>
                <h1 class="font-display text-3xl font-bold uppercase tracking-wide">Live Scoreboard</h1>
            </div>
            <p class="text-sm text-muted-foreground">Every game in progress right now, updating live.</p>
        </header>

        <div v-if="games.length === 0" class="rounded-xl border border-dashed p-12 text-center text-sm text-muted-foreground">
            No games are in progress right now. Check back when kick-off rolls around.
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="game in games" :key="game.id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="flex items-center justify-between gap-2 bg-primary px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-primary-foreground">
                    <span class="truncate">{{ game.league_name ?? 'League' }}</span>
                    <span class="flex shrink-0 items-center gap-1.5">
                        <span v-if="game.status === 'live'" class="inline-flex h-1.5 w-1.5 animate-pulse rounded-full bg-volt" />
                        {{ game.status_label }}
                        <template v-if="game.status === 'live' && game.current_minute !== null">
                            <span class="font-display text-sm tabular-nums text-volt">{{ game.current_minute }}'</span>
                        </template>
                    </span>
                </div>

                <div class="space-y-1 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="w-10 shrink-0 rounded bg-muted px-1 text-center font-display text-xs font-semibold uppercase text-muted-foreground">
                                {{ game.home_team.acronym ?? '—' }}
                            </span>
                            <span class="truncate font-medium">{{ game.home_team.name ?? 'TBD' }}</span>
                        </span>
                        <span class="font-display text-3xl font-bold tabular-nums leading-none">{{ game.home_team_score ?? '–' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="w-10 shrink-0 rounded bg-muted px-1 text-center font-display text-xs font-semibold uppercase text-muted-foreground">
                                {{ game.away_team.acronym ?? '—' }}
                            </span>
                            <span class="truncate font-medium">{{ game.away_team.name ?? 'TBD' }}</span>
                        </span>
                        <span class="font-display text-3xl font-bold tabular-nums leading-none">{{ game.away_team_score ?? '–' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
