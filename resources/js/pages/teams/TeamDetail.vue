<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { type BreadcrumbItem } from '@/types';


interface Team {
    id: number;
    name: string;
    logo: string;
    coach: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    drafted_pokemon: DraftedPokemon[];
}

interface League {
    id: number;
    name: string;
}

interface props {
    team: Team;
    league: League;
}

const props = defineProps<props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.league.name,
        href: `/leagues/${props.league.id}`,
    },
    {
        title: props.team.name,
        href: `/teams/${props.team.id}`,
    },
];

</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div>
            <h1>{{ props.team.name }}</h1>
        </div>
        <ScrollArea class="h-[500px] w-full">
            <ScrollBar orientation="horizontal" />
            <div class="w-full bg-red-500">
                <PokemonCard v-for="pokemon in props.team.drafted_pokemon" :key="pokemon.id" :pokemon="pokemon" />
            </div>
        </ScrollArea>
    </AppLayout>
</template>