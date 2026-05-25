<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { show as seasonShow, update } from '@/routes/seasons';
import type { BreadcrumbItem } from '@/types';

interface LeagueSummary {
    id: number;
    name: string;
    slug: string;
}

interface Season {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string | null;
    is_active: boolean;
}

const props = defineProps<{
    league: LeagueSummary;
    season: Season;
}>();

const form = useForm({
    name: props.season.name,
    starts_on: props.season.starts_on,
    ends_on: props.season.ends_on ?? '',
    is_active: props.season.is_active,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: leaguesIndex().url },
        ],
    },
});

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Leagues', href: leaguesIndex().url },
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: seasonShow([props.league.slug, props.season.id]).url },
    { title: 'Edit', href: '#' },
]);

function submit() {
    form.put(update([props.league.slug, props.season.id]).url);
}
</script>

<template>
    <Head :title="`Edit ${season.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Edit season</h1>
            <p class="text-sm text-muted-foreground">
                Update <span class="font-medium text-foreground">{{ season.name }}</span> in <span class="font-medium text-foreground">{{ league.name }}</span>.
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
                    <Label for="starts_on">Starts on</Label>
                    <Input id="starts_on" type="date" v-model="form.starts_on" required />
                    <InputError :message="form.errors.starts_on" />
                </div>

                <div class="grid gap-2">
                    <Label for="ends_on">Ends on (optional)</Label>
                    <Input id="ends_on" type="date" v-model="form.ends_on" />
                    <InputError :message="form.errors.ends_on" />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox id="is_active" v-model="form.is_active" />
                <Label for="is_active" class="cursor-pointer">This is the active season</Label>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="seasonShow([league.slug, season.id]).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
