<script setup lang="ts">
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Team {
    id: number;
    name: string;
    logo: string;
    coach: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    pokemon: Array<{
        id: number;
        name: string;
        cost: number;
        pokemon: {
            sprite_url: string;
            type1: string;
            type2?: string;
        };
    }>;
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
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 md:px-6">
            <div class="flex flex-col items-center justify-center rounded-lg border border-gray-200 p-6 dark:border-gray-700">
                <h1 class="flex flex-col items-center gap-2 text-center">
                    <img :src="props.team.logo" alt="Team Logo" class="inline size-28 rounded-full object-cover md:size-32" />
                    <span class="text-2xl font-bold">{{ props.team.name }}</span>
                    <span class="text-muted-foreground">Coach: {{ props.team.coach }}</span>
                </h1>
            </div>
            <div class="flex flex-col gap-6 lg:flex-row lg:items-stretch lg:justify-center">
                <div class="flex w-full flex-col items-center rounded-lg border border-gray-200 p-4 dark:border-gray-700 lg:w-1/3">
                    <h2 class="mb-4 text-xl font-bold md:text-2xl">Drafted Pokemon</h2>
                    <div class="flex w-full flex-wrap items-center justify-center gap-2">
                        <PokemonCard
                            v-for="pokemon in props.team.pokemon"
                            :key="pokemon.id"
                            :pokemon="{ name: pokemon.name, ...pokemon.pokemon, cost: pokemon.cost, type2: pokemon.pokemon.type2 || '-' }"
                        />
                    </div>
                </div>
                <div class="flex w-full flex-col items-center rounded-lg border border-gray-200 p-4 dark:border-gray-700 lg:w-1/3">
                    <h2 class="text-xl font-bold md:text-2xl">Upcoming Matches</h2>
                </div>
                <div class="flex w-full flex-col items-center rounded-lg border border-gray-200 p-4 dark:border-gray-700 lg:w-1/3">
                    <h2 class="text-xl font-bold md:text-2xl">Record</h2>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
