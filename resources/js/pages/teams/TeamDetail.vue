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
    draft_picks: [];
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
        <div class="flex flex-col items-center justify-center border-1 border-indigo-600">
            <h1 class="flex flex-col items-center justify-center">
                <img :src="props.team.logo" alt="Team Logo" class="inline h-30 w-30 rounded-full" />
                <text class="">{{ props.team.name }}</text>
                <span class="self-bottom"> Coach:{{ props.team.coach }} </span>
            </h1>
        </div>
        <div class="items-top flex flex-row justify-center border-1 border-green-600">
            <div class="flex w-1/3 flex-wrap items-center justify-center">
                <div class="flex flex-col items-center border-1 border-red-600">
                    <h2 class="mb-4 object-top text-2xl font-bold">Drafted Pokemon</h2>
                    <div class="flex flex-row flex-wrap items-center justify-center gap-2">
                        <PokemonCard
                            v-for="draft_pick in props.team.draft_picks"
                            :key="draft_pick.id"
                            :pokemon="draft_pick.league_pokemon.pokemon"
                            :cost="{ cost: draft_pick.league_pokemon.cost }"
                        />
                    </div>
                </div>
            </div>
            <div class="items-top container flex w-1/3 flex-wrap justify-center border-1 border-blue-600">
                <h2 class="object-top text-2xl font-bold">Upcoming Matches</h2>
            </div>
            <div class="items-top container flex w-1/3 flex-wrap justify-center border-1 border-blue-600">
                <h2 class="object-top text-2xl font-bold">Record</h2>
            </div>
        </div>
    </AppLayout>
</template>
