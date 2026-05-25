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
import { store } from '@/routes/stages';
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

interface FormatOption {
    value: string;
    label: string;
    hasGroups: boolean;
    isBracket: boolean;
}

const props = defineProps<{
    league: LeagueSummary;
    season: SeasonSummary;
    formats: FormatOption[];
}>();

const form = useForm({
    name: '',
    format: 'round_robin_single',
    order: 0,
    starts_on: '',
    ends_on: '',
    advances_count: null as number | null,
    config: {
        legs_per_group: 1 as 1 | 2,
    },
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: leaguesIndex().url },
        ],
    },
});

const selectedFormat = computed(() => props.formats.find((f) => f.value === form.format));

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Leagues', href: leaguesIndex().url },
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: seasonShow([props.league.slug, props.season.id]).url },
    { title: 'New stage', href: '#' },
]);

function submit() {
    form.post(store([props.league.slug, props.season.id]).url);
}
</script>

<template>
    <Head :title="`New stage — ${season.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">New stage</h1>
            <p class="text-sm text-muted-foreground">
                A stage is a phase of the season — e.g. "Regular Season", "Group Stage", or "Playoffs".
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus placeholder="Regular Season" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="format">Format</Label>
                <Select v-model="form.format">
                    <SelectTrigger id="format">
                        <SelectValue placeholder="Pick a format" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in formats" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    The format determines how fixtures are generated. It can't be changed after the stage is created.
                </p>
                <InputError :message="form.errors.format" />
            </div>

            <div v-if="selectedFormat?.hasGroups" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700/40 dark:bg-amber-950/40 dark:text-amber-200">
                Grouped formats need you to create groups and assign teams to them before fixtures can be generated. You can manage groups from the stage page after creating it.
            </div>

            <div v-if="form.format === 'group_stage'" class="grid gap-2">
                <Label for="legs_per_group">Legs per group</Label>
                <Select v-model.number="form.config.legs_per_group">
                    <SelectTrigger id="legs_per_group">
                        <SelectValue placeholder="How many times each pair plays" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="1">Single round-robin (1 leg)</SelectItem>
                        <SelectItem :value="2">Home and away (2 legs)</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    For 5 teams per group: 1 leg = 10 games/group · 2 legs = 20 games/group.
                </p>
                <InputError :message="form.errors['config.legs_per_group']" />
            </div>

            <div v-if="selectedFormat?.isBracket" class="rounded-md border border-sky-300 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-700/40 dark:bg-sky-950/40 dark:text-sky-200">
                Knockout formats only generate round-1 fixtures up front. Later rounds get populated as winners advance.
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
                <p class="text-xs text-muted-foreground">
                    Lower numbers come first. Use 10, 20, 30 to leave room for inserting later.
                </p>
                <InputError :message="form.errors.order" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Create stage</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="seasonShow([league.slug, season.id]).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
