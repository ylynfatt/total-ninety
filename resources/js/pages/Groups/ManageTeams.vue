<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { sync as syncTeams } from '@/routes/groups/teams';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { show as seasonShow } from '@/routes/seasons';
import { edit as seasonTeamsEdit } from '@/routes/seasons/teams';
import { show as stageShow } from '@/routes/stages';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    id: number;
    name: string;
    slug?: string;
}

interface Team {
    id: number;
    name: string;
    acronym: string;
}

const props = defineProps<{
    league: Summary & { slug: string };
    season: Summary;
    stage: Summary;
    group: Summary;
    teams: Team[];
    attached_team_ids: number[];
}>();

const form = useForm({
    team_ids: [...props.attached_team_ids],
});

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Leagues', href: leaguesIndex().url },
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: seasonShow([props.league.slug, props.season.id]).url },
    { title: props.stage.name, href: stageShow([props.league.slug, props.season.id, props.stage.id]).url },
    { title: props.group.name, href: '#' },
    { title: 'Manage teams', href: '#' },
]);

function toggle(teamId: number, checked: boolean) {
    if (checked) {
        if (!form.team_ids.includes(teamId)) {
            form.team_ids = [...form.team_ids, teamId];
        }
    } else {
        form.team_ids = form.team_ids.filter((id) => id !== teamId);
    }
}

function submit() {
    form.put(syncTeams([props.league.slug, props.season.id, props.stage.id, props.group.id]).url);
}
</script>

<template>
    <Head :title="`${group.name} — manage teams`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">{{ group.name }} — teams</h1>
            <p class="text-sm text-muted-foreground">
                Pick which of the season's teams play in this group. Only teams already in
                <Link :href="seasonShow([league.slug, season.id]).url" class="font-medium text-foreground underline underline-offset-2">{{ season.name }}</Link>
                are eligible.
            </p>
        </header>

        <Card v-if="teams.length === 0">
            <CardHeader>
                <CardTitle class="text-base">No season teams yet</CardTitle>
                <CardDescription>
                    Add teams to the season first via
                    <Link :href="seasonTeamsEdit([league.slug, season.id]).url" class="underline underline-offset-2">Manage teams</Link>,
                    then come back to assign them to this group.
                </CardDescription>
            </CardHeader>
        </Card>

        <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-2 rounded-md border p-4">
                <p class="mb-2 text-sm font-medium">
                    Selected:
                    <span class="font-normal text-muted-foreground">{{ form.team_ids.length }} of {{ teams.length }}</span>
                </p>
                <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <li v-for="team in teams" :key="team.id" class="flex items-center gap-2 rounded-md px-2 py-1.5 transition hover:bg-muted/40">
                        <Checkbox
                            :id="`team-${team.id}`"
                            :model-value="form.team_ids.includes(team.id)"
                            @update:model-value="(value) => toggle(team.id, !!value)"
                        />
                        <label :for="`team-${team.id}`" class="flex flex-1 cursor-pointer items-center gap-2 text-sm">
                            <span class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">{{ team.acronym }}</span>
                            <span>{{ team.name }}</span>
                        </label>
                    </li>
                </ul>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Update roster</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="stageShow([league.slug, season.id, stage.id]).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
