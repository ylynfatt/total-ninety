<script setup lang="ts">
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div role="status" aria-label="Loading matchday overview" class="flex flex-col gap-6">
        <span class="sr-only">Loading your matchday overview…</span>

        <div aria-hidden="true" class="grid gap-4 sm:grid-cols-3">
            <div v-for="item in 3" :key="item" class="rounded-xl border bg-card p-5 shadow-sm">
                <Skeleton class="h-5 w-28 max-w-full motion-reduce:animate-none" />
                <Skeleton class="mt-2 h-10 w-12 motion-reduce:animate-none" />
                <Skeleton class="mt-2 h-4 w-64 max-w-full motion-reduce:animate-none" />
            </div>
        </div>

        <div aria-hidden="true" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <Card class="min-w-0">
                <CardHeader>
                    <Skeleton class="h-4 w-36 max-w-full motion-reduce:animate-none" />
                    <Skeleton class="h-5 w-full motion-reduce:animate-none" />
                </CardHeader>
                <CardContent class="flex flex-col gap-6">
                    <div class="flex flex-col gap-3">
                        <div>
                            <Skeleton class="h-5 w-28 max-w-full motion-reduce:animate-none" />
                            <Skeleton class="mt-1 h-4 w-64 max-w-full motion-reduce:animate-none" />
                        </div>
                        <div v-for="match in 2" :key="match" class="flex flex-col gap-3 rounded-lg border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <Skeleton class="h-4 w-48 max-w-full motion-reduce:animate-none" />
                                <Skeleton class="h-5 w-20 rounded-full motion-reduce:animate-none" />
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <Skeleton class="h-5 w-56 max-w-full motion-reduce:animate-none" />
                                <Skeleton class="h-8 w-14 shrink-0 motion-reduce:animate-none" />
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <Skeleton class="h-4 w-36 max-w-full motion-reduce:animate-none" />
                                <div class="flex flex-wrap gap-3">
                                    <Skeleton class="h-4 w-20 motion-reduce:animate-none" />
                                    <Skeleton class="h-4 w-20 motion-reduce:animate-none" />
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="min-w-0">
                <CardHeader>
                    <Skeleton class="h-4 w-40 max-w-full motion-reduce:animate-none" />
                    <Skeleton class="h-5 w-56 max-w-full motion-reduce:animate-none" />
                </CardHeader>
                <CardContent class="flex flex-col gap-6">
                    <div class="flex flex-col gap-3">
                        <div>
                            <Skeleton class="h-5 w-36 max-w-full motion-reduce:animate-none" />
                            <Skeleton class="mt-1 h-4 w-full motion-reduce:animate-none" />
                        </div>
                        <div class="divide-y rounded-lg border px-4">
                            <div v-for="task in 3" :key="task" class="flex flex-col gap-1 py-3">
                                <Skeleton class="h-5 w-44 max-w-full motion-reduce:animate-none" />
                                <Skeleton class="h-4 w-full motion-reduce:animate-none" />
                                <Skeleton class="mt-1 h-4 w-20 motion-reduce:animate-none" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div aria-hidden="true" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Skeleton class="h-7 w-28 max-w-full motion-reduce:animate-none" />
                <Skeleton class="h-5 w-28 motion-reduce:animate-none" />
            </div>
            <div class="grid items-start gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <Card v-for="league in 2" :key="league" class="min-w-0">
                    <CardHeader>
                        <div class="flex items-start justify-between gap-3">
                            <Skeleton class="h-4 w-40 max-w-full motion-reduce:animate-none" />
                            <Skeleton class="h-5 w-14 shrink-0 rounded-full motion-reduce:animate-none" />
                        </div>
                        <Skeleton class="h-5 w-28 max-w-full motion-reduce:animate-none" />
                    </CardHeader>
                    <CardContent class="flex flex-col gap-5">
                        <div class="flex flex-col gap-2">
                            <Skeleton class="h-5 w-32 max-w-full motion-reduce:animate-none" />
                            <Skeleton class="h-4 w-44 max-w-full motion-reduce:animate-none" />
                            <Skeleton class="h-1.5 w-full rounded-full motion-reduce:animate-none" />
                            <div class="mt-1">
                                <Skeleton class="h-4 w-56 max-w-full motion-reduce:animate-none" />
                                <Skeleton class="mt-1 h-4 w-40 max-w-full motion-reduce:animate-none" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
