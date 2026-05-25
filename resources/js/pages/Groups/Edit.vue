<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/groups';
import { index as leaguesIndex, show as leagueShow } from '@/routes/leagues';
import { show as seasonShow } from '@/routes/seasons';
import { show as stageShow } from '@/routes/stages';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    id: number;
    name: string;
    slug?: string;
}

interface Group {
    id: number;
    name: string;
    order: number;
}

const props = defineProps<{
    league: Summary & { slug: string };
    season: Summary;
    stage: Summary;
    group: Group;
}>();

const form = useForm({
    name: props.group.name,
    order: props.group.order,
});

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Leagues', href: leaguesIndex().url },
    { title: props.league.name, href: leagueShow(props.league.slug).url },
    { title: props.season.name, href: seasonShow([props.league.slug, props.season.id]).url },
    { title: props.stage.name, href: stageShow([props.league.slug, props.season.id, props.stage.id]).url },
    { title: props.group.name, href: '#' },
    { title: 'Edit', href: '#' },
]);

function submit() {
    form.put(update([props.league.slug, props.season.id, props.stage.id, props.group.id]).url);
}
</script>

<template>
    <Head :title="`Edit ${group.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Edit group</h1>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="order">Order</Label>
                <Input id="order" type="number" min="0" max="65535" v-model="form.order" />
                <InputError :message="form.errors.order" />
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
