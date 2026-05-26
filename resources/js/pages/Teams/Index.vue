<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/teams';
import type { BreadcrumbItem } from '@/types';

interface Team {
    id: number;
    name: string;
    acronym: string;
    home_ground: string | null;
    year_founded: number;
    seasons_count: number;
}

defineProps<{
    teams: Team[];
}>();

const page = usePage();
const canCreate = computed(() => page.props.auth?.user !== null && page.props.auth?.user !== undefined);

const pageBreadcrumbs: BreadcrumbItem[] = [
    { title: 'Teams', href: index().url },
];
</script>

<template>
    <Head title="Teams" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <Breadcrumbs :breadcrumbs="pageBreadcrumbs" />

        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Teams</h1>
                <p class="text-sm text-muted-foreground">
                    Every team in Total Ninety. Add new ones here so they can be attached to seasons.
                </p>
            </div>
            <Button v-if="canCreate" as-child>
                <Link :href="create().url">Add team</Link>
            </Button>
        </header>

        <div v-if="teams.length === 0" class="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
            No teams yet.
            <Link v-if="canCreate" :href="create().url" class="font-medium text-foreground underline underline-offset-4">
                Add the first one.
            </Link>
        </div>

        <div v-else class="overflow-hidden rounded-md border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 text-left">Acronym</th>
                        <th class="px-3 py-2 text-left">Name</th>
                        <th class="hidden px-3 py-2 text-left md:table-cell">Home ground</th>
                        <th class="hidden px-3 py-2 text-right tabular-nums sm:table-cell">Founded</th>
                        <th class="px-3 py-2 text-right tabular-nums">Seasons</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="team in teams" :key="team.id" class="hover:bg-muted/30">
                        <td class="px-3 py-2">
                            <span class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">{{ team.acronym }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <Link :href="show(team.id).url" class="font-medium hover:underline">
                                {{ team.name }}
                            </Link>
                        </td>
                        <td class="hidden px-3 py-2 text-muted-foreground md:table-cell">
                            {{ team.home_ground ?? '—' }}
                        </td>
                        <td class="hidden px-3 py-2 text-right tabular-nums text-muted-foreground sm:table-cell">
                            {{ team.year_founded }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ team.seasons_count }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
