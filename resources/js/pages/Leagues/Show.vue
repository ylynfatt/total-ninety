<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { destroy, edit, index, show } from '@/routes/leagues';

interface Season {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string | null;
    is_active: boolean;
}

interface League {
    id: number;
    user_id: number;
    name: string;
    slug: string;
    description: string | null;
    country: string | null;
    is_public: boolean;
    seasons: Season[];
}

const props = defineProps<{
    league: League;
    can: {
        update: boolean;
        delete: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: index().url },
            { title: '', href: '' }, // populated dynamically below
        ],
    },
});

function deleteLeague() {
    if (!confirm(`Delete league "${props.league.name}"? This cannot be undone.`)) {
        return;
    }
    router.delete(destroy(props.league.slug).url);
}
</script>

<template>
    <Head :title="league.name" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ league.name }}</h1>
                    <Badge v-if="!league.is_public" variant="secondary">Private</Badge>
                </div>
                <p v-if="league.country" class="text-sm text-muted-foreground">{{ league.country }}</p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(league.slug).url">Edit</Link>
                </Button>
                <Button v-if="can.delete" variant="destructive" @click="deleteLeague">
                    Delete
                </Button>
            </div>
        </header>

        <Card v-if="league.description">
            <CardHeader>
                <CardTitle class="text-base">About</CardTitle>
            </CardHeader>
            <CardContent class="text-sm">
                {{ league.description }}
            </CardContent>
        </Card>

        <section>
            <h2 class="mb-3 text-lg font-semibold">Seasons</h2>
            <div v-if="league.seasons.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                No seasons created yet.
            </div>
            <div v-else class="grid gap-3 sm:grid-cols-2">
                <Card v-for="season in league.seasons" :key="season.id">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-2">
                            <CardTitle class="text-base">{{ season.name }}</CardTitle>
                            <Badge v-if="season.is_active">Active</Badge>
                        </div>
                        <CardDescription>
                            {{ season.starts_on }}<span v-if="season.ends_on"> &mdash; {{ season.ends_on }}</span>
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </section>
    </div>
</template>
