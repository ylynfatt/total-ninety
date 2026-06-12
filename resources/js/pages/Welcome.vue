<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';
import { index as leaguesIndex } from '@/routes/leagues';
import { index as scoreboardIndex } from '@/routes/scoreboard';
</script>

<template>
    <Head title="Welcome" />

    <div class="bg-pitch relative flex min-h-screen flex-col text-sidebar-foreground">
        <header class="px-6 py-5">
            <nav class="mx-auto flex max-w-5xl items-center justify-between gap-3 text-sm">
                <div class="flex items-center gap-2.5">
                    <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                        <AppLogoIcon class="size-6" />
                    </div>
                    <span class="font-display text-lg font-bold uppercase tracking-[0.14em]">
                        Total <span class="text-sidebar-primary">Ninety</span>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center rounded-md bg-sidebar-primary px-4 py-2 font-semibold text-sidebar-primary-foreground transition hover:opacity-90"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex items-center rounded-md px-4 py-2 font-medium text-sidebar-foreground/80 transition hover:text-sidebar-foreground"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex items-center rounded-md bg-sidebar-primary px-4 py-2 font-semibold text-sidebar-primary-foreground transition hover:opacity-90"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <main class="flex flex-1 flex-col items-center justify-center gap-8 px-6 pb-24 text-center">
            <p class="font-display text-sm font-semibold uppercase tracking-[0.35em] text-sidebar-primary">
                Every minute of the match
            </p>

            <h1 class="font-display text-6xl font-bold uppercase leading-none tracking-tight sm:text-7xl md:text-8xl">
                Total <span class="text-sidebar-primary">Ninety</span>
            </h1>

            <p class="max-w-xl text-balance text-base text-sidebar-foreground/80 sm:text-lg">
                Run your league like the big leagues — fixtures, live scores, gamecasts,
                standings, and knockout brackets, all in one place.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <Link
                    :href="leaguesIndex().url"
                    class="inline-flex items-center rounded-md bg-sidebar-primary px-6 py-3 text-sm font-bold uppercase tracking-wider text-sidebar-primary-foreground transition hover:opacity-90"
                >
                    Browse leagues
                </Link>
                <Link
                    :href="scoreboardIndex().url"
                    class="inline-flex items-center gap-2 rounded-md border border-sidebar-foreground/30 px-6 py-3 text-sm font-bold uppercase tracking-wider transition hover:border-sidebar-foreground/60"
                >
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-live opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-live" />
                    </span>
                    Live scores
                </Link>
            </div>
        </main>
    </div>
</template>
