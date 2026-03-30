<script setup lang="ts">
import LearnsetMoveRow from '@/components/pokedex/LearnsetMoveRow.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface VersionGroupRow {
    id: number;
    slug: string;
    name: string;
    generation: number;
    sort_order: number;
}

interface AbilityRow {
    pokeapi_ability_id: number;
    ability_name: string;
    slot: number;
    is_hidden: boolean;
}

interface LearnsetMove {
    move_id: number;
    move_name: string;
    method: string;
    level: number;
    type_slug?: string | null;
    damage_class?: string | null;
    power?: number | null;
    accuracy?: number | null;
    ailment_name?: string | null;
}

interface GameDataPayload {
    hp: number;
    atk: number;
    def: number;
    spa: number;
    spd: number;
    spe: number;
    type1: string;
    type2: string | null;
    ability_primary_pokeapi_id: number | null;
    ability_secondary_pokeapi_id: number | null;
    ability_hidden_pokeapi_id: number | null;
    abilities: AbilityRow[];
    learnset: LearnsetMove[];
    mechanics: Record<string, boolean>;
}

interface PokemonSummary {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    nationaldex_id: number;
}

interface Props {
    pokemon: PokemonSummary;
    versionGroups: VersionGroupRow[];
    selectedVersionSlug: string;
    gameData: GameDataPayload | null;
}

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pokedex', href: '/pokedex' },
    { title: props.pokemon.name, href: route('pokedex.show', props.pokemon.id) },
]);

const learnsetByMethod = computed(() => {
    const data = props.gameData?.learnset ?? [];
    const groups: Record<string, LearnsetMove[]> = {};
    for (const row of data) {
        const key = row.method || 'other';
        if (!groups[key]) {
            groups[key] = [];
        }
        groups[key].push(row);
    }
    return groups;
});

function formatItemName(slug: string): string {
    return slug
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function switchVersion(slug: string) {
    router.get(route('pokedex.show', props.pokemon.id), { game: slug }, { preserveScroll: true, only: ['gameData', 'selectedVersionSlug'] });
}
</script>

<template>
    <Head :title="pokemon.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-8 px-4 py-6 pb-10">
            <div class="flex flex-wrap items-center gap-4">
                <Link
                    :href="route('pokedex.index')"
                    class="inline-flex min-h-11 items-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-xs touch-manipulation hover:bg-accent hover:text-accent-foreground"
                >
                    Back to Pokedex
                </Link>
            </div>

            <div class="flex flex-col gap-6 md:flex-row md:items-start">
                <div class="shrink-0">
                    <PokemonCard class="pointer-events-none" :pokemon="pokemon" />
                </div>
                <div class="min-w-0 flex-1 space-y-4">
                    <div>
                        <h1 class="text-3xl font-bold capitalize">{{ pokemon.name }}</h1>
                        <p class="text-sm text-muted-foreground">National Dex #{{ Math.floor(pokemon.nationaldex_id) }}</p>
                    </div>

                    <div class="grid max-w-xs gap-2">
                        <Label for="game-version">Game version</Label>
                        <select
                            id="game-version"
                            :value="selectedVersionSlug"
                            class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none md:h-9 md:min-h-9 md:py-1 md:text-sm"
                            @change="switchVersion(($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="vg in versionGroups" :key="vg.id" :value="vg.slug">
                                {{ vg.name }} (Gen {{ vg.generation }})
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="!gameData"
                        class="rounded-md border border-dashed border-border bg-muted/30 p-4 text-sm text-muted-foreground"
                    >
                        No imported data for this game yet. Run
                        <code class="rounded bg-muted px-1 py-0.5 text-xs">php artisan pokemon:import-version-group</code>
                        to sync from PokeAPI.
                    </div>

                    <template v-else>
                        <section class="space-y-2">
                            <h2 class="text-lg font-semibold">Base stats</h2>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-3">
                                <div>
                                    <dt class="text-muted-foreground">HP</dt>
                                    <dd class="font-medium">{{ gameData.hp }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Attack</dt>
                                    <dd class="font-medium">{{ gameData.atk }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Defense</dt>
                                    <dd class="font-medium">{{ gameData.def }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Sp. Atk</dt>
                                    <dd class="font-medium">{{ gameData.spa }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Sp. Def</dt>
                                    <dd class="font-medium">{{ gameData.spd }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Speed</dt>
                                    <dd class="font-medium">{{ gameData.spe }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="space-y-2">
                            <h2 class="text-lg font-semibold">Types (this game)</h2>
                            <p class="text-sm capitalize">
                                {{ gameData.type1 }}
                                <template v-if="gameData.type2"> / {{ gameData.type2 }}</template>
                            </p>
                        </section>

                        <section class="space-y-2">
                            <h2 class="text-lg font-semibold">Abilities</h2>
                            <ul class="list-inside list-disc text-sm">
                                <li v-for="a in gameData.abilities" :key="a.pokeapi_ability_id + '-' + a.slot">
                                    <Link
                                        :href="route('pokedex.abilities.show', a.pokeapi_ability_id)"
                                        class="font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ formatItemName(a.ability_name) }}
                                    </Link>
                                    <span v-if="a.is_hidden" class="text-muted-foreground"> (hidden)</span>
                                </li>
                            </ul>
                            <p v-if="gameData.abilities.length === 0" class="text-sm text-muted-foreground">No ability rows imported.</p>
                        </section>

                        <section class="space-y-2">
                            <h2 class="text-lg font-semibold">Mechanics</h2>
                            <ul class="flex flex-wrap gap-2 text-xs">
                                <li
                                    v-for="(on, key) in gameData.mechanics"
                                    :key="key"
                                    class="rounded-full px-2 py-0.5"
                                    :class="on ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground line-through'"
                                >
                                    {{ key.replaceAll('_', ' ') }}
                                </li>
                            </ul>
                        </section>

                        <section class="space-y-3">
                            <h2 class="text-lg font-semibold">Learnset</h2>
                            <div v-for="(entries, method) in learnsetByMethod" :key="method" class="space-y-1">
                                <h3 class="text-sm font-medium capitalize text-muted-foreground">
                                    {{ method.replaceAll('-', ' ') }}
                                </h3>
                                <ul class="max-h-64 overflow-y-auto rounded border border-border px-2 py-1">
                                    <LearnsetMoveRow v-for="m in entries" :key="m.move_id + '-' + m.method + '-' + m.level" :move="m" />
                                </ul>
                            </div>
                        </section>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
