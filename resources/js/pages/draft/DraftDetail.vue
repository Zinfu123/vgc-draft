<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePoll } from '@inertiajs/vue3';
import {Stepper, StepperItem, StepperTrigger, StepperDescription, StepperSeparator} from '@/components/ui/stepper';
import SelectPokemonForm from '@/components/draft/SelectPokemonForm.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import {Tabs, TabsList, TabsTrigger, TabsContent} from '@/components/ui/tabs';
import { Item } from '@/components/ui/item';



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
    logo: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
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
    pick_number: number;
}

interface DraftOrder {
    id: number;
    pick_number: number;
    status: number;
    team: {
        id: number;
        name: string;
        logo: string;
        draft_points: number;
    };
}

interface UserTeam {
    id: number;
}
interface CurrentPicker {
    team_id: number;
}

interface props {
    league: League;
    teams: Teams[];
    pokemon: Pokemon[];
    draft: Draft;
    costHeaders: CostHeaders[];
    draftOrders: DraftOrder[];
    currentPicker: CurrentPicker;
    userTeam: UserTeam;
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

usePoll(5000, {
    only: ['draftOrders', 'currentPicker', 'userTeam'],
});
</script>

<template>
    <!-- Draft Detail -->
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head>
            <title>{{ props.league.name }} Draft Detail</title>
        </Head>
        <div class="flex flex-col mx-auto items-center mt-10 mb-10 gap-4">
            <h1 class="text-3xl font-bold">{{ props.league.name }} Draft</h1>
        </div>

        <!-- Draft Order -->
        <div class="flex flex-col mx-auto items-center mt-10 mb-10">
        <Stepper class="w-full">
            <StepperItem v-for="draftOrder in props.draftOrders" :key="draftOrder.id" :step="draftOrder.pick_number" :disabled="draftOrder.status === 0">
                <StepperTrigger>
                    <StepperIndicator>
                        <img :src="draftOrder.team.logo" alt="Team Logo" class="w-15 h-15 rounded-full" :class="{ 'opacity-40': draftOrder.status === 0 }" />
                    </StepperIndicator>
                    <div class="items-center">
                        <StepperTitle>{{ draftOrder.team.name }}</StepperTitle>
                        <StepperDescription>Draft Points: {{ draftOrder.team.draft_points }}</StepperDescription>
                    </div>
                </StepperTrigger>
                <StepperSeparator v-if="draftOrder.pick_number !== props.draftOrders.length" class="w-full h-px"/>
            </StepperItem>
        </Stepper>
        </div>
        <!-- Select Pokemon -->
        <div class= "border-1 border-indigo-600 ml-2 mr-2 w-full items-center justify-center">
            <div class="flex flex-col items-center mt-5 mb-5">
                <SelectPokemonForm :pokemon="props.pokemon" :league="props.league" v-if="props.currentPicker.team_id === props.userTeam.id" />
            </div>
        </div>

        <!-- Pokemon Grid -->
    <div class="flex flex-col mx-auto items-center mt-10 mb-10 w-full">
            <Tabs defaultValue="pokemon" class="justify-center items-center w-full mt-8">
                <TabsList>
                    <TabsTrigger value="pokemon" class="dark:data-[state=active]:bg-black/80 w-full">Pokemon</TabsTrigger>
                    <TabsTrigger value="teams" class="dark:data-[state=active]:bg-black/80 w-full">Teams</TabsTrigger>
                </TabsList>
                <TabsContent value="pokemon">
                    <div class="grid grid-cols-5 grid-flow-row gap-2 mt-10">
                    <div v-for="costHeader in props.costHeaders" :key="costHeader">
                        <div class="grid grid-cols-subgrid gap-2">
                            <span class="bg-gray-800/85 dark:bg-muted/85 px-5 py-3 text-sm text-center rounded-md"> Cost: {{ costHeader }}</span>
                        </div>
                            <PokemonCard v-for="pokemon in props.pokemon.filter(pokemon => pokemon.league[0].pivot.cost === costHeader)" :key="pokemon.id" :pokemon="pokemon" />
                    </div>    
                    </div>
                </TabsContent>
                <TabsContent value="teams">
                    <div class="grid grid-cols-5 grid-flow-row gap-2 mt-10">
                        <div v-for="team in props.teams" :key="team.id">
                            <item>
                                <img :src="team.logo" alt="Team Logo" class="w-15 h-15 rounded-full" v-if="team.logo !== null"/>
                                <span class="bg-gray-800/85 dark:bg-muted/85 px-10 py-5 text-sm text-center rounded-md">{{ team.name }} 
                                </span>
                            </item>
                            <div class="flex flex-col items-center mx-auto">
                                <PokemonCard v-for="draft_pick in team.draft_picks" :key="draft_pick.id" :pokemon="draft_pick.league_pokemon.pokemon" :cost="{ cost: draft_pick.league_pokemon.cost }" />
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>