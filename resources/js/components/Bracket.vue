<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show as gamecastShow } from '@/routes/games';

interface TeamRef {
    id: number;
    name: string;
    acronym: string;
}

interface BracketGame {
    id: number;
    bracket_position: number;
    home_team: TeamRef | null;
    away_team: TeamRef | null;
    home_team_score: number | null;
    away_team_score: number | null;
    status: string;
    winner: 'home' | 'away' | null;
}

interface BracketRound {
    round: number;
    label: string;
    games: BracketGame[];
}

defineProps<{
    rounds: BracketRound[];
    routeArgs: { league: string; season: number; stage: number };
}>();
</script>

<template>
    <div class="overflow-x-auto pb-2">
        <div class="flex min-w-max gap-6">
            <div v-for="round in rounds" :key="round.round" class="flex min-w-[200px] flex-1 flex-col">
                <h3 class="mb-3 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ round.label }}
                </h3>

                <div class="flex flex-1 flex-col justify-around gap-3">
                    <Link
                        v-for="game in round.games"
                        :key="game.id"
                        :href="gamecastShow({ ...routeArgs, game: game.id }).url"
                        class="block rounded-md border bg-card shadow-sm transition-colors hover:border-foreground/30"
                    >
                        <div
                            class="flex items-center justify-between gap-2 border-b px-3 py-1.5 text-sm"
                            :class="game.winner === 'home' ? 'font-semibold' : 'text-muted-foreground'"
                        >
                            <span class="truncate">{{ game.home_team?.name ?? 'TBD' }}</span>
                            <span class="tabular-nums">{{ game.home_team_score ?? '–' }}</span>
                        </div>
                        <div
                            class="flex items-center justify-between gap-2 px-3 py-1.5 text-sm"
                            :class="game.winner === 'away' ? 'font-semibold' : 'text-muted-foreground'"
                        >
                            <span class="truncate">{{ game.away_team?.name ?? 'TBD' }}</span>
                            <span class="tabular-nums">{{ game.away_team_score ?? '–' }}</span>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
