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
import { store } from '@/routes/seasons';
import type { BreadcrumbItem } from '@/types';

interface LeagueSummary {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    league: LeagueSummary;
}>();

const form = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
    is_active: false,
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
    { title: 'New season', href: '#' },
]);

function submit() {
    form.post(store(props.league.slug).url);
}
</script>

<template>
    <Head :title="`New season — ${league.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">New season</h1>
            <p class="text-sm text-muted-foreground">
                Add a season to <span class="font-medium text-foreground">{{ league.name }}</span>.
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus placeholder="2025/26" />
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
                <Label for="is_active" class="cursor-pointer">Mark as the active season</Label>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Create season</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="leagueShow(league.slug).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
