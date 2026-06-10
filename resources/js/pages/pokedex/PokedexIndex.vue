<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pokedex', href: '/pokedex' },
];

interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface PokemonRow {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
}

interface VersionGroupRow {
    id: number;
    slug: string;
    name: string;
    generation: number;
    sort_order: number;
}

interface FilterState {
    search: string;
    type1: string;
    type2: string;
    generation: number | null;
    game: string;
    ability: string;
    move: string;
    per_page: number;
}

interface Props {
    pokemon: Paginator<PokemonRow>;
    filters: FilterState;
    typeOptions: string[];
    generationFilterOptions: number[];
    versionGroups: VersionGroupRow[];
    abilityFilterOptions: string[];
}

const props = defineProps<Props>();

const searchDraft = ref(props.filters.search);
const abilityDraft = ref(props.filters.ability);
const moveDraft = ref(props.filters.move);
const gameDraft = ref(props.filters.game);
const type1Draft = ref(props.filters.type1);
const type2Draft = ref(props.filters.type2);
const generationDraft = ref<number | ''>(props.filters.generation ?? '');
const perPageDraft = ref(props.filters.per_page);

watch(
    () => props.filters,
    (filters) => {
        searchDraft.value = filters.search;
        abilityDraft.value = filters.ability;
        moveDraft.value = filters.move;
        gameDraft.value = filters.game;
        type1Draft.value = filters.type1;
        type2Draft.value = filters.type2;
        generationDraft.value = filters.generation ?? '';
        perPageDraft.value = filters.per_page;
    },
    { deep: true },
);

function formatSlugLabel(slug: string): string {
    return slug
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function currentFilters(): FilterState {
    return {
        search: searchDraft.value,
        type1: type1Draft.value,
        type2: type2Draft.value,
        generation: generationDraft.value === '' ? null : Number(generationDraft.value),
        game: gameDraft.value,
        ability: abilityDraft.value,
        move: moveDraft.value,
        per_page: perPageDraft.value,
    };
}

function applyFilters(overrides: Partial<FilterState> = {}) {
    const merged = { ...currentFilters(), ...overrides };

    searchDraft.value = merged.search;
    abilityDraft.value = merged.ability;
    moveDraft.value = merged.move;
    gameDraft.value = merged.game;
    type1Draft.value = merged.type1;
    type2Draft.value = merged.type2;
    generationDraft.value = merged.generation ?? '';
    perPageDraft.value = merged.per_page;

    router.get(
        route('pokedex.index'),
        {
            search: merged.search || undefined,
            type1: merged.type1 || undefined,
            type2: merged.type2 || undefined,
            generation: merged.generation ?? undefined,
            game: merged.game || undefined,
            ability: merged.ability || undefined,
            move: merged.move || undefined,
            per_page: merged.per_page,
        },
        { preserveScroll: true, replace: true },
    );
}

function submitFilters() {
    applyFilters();
}

function onGameChange() {
    abilityDraft.value = '';
    moveDraft.value = '';
    applyFilters({ game: gameDraft.value, ability: '', move: '' });
}

function pokemonShowHref(id: number): string {
    const params = new URLSearchParams();
    if (gameDraft.value) {
        params.set('game', gameDraft.value);
    }

    const query = params.toString();

    return route('pokedex.show', id) + (query ? `?${query}` : '');
}
</script>

<template>
    <Head title="Pokedex" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6">
            <h1 class="text-2xl font-bold tracking-tight">Pokedex</h1>

            <form
                class="flex max-w-full flex-col gap-4 overflow-x-auto rounded-lg border border-border bg-card p-4 shadow-sm md:flex-row md:flex-wrap md:items-end"
                @submit.prevent="submitFilters()"
            >
                <div class="grid min-w-[200px] flex-1 gap-2">
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="searchDraft" type="search" placeholder="Name…" class="min-h-11 text-base md:min-h-9 md:text-sm" />
                </div>
                <div class="grid min-w-[180px] gap-2">
                    <Label for="game">Game version</Label>
                    <select
                        id="game"
                        v-model="gameDraft"
                        class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none md:h-9 md:min-h-9 md:py-1 md:text-sm"
                        @change="onGameChange()"
                    >
                        <option v-for="vg in versionGroups" :key="vg.id" :value="vg.slug">
                            {{ vg.name }} (Gen {{ vg.generation }})
                        </option>
                    </select>
                </div>
                <div class="grid min-w-[180px] gap-2">
                    <Label for="ability">Ability</Label>
                    <Input
                        id="ability"
                        v-model="abilityDraft"
                        type="search"
                        list="ability-options"
                        placeholder="e.g. overgrow"
                        class="min-h-11 text-base md:min-h-9 md:text-sm"
                    />
                    <datalist id="ability-options">
                        <option v-for="ability in abilityFilterOptions" :key="ability" :value="ability">
                            {{ formatSlugLabel(ability) }}
                        </option>
                    </datalist>
                </div>
                <div class="grid min-w-[180px] gap-2">
                    <Label for="move">Move in learnset</Label>
                    <Input
                        id="move"
                        v-model="moveDraft"
                        type="search"
                        placeholder="e.g. earthquake"
                        class="min-h-11 text-base md:min-h-9 md:text-sm"
                    />
                </div>
                <div class="grid min-w-[140px] gap-2">
                    <Label for="type1">Type (either slot)</Label>
                    <select
                        id="type1"
                        v-model="type1Draft"
                        class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none md:h-9 md:min-h-9 md:py-1 md:text-sm"
                        @change="applyFilters()"
                    >
                        <option value="">Any</option>
                        <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div class="grid min-w-[140px] gap-2">
                    <Label for="type2">Also has type</Label>
                    <select
                        id="type2"
                        v-model="type2Draft"
                        class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none md:h-9 md:min-h-9 md:py-1 md:text-sm"
                        @change="applyFilters()"
                    >
                        <option value="">Any</option>
                        <option v-for="t in typeOptions" :key="'s-' + t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div class="grid min-w-[140px] gap-2">
                    <Label for="generation">Generation (game data)</Label>
                    <select
                        id="generation"
                        v-model="generationDraft"
                        class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none md:h-9 md:min-h-9 md:py-1 md:text-sm"
                        @change="applyFilters()"
                    >
                        <option value="">Any</option>
                        <option v-for="g in generationFilterOptions" :key="g" :value="g">Gen {{ g }}</option>
                    </select>
                </div>
                <div class="grid min-w-[100px] gap-2">
                    <Label for="per_page">Per page</Label>
                    <select
                        id="per_page"
                        v-model.number="perPageDraft"
                        class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none md:h-9 md:min-h-9 md:py-1 md:text-sm"
                        @change="applyFilters()"
                    >
                        <option :value="18">18</option>
                        <option :value="36">36</option>
                        <option :value="72">72</option>
                    </select>
                </div>
                <Button type="submit" variant="secondary" class="min-h-11 w-full touch-manipulation md:mb-0 md:h-9 md:min-h-9 md:w-auto">Apply</Button>
            </form>

            <p class="text-xs text-muted-foreground">Dropdown filters apply immediately. Use Apply for name, ability, and move text fields.</p>

            <p v-if="filters.ability || filters.move" class="text-sm text-muted-foreground">
                Ability and move filters use
                <span class="font-medium text-foreground">{{ versionGroups.find((vg) => vg.slug === filters.game)?.name ?? filters.game }}</span>
                learnsets.
            </p>

            <p v-if="pokemon.total > 0" class="text-sm text-muted-foreground">
                Showing {{ pokemon.data.length }} of {{ pokemon.total }} Pokémon
            </p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                <Link v-for="row in pokemon.data" :key="row.id" :href="pokemonShowHref(row.id)" class="transition-opacity hover:opacity-90">
                    <PokemonCard :pokemon="row" />
                </Link>
            </div>

            <nav v-if="pokemon.last_page > 1" class="flex flex-wrap items-center justify-center gap-1 py-4">
                <template v-for="link in pokemon.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md px-3 py-1 text-sm hover:bg-muted"
                        :class="{ 'bg-muted font-semibold': link.active }"
                        preserve-scroll
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span v-else class="rounded-md px-3 py-1 text-sm text-muted-foreground" v-html="link.label" />
                </template>
            </nav>
        </div>
    </AppLayout>
</template>
