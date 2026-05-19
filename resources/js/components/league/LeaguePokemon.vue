<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Link } from '@inertiajs/vue3';

interface Pokemon {
    id: number;
    pokedex_id: number;
    cost: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2?: string;
}

interface PokemonDrafted {
    id: number;
    pokedex_id: number;
    cost: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2?: string;
}

interface League {
    id: number;
}

const props = defineProps<{
    pokemon: Pokemon[];
    league: League;
    pokemon_drafted: PokemonDrafted[];
}>();
</script>

<template>
    <div class="flex w-full flex-col items-center">
        <h1 class="text-2xl font-bold">Available Pokemon</h1>
        <div class="grid w-full grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            <Link
                v-for="pokemon in props.pokemon"
                :key="pokemon.id"
                :href="route('pokedex.show', pokemon.pokedex_id)"
                class="transition-opacity hover:opacity-90"
            >
                <PokemonCard :pokemon="pokemon" />
            </Link>
        </div>
        <h1 class="text-2xl font-bold">Drafted Pokemon</h1>
        <div class="grid w-full grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            <Link
                v-for="pokemon in props.pokemon_drafted"
                :key="pokemon.id"
                :href="route('pokedex.show', pokemon.pokedex_id)"
                class="transition-opacity hover:opacity-90"
            >
                <PokemonCard :pokemon="pokemon" />
            </Link>
        </div>
    </div>
</template>
