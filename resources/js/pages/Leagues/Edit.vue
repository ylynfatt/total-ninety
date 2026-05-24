<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, show, update } from '@/routes/leagues';

interface League {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    country: string | null;
    is_public: boolean;
}

const props = defineProps<{
    league: League;
}>();

const form = useForm({
    name: props.league.name,
    slug: props.league.slug,
    description: props.league.description ?? '',
    country: props.league.country ?? '',
    is_public: props.league.is_public,
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Leagues', href: index().url },
        ],
    },
});

function submit() {
    form.put(update(props.league.slug).url);
}
</script>

<template>
    <Head :title="`Edit ${league.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Edit league</h1>
            <p class="text-sm text-muted-foreground">
                Update the details for <span class="font-medium text-foreground">{{ league.name }}</span>.
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required autofocus />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">Slug</Label>
                <Input id="slug" v-model="form.slug" />
                <p class="text-xs text-muted-foreground">
                    Changing this changes the league's public URL.
                </p>
                <InputError :message="form.errors.slug" />
            </div>

            <div class="grid gap-2">
                <Label for="country">Country</Label>
                <Input id="country" v-model="form.country" />
                <InputError :message="form.errors.country" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] dark:bg-input/30"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="flex items-center gap-2">
                <Checkbox id="is_public" v-model="form.is_public" />
                <Label for="is_public" class="cursor-pointer">Public — anyone can view this league</Label>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    Save changes
                </Button>
                <Button type="button" variant="ghost" as-child>
                    <Link :href="show(league.slug).url">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
