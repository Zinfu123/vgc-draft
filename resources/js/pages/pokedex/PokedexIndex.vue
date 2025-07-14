<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Head } from '@inertiajs/vue3';

interface Pokemon {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
}

interface props {
    pokemon: Pokemon[];
}

const props = defineProps<props>();

</script>

<template>
    <Head title="Pokedex" />
    <div class="grid grid-cols-6 gap-3">
        <PokemonCard v-for="pokemon in props.pokemon" :key="pokemon.id" :pokemon="pokemon" />
    </div>
</template>