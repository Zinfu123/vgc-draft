<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePoll } from '@inertiajs/vue3';
import {Stepper, StepperItem, StepperTrigger, StepperDescription, StepperSeparator} from '@/components/ui/stepper';
import SelectPokemonForm from '@/components/draft/SelectPokemonForm.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import {Tabs, TabsList, TabsTrigger, TabsContent} from '@/components/ui/tabs';
import { Item, ItemHeader, ItemContent} from '@/components/ui/item';    
import {Separator} from '@/components/ui/separator';
import { ButtonGroup } from '@/components/ui/button-group';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';


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
    cost: number;
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
    admin_flag: number;
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

usePoll(10000);

const revertLastPick = () => {
    router.post(route('draft.revert-last-pick'), {
        league_id: props.league.id,
    });
}

const abortDraft = () => {
    router.post(route('draft.abort-draft'), {
        league_id: props.league.id,
    });
}
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
        <div class="flex flex-col gap-8 items-end">
            <ButtonGroup v-if="props.userTeam.admin_flag == 1">
                <Button variant="outline" @click="revertLastPick">
                    Revert Last Pick
                </Button>
                <Button variant="destructive" @click="abortDraft">
                    Abort Draft
                </Button>
        </ButtonGroup>
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
    <div class="flex flex-col items-center w-full">
            <Tabs defaultValue="pokemon" class="justify-center items-center w-full mt-4">
                <TabsList>
                    <TabsTrigger value="pokemon" class="dark:data-[state=active]:bg-black/80 w-full">Pokemon</TabsTrigger>
                    <TabsTrigger value="teams" class="dark:data-[state=active]:bg-black/80 w-full">Teams</TabsTrigger>
                </TabsList>
                <TabsContent value="pokemon">
                    <div class="grid grid-cols-5 grid-flow-row gap-2 mt-10">
                    <div v-for="costHeader in props.costHeaders" :key="costHeader" class="gap-2">
                        <div class="grid grid-cols-subgrid gap-2">
                            <span class="bg-gray-800/85 dark:bg-muted/85 px-5 py-3 text-sm text-center rounded-md"> Cost: {{ costHeader }}</span>
                        </div>
                            <PokemonCard v-for="pokemon in props.pokemon.filter(pokemon => pokemon.cost === costHeader)" :key="pokemon.id" :pokemon="pokemon" class="gap-2 mt-2" />
                    </div>    
                    </div>
                </TabsContent>
                <TabsContent value="teams" class="ml-4 mr-4">
                    <div class="outline-1 outline-gray-200 mx-auto grid grid-cols-6 grid-flow-row">
                            <div v-for="team in props.teams" :key="team.id" class="outline-1 outline-blue-500 center">
                                <Item>
                                <ItemHeader class="justify-center">
                                    <div class="flex flex-row items-center justify-center">
                                        <img :src="team.logo" alt="Team Logo" class="w-15 h-15 rounded-full" v-if="team.logo !== null"/>
                                        <span class="bg-gray-800/85 dark:bg-muted/85 px-10 py-5 text-sm text-center rounded-md">{{ team.name }}</span>
                                    </div>
                                </ItemHeader>
                                <ItemContent class="justify-center items-center">
                                <PokemonCard v-for="draft_pick in team.draft_picks" :key="draft_pick.id" :pokemon="draft_pick.league_pokemon.pokemon" :cost="{ cost: draft_pick.league_pokemon.cost }"/>
                            </ItemContent>
                            </Item>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>