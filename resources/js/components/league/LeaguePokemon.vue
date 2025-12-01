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

interface League {
    id: number;
}

const props = defineProps<{
    pokemon: Pokemon[];
    league: League;
}>();
</script>

<template>
    <div class="grid grid-cols-6 gap-4">
        <PokemonCard v-for="pokemon in props.pokemon" :key="pokemon.id" :pokemon="pokemon" />
    </div>
</template>
