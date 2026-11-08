<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { create as createLeague, index as leaguesIndex } from '@/routes/leagues';

interface Match {
    id: number;
    homeTeam: string;
    awayTeam: string;
    homeScore: number | null;
    awayScore: number | null;
    status: string;
    statusLabel: string;
    kickoff: string | null;
    location: string | null;
    context: string;
    viewUrl: string;
    manageUrl: string;
}

interface AttentionGroup {
    key: string;
    title: string;
    description: string;
    count: number;
    items: { id: number; title: string; context: string; href: string; action: string }[];
}

interface Season {
    id: number;
    name: string;
    href: string;
    games: number;
    completed: number;
    nextFixture: { homeTeam: string; awayTeam: string; kickoff: string } | null;
}

interface League {
    id: number;
    name: string;
    isPublic: boolean;
    href: string;
    createSeasonUrl: string;
    seasons: Season[];
}

const props = defineProps<{
    dateLabel: string;
    timezone: string;
    summary: { live: number; today: number; attention: number };
    matchday: { live: Match[]; upcoming: Match[]; finished: Match[]; other: Match[] };
    attention: AttentionGroup[];
    leagues: League[];
}>();

const summaryCards = computed(() => [
    { label: 'In progress', count: props.summary.live, href: '#matchday', description: 'Live and half-time matches in your leagues' },
    { label: 'Fixtures today', count: props.summary.today, href: '#matchday', description: 'All matches scheduled for today' },
    { label: 'Needs attention', count: props.summary.attention, href: '#attention', description: 'Tasks across your active seasons' },
]);

const matchGroups = computed(() => [
    { key: 'live', title: 'In progress', description: 'Includes matches still in progress from earlier dates.', matches: props.matchday.live },
    { key: 'upcoming', title: 'Scheduled today', description: 'Ready for kickoff or awaiting a status update.', matches: props.matchday.upcoming },
    { key: 'finished', title: 'Finished today', description: 'Full-time scores from today’s fixtures.', matches: props.matchday.finished },
    { key: 'other', title: 'Postponed or cancelled', description: 'Changes to today’s schedule.', matches: props.matchday.other },
].filter((group) => group.matches.length > 0));

const attentionGroups = computed(() => props.attention.filter((group) => group.count > 0));

function kickoffLabel(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: props.timezone,
    }).format(new Date(value));
}

function progress(season: Season): number {
    return season.games === 0 ? 0 : Math.round(season.completed / season.games * 100);
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Your matchday</p>
                <h1 class="mt-1 font-display text-3xl font-bold uppercase tracking-wide">Matchday overview</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ dateLabel }} · Times in {{ timezone }}</p>
                <p class="mt-1 text-sm text-muted-foreground">Matches and next steps for the leagues you manage.</p>
            </div>
            <div v-if="leagues.length > 0" class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="dashboard()" preserve-scroll>Refresh overview</Link>
                </Button>
                <Button as-child>
                    <Link :href="createLeague()">Create league</Link>
                </Button>
            </div>
        </header>

        <Card v-if="leagues.length === 0" class="border-dashed">
            <CardHeader class="gap-3">
                <CardTitle class="text-xl">Your first matchday starts here</CardTitle>
                <CardDescription class="max-w-xl">
                    Create a league, add a season, then set up stages and fixtures. Your matches and next steps will appear here.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-3">
                <Button as-child><Link :href="createLeague()">Create a league</Link></Button>
                <Button variant="outline" as-child><Link :href="leaguesIndex()">Browse leagues</Link></Button>
            </CardContent>
        </Card>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-3" aria-label="Matchday summary">
                <a
                    v-for="item in summaryCards"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <p class="text-sm font-medium text-muted-foreground">{{ item.label }}</p>
                    <p class="mt-2 font-display text-4xl font-bold tabular-nums">{{ item.count }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">{{ item.description }}</p>
                </a>
            </div>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <Card id="matchday" class="min-w-0 scroll-mt-6">
                    <CardHeader>
                        <CardTitle>Today’s matches</CardTitle>
                        <CardDescription>Today’s schedule, plus any matches still in progress. Refresh for the latest overview.</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-6">
                        <div v-if="matchGroups.length === 0" class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            No matches today and none in progress. Your active seasons and outstanding tasks are below.
                        </div>
                        <section v-for="group in matchGroups" :key="group.key" class="flex flex-col gap-3" :aria-labelledby="`matches-${group.key}`">
                            <div>
                                <h2 :id="`matches-${group.key}`" class="text-sm font-semibold">{{ group.title }} <span class="text-muted-foreground">({{ group.matches.length }})</span></h2>
                                <p class="mt-1 text-xs text-muted-foreground">{{ group.description }}</p>
                            </div>
                            <article v-for="match in group.matches" :key="match.id" class="flex flex-col gap-3 rounded-lg border p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="min-w-0 break-words text-xs text-muted-foreground">{{ match.context }}</p>
                                    <Badge :variant="group.key === 'live' ? 'default' : 'secondary'">{{ match.statusLabel }}</Badge>
                                </div>
                                <Link :href="match.viewUrl" class="flex items-center justify-between gap-4 rounded-sm text-sm font-semibold hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    <span class="min-w-0 break-words">{{ match.homeTeam }} <span class="font-normal text-muted-foreground">vs</span> {{ match.awayTeam }}</span>
                                    <span class="shrink-0 font-display text-2xl tabular-nums">{{ match.homeScore ?? '–' }} : {{ match.awayScore ?? '–' }}</span>
                                </Link>
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-xs text-muted-foreground">
                                        <time v-if="match.kickoff" :datetime="match.kickoff">{{ kickoffLabel(match.kickoff) }}</time>
                                        <span v-else>Not scheduled</span>
                                        <span v-if="match.location"> · {{ match.location }}</span>
                                    </p>
                                    <div class="flex flex-wrap gap-3 text-xs font-medium">
                                        <Link :href="match.viewUrl" class="underline underline-offset-4">View gamecast</Link>
                                        <Link :href="match.manageUrl" class="underline underline-offset-4">{{ group.key === 'finished' ? 'Review result' : 'Manage match' }}</Link>
                                    </div>
                                </div>
                            </article>
                        </section>
                    </CardContent>
                </Card>

                <Card id="attention" class="min-w-0 scroll-mt-6">
                    <CardHeader>
                        <CardTitle>Needs attention <span class="text-muted-foreground">({{ summary.attention }})</span></CardTitle>
                        <CardDescription>Next steps for your active seasons.</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-6">
                        <div v-if="attentionGroups.length === 0" class="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                            You’re all caught up. No overdue matches or fixture setup tasks in your active seasons.
                        </div>
                        <section v-for="group in attentionGroups" :key="group.key" class="flex flex-col gap-3" :aria-labelledby="`attention-${group.key}`">
                            <div>
                                <h2 :id="`attention-${group.key}`" class="text-sm font-semibold">{{ group.title }} ({{ group.count }})</h2>
                                <p class="mt-1 text-xs text-muted-foreground">{{ group.description }}</p>
                            </div>
                            <ul class="divide-y rounded-lg border px-4">
                                <li v-for="item in group.items" :key="item.id" class="flex flex-col gap-1 py-3">
                                    <p class="break-words text-sm font-medium">{{ item.title }}</p>
                                    <p class="break-words text-xs text-muted-foreground">{{ item.context }}</p>
                                    <Link :href="item.href" class="mt-1 self-start text-xs font-medium underline underline-offset-4">{{ item.action }}</Link>
                                </li>
                            </ul>
                            <p v-if="group.count > group.items.length" class="text-xs text-muted-foreground">Showing {{ group.items.length }} of {{ group.count }}. Resolve these to see the next tasks.</p>
                        </section>
                    </CardContent>
                </Card>
            </div>

            <section aria-labelledby="my-leagues" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="my-leagues" class="text-xl font-semibold">My leagues</h2>
                    <Link :href="leaguesIndex()" class="text-sm font-medium underline underline-offset-4">Browse leagues</Link>
                </div>
                <div class="grid items-start gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    <Card v-for="league in leagues" :key="league.id" class="min-w-0">
                        <CardHeader>
                            <div class="flex items-start justify-between gap-3">
                                <CardTitle class="break-words text-base"><Link :href="league.href" class="hover:underline">{{ league.name }}</Link></CardTitle>
                                <Badge variant="outline">{{ league.isPublic ? 'Public' : 'Private' }}</Badge>
                            </div>
                            <CardDescription>{{ league.seasons.length }} active {{ league.seasons.length === 1 ? 'season' : 'seasons' }}</CardDescription>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-5">
                            <div v-if="league.seasons.length === 0" class="flex flex-col gap-2 text-sm">
                                <p class="text-muted-foreground">No active season. Open the league to activate one, or start a new season.</p>
                                <Link :href="league.createSeasonUrl" class="self-start font-medium underline underline-offset-4">Add a season</Link>
                            </div>
                            <div v-for="season in league.seasons" :key="season.id" class="flex flex-col gap-2">
                                <Link :href="season.href" class="break-words text-sm font-semibold hover:underline">{{ season.name }}</Link>
                                <p class="text-xs text-muted-foreground">{{ season.completed }} of {{ season.games }} matches finished</p>
                                <div role="progressbar" :aria-label="`${season.name} match completion`" :aria-valuenow="progress(season)" :aria-valuemin="0" :aria-valuemax="100" class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary" :style="{ width: `${progress(season)}%` }" />
                                </div>
                                <div v-if="season.nextFixture" class="mt-1 text-xs">
                                    <p class="text-muted-foreground">Next fixture · {{ kickoffLabel(season.nextFixture.kickoff) }}</p>
                                    <p class="mt-1 break-words">{{ season.nextFixture.homeTeam }} vs {{ season.nextFixture.awayTeam }}</p>
                                </div>
                                <p v-else class="mt-1 text-xs text-muted-foreground">No upcoming fixture scheduled.</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </template>
    </div>
</template>
