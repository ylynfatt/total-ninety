<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DashboardEmptyState from '@/components/DashboardEmptyState.vue';
import DashboardSkeleton from '@/components/DashboardSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
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

interface Overview {
    summary: { live: number; today: number; attention: number };
    matchday: { live: Match[]; upcoming: Match[]; finished: Match[]; other: Match[] };
    attention: AttentionGroup[];
    leagues: League[];
}

const props = defineProps<{
    dateLabel: string;
    timezone: string;
    overview?: Overview;
}>();

const isLoading = ref(false);
const loadError = ref<string | null>(null);
const leagues = computed(() => props.overview?.leagues ?? []);
const summary = computed(() => props.overview?.summary);
const hasActiveSeasons = computed(() => leagues.value.some((league) => league.seasons.length > 0));
let cancelPending: (() => void) | null = null;

function loadOverview(): void {
    if (isLoading.value) {
        return;
    }

    isLoading.value = true;
    loadError.value = null;
    router.reload({
        only: ['overview', 'dateLabel', 'timezone'],
        onCancelToken: (token) => {
            cancelPending = () => token.cancel();
        },
        onSuccess: (page) => {
            if (!page.props.overview) {
                loadError.value = 'The overview could not be loaded. Please try again.';
            }
        },
        onError: () => {
            loadError.value = 'The overview could not be loaded. Please try again.';
        },
        onHttpException: () => {
            loadError.value = 'The dashboard is temporarily unavailable. Please try again.';

            return false;
        },
        onNetworkError: () => {
            loadError.value = 'We couldn’t connect. Check your connection and try again.';

            return false;
        },
        onFinish: () => {
            isLoading.value = false;
            cancelPending = null;
        },
    });
}

onMounted(() => {
    if (!props.overview) {
        loadOverview();
    }
});

onBeforeUnmount(() => cancelPending?.());

const summaryCards = computed(() => [
    { label: 'In progress', count: props.overview?.summary.live ?? 0, href: '#matchday', description: 'Live and half-time matches in your leagues' },
    { label: 'Fixtures today', count: props.overview?.summary.today ?? 0, href: '#matchday', description: 'All matches scheduled for today' },
    { label: 'Needs attention', count: props.overview?.summary.attention ?? 0, href: '#attention', description: 'Tasks across your active seasons' },
]);

const matchGroups = computed(() => [
    { key: 'live', title: 'In progress', description: 'Includes matches still in progress from earlier dates.', matches: props.overview?.matchday.live ?? [] },
    { key: 'upcoming', title: 'Scheduled today', description: 'Ready for kickoff or awaiting a status update.', matches: props.overview?.matchday.upcoming ?? [] },
    { key: 'finished', title: 'Finished today', description: 'Full-time scores from today’s fixtures.', matches: props.overview?.matchday.finished ?? [] },
    { key: 'other', title: 'Postponed or cancelled', description: 'Changes to today’s schedule.', matches: props.overview?.matchday.other ?? [] },
].filter((group) => group.matches.length > 0));

const attentionGroups = computed(() => (props.overview?.attention ?? []).filter((group) => group.count > 0));

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

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6" :aria-busy="isLoading">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Your matchday</p>
                <h1 class="mt-1 font-display text-3xl font-bold uppercase tracking-wide">Matchday overview</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ dateLabel }} · Times in {{ timezone }}</p>
                <p class="mt-1 text-sm text-muted-foreground">Matches and next steps for the leagues you manage.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" :disabled="isLoading" @click="loadOverview">
                    <Spinner v-if="isLoading" aria-hidden="true" />
                    {{ isLoading ? (overview ? 'Refreshing…' : 'Loading…') : 'Refresh overview' }}
                </Button>
                <Button as-child>
                    <Link :href="createLeague()">Create league</Link>
                </Button>
            </div>
        </header>

        <p v-if="isLoading && overview" role="status" class="text-sm text-muted-foreground">Refreshing your overview. Your previous data remains visible.</p>

        <Card v-if="loadError" role="alert" class="border-destructive/50">
            <CardHeader>
                <CardTitle>{{ overview ? 'Couldn’t refresh the overview' : 'Couldn’t load the overview' }}</CardTitle>
                <CardDescription>{{ loadError }}</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center gap-3">
                <Button :disabled="isLoading" @click="loadOverview">Try again</Button>
                <p v-if="overview" class="text-sm text-muted-foreground">Showing the last successful overview. It may be out of date.</p>
            </CardContent>
        </Card>

        <DashboardSkeleton v-if="!overview && !loadError" />

        <template v-if="overview && summary">
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
                        <DashboardEmptyState
                            v-if="matchGroups.length === 0"
                            :title="leagues.length === 0 ? 'Your first kickoff is ahead' : 'A quiet matchday'"
                            :description="leagues.length === 0 ? 'Create a league and set up its season to bring your fixtures here.' : 'No matches today and none in progress. Open a season to schedule your next fixture.'"
                            class="min-h-48 justify-center"
                        >
                            <Button v-if="leagues.length === 0" as-child class="w-full sm:w-auto"><Link :href="createLeague()">Create a league</Link></Button>
                            <Button v-else variant="outline" as-child class="w-full sm:w-auto"><a href="#my-leagues">Go to my leagues</a></Button>
                        </DashboardEmptyState>
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
                        <DashboardEmptyState
                            v-if="attentionGroups.length === 0 && !hasActiveSeasons"
                            title="Ready when you are"
                            description="Setup reminders will appear here once you have an active season. Start with a league, then add or activate a season."
                            class="min-h-48 justify-center"
                        >
                            <Button variant="outline" as-child class="w-full sm:w-auto"><a href="#my-leagues">{{ leagues.length === 0 ? 'Get started below' : 'Open my leagues' }}</a></Button>
                        </DashboardEmptyState>
                        <DashboardEmptyState
                            v-else-if="attentionGroups.length === 0"
                            title="You’re all caught up"
                            description="No overdue matches or fixture setup tasks in your active seasons."
                            class="min-h-48 justify-center"
                        />
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
                    <Card v-if="leagues.length === 0" class="min-w-0">
                        <CardHeader>
                            <CardTitle class="text-base">Your first matchday starts here</CardTitle>
                            <CardDescription>No leagues yet</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DashboardEmptyState
                                title="Create your first league"
                                description="Add a season, set up stages, then generate fixtures. Your matches and next steps will appear in this overview."
                            >
                                <Button as-child class="w-full sm:w-auto"><Link :href="createLeague()">Create a league</Link></Button>
                                <Button variant="outline" as-child class="w-full sm:w-auto"><Link :href="leaguesIndex()">Browse leagues</Link></Button>
                            </DashboardEmptyState>
                        </CardContent>
                    </Card>
                    <Card v-for="league in leagues" :key="league.id" class="min-w-0">
                        <CardHeader>
                            <div class="flex items-start justify-between gap-3">
                                <CardTitle class="break-words text-base"><Link :href="league.href" class="hover:underline">{{ league.name }}</Link></CardTitle>
                                <Badge variant="outline">{{ league.isPublic ? 'Public' : 'Private' }}</Badge>
                            </div>
                            <CardDescription>{{ league.seasons.length }} active {{ league.seasons.length === 1 ? 'season' : 'seasons' }}</CardDescription>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-5">
                            <DashboardEmptyState
                                v-if="league.seasons.length === 0"
                                title="No active season"
                                description="Activate an existing season or start a new one to see its progress here."
                                compact
                            >
                                <Button variant="outline" size="sm" as-child class="w-full sm:w-auto"><Link :href="league.href">Open league</Link></Button>
                                <Button size="sm" as-child class="w-full sm:w-auto"><Link :href="league.createSeasonUrl">Add a season</Link></Button>
                            </DashboardEmptyState>
                            <div v-for="season in league.seasons" :key="season.id" class="flex flex-col gap-2">
                                <Link :href="season.href" class="break-words text-sm font-semibold hover:underline">{{ season.name }}</Link>
                                <DashboardEmptyState
                                    v-if="season.games === 0"
                                    title="No fixtures yet"
                                    description="Set up stages and generate this season’s schedule."
                                    compact
                                >
                                    <Button variant="outline" size="sm" as-child class="w-full sm:w-auto"><Link :href="season.href">Set up fixtures</Link></Button>
                                </DashboardEmptyState>
                                <p v-else class="text-xs text-muted-foreground">{{ season.completed }} of {{ season.games }} matches finished</p>
                                <div v-if="season.games > 0" role="progressbar" :aria-label="`${season.name} match completion`" :aria-valuenow="progress(season)" :aria-valuemin="0" :aria-valuemax="100" class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary" :style="{ width: `${progress(season)}%` }" />
                                </div>
                                <div v-if="season.nextFixture" class="mt-1 text-xs">
                                    <p class="text-muted-foreground">Next fixture · {{ kickoffLabel(season.nextFixture.kickoff) }}</p>
                                    <p class="mt-1 break-words">{{ season.nextFixture.homeTeam }} vs {{ season.nextFixture.awayTeam }}</p>
                                </div>
                                <DashboardEmptyState
                                    v-else-if="season.games > 0"
                                    title="No upcoming fixture scheduled"
                                    description="Review the season’s schedule to see results or assign the next kickoff."
                                    compact
                                >
                                    <Button variant="outline" size="sm" as-child class="w-full sm:w-auto"><Link :href="season.href">Review schedule</Link></Button>
                                </DashboardEmptyState>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </template>
    </div>
</template>
