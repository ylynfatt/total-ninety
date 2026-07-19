<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { show as seasonShow } from '@/routes/seasons';
import { show as stageShow, update } from '@/routes/stages';
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

interface Stage {
    id: number;
    name: string;
    format: string;
    order: number;
    starts_on: string | null;
    ends_on: string | null;
    advances_count: number | null;
    config: Record<string, unknown> | null;
}

interface FormatOption {
    value: string;
    label: string;
}

interface SourceStage {
    id: number;
    name: string;
    advances_count: number;
    best_placed_count: number;
    groups: { id: number; name: string }[];
}

type Entrant = { type: 'group'; group: string; position: number } | { type: 'best_placed'; rank: number };

const props = defineProps<{
    league: LeagueSummary;
    season: SeasonSummary;
    stage: Stage;
    formats: FormatOption[];
    sourceStage: SourceStage | null;
}>();

const currentLegs = (props.stage.config?.legs_per_group as 1 | 2 | undefined) ?? 1;
const currentBestPlaced = (props.stage.config?.best_placed_count as number | undefined) ?? null;

const form = useForm({
    name: props.stage.name,
    order: props.stage.order,
    starts_on: props.stage.starts_on ?? '',
    ends_on: props.stage.ends_on ?? '',
    advances_count: props.stage.advances_count,
    config: {
        legs_per_group: currentLegs,
        best_placed_count: currentBestPlaced,
        entrants: (props.stage.config?.entrants as Entrant[] | undefined) ?? null,
    },
});

const hasGroupedFormat = ['group_stage', 'conference'].includes(props.stage.format);
const isBracketFormat = ['single_elimination', 'double_elimination'].includes(props.stage.format);

/*
 * Knockout entrant builder. Slots are edited as string tokens
 * ("group|Group A|1" / "best|3") so a <Select> can hold them, and mirrored
 * into form.config.entrants as descriptor objects on every change.
 */
function tokenOf(entrant: Entrant): string {
    return entrant.type === 'group' ? `group|${entrant.group}|${entrant.position}` : `best|${entrant.rank}`;
}

function entrantOf(token: string): Entrant {
    const parts = token.split('|');

    return parts[0] === 'best'
        ? { type: 'best_placed', rank: Number(parts[1]) }
        : { type: 'group', group: parts[1], position: Number(parts[2]) };
}

function positionLabel(position: number, group: string): string {
    if (position === 1) return `Winner ${group}`;
    if (position === 2) return `Runner-up ${group}`;

    const suffix = position % 10 === 3 && position % 100 !== 13 ? 'rd' : 'th';

    return `${position}${suffix} ${group}`;
}

const slotOptions = computed<{ value: string; label: string }[]>(() => {
    if (!props.sourceStage) return [];

    const options: { value: string; label: string }[] = [];

    for (const group of props.sourceStage.groups) {
        for (let position = 1; position <= props.sourceStage.advances_count; position++) {
            options.push({ value: `group|${group.name}|${position}`, label: positionLabel(position, group.name) });
        }
    }

    for (let rank = 1; rank <= props.sourceStage.best_placed_count; rank++) {
        options.push({ value: `best|${rank}`, label: `Best-placed #${rank}` });
    }

    return options;
});

const qualifierTotal = computed(() => {
    if (!props.sourceStage) return 0;

    return props.sourceStage.groups.length * props.sourceStage.advances_count + props.sourceStage.best_placed_count;
});

const qualifierTotalIsPowerOfTwo = computed(() => qualifierTotal.value >= 2 && (qualifierTotal.value & (qualifierTotal.value - 1)) === 0);

const entrantTokens = ref<string[]>(((props.stage.config?.entrants as Entrant[] | undefined) ?? []).map(tokenOf));

watch(
    entrantTokens,
    (tokens) => {
        form.config.entrants = tokens.length > 0 ? tokens.map(entrantOf) : null;
    },
    { deep: true },
);

/**
 * Classic template for even group counts with exactly 2 qualifiers and no
 * best-placed spots: 1A v 2B, 1C v 2D, …, then 1B v 2A, 1D v 2C, … so
 * group-mates land in opposite bracket halves.
 */
const classicTemplateAvailable = computed(() => {
    const src = props.sourceStage;

    return !!src && src.best_placed_count === 0 && src.advances_count === 2 && src.groups.length >= 2 && src.groups.length % 2 === 0 && qualifierTotalIsPowerOfTwo.value;
});

function applyClassicTemplate(): void {
    const groups = props.sourceStage!.groups.map((g) => g.name);
    const firstHalf: string[] = [];
    const secondHalf: string[] = [];

    for (let i = 0; i < groups.length; i += 2) {
        firstHalf.push(`group|${groups[i]}|1`, `group|${groups[i + 1]}|2`);
        secondHalf.push(`group|${groups[i + 1]}|1`, `group|${groups[i]}|2`);
    }

    entrantTokens.value = [...firstHalf, ...secondHalf];
}

/**
 * Seeded template for any qualifier shape totalling a power of two: group
 * winners are the strongest seeds (in group order), then runners-up, then
 * best-placed teams — laid out with standard bracket seeding so seeds 1
 * and 2 can only meet in the final.
 */
function applySeededTemplate(): void {
    const src = props.sourceStage!;
    const seedList: string[] = [];

    for (let position = 1; position <= src.advances_count; position++) {
        for (const group of src.groups) {
            seedList.push(`group|${group.name}|${position}`);
        }
    }

    for (let rank = 1; rank <= src.best_placed_count; rank++) {
        seedList.push(`best|${rank}`);
    }

    const rounds = Math.log2(seedList.length);
    let seeds = [1, 2];

    for (let round = 2; round <= rounds; round++) {
        const size = 2 ** round;
        const next: number[] = [];

        for (const seed of seeds) {
            next.push(seed, size + 1 - seed);
        }

        seeds = next;
    }

    entrantTokens.value = seeds.map((seed) => seedList[seed - 1]);
}

function clearEntrants(): void {
    entrantTokens.value = [];
}

const formatLabel = props.formats.find((f) => f.value === props.stage.format)?.label ?? props.stage.format;

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Leagues', href: leaguesIndex().url },
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: seasonShow([props.league.slug, props.season.id]).url },
    { title: props.stage.name, href: stageShow([props.league.slug, props.season.id, props.stage.id]).url },
    { title: 'Edit', href: '#' },
]);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: leaguesIndex().url },
        ],
    },
});

function submit() {
    form.put(update([props.league.slug, props.season.id, props.stage.id]).url);
}
</script>

<template>
    <Head :title="`Edit ${stage.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Edit stage</h1>
            <p class="text-sm text-muted-foreground">
                Format: <span class="font-medium text-foreground">{{ formatLabel }}</span> (locked after creation)
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="starts_on">Starts on (optional)</Label>
                    <Input id="starts_on" type="date" v-model="form.starts_on" />
                    <InputError :message="form.errors.starts_on" />
                </div>

                <div class="grid gap-2">
                    <Label for="ends_on">Ends on (optional)</Label>
                    <Input id="ends_on" type="date" v-model="form.ends_on" />
                    <InputError :message="form.errors.ends_on" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="order">Order</Label>
                <Input id="order" type="number" min="0" max="65535" v-model="form.order" />
                <InputError :message="form.errors.order" />
            </div>

            <div v-if="stage.format === 'group_stage'" class="grid gap-2">
                <Label for="legs_per_group">Legs per group</Label>
                <Select v-model.number="form.config.legs_per_group">
                    <SelectTrigger id="legs_per_group">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="1">Single round-robin (1 leg)</SelectItem>
                        <SelectItem :value="2">Home and away (2 legs)</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    Changing this only affects new fixtures generated after the change.
                </p>
                <InputError :message="form.errors['config.legs_per_group']" />
            </div>

            <div v-if="isBracketFormat" class="grid gap-3 rounded-md border p-4">
                <div>
                    <h2 class="text-sm font-semibold">Knockout entrants</h2>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="sourceStage">
                            Which qualifier from "{{ sourceStage.name }}" fills each round-1 slot. Consecutive slots play each other.
                            {{ sourceStage.groups.length }} groups × top {{ sourceStage.advances_count }}<template v-if="sourceStage.best_placed_count"> + {{ sourceStage.best_placed_count }} best-placed</template>
                            = {{ qualifierTotal }} qualifiers.
                        </template>
                        <template v-else>
                            No earlier grouped stage found in this season — entrants can be configured once one exists.
                        </template>
                    </p>
                </div>

                <template v-if="sourceStage">
                    <div v-if="!qualifierTotalIsPowerOfTwo" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-700/40 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ qualifierTotal }} qualifiers can't fill a bracket — the total must be a power of two (2, 4, 8, 16, 32…).
                        Adjust "Advance per group" or "Best-placed qualifiers" on the group stage.
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button v-if="classicTemplateAvailable" type="button" size="sm" variant="outline" @click="applyClassicTemplate">
                            Classic template (1A v 2B …)
                        </Button>
                        <Button type="button" size="sm" variant="outline" :disabled="!qualifierTotalIsPowerOfTwo" @click="applySeededTemplate">
                            Seeded template
                        </Button>
                        <Button v-if="entrantTokens.length > 0" type="button" size="sm" variant="ghost" class="text-destructive" @click="clearEntrants">
                            Clear
                        </Button>
                    </div>

                    <div v-if="entrantTokens.length > 0" class="grid gap-2">
                        <div
                            v-for="match in entrantTokens.length / 2"
                            :key="match"
                            class="flex flex-wrap items-center gap-2 rounded-md border bg-card px-3 py-2"
                        >
                            <span class="w-16 shrink-0 font-display text-xs font-semibold uppercase text-muted-foreground">Match {{ match }}</span>
                            <Select v-model="entrantTokens[2 * (match - 1)]">
                                <SelectTrigger class="h-8 w-56 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in slotOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <span class="text-xs text-muted-foreground">vs</span>
                            <Select v-model="entrantTokens[2 * (match - 1) + 1]">
                                <SelectTrigger class="h-8 w-56 text-xs"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in slotOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <InputError :message="form.errors['config.entrants']" />
                </template>
            </div>

            <div v-if="hasGroupedFormat" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="advances_count">Advance per group (optional)</Label>
                    <Input id="advances_count" type="number" min="1" max="8" v-model="form.advances_count" placeholder="2" />
                    <p class="text-xs text-muted-foreground">
                        How many teams from each group qualify automatically. Defaults to 2.
                    </p>
                    <InputError :message="form.errors.advances_count" />
                </div>

                <div class="grid gap-2">
                    <Label for="best_placed_count">Best-placed qualifiers (optional)</Label>
                    <Input id="best_placed_count" type="number" min="1" max="16" v-model="form.config.best_placed_count" placeholder="None" />
                    <p class="text-xs text-muted-foreground">
                        Extra spots for the best teams finishing just below the cut — e.g. 8 best third-placed teams.
                    </p>
                    <InputError :message="form.errors['config.best_placed_count']" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="stageShow([league.slug, season.id, stage.id]).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
