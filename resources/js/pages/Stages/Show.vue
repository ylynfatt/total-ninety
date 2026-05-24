<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { show as seasonShow } from '@/routes/seasons';
import { destroy, edit as stageEdit, generateFixtures } from '@/routes/stages';

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
</script>

<template>
    <Head :title="`${stage.name} — ${season.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">
                    <Link :href="leagueShow(league.slug).url" class="hover:underline">{{ league.name }}</Link>
                    <span> / </span>
                    <Link :href="seasonShow([league.slug, season.id]).url" class="hover:underline">{{ season.name }}</Link>
                </p>
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

        <!-- Groups note for grouped formats -->
        <Card v-if="hasGroupedFormat && stage.groups.length === 0">
            <CardHeader>
                <CardTitle class="text-base">Groups required</CardTitle>
                <CardDescription>
                    This format needs groups (or conferences) with teams attached before fixtures can be generated. The Groups UI is coming in the next update.
                </CardDescription>
            </CardHeader>
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
