<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { create, index, show } from '@/routes/leagues';

interface League {
    id: number;
    user_id: number;
    name: string;
    slug: string;
    description: string | null;
    country: string | null;
    is_public: boolean;
}

defineProps<{
    leagues: League[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: index().url },
        ],
    },
});

const page = usePage();
const canCreate = computed(() => page.props.auth?.permissions?.league?.create ?? false);
</script>

<template>
    <Head title="Leagues" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Leagues</h1>
                <p class="text-sm text-muted-foreground">
                    Browse public soccer leagues — or sign in to manage your own.
                </p>
            </div>
            <Button v-if="canCreate" as-child>
                <Link :href="create().url">Create league</Link>
            </Button>
        </header>

        <div v-if="leagues.length === 0" class="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
            No leagues yet.
            <Link v-if="canCreate" :href="create().url" class="font-medium text-foreground underline underline-offset-4">
                Create the first one.
            </Link>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="league in leagues" :key="league.id" class="flex flex-col">
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <CardTitle class="leading-tight">
                            <Link :href="show(league.slug).url" class="hover:underline">
                                {{ league.name }}
                            </Link>
                        </CardTitle>
                        <Badge v-if="!league.is_public" variant="secondary">Private</Badge>
                    </div>
                    <CardDescription v-if="league.country">{{ league.country }}</CardDescription>
                </CardHeader>
                <CardContent class="flex-1 text-sm text-muted-foreground">
                    <p v-if="league.description" class="line-clamp-3">{{ league.description }}</p>
                    <p v-else class="italic">No description yet.</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
