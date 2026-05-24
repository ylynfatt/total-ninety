<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/leagues';

const form = useForm({
    name: '',
    slug: '',
    description: '',
    country: '',
    is_public: true,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: index().url },
            { title: 'Create', href: create().url },
        ],
    },
});

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Create league" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Create league</h1>
            <p class="text-sm text-muted-foreground">
                Set up a new soccer league. You'll add seasons and teams next.
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus placeholder="Premier League" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">Slug (optional)</Label>
                <Input id="slug" v-model="form.slug" placeholder="premier-league" />
                <p class="text-xs text-muted-foreground">
                    Used in the URL. Leave blank to auto-generate from the name.
                </p>
                <InputError :message="form.errors.slug" />
            </div>

            <div class="grid gap-2">
                <Label for="country">Country (optional)</Label>
                <Input id="country" v-model="form.country" placeholder="England" />
                <InputError :message="form.errors.country" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description (optional)</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] dark:bg-input/30"
                    placeholder="Brief description of this league…"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="flex items-center gap-2">
                <Checkbox id="is_public" v-model="form.is_public" />
                <Label for="is_public" class="cursor-pointer">Public — anyone can view this league</Label>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    Create league
                </Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="index().url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
