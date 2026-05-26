<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { show as leagueShow } from '@/routes/leagues';
import { show as seasonShow } from '@/routes/seasons';
import { destroy, edit, index } from '@/routes/teams';
import type { BreadcrumbItem } from '@/types';

interface LeagueSummary {
    id: number;
    name: string;
    slug: string;
}

interface SeasonEntry {
    id: number;
    name: string;
    league: LeagueSummary;
}

interface Team {
    id: number;
    name: string;
    acronym: string;
    home_ground: string | null;
    year_founded: number;
    seasons: SeasonEntry[];
}

const props = defineProps<{
    team: Team;
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Teams', href: index().url },
    { title: props.team.name, href: '#' },
]);

function deleteTeam() {
    if (!confirm(`Delete team "${props.team.name}"? This cannot be undone.`)) {
        return;
    }
    router.delete(destroy(props.team.id).url);
}
</script>

<template>
    <Head :title="team.name" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-muted px-2 py-1 font-mono text-sm text-muted-foreground">{{ team.acronym }}</span>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ team.name }}</h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Founded {{ team.year_founded }}<span v-if="team.home_ground"> · {{ team.home_ground }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(team.id).url">Edit</Link>
                </Button>
                <Button v-if="can.delete" variant="destructive" @click="deleteTeam">Delete</Button>
            </div>
        </header>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Seasons</CardTitle>
                <CardDescription>
                    {{ team.seasons.length }} season{{ team.seasons.length === 1 ? '' : 's' }}
                    <span v-if="team.seasons.length === 0">— this team isn't attached to any season yet.</span>
                </CardDescription>
            </CardHeader>
            <CardContent v-if="team.seasons.length > 0">
                <ul class="divide-y rounded-md border">
                    <li v-for="season in team.seasons" :key="season.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                        <Link :href="seasonShow([season.league.slug, season.id]).url" class="font-medium hover:underline">
                            {{ season.name }}
                        </Link>
                        <Link :href="leagueShow(season.league.slug).url" class="text-muted-foreground hover:underline">
                            {{ season.league.name }}
                        </Link>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
