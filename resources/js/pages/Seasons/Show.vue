<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { destroy, edit as seasonEdit } from '@/routes/seasons';
import { edit as editTeams } from '@/routes/seasons/teams';
import { create as createStage, show as stageShow } from '@/routes/stages';

interface LeagueSummary {
    id: number;
    name: string;
    slug: string;
}

interface Team {
    id: number;
    name: string;
    acronym: string;
}

interface Stage {
    id: number;
    name: string;
    format: string;
    order: number;
}

interface Season {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string | null;
    is_active: boolean;
    teams: Team[];
    stages: Stage[];
}

const props = defineProps<{
    league: LeagueSummary;
    season: Season;
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

function deleteSeason() {
    if (!confirm(`Delete season "${props.season.name}"? All stages and games inside it will be deleted too.`)) {
        return;
    }
    router.delete(destroy([props.league.slug, props.season.id]).url);
}
</script>

<template>
    <Head :title="`${season.name} — ${league.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted-foreground">
                    <Link :href="leagueShow(league.slug).url" class="hover:underline">{{ league.name }}</Link>
                </p>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ season.name }}</h1>
                    <Badge v-if="season.is_active">Active</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ season.starts_on }}<span v-if="season.ends_on"> &mdash; {{ season.ends_on }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="seasonEdit([league.slug, season.id]).url">Edit</Link>
                </Button>
                <Button v-if="can.delete" variant="destructive" @click="deleteSeason">Delete</Button>
            </div>
        </header>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <CardTitle class="text-base">Teams</CardTitle>
                        <CardDescription>{{ season.teams.length }} attached</CardDescription>
                    </div>
                    <Button v-if="can.update" variant="outline" size="sm" as-child>
                        <Link :href="editTeams([league.slug, season.id]).url">Manage teams</Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="season.teams.length === 0" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No teams attached yet.
                </div>
                <ul v-else class="flex flex-wrap gap-2">
                    <li v-for="team in season.teams" :key="team.id" class="rounded-md border bg-muted/40 px-3 py-1 text-sm">
                        <span class="font-mono text-xs text-muted-foreground">{{ team.acronym }}</span>
                        <span class="ml-2">{{ team.name }}</span>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Stages</h2>
                <Button v-if="can.update" size="sm" as-child>
                    <Link :href="createStage([league.slug, season.id]).url">Create stage</Link>
                </Button>
            </div>
            <div v-if="season.stages.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                No stages defined yet.
                <Link v-if="can.update" :href="createStage([league.slug, season.id]).url" class="font-medium text-foreground underline underline-offset-4">
                    Add the first one.
                </Link>
            </div>
            <div v-else class="grid gap-3 sm:grid-cols-2">
                <Link
                    v-for="stage in season.stages"
                    :key="stage.id"
                    :href="stageShow([league.slug, season.id, stage.id]).url"
                    class="block transition hover:opacity-90"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">{{ stage.name }}</CardTitle>
                            <CardDescription>{{ stage.format.replace(/_/g, ' ') }}</CardDescription>
                        </CardHeader>
                    </Card>
                </Link>
            </div>
        </section>
    </div>
</template>
