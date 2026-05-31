<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { computed, ref, watch } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import GamecastEditor from '@/components/GamecastEditor.vue';
import { formatDate } from '@/lib/datetime';
import { show as leagueShow } from '@/routes/leagues';
import { show as stageShow } from '@/routes/stages';
import type { BreadcrumbItem } from '@/types';

interface TeamRef {
    id: number;
    name: string;
    acronym: string;
}

interface GamecastGame {
    id: number;
    status: string;
    status_label: string;
    current_minute: number | null;
    match_date: string | null;
    location: string | null;
    home_team: TeamRef | null;
    away_team: TeamRef | null;
    home_team_score: number | null;
    away_team_score: number | null;
}

interface GamecastEvent {
    id: number;
    minute: number | null;
    stoppage: number | null;
    type: string;
    type_label: string;
    is_scoring: boolean;
    team_acronym: string | null;
    player_name: string | null;
    assist_player_name: string | null;
    secondary_player_name: string | null;
    description: string | null;
}

interface Roster {
    team_id: number | null;
    players: { id: number; name: string; shirt_number: number | null }[];
}

const props = defineProps<{
    league: { id: number; name: string; slug: string };
    season: { id: number; name: string };
    stage: { id: number; name: string };
    game: GamecastGame;
    events: GamecastEvent[];
    can: { update: boolean };
    rosters: { home: Roster; away: Roster } | null;
}>();

const game = ref<GamecastGame>({ ...props.game });

watch(
    () => props.game,
    (next) => {
        game.value = { ...next };
    },
);

const statusLabels: Record<string, string> = {
    scheduled: 'Scheduled',
    live: 'Live',
    half_time: 'Half Time',
    full_time: 'Full Time',
    postponed: 'Postponed',
    cancelled: 'Cancelled',
};

const isLive = computed(() => game.value.status === 'live' || game.value.status === 'half_time');

/**
 * Patch the scoreline / status in place. The payload carries everything the
 * header needs, so no refetch is required for scores.
 */
useEchoPublic(
    `game.${props.game.id}`,
    'ScoreUpdated',
    (e: { home_team_score: number | null; away_team_score: number | null; current_minute: number | null; status: string }) => {
        game.value.home_team_score = e.home_team_score;
        game.value.away_team_score = e.away_team_score;
        game.value.current_minute = e.current_minute;
        game.value.status = e.status;
        game.value.status_label = statusLabels[e.status] ?? e.status;
    },
);

useEchoPublic(`game.${props.game.id}`, 'GameStatusChanged', (e: { status: string; current_minute: number | null }) => {
    game.value.status = e.status;
    game.value.current_minute = e.current_minute;
    game.value.status_label = statusLabels[e.status] ?? e.status;
});

/**
 * A new timeline entry. The broadcast carries only IDs, so reload the resolved
 * `events` prop rather than rebuilding name lookups on the client.
 */
useEchoPublic(`game.${props.game.id}`, 'GameEventRecorded', () => {
    router.reload({ only: ['events'] });
});

function eventMinute(event: GamecastEvent): string {
    if (event.minute === null) {
        return '';
    }

    return event.stoppage ? `${event.minute}+${event.stoppage}'` : `${event.minute}'`;
}

const eventGlyphs: Record<string, string> = {
    goal: '⚽',
    own_goal: '⚽',
    penalty_goal: '⚽',
    yellow_card: '🟨',
    red_card: '🟥',
    substitution: '🔁',
    kick_off: '▶️',
    half_time: '⏸️',
    full_time: '⏹️',
    var_check: '📺',
    commentary: '💬',
};

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: stageShow({ league: props.league.slug, season: props.season.id, stage: props.stage.id }).url },
    { title: 'Gamecast', href: '#' },
]);

const matchTitle = computed(() => `${props.game.home_team?.name ?? 'TBD'} vs ${props.game.away_team?.name ?? 'TBD'}`);
</script>

<template>
    <Head :title="matchTitle" />

    <div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <!-- Scoreboard header -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-wide">
                <span v-if="isLive" class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75" />
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500" />
                </span>
                <span :class="isLive ? 'text-red-500' : 'text-muted-foreground'">
                    {{ game.status_label }}
                    <template v-if="game.status === 'live' && game.current_minute !== null"> · {{ game.current_minute }}'</template>
                </span>
            </div>

            <div class="grid grid-cols-3 items-center gap-2">
                <div class="text-center">
                    <div class="text-sm font-semibold text-muted-foreground">{{ game.home_team?.acronym ?? '—' }}</div>
                    <div class="truncate text-base font-medium">{{ game.home_team?.name ?? 'TBD' }}</div>
                </div>
                <div class="text-center text-4xl font-bold tabular-nums">
                    {{ game.home_team_score ?? '–' }}<span class="px-2 text-muted-foreground">:</span>{{ game.away_team_score ?? '–' }}
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-muted-foreground">{{ game.away_team?.acronym ?? '—' }}</div>
                    <div class="truncate text-base font-medium">{{ game.away_team?.name ?? 'TBD' }}</div>
                </div>
            </div>

            <p v-if="game.match_date || game.location" class="mt-4 text-center text-xs text-muted-foreground">
                <span v-if="game.match_date">{{ formatDate(game.match_date) }}</span>
                <span v-if="game.match_date && game.location"> · </span>
                <span v-if="game.location">{{ game.location }}</span>
            </p>
        </div>

        <!-- Owner controls -->
        <GamecastEditor
            v-if="can.update && rosters"
            :route-args="{ league: league.slug, season: season.id, stage: stage.id, game: game.id }"
            :status="game.status"
            :current-minute="game.current_minute"
            :home-team="game.home_team"
            :away-team="game.away_team"
            :rosters="rosters"
        />

        <!-- Timeline -->
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Timeline</h2>

            <div v-if="events.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                No events recorded yet.
            </div>

            <ol v-else class="space-y-1">
                <li v-for="event in events" :key="event.id" class="flex items-start gap-3 rounded-md px-2 py-2 hover:bg-muted/40">
                    <span class="w-10 shrink-0 text-right text-sm font-semibold tabular-nums text-muted-foreground">{{ eventMinute(event) }}</span>
                    <span class="shrink-0 text-lg leading-tight">{{ eventGlyphs[event.type] ?? '•' }}</span>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-medium">
                            {{ event.type_label }}
                            <span v-if="event.team_acronym" class="text-muted-foreground">· {{ event.team_acronym }}</span>
                        </div>
                        <div v-if="event.player_name" class="text-muted-foreground">
                            {{ event.player_name }}
                            <template v-if="event.type === 'substitution' && event.secondary_player_name">→ {{ event.secondary_player_name }}</template>
                            <template v-else-if="event.assist_player_name">(assist: {{ event.assist_player_name }})</template>
                        </div>
                        <div v-if="event.description" class="text-muted-foreground">{{ event.description }}</div>
                    </div>
                </li>
            </ol>
        </section>

        <Link :href="leagueShow(league.slug).url" class="text-center text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground">
            Back to {{ league.name }}
        </Link>
    </div>
</template>
