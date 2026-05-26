<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/teams';
import type { BreadcrumbItem } from '@/types';

const form = useForm({
    name: '',
    acronym: '',
    year_founded: new Date().getFullYear(),
    home_ground: '',
});

const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Teams', href: index().url },
    { title: 'Add team', href: create().url },
]);

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Add team" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Add team</h1>
            <p class="text-sm text-muted-foreground">
                Teams are shared across the app — once added, they can be attached to any season.
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus placeholder="Manchester United" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div class="grid gap-2 sm:col-span-1">
                    <Label for="acronym">Acronym</Label>
                    <Input
                        id="acronym"
                        v-model="form.acronym"
                        required
                        maxlength="3"
                        class="font-mono uppercase"
                        placeholder="MUN"
                    />
                    <p class="text-xs text-muted-foreground">3 letters · auto-uppercased</p>
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
                <Input id="home_ground" v-model="form.home_ground" placeholder="Old Trafford" />
                <InputError :message="form.errors.home_ground" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Add team</Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="index().url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
