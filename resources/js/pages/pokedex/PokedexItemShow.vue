<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    error: string | null;
    id: number;
    name_display: string;
    name_slug?: string;
    cost: number;
    category?: string | null;
    effect?: string | null;
    short_effect?: string | null;
    sprite_url?: string | null;
    flavor_lines?: Array<{ text: string; version_group: string }>;
}

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pokedex', href: '/pokedex' },
    { title: props.name_display, href: route('pokedex.items.show', props.id) },
]);
</script>

<template>
    <Head :title="name_display" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6 pb-10">
            <Link
                :href="route('pokedex.index')"
                class="inline-flex min-h-11 w-fit items-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-xs hover:bg-accent"
            >
                Back to Pokedex
            </Link>

            <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 p-4 text-sm">
                Could not load this item from PokéAPI.
            </div>

            <template v-else>
                <div class="flex flex-wrap items-start gap-6">
                    <div
                        v-if="sprite_url"
                        class="flex size-24 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/30 p-2"
                    >
                        <img :src="sprite_url" :alt="name_display" class="max-h-full max-w-full object-contain" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">{{ name_display }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <span v-if="category">{{ category }}</span>
                            <span v-if="category"> · </span>
                            <span>Cost {{ cost }}</span>
                        </p>
                    </div>
                </div>

                <section v-if="short_effect" class="space-y-2">
                    <h2 class="text-lg font-semibold">Effect (short)</h2>
                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ short_effect }}</p>
                </section>

                <section v-if="effect" class="space-y-2">
                    <h2 class="text-lg font-semibold">Effect</h2>
                    <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ effect }}</p>
                </section>

                <section v-if="flavor_lines && flavor_lines.length" class="space-y-2">
                    <h2 class="text-lg font-semibold">Flavor text</h2>
                    <ul class="space-y-3 text-sm text-muted-foreground">
                        <li v-for="(fl, idx) in flavor_lines" :key="idx" class="border-b border-border/60 pb-3 last:border-0">
                            <p class="whitespace-pre-wrap">{{ fl.text }}</p>
                            <p v-if="fl.version_group" class="mt-1 text-xs capitalize">{{ fl.version_group.replaceAll('-', ' ') }}</p>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
