<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

interface Game {
    id: number;
    home_team_id: number;
    away_team_id: number;
    home_team: TeamSummary | null;
    away_team: TeamSummary | null;
    match_date: string | null;
    location: string | null;
    group_id: number | null;
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
    groups: Group[];
    games: Game[];
}

const props = defineProps<{
    league: LeagueSummary;
    season: SeasonSummary;
    stage: Stage;
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

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
                        <span class="text-xs text-muted-foreground">vs</span>
                        <span class="flex-1">{{ game.away_team?.name ?? '—' }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ game.match_date ?? 'TBD' }}
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
