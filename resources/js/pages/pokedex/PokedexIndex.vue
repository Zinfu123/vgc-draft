<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Pokedex',
        href: '/pokedex',
    },
];
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
    <AppLayout :breadcrumbs="breadcrumbs">
        
    <div class="grid grid-cols-6 gap-3">
        <PokemonCard v-for="pokemon in props.pokemon" :key="pokemon.id" :pokemon="pokemon" />
    </div>
</AppLayout>
</template>