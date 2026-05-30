<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import StandingsTable from '@/components/StandingsTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { edit as editFixture } from '@/routes/fixtures';
import { show as gamecastShow } from '@/routes/games';
import { create as createGroup, destroy as destroyGroup, edit as editGroup } from '@/routes/groups';
import { edit as editGroupTeams } from '@/routes/groups/teams';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { show as seasonShow } from '@/routes/seasons';
import { destroy, edit as stageEdit, generateFixtures } from '@/routes/stages';
import type { BreadcrumbItem } from '@/types';

interface LeagueSummary {
    id: number;
    name: string;
    slug: string;
}

interface SeasonSummary {
    id: number;
    name: string;
}

interface TeamSummary {
    id: number;
    name: string;
    acronym: string;
}

interface ResultRow {
    id: number;
    home_team_score: number;
    away_team_score: number;
}

interface Game {
    id: number;
    home_team_id: number;
    away_team_id: number;
    home_team: TeamSummary | null;
    away_team: TeamSummary | null;
    match_date: string | null;
    location: string | null;
    group_id: number | null;
    result: ResultRow | null;
}

interface Group {
    id: number;
    name: string;
    order: number;
    teams_count: number;
}

interface Stage {
    id: number;
    name: string;
    format: string;
    order: number;
    starts_on: string | null;
    ends_on: string | null;
    config: Record<string, unknown> | null;
    groups: Group[];
    games: Game[];
}

interface StandingRow {
    team_id: number;
    team_name: string;
    team_acronym: string;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    goals_for: number;
    goals_against: number;
    goal_difference: number;
    points: number;
    form: string;
}

interface OverallStandings {
    overall: StandingRow[];
}

interface GroupedStandingsEntry {
    group: { id: number; name: string };
    rows: StandingRow[];
}

// PHP serializes the per-group standings as an object keyed by group id
// (since they're string keys). Vue/TS sees that as Record<string, ...>.
type Standings = OverallStandings | Record<string, GroupedStandingsEntry> | null;

const props = defineProps<{
    league: LeagueSummary;
    season: SeasonSummary;
    stage: Stage;
    standings: Standings;
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

const overallStandings = computed<StandingRow[] | null>(() => {
    if (props.standings && 'overall' in props.standings) {
        return props.standings.overall;
    }
    return null;
});

const groupedStandings = computed<GroupedStandingsEntry[] | null>(() => {
    if (props.standings && !('overall' in props.standings)) {
        // Preserve group order by following stage.groups
        const map = props.standings as Record<string, GroupedStandingsEntry>;
        return props.stage.groups
            .map((g) => map[String(g.id)])
            .filter((entry): entry is GroupedStandingsEntry => entry !== undefined);
    }
    return null;
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: leaguesIndex().url },
        ],
    },
});

const hasGroupedFormat = computed(() => ['group_stage', 'conference'].includes(props.stage.format));
const isBracketFormat = computed(() => ['single_elimination', 'double_elimination'].includes(props.stage.format));
const fixturesGenerated = computed(() => props.stage.games.length > 0);

const legsPerGroup = computed<1 | 2>(() => {
    const value = props.stage.config?.legs_per_group as number | undefined;

    return value === 2 ? 2 : 1;
});

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Leagues', href: leaguesIndex().url },
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: seasonShow([props.league.slug, props.season.id]).url },
    { title: props.stage.name, href: '#' },
]);

const generateForm = useForm({});

function generate() {
    generateForm.post(
        generateFixtures([props.league.slug, props.season.id, props.stage.id]).url,
        { preserveScroll: true },
    );
}

function deleteStage() {
    if (!confirm(`Delete stage "${props.stage.name}"? All groups and games in this stage will be deleted too.`)) {
        return;
    }
    router.delete(destroy([props.league.slug, props.season.id, props.stage.id]).url);
}

function deleteGroup(group: Group) {
    if (!confirm(`Delete group "${group.name}"? Any games already in this group will become unassigned (but won't be deleted).`)) {
        return;
    }
    router.delete(destroyGroup([props.league.slug, props.season.id, props.stage.id, group.id]).url);
}
</script>

<template>
    <Head :title="`${stage.name} — ${season.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ stage.name }}</h1>
                    <Badge variant="secondary">{{ stage.format.replace(/_/g, ' ') }}</Badge>
                </div>
                <p v-if="stage.starts_on || stage.ends_on" class="text-sm text-muted-foreground">
                    {{ stage.starts_on }}<span v-if="stage.ends_on"> &mdash; {{ stage.ends_on }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="stageEdit([league.slug, season.id, stage.id]).url">Edit</Link>
                </Button>
                <Button v-if="can.delete" variant="destructive" @click="deleteStage">Delete</Button>
            </div>
        </header>

        <!-- Groups card for grouped formats -->
        <Card v-if="hasGroupedFormat">
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <CardTitle class="text-base">Groups</CardTitle>
                        <CardDescription>
                            {{ stage.groups.length }} group{{ stage.groups.length === 1 ? '' : 's' }}
                            <span v-if="stage.format === 'group_stage'">
                                · {{ legsPerGroup === 2 ? 'home and away' : 'single round-robin' }}
                            </span>
                            <span v-if="stage.groups.length === 0">— add at least one before generating fixtures</span>
                        </CardDescription>
                    </div>
                    <Button v-if="can.update" size="sm" as-child>
                        <Link :href="createGroup([league.slug, season.id, stage.id]).url">Create group</Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent v-if="stage.groups.length > 0">
                <ul class="grid gap-2">
                    <li
                        v-for="group in stage.groups"
                        :key="group.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-card px-3 py-2"
                    >
                        <div class="flex items-center gap-3 text-sm">
                            <span class="font-medium">{{ group.name }}</span>
                            <span class="text-muted-foreground">{{ group.teams_count }} team{{ group.teams_count === 1 ? '' : 's' }}</span>
                        </div>
                        <div v-if="can.update" class="flex gap-2">
                            <Button size="sm" variant="outline" as-child>
                                <Link :href="editGroupTeams([league.slug, season.id, stage.id, group.id]).url">Manage teams</Link>
                            </Button>
                            <Button size="sm" variant="ghost" as-child>
                                <Link :href="editGroup([league.slug, season.id, stage.id, group.id]).url">Edit</Link>
                            </Button>
                            <Button size="sm" variant="ghost" class="text-destructive" @click="deleteGroup(group)">
                                Delete
                            </Button>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- Standings (ungrouped) -->
        <Card v-if="overallStandings">
            <CardHeader>
                <CardTitle class="text-base">Standings</CardTitle>
                <CardDescription>
                    Recomputed from the games above. Teams without any decided games still appear at the bottom.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <StandingsTable :rows="overallStandings" />
            </CardContent>
        </Card>

        <!-- Standings (grouped: one table per group) -->
        <Card v-if="groupedStandings">
            <CardHeader>
                <CardTitle class="text-base">Standings</CardTitle>
                <CardDescription>One table per group.</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-6">
                <section v-for="entry in groupedStandings" :key="entry.group.id" class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold tracking-wide text-muted-foreground">
                        {{ entry.group.name }}
                    </h3>
                    <StandingsTable :rows="entry.rows" />
                </section>
            </CardContent>
        </Card>

        <!-- Fixture generation card -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <CardTitle class="text-base">Fixtures</CardTitle>
                        <CardDescription>
                            {{ fixturesGenerated ? `${stage.games.length} games scheduled` : 'No fixtures generated yet' }}
                        </CardDescription>
                    </div>
                    <Button
                        v-if="can.update && !fixturesGenerated"
                        :disabled="generateForm.processing || (hasGroupedFormat && stage.groups.length === 0)"
                        @click="generate"
                    >
                        Generate fixtures
                    </Button>
                </div>
                <p v-if="generateForm.errors.fixtures" class="mt-2 text-sm text-destructive">
                    {{ generateForm.errors.fixtures }}
                </p>
            </CardHeader>
            <CardContent>
                <div v-if="!fixturesGenerated" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    <p v-if="isBracketFormat">Round-1 fixtures will be generated on click.</p>
                    <p v-else-if="hasGroupedFormat">Add groups and team assignments, then come back to generate fixtures.</p>
                    <p v-else>Click "Generate fixtures" to create the full schedule for this stage.</p>
                </div>
                <ul v-else class="divide-y rounded-md border">
                    <li v-for="game in stage.games" :key="game.id" class="flex items-center gap-4 px-4 py-2 text-sm">
                        <span class="font-mono text-xs text-muted-foreground tabular-nums">#{{ game.id }}</span>
                        <span class="flex-1 text-right">{{ game.home_team?.name ?? '—' }}</span>
                        <span v-if="game.result" class="rounded bg-muted px-2 py-0.5 text-sm font-semibold tabular-nums">
                            {{ game.result.home_team_score }}&nbsp;–&nbsp;{{ game.result.away_team_score }}
                        </span>
                        <span v-else class="text-xs text-muted-foreground">vs</span>
                        <span class="flex-1">{{ game.away_team?.name ?? '—' }}</span>
                        <span class="text-xs text-muted-foreground tabular-nums">
                            {{ game.match_date ? game.match_date.slice(0, 10) : 'TBD' }}
                        </span>
                        <Link
                            :href="gamecastShow([league.slug, season.id, stage.id, game.id]).url"
                            class="text-xs font-medium text-muted-foreground hover:text-foreground hover:underline"
                        >
                            Gamecast
                        </Link>
                        <Link
                            v-if="can.update"
                            :href="editFixture([league.slug, season.id, stage.id, game.id]).url"
                            class="text-xs font-medium text-muted-foreground hover:text-foreground hover:underline"
                        >
                            Edit
                        </Link>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
