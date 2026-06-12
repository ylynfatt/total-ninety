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
    side: 'home' | 'away' | null;
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

// Commentary is narration, not a match event — it gets its own feed below the
// timeline. Everything else stays on the timeline.
const timelineEvents = computed(() => props.events.filter((event) => event.type !== 'commentary'));
const commentaryEvents = computed(() => props.events.filter((event) => event.type === 'commentary'));

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
        <div class="overflow-hidden rounded-xl border border-sidebar-border shadow-md">
            <div class="bg-pitch p-6 text-sidebar-foreground">
                <div class="mb-5 flex items-center justify-center">
                    <span
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-widest"
                        :class="isLive ? 'bg-volt text-volt-foreground' : 'bg-sidebar-accent text-sidebar-accent-foreground'"
                    >
                        <span v-if="isLive" class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-live opacity-75" />
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-live" />
                        </span>
                        {{ game.status_label }}
                        <template v-if="game.status === 'live' && game.current_minute !== null">· {{ game.current_minute }}'</template>
                    </span>
                </div>

                <div class="grid grid-cols-3 items-center gap-2">
                    <div class="text-center">
                        <div class="font-display text-lg font-bold uppercase tracking-wider text-volt">{{ game.home_team?.acronym ?? '—' }}</div>
                        <div class="truncate text-sm font-medium opacity-90">{{ game.home_team?.name ?? 'TBD' }}</div>
                    </div>
                    <div class="text-center font-display text-6xl font-bold tabular-nums leading-none">
                        {{ game.home_team_score ?? '–' }}<span class="px-2 opacity-50">:</span>{{ game.away_team_score ?? '–' }}
                    </div>
                    <div class="text-center">
                        <div class="font-display text-lg font-bold uppercase tracking-wider text-volt">{{ game.away_team?.acronym ?? '—' }}</div>
                        <div class="truncate text-sm font-medium opacity-90">{{ game.away_team?.name ?? 'TBD' }}</div>
                    </div>
                </div>

                <p v-if="game.match_date || game.location" class="mt-5 text-center text-xs uppercase tracking-wider opacity-70">
                    <span v-if="game.match_date">{{ formatDate(game.match_date) }}</span>
                    <span v-if="game.match_date && game.location"> · </span>
                    <span v-if="game.location">{{ game.location }}</span>
                </p>
            </div>
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

            <div v-if="timelineEvents.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                No events recorded yet.
            </div>

            <ol v-else class="relative space-y-1">
                <li v-for="event in timelineEvents" :key="event.id">
                    <!-- Neutral / lifecycle events are dividers between phases. -->
                    <div
                        v-if="!event.side"
                        class="flex items-center gap-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        <span class="h-px flex-1 bg-border" />
                        <span class="flex items-center gap-1.5">
                            <span class="text-sm leading-none">{{ eventGlyphs[event.type] ?? '•' }}</span>
                            {{ event.type_label }}
                            <span v-if="eventMinute(event)" class="tabular-nums">· {{ eventMinute(event) }}</span>
                        </span>
                        <span class="h-px flex-1 bg-border" />
                    </div>

                    <!-- Team events sit on their own side around a shared center rail. -->
                    <div v-else class="grid grid-cols-[1fr_3rem_1fr] items-center gap-2 rounded-md py-1.5 hover:bg-muted/40">
                        <div
                            class="flex min-w-0 flex-col text-sm"
                            :class="event.side === 'home' ? 'col-start-1 items-end text-right' : 'col-start-3 items-start text-left'"
                        >
                            <div :class="event.is_scoring ? 'font-semibold' : 'font-medium'">
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

                        <div class="col-start-2 flex flex-col items-center leading-none">
                            <span class="text-lg">{{ eventGlyphs[event.type] ?? '•' }}</span>
                            <span class="mt-0.5 text-xs font-semibold tabular-nums text-muted-foreground">{{ eventMinute(event) }}</span>
                        </div>
                    </div>
                </li>
            </ol>
        </section>

        <!-- Commentary -->
        <section v-if="commentaryEvents.length > 0">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Commentary</h2>

            <ul class="space-y-2">
                <li v-for="event in commentaryEvents" :key="event.id" class="flex gap-3 rounded-md border bg-card p-3 text-sm shadow-sm">
                    <span v-if="eventMinute(event)" class="w-10 shrink-0 text-right font-semibold tabular-nums text-muted-foreground">
                        {{ eventMinute(event) }}
                    </span>
                    <p class="min-w-0 flex-1">{{ event.description }}</p>
                </li>
            </ul>
        </section>

        <Link :href="leagueShow(league.slug).url" class="text-center text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground">
            Back to {{ league.name }}
        </Link>
    </div>
</template>
