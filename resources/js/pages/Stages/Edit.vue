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

/**
 * The bracket is a fixed set of slots (one per qualifier) that start empty
 * and get a qualifier dropped into each. slots[2i] / slots[2i+1] are the two
 * sides of round-1 match i, and the order of the matches is the bracket tree.
 * We only mirror them into the saved entrants once every slot is filled — a
 * half-built bracket isn't a valid one.
 */
const bracketSize = computed(() => (qualifierTotalIsPowerOfTwo.value ? qualifierTotal.value : 0));

function initialSlots(): (string | null)[] {
    const saved = ((props.stage.config?.entrants as Entrant[] | undefined) ?? []).map(tokenOf);

    if (bracketSize.value === 0) {
        return saved;
    }

    return Array.from({ length: bracketSize.value }, (_, index) => saved[index] ?? null);
}

const slots = ref<(string | null)[]>(initialSlots());

watch(
    slots,
    (value) => {
        const filled = value.length > 0 && value.every((token): token is string => token !== null);
        form.config.entrants = filled ? (value as string[]).map(entrantOf) : null;
    },
    { deep: true },
);

const matchCount = computed(() => slots.value.length / 2);
const placedCount = computed(() => slots.value.filter((token) => token !== null).length);
const firstRoundLabel = computed(() => roundLabelFor(matchCount.value));

/** The two slot indices of a 1-based match number. */
function matchSlots(match: number): [number, number] {
    return [2 * (match - 1), 2 * (match - 1) + 1];
}

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

    slots.value = [...firstHalf, ...secondHalf];
    selected.value = null;
    selectedMatch.value = null;
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

    slots.value = seeds.map((seed) => seedList[seed - 1]);
    selected.value = null;
    selectedMatch.value = null;
}

function clearEntrants(): void {
    slots.value = Array.from({ length: bracketSize.value }, () => null);
    selected.value = null;
    selectedMatch.value = null;
}

/**
 * Full slot label, e.g. "Winner Group A" → "Winner A", "Best-placed #3" →
 * "3rd #3". The group's "Group " prefix is dropped so chips stay compact.
 */
function slotLabel(token: string): string {
    const parts = token.split('|');

    if (parts[0] === 'best') {
        return `3rd #${parts[1]}`;
    }

    const group = parts[1].replace(/^Group\s+/i, '');
    const position = Number(parts[2]);

    if (position === 1) {
        return `Winner ${group}`;
    }

    if (position === 2) {
        return `Runner-up ${group}`;
    }

    const suffix = position % 10 === 3 && position % 100 !== 13 ? 'rd' : 'th';

    return `${position}${suffix} ${group}`;
}

type SlotKind = 'winner' | 'ru' | 'third' | 'other';

function kindForToken(token: string): SlotKind {
    const parts = token.split('|');

    if (parts[0] === 'best') {
        return 'third';
    }

    const position = Number(parts[2]);

    return position === 1 ? 'winner' : position === 2 ? 'ru' : 'other';
}

function dotClass(kind: SlotKind): string {
    return { winner: 'bg-primary', ru: 'bg-sky-500', third: 'bg-amber-500', other: 'bg-muted-foreground' }[kind];
}

/** Qualifier chips grouped by kind, marking the ones already on the bracket. */
const paletteGroups = computed<{ label: string; chips: { token: string; label: string; kind: SlotKind; placed: boolean }[] }[]>(() => {
    const placed = new Set(slots.value.filter((token): token is string => token !== null));
    const labels: Record<SlotKind, string> = {
        winner: 'Group winners',
        ru: 'Runners-up',
        third: 'Best-placed',
        other: 'Other qualifiers',
    };
    const grouped: Record<SlotKind, { token: string; label: string; kind: SlotKind; placed: boolean }[]> = { winner: [], ru: [], third: [], other: [] };

    for (const option of slotOptions.value) {
        const kind = kindForToken(option.value);
        grouped[kind].push({ token: option.value, label: slotLabel(option.value), kind, placed: placed.has(option.value) });
    }

    return (['winner', 'ru', 'third', 'other'] as SlotKind[])
        .filter((kind) => grouped[kind].length > 0)
        .map((kind) => ({ label: labels[kind], chips: grouped[kind] }));
});

// ----- drag and drop (native, no dependency) -----

type DragState = { kind: 'chip'; token: string; from: number | null } | { kind: 'match'; index: number };

const drag = ref<DragState | null>(null);
const overSlot = ref<number | null>(null);
const dropMatch = ref<{ index: number; after: boolean } | null>(null);

function assignSlot(token: string, index: number, from: number | null): void {
    const next = [...slots.value];
    const occupant = next[index];
    next[index] = token;

    // Dragged out of another slot: leave the source empty, or swap when the
    // target was occupied.
    if (from !== null) {
        next[from] = occupant && occupant !== token ? occupant : null;
    }

    slots.value = next;
}

function unassignSlot(index: number): void {
    const next = [...slots.value];
    next[index] = null;
    slots.value = next;
}

/** Move round-1 match `from` to position `to`, sliding the rest along. */
function moveMatch(from: number, to: number): void {
    if (from === to) {
        return;
    }

    const pairs: [string | null, string | null][] = [];
    for (let i = 0; i < slots.value.length; i += 2) {
        pairs.push([slots.value[i], slots.value[i + 1]]);
    }

    const [pair] = pairs.splice(from, 1);
    pairs.splice(to, 0, pair);
    slots.value = pairs.flat();
}

function onChipDragStart(event: DragEvent, token: string, from: number | null): void {
    drag.value = { kind: 'chip', token, from };
    // Firefox only starts a drag once data is set; the value itself is unused.
    event.dataTransfer?.setData('text/plain', token);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onMatchDragStart(event: DragEvent, index: number): void {
    drag.value = { kind: 'match', index };
    event.dataTransfer?.setData('text/plain', `match:${index}`);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd(): void {
    drag.value = null;
    overSlot.value = null;
    dropMatch.value = null;
}

function onSlotDragOver(event: DragEvent, index: number): void {
    if (drag.value?.kind !== 'chip') {
        return;
    }

    event.preventDefault();
    overSlot.value = index;
}

function onSlotDrop(index: number): void {
    if (drag.value?.kind !== 'chip') {
        return;
    }

    assignSlot(drag.value.token, index, drag.value.from);
    onDragEnd();
}

function dropTargetAfter(event: DragEvent): boolean {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();

    return event.clientY > rect.top + rect.height / 2;
}

function onMatchDragOver(event: DragEvent, index: number): void {
    if (drag.value?.kind !== 'match') {
        return;
    }

    event.preventDefault();
    dropMatch.value = { index, after: dropTargetAfter(event) };
}

function onMatchDrop(event: DragEvent, index: number): void {
    if (drag.value?.kind !== 'match') {
        return;
    }

    let to = index + (dropTargetAfter(event) ? 1 : 0);

    if (drag.value.index < to) {
        to -= 1;
    }

    moveMatch(drag.value.index, to);
    onDragEnd();
}

// ----- tap to place (works on touch, where native drag never fires) -----

const selected = ref<{ token: string; from: number | null } | null>(null);

function isSelected(token: string, from: number | null): boolean {
    return selected.value?.token === token && selected.value.from === from;
}

function onChipTap(token: string): void {
    selected.value = isSelected(token, null) ? null : { token, from: null };
}

function onSlotTap(index: number): void {
    if (selected.value) {
        // Tapping the slot a chip was picked up from just cancels the pickup.
        if (selected.value.from === index) {
            selected.value = null;

            return;
        }

        assignSlot(selected.value.token, index, selected.value.from);
        selected.value = null;

        return;
    }

    const token = slots.value[index];

    if (token !== null) {
        selected.value = { token, from: index };
    }
}

// Tap a matchup's handle to pick it up, then another to drop it there.
const selectedMatch = ref<number | null>(null);

function onMatchHandleTap(index: number): void {
    if (selectedMatch.value === null || selectedMatch.value === index) {
        selectedMatch.value = selectedMatch.value === index ? null : index;

        return;
    }

    const to = selectedMatch.value < index ? index - 1 : index;
    moveMatch(selectedMatch.value, to);
    selectedMatch.value = null;
}

function roundLabelFor(gameCount: number): string {
    const labels: Record<number, string> = { 1: 'Final', 2: 'Semifinals', 4: 'Quarterfinals', 8: 'Round of 16', 16: 'Round of 32' };

    return labels[gameCount] ?? `Round of ${gameCount * 2}`;
}

function roundTag(gameCount: number, index: number): string {
    const prefixes: Record<number, string> = { 1: 'F', 2: 'SF', 4: 'QF', 8: 'R16', 16: 'M' };
    const prefix = prefixes[gameCount] ?? `R${gameCount * 2}`;

    return gameCount === 1 ? prefix : `${prefix}${index + 1}`;
}

/**
 * The bracket columns after round 1: each later round's games and which
 * earlier games feed them (by tag — M1, QF1 …). Independent of who's placed,
 * so the tree is visible while the admin is still filling slots.
 */
const derivedRounds = computed<{ label: string; games: { tag: string; feedA: string; feedB: string }[] }[]>(() => {
    if (matchCount.value < 2 || (matchCount.value & (matchCount.value - 1)) !== 0) {
        return [];
    }

    let previousTags = Array.from({ length: matchCount.value }, (_, index) => `M${index + 1}`);
    const rounds: { label: string; games: { tag: string; feedA: string; feedB: string }[] }[] = [];

    while (previousTags.length > 1) {
        const gameCount = previousTags.length / 2;
        const games = [];

        for (let j = 0; j < gameCount; j++) {
            games.push({ tag: roundTag(gameCount, j), feedA: previousTags[2 * j], feedB: previousTags[2 * j + 1] });
        }

        rounds.push({ label: roundLabelFor(gameCount), games });
        previousTags = games.map((game) => game.tag);
    }

    return rounds;
});

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

    <div class="mx-auto flex w-full flex-col gap-6 p-4 sm:p-6" :class="isBracketFormat ? 'max-w-6xl' : 'max-w-2xl'">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Edit stage</h1>
            <p class="text-sm text-muted-foreground">
                Format: <span class="font-medium text-foreground">{{ formatLabel }}</span> (locked after creation)
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid max-w-2xl gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid max-w-2xl grid-cols-1 gap-5 sm:grid-cols-2">
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

            <div class="grid max-w-2xl gap-2">
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

                    <div class="flex flex-wrap items-center gap-2">
                        <Button v-if="classicTemplateAvailable" type="button" size="sm" variant="outline" @click="applyClassicTemplate">
                            Classic template
                        </Button>
                        <Button type="button" size="sm" variant="outline" :disabled="!qualifierTotalIsPowerOfTwo" @click="applySeededTemplate">
                            Seeded template
                        </Button>
                        <Button v-if="placedCount > 0" type="button" size="sm" variant="ghost" class="text-destructive" @click="clearEntrants">
                            Clear
                        </Button>
                        <span class="ml-auto text-xs tabular-nums text-muted-foreground">{{ placedCount }} of {{ bracketSize }} placed</span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Tap a qualifier, then a slot to place it — or drag it across. Reorder a matchup with its <span class="font-semibold">⠿</span> handle (drag it, or tap the handle then another). The later rounds show who's set up to meet.
                    </p>

                    <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                        <!-- Qualifier palette -->
                        <div class="flex flex-col gap-2.5 self-start rounded-md border bg-muted/40 p-3 lg:sticky lg:top-4">
                            <div v-for="group in paletteGroups" :key="group.label" class="flex flex-col gap-1.5">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ group.label }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="chip in group.chips"
                                        :key="chip.token"
                                        type="button"
                                        :draggable="!chip.placed"
                                        :disabled="chip.placed"
                                        class="inline-flex select-none items-center gap-1.5 rounded-full border bg-card px-2.5 py-1 text-xs font-medium disabled:opacity-40"
                                        :class="[
                                            chip.placed ? '' : 'cursor-grab',
                                            isSelected(chip.token, null) ? 'ring-2 ring-volt' : '',
                                        ]"
                                        @click="onChipTap(chip.token)"
                                        @dragstart="onChipDragStart($event, chip.token, null)"
                                        @dragend="onDragEnd"
                                    >
                                        <span class="h-2 w-2 rounded-full" :class="dotClass(chip.kind)" />
                                        {{ chip.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Visual bracket -->
                        <div class="min-w-0 overflow-x-auto pb-1">
                            <div class="flex min-w-max items-stretch gap-5">
                                <div class="flex min-w-[190px] flex-col justify-around gap-2">
                                    <p class="text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ firstRoundLabel }}</p>
                                    <div
                                        v-for="match in matchCount"
                                        :key="match"
                                        class="overflow-hidden rounded-lg border bg-card shadow-sm"
                                        :class="[
                                            drag?.kind === 'match' && drag.index === match - 1 ? 'opacity-40' : '',
                                            dropMatch?.index === match - 1 || selectedMatch === match - 1 ? 'ring-2 ring-inset ring-volt' : '',
                                        ]"
                                        @dragover="onMatchDragOver($event, match - 1)"
                                        @dragleave="dropMatch = null"
                                        @drop="onMatchDrop($event, match - 1)"
                                    >
                                        <div class="flex items-center gap-1.5 border-b bg-muted px-2 py-1">
                                            <button
                                                type="button"
                                                draggable="true"
                                                class="cursor-grab text-sm leading-none text-muted-foreground hover:text-foreground"
                                                :class="selectedMatch === match - 1 ? 'text-volt' : ''"
                                                aria-label="Reorder matchup"
                                                @click="onMatchHandleTap(match - 1)"
                                                @dragstart="onMatchDragStart($event, match - 1)"
                                                @dragend="onDragEnd"
                                            >⠿</button>
                                            <span class="font-display text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Match {{ match }}</span>
                                        </div>
                                        <div
                                            v-for="(slotIndex, side) in matchSlots(match)"
                                            :key="slotIndex"
                                            class="flex min-h-[40px] cursor-pointer items-center gap-2 px-2.5 py-2 text-sm"
                                            :class="[
                                                side === 1 ? 'border-t border-dashed' : '',
                                                overSlot === slotIndex || selected?.from === slotIndex ? 'bg-volt/15 ring-1 ring-inset ring-volt' : '',
                                            ]"
                                            @click="onSlotTap(slotIndex)"
                                            @dragover="onSlotDragOver($event, slotIndex)"
                                            @dragleave="overSlot = null"
                                            @drop="onSlotDrop(slotIndex)"
                                        >
                                            <template v-if="slots[slotIndex]">
                                                <span class="h-2 w-2 shrink-0 rounded-full" :class="dotClass(kindForToken(slots[slotIndex]!))" />
                                                <span
                                                    draggable="true"
                                                    class="flex-1 cursor-grab truncate font-medium"
                                                    @dragstart="onChipDragStart($event, slots[slotIndex]!, slotIndex)"
                                                    @dragend="onDragEnd"
                                                >{{ slotLabel(slots[slotIndex]!) }}</span>
                                                <button
                                                    type="button"
                                                    class="shrink-0 rounded px-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                    aria-label="Remove qualifier"
                                                    @click.stop="unassignSlot(slotIndex)"
                                                >×</button>
                                            </template>
                                            <span v-else class="italic text-muted-foreground">{{ selected ? 'Tap to place' : 'Drop a qualifier' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-for="round in derivedRounds" :key="round.label" class="flex min-w-[170px] flex-col justify-around gap-2">
                                    <p class="text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ round.label }}</p>
                                    <div v-for="game in round.games" :key="game.tag" class="rounded-lg border border-dashed bg-card/60 px-3 py-2 text-center">
                                        <p class="font-display text-[11px] font-semibold text-muted-foreground">{{ game.tag }}</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">winner {{ game.feedA }} vs winner {{ game.feedB }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="placedCount < bracketSize" class="text-xs text-muted-foreground">
                        {{ bracketSize - placedCount }} slot{{ bracketSize - placedCount === 1 ? '' : 's' }} still empty — fill every slot to save the bracket.
                    </p>

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

            <div class="flex max-w-2xl items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="stageShow([league.slug, season.id, stage.id]).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
