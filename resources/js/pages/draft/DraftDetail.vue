<script setup lang="ts">
import SelectPokemonForm from '@/components/draft/SelectPokemonForm.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Item, ItemContent, ItemHeader } from '@/components/ui/item';
import { Stepper, StepperDescription, StepperItem, StepperTrigger } from '@/components/ui/stepper';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';

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
    team_name: string;
}

interface props {
    league: League;
    teams: Teams[];
    pokemon: Pokemon[];
    draft: Draft;
    costHeaders: number[];
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

// usePoll(10000);

useEchoPublic(`draft.detail.${props.league.id}`, 'DraftDetailEvent', (e) => {
    console.log(e);
    if (e.end_draft === 1) {
        router.get(route('leagues.detail', { league: props.league.id }));
    } else {
        router.visit(route('draft.detail', { league_id: props.league.id }), {
            only: ['draftOrders', 'pokemon', 'teams', 'currentPicker'],
            preserveState: true,
            preserveScroll: true,
        });
    }
});

// useEchoPublic<boolean>(
//     `end.draft.${props.draft.id}`,
//     "EndDraftEvent",
//     (e) => {
//         endDraft.value = e.end_draft === 1;
//         if (endDraft.value) {
//             router.get(route('leagues.detail', { league: props.league.id }));
//         }
//     }
// )
const revertLastPick = () => {
    router.post(route('draft.revert-last-pick'), {
        league_id: props.league.id,
    });
};

const abortDraft = () => {
    router.post(route('draft.abort-draft'), {
        league_id: props.league.id,
    });
};
</script>

<template>
    <!-- Draft Detail -->
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head>
            <title>{{ props.league.name }} Draft Detail</title>
        </Head>
        <div class="mx-auto mt-10 mb-10 flex flex-col items-center gap-4">
            <h1 class="text-3xl font-bold">{{ props.league.name }} Draft</h1>
        </div>
        <div class="flex flex-col items-end gap-8">
            <ButtonGroup v-if="props.userTeam.admin_flag == 1">
                <Button variant="outline" @click="revertLastPick"> Revert Last Pick </Button>
                <Button variant="destructive" @click="abortDraft"> Abort Draft </Button>
            </ButtonGroup>
        </div>
        <!-- Draft Order -->
        <div class="mr-4 ml-2 grid w-full auto-cols-max grid-flow-col auto-rows-max items-center justify-center outline-1 outline-indigo-600">
            <Stepper class="outline-1 outline-green-500">
                <StepperItem
                    v-for="draftOrderItem in props.draftOrders"
                    :key="draftOrderItem.id"
                    :step="draftOrderItem.pick_number"
                    :disabled="draftOrderItem.status === 0"
                    class=""
                >
                    <div class="items-center justify-center">
                        <StepperTrigger class="w-full outline-1 outline-yellow-500">
                            <img :src="draftOrderItem.team.logo" alt="Team Logo" class="inline max-h-15 max-w-15 rounded-full" />
                            <span class="flex flex-row items-center justify-center rounded-md text-center text-sm">
                                {{ draftOrderItem.team.name }}
                            </span>
                            <StepperDescription>Draft Points: {{ draftOrderItem.team.draft_points }}</StepperDescription>
                        </StepperTrigger>
                    </div>
                </StepperItem>
            </Stepper>
            <!-- Select Pokemon -->
            <div class="subgrid row-span-1 row-start-3 mt-2 flex grid-cols-6 grid-rows-2 flex-col items-center outline-1 outline-blue-500">
                <SelectPokemonForm :pokemon="props.pokemon" :league="props.league" v-if="props.currentPicker.team_id === props.userTeam.id" />
                <div v-else class="col-span-2 row-span-1 flex flex-col items-center outline-1 outline-blue-500">
                    <p>Current Picker:</p>
                    {{ props.currentPicker.team_name }}
                    <img
                        :src="props.currentPicker.team.logo"
                        alt="Team Logo"
                        class="h-15 w-15 justify-center rounded-full"
                        v-if="props.currentPicker.team.logo !== null"
                    />
                </div>
            </div>
        </div>

        <!-- Pokemon Grid -->
        <Tabs defaultValue="pokemon" class="mt-4 w-full items-center justify-center">
            <TabsList>
                <TabsTrigger value="pokemon" class="w-full dark:data-[state=active]:bg-black/80">Pokemon</TabsTrigger>
                <TabsTrigger value="teams" class="w-full dark:data-[state=active]:bg-black/80">Teams</TabsTrigger>
            </TabsList>
            <div>
                <TabsContent value="pokemon" class="w-full outline-1 outline-indigo-600">
                    <div class="grid-rows-auto grid auto-rows-max grid-cols-6">
                        <div
                            v-for="costHeader in props.costHeaders"
                            :key="costHeader"
                            class="rounded-md bg-gray-800/85 text-center text-sm dark:bg-muted/85"
                        >
                            <span class="rounded-md bg-gray-800/85 text-center text-sm dark:bg-muted/85"> Cost: {{ costHeader }}</span>
                            <PokemonCard
                                v-for="pokemon in props.pokemon.filter((pokemon) => pokemon.cost === costHeader)"
                                :key="pokemon.id"
                                :pokemon="pokemon"
                                class="mt-2 gap-2"
                            />
                        </div>
                    </div>
                </TabsContent>
            </div>
            <TabsContent value="teams" class="mr-4 ml-4">
                <div class="grid w-full auto-cols-max grid-flow-col auto-rows-max items-center justify-center outline-1 outline-indigo-600">
                    <div v-for="team in props.teams" :key="team.id" class="center subgrid grid-cols-6 outline-1 outline-blue-500">
                        <Item>
                            <ItemHeader class="justify-center">
                                <div class="col-span-6 row-span-1 flex flex-row items-center justify-center outline-1 outline-blue-500">
                                    <span class="flex flex-row items-center justify-center rounded-md text-center text-sm">
                                        <img
                                            :src="team.logo"
                                            alt="Team Logo"
                                            class="col-span-1 row-span-1 h-15 w-15 rounded-full"
                                            v-if="team.logo !== null"
                                        />{{ team.name }}
                                    </span>
                                </div>
                            </ItemHeader>
                            <ItemContent class="col-span-4 row-span-1 row-start-2 items-center justify-center outline-1 outline-blue-500">
                                <PokemonCard
                                    v-for="draft_pick in team.draft_picks"
                                    :key="draft_pick.id"
                                    :pokemon="draft_pick.league_pokemon.pokemon"
                                    :cost="{ cost: draft_pick.league_pokemon.cost }"
                                    class="h-[150px] w-[150px]"
                                />
                            </ItemContent>
                        </Item>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </AppLayout>
</template>
