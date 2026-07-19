<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const props = defineProps<{
    league: LeagueSummary;
    season: SeasonSummary;
    stage: Stage;
    formats: FormatOption[];
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
    },
});

const hasGroupedFormat = ['group_stage', 'conference'].includes(props.stage.format);

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
