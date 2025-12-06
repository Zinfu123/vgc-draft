<script setup lang="ts">
import SelectPokemonForm from '@/components/draft/SelectPokemonForm.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Item, ItemContent, ItemHeader } from '@/components/ui/item';
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
    [key: number]: {
        id: number;
        name: string;
        coach: string;
        draft_points: number;
        draft_picks: [];
        logo: string;
        set_wins: number;
        set_losses: number;
        victory_points: number;
    };
}

interface Pokemon {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    cost: number;
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
    id: number;
    round_number: number;
    pick_number: number;
    team: {
        id: number;
        name: string;
        logo: string;
        draft_points: number;
    };
}

interface LastPick {
    id: number;
    round_number: number;
    pick_number: number;
    team: {
        id: number;
        name: string;
        draft_points: number;
        logo: string;
        coach: string;
    };
    league_pokemon: {
        id: number;
        name: string;
        sprite_url: string;
        type1: string;
        type2: string;
        cost: number;
    };
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
    lastPick: LastPick;
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

useEchoPublic(`draft.detail.${props.league.id}`, 'DraftDetailEvent', (e: any) => {
    if (e && e.end_draft === 1) {
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
        <div class="flex flex-col items-center justify-center">
            <div class="flex flex-row items-center justify-center gap-20">
            <div
                class="mb-4 overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
            >
                <div class="px-4 py-5 sm:p-6 flex flex-col items-center justify-center">
                    <h1>Current Picker</h1>
                    <img
                        :src="props.currentPicker.team.logo"
                        alt="Team Logo"
                        class="mx-auto size-32 shrink-0 rounded-full bg-gray-300 outline -outline-offset-1 outline-black/5 dark:bg-gray-700 dark:outline-white/10"
                        v-if="props.currentPicker.team.logo !== null"
                    />
                    <p>Name: {{ props.currentPicker.team.name }}</p>
                    <p>Draft Points: {{ props.currentPicker.team.draft_points }}</p>
                </div>
            </div>
            
            <div v-if="props.lastPick !== null" class="flex flex-col items-center justify-center mb-4 overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10">
                <div class="px-4 py-5 sm:p-6 flex flex-col items-center justify-center">
                <h1>Last Pick</h1>
                    <img
                        :src="props.lastPick.team.logo"
                        alt="Team Logo"
                        class="mx-auto size-32 shrink-0 rounded-full bg-gray-300 outline -outline-offset-1 outline-black/5 dark:bg-gray-700 dark:outline-white/10"
                        v-if="props.lastPick.team.logo !== null"
                    />
                    <p>Name: {{ props.lastPick.team.name }}</p>
                    <p>Draft Points: {{ props.lastPick.team.draft_points }}</p>
                    </div>
                </div>
                <PokemonCard :pokemon="props.lastPick.league_pokemon.pokemon" />
            </div>

            <!-- Select Pokemon -->
            <div class="flex flex-col items-center justify-center">
                <SelectPokemonForm :pokemon="props.pokemon" :league="props.league" v-if="props.currentPicker.team.id === props.userTeam.id" />
                <div v-else class="col-span-2 row-span-1 flex flex-col items-center outline-1 outline-blue-500">
                    <p>Current Picker:</p>
                    {{ props.currentPicker.team.name }}
                </div>
            </div>
            <!-- Draft Order -->
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="flex flex-col items-center">
                    <div
                        class="mb-2 overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
                    >
                        <h1 class="mb-2 text-center text-2xl font-bold">Draft Order</h1>
                        <ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                            <li
                                v-for="draftOrderItem in props.draftOrders"
                                :key="draftOrderItem.id"
                                class="col-span-1 flex flex-col divide-y divide-gray-200 rounded-lg bg-white text-center shadow-sm dark:divide-white/10 dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
                            >
                                <div class="flex flex-1 flex-col p-8">
                                    <img
                                        class="mx-auto size-32 shrink-0 rounded-full bg-gray-300 outline -outline-offset-1 outline-black/5 dark:bg-gray-700 dark:outline-white/10"
                                        :src="draftOrderItem.team.logo"
                                        alt=""
                                    />
                                    <h3 class="mt-6 text-sm font-medium text-gray-900 dark:text-white">{{ draftOrderItem.team.name }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Draft Points: {{ draftOrderItem.team.draft_points }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>
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
                <div v-for="key in Object.keys(props.teams)" :key="key" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="team in props.teams[key]"
                        :key="team.id"
                        class="col-span-1 divide-y divide-gray-200 rounded-lg bg-white shadow-sm dark:divide-white/10 dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
                    >
                        <Item class="w-max">
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
