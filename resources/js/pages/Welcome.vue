<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';
</script>

<template>
    <Head title="Welcome" />

    <div class="relative flex min-h-screen flex-col bg-slate-100 dark:bg-neutral-950">
        <header class="absolute inset-x-0 top-0 px-6 py-4">
            <nav class="flex items-center justify-end gap-3 text-sm">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 font-medium text-slate-900 shadow-sm transition hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="inline-flex items-center rounded-md px-4 py-2 font-medium text-slate-700 transition hover:text-slate-900 dark:text-neutral-300 dark:hover:text-neutral-100"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 font-medium text-slate-900 shadow-sm transition hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <main class="flex flex-1 items-center justify-center px-6">
            <h1
                class="text-center text-4xl font-extrabold leading-none tracking-tight text-gray-900 md:text-5xl lg:text-6xl dark:text-neutral-100"
            >
                ⚽️ Total Ninety
            </h1>
        </main>
    </div>
</template>
