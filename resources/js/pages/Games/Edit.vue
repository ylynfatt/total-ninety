<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy as destroyResult, store as storeResult } from '@/routes/fixtures/result';
import { update as updateSchedule } from '@/routes/fixtures/schedule';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { show as seasonShow } from '@/routes/seasons';
import { show as stageShow } from '@/routes/stages';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    id: number;
    name: string;
    slug?: string;
}

interface TeamSummary {
    id: number;
    name: string;
    acronym: string;
}

interface GroupSummary {
    id: number;
    name: string;
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
    group: GroupSummary | null;
    result: ResultRow | null;
}

const props = defineProps<{
    league: Summary & { slug: string };
    season: Summary;
    stage: Summary & { format: string };
    game: Game;
}>();

const scheduleForm = useForm({
    match_date: props.game.match_date ?? '',
    location: props.game.location ?? '',
});

const resultForm = useForm({
    home_team_score: props.game.result?.home_team_score ?? 0,
    away_team_score: props.game.result?.away_team_score ?? 0,
});

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Leagues', href: leaguesIndex().url },
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: seasonShow([props.league.slug, props.season.id]).url },
    { title: props.stage.name, href: stageShow([props.league.slug, props.season.id, props.stage.id]).url },
    { title: 'Edit fixture', href: '#' },
]);

const hasResult = computed(() => props.game.result !== null);

const matchTitle = computed(() => {
    const home = props.game.home_team?.name ?? 'Home';
    const away = props.game.away_team?.name ?? 'Away';

    return `${home} vs ${away}`;
});

// match_date is a Carbon-serialized datetime — slice to YYYY-MM-DD for the date input.
function isoDate(value: string | null | undefined): string {
    if (!value) {
        return '';
    }
    return value.length >= 10 ? value.slice(0, 10) : value;
}

scheduleForm.match_date = isoDate(props.game.match_date);

function submitSchedule() {
    scheduleForm.patch(updateSchedule([props.league.slug, props.season.id, props.stage.id, props.game.id]).url, {
        preserveScroll: true,
    });
}

function submitResult() {
    resultForm.put(storeResult([props.league.slug, props.season.id, props.stage.id, props.game.id]).url, {
        preserveScroll: true,
    });
}

function clearResult() {
    if (!confirm('Clear the recorded result for this fixture?')) {
        return;
    }
    router.delete(
        destroyResult([props.league.slug, props.season.id, props.stage.id, props.game.id]).url,
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Edit fixture — ${matchTitle}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <p class="text-xs uppercase tracking-wide text-muted-foreground">
                Fixture #{{ game.id }}
                <span v-if="game.group"> · {{ game.group.name }}</span>
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">{{ matchTitle }}</h1>
        </header>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Schedule</CardTitle>
                <CardDescription>Set when and where the match is played.</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="flex flex-col gap-4" @submit.prevent="submitSchedule">
                    <div class="grid gap-2">
                        <Label for="match_date">Match date</Label>
                        <Input id="match_date" type="date" v-model="scheduleForm.match_date" />
                        <InputError :message="scheduleForm.errors.match_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="location">Location</Label>
                        <Input id="location" v-model="scheduleForm.location" placeholder="Stadium or venue" />
                        <InputError :message="scheduleForm.errors.location" />
                    </div>
                    <div>
                        <Button type="submit" :disabled="scheduleForm.processing">Save schedule</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <CardTitle class="text-base">Result</CardTitle>
                        <CardDescription>
                            {{ hasResult ? 'Edit or clear the recorded result.' : 'Record the final score once the match is played.' }}
                        </CardDescription>
                    </div>
                    <Button v-if="hasResult" variant="ghost" class="text-destructive" @click="clearResult">
                        Clear result
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <form class="flex flex-col gap-4" @submit.prevent="submitResult">
                    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-[1fr_auto_1fr]">
                        <div class="grid gap-2">
                            <Label for="home_team_score">{{ game.home_team?.name ?? 'Home' }}</Label>
                            <Input id="home_team_score" type="number" min="0" max="999" v-model="resultForm.home_team_score" />
                            <InputError :message="resultForm.errors.home_team_score" />
                        </div>
                        <div class="pb-2 text-center text-sm text-muted-foreground">vs</div>
                        <div class="grid gap-2">
                            <Label for="away_team_score">{{ game.away_team?.name ?? 'Away' }}</Label>
                            <Input id="away_team_score" type="number" min="0" max="999" v-model="resultForm.away_team_score" />
                            <InputError :message="resultForm.errors.away_team_score" />
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="resultForm.processing">
                            {{ hasResult ? 'Update result' : 'Record result' }}
                        </Button>
                        <Button type="button" variant="ghost" as-child>
                            <Link :href="stageShow([league.slug, season.id, stage.id]).url">Back to stage</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
