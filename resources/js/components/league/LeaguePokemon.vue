<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';

interface Pokemon {
    id: number;
    cost: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2?: string;
}

interface PokemonDrafted {
    id: number;
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
    <div class="flex flex-col items-center justify-center">
        <h1 class = "text-2xl font-bold">Available Pokemon</h1>
        <div class="grid grid-cols-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-4">
        <PokemonCard v-for="pokemon in props.pokemon" :key="pokemon.id" :pokemon="pokemon" />
        </div>
        <h1 class = "text-2xl font-bold">Drafted Pokemon</h1>
        <div class="grid grid-cols-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-4">
        <PokemonCard v-for="pokemon in props.pokemon_drafted" :key="pokemon.id" :pokemon="pokemon" />
        </div>
    </div>
</template>
