<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show as teamShow, update } from '@/routes/teams';
import type { BreadcrumbItem } from '@/types';

interface Team {
    id: number;
    name: string;
    acronym: string;
    home_ground: string | null;
    year_founded: number;
}

const props = defineProps<{
    team: Team;
}>();

const form = useForm({
    name: props.team.name,
    acronym: props.team.acronym,
    year_founded: props.team.year_founded,
    home_ground: props.team.home_ground ?? '',
});

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Teams', href: index().url },
    { title: props.team.name, href: teamShow(props.team.id).url },
    { title: 'Edit', href: '#' },
]);

function submit() {
    form.put(update(props.team.id).url);
}
</script>

<template>
    <Head :title="`Edit ${team.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Edit team</h1>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div class="grid gap-2 sm:col-span-1">
                    <Label for="acronym">Acronym</Label>
                    <Input id="acronym" v-model="form.acronym" required maxlength="3" class="font-mono uppercase" />
                    <InputError :message="form.errors.acronym" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="year_founded">Year founded</Label>
                    <Input
                        id="year_founded"
                        type="number"
                        min="1800"
                        :max="new Date().getFullYear()"
                        v-model="form.year_founded"
                        required
                    />
                    <InputError :message="form.errors.year_founded" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="home_ground">Home ground (optional)</Label>
                <Input id="home_ground" v-model="form.home_ground" />
                <InputError :message="form.errors.home_ground" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="teamShow(team.id).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
