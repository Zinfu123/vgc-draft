<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsList, TabsTrigger, TabsContent} from '@/components/ui/tabs';
import SelectPokemonForm from '@/components/draft/SelectPokemonForm.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import {
  Item,
  ItemHeader,
  ItemContent,
  ItemGroup,
} from "@/components/ui/item"

interface League {
    id: number;
    name: string;
}

interface Teams {
    id: number;
    name: string;
    coach: string;
    draft_points: number;
    draft_picks: [];
}

interface Pokemon {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    league: Array<{
        pivot: {
            cost: number;
        };
    }>;
}

interface CostHeaders {
    costHeaders: number;
}

interface Draft {
    id: number | null;
    round_number: number;
}

interface props {
    league: League;
    teams: Teams[];
    pokemon: Pokemon[];
    draft: Draft;
    costHeaders: CostHeaders[];
}

const props = defineProps<props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.league.name,
        href: `/leagues/${props.league.id}`,
    },
    {
        title: 'Draft Detail',
        href: `/draft/${props.league.id}`,
    },
];

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head>
            <title>{{ props.league.name }} Draft Detail</title>
        </Head>
        <div class="flex flex-col mx-auto items-center mt-10 mb-10 gap-4">
            <h1 class="text-3xl font-bold">{{ props.league.name }} Draft</h1>
        </div>
        <div class= "border-1 border-indigo-600 ml-2 mr-2">
        <ItemGroup class="grid grid-cols-3 gap-4 h-96 ml-5 mt-4 mb-4">
            <ItemGroup variant="outline" class="h-96 grid grid-rows-3 gap-4">
            <Item variant="outline" class="items-center">
                <ItemHeader class="justify-center">
                    <span> Teams Info:</span>
                </ItemHeader>
                <ItemContent>
                    <div class="object-bottom">
                        <span v-for="team in props.teams" :key="team.id" class="grid grid-flow-col">{{ team.name }}{{ team.draft_points }}</span>
                    </div>
                </ItemContent>
            </Item>
                <Item class="object-bottom">
                <ItemContent class="object-bottom">
                    <div class="object-bottom">
                        <SelectPokemonForm :pokemon="props.pokemon" :league="props.league" />
                    </div>
                </ItemContent>
        </Item>
        </ItemGroup>
        <Item variant="outline" class="justify-start items-start">
            <ItemContent>
                <span> Turn Order:</span>
            </ItemContent>
        </Item>
        </ItemGroup>
        </div>
        <div class="grid grid-cols-3 grid-flow-row gap-2 mt-10">
        <div v-for="costHeader in props.costHeaders" :key="costHeader">
        <div class="grid grid-cols-subgrid gap-2">
            <span class="bg-gray-800/85 dark:bg-muted/85 px-5 py-3 text-sm text-center rounded-md"> Cost: {{ costHeader }}</span>
            <PokemonCard v-for="pokemon in props.pokemon.filter(pokemon => pokemon.league[0].pivot.cost === costHeader)" :key="pokemon.id" :pokemon="pokemon" />
        </div>
        </div>
        </div>
    </AppLayout>
</template>