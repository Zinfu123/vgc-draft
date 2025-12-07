<script setup lang="ts">
import SelectPokemonForm from '@/components/draft/SelectPokemonForm.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { ref, computed } from 'vue';
import { LoaderCircle } from 'lucide-vue-next';



interface League {
    id: number;
    name: string;
}

interface Teams {
    id: number;
    name: string;
    coach: string;
    draft_points: number;
    draft_picks: [{
        id: number;
        league_pokemon: {
            id: number;
            pokemon: {
                id: number;
                name: string;
                sprite_url: string;
                type1: string;
                type2?: string;
            };
            cost: number;
        };
    }];
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
        logo: string | null;
        draft_points: number;
    } | null;
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
        logo: string | null;
        draft_points: number;
        user: {
            name: string;
        };
    } | null;
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
        coach: string | null;
    } | null;
    league_pokemon: {
        id: number;
        name: string;
        cost: number;
        pokemon: {
            id: number;
            name: string;
            sprite_url: string;
            type1: string;
            type2: string;
        };
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
    lastPick: LastPick | null;
}

const props = defineProps<props>();

const minCost = ref<string | number | undefined>(undefined);
const maxCost = ref<string | number | undefined>(undefined);
const selectedPokemon = ref<Pokemon | null>(null);
const isDialogOpen = ref(false);
const isSubmitting = ref(false);

const filteredCostHeaders = computed(() => {
    return props.costHeaders.filter((cost) => {
        const min = minCost.value !== undefined && minCost.value !== '' ? Number(minCost.value) : null;
        const max = maxCost.value !== undefined && maxCost.value !== '' ? Number(maxCost.value) : null;
        
        if (min !== null && cost < min) return false;
        if (max !== null && cost > max) return false;
        return true;
    });
});

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

useEchoPublic(`draft.detail.${props.league.id}`, 'DraftDetailEvent', () => {
    {
        router.visit(route('draft.detail', { league_id: props.league.id }), {
            only: ['draftOrders', 'pokemon', 'teams', 'currentPicker', 'lastPick'],
            preserveState: true,
            preserveScroll: true,
        });
    }
});

useEchoPublic(
    `end.draft.${props.draft.id}`, 'EndDraftEvent', () => {
        router.visit(route('leagues.detail', { league: props.league.id }), {
            preserveState: true,
            preserveScroll: true,
        });
});

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

const openPokemonDialog = (pokemon: Pokemon) => {
    if (isSubmitting.value) return;
    if (props.currentPicker.team?.id === props.userTeam.id) {
        selectedPokemon.value = pokemon;
        isDialogOpen.value = true;
    }
};

const submitPokemonPick = () => {
    if (isSubmitting.value || !selectedPokemon.value) return;
    
    isSubmitting.value = true;
    router.post(
        route('draft.pick'),
        {
            pokemon_id: selectedPokemon.value.id,
            pokemon_name: selectedPokemon.value.name,
            pokemon_cost: selectedPokemon.value.cost,
            league_id: props.league.id,
        },
        {
            onSuccess: () => {
                selectedPokemon.value = null;
                isDialogOpen.value = false;
                isSubmitting.value = false;
                router.visit(route('draft.detail', { league_id: props.league.id }), {
                    only: ['draftOrders', 'pokemon', 'teams', 'currentPicker', 'lastPick'],
                    preserveState: true,
                    preserveScroll: true,
                });
            },
            onError: () => {
                isSubmitting.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-center justify-center">
            <!-- Current Picker - Larger (2 columns) -->
            <div
                class="lg:col-span-2 overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
            >
                <div class="px-6 py-8 flex flex-col items-center justify-center">
                    <h1 class="text-2xl font-semibold mb-6">Current Picker</h1>
                    <img
                        :src="props.currentPicker.team?.logo ?? ''"
                        alt="Team Logo"
                        class="mx-auto size-40 shrink-0 rounded-full bg-gray-300 outline -outline-offset-1 outline-black/5 dark:bg-gray-700 dark:outline-white/10"
                        v-if="props.currentPicker.team?.logo !== null"
                    />
                    <p class="mt-4 text-lg font-medium">Name: {{ props.currentPicker.team?.name ?? 'N/A' }}</p>
                    <p class="mt-1 text-base text-gray-500 dark:text-gray-400">Coach: {{ props.currentPicker.team?.user?.name ?? 'N/A' }}</p>
                </div>
            </div>
            
            <!-- Last Pick - Smaller (1 column) -->
            <div v-if="props.lastPick !== null && props.lastPick.team !== null" class="lg:col-span-1 overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10">
                <div class="px-4 py-4">
                    <h1 class="text-lg font-semibold mb-3 text-center">Last Pick</h1>
                    <div class="flex flex-row items-center justify-center gap-3">
                        <!-- Team Info -->
                        <div class="flex flex-col items-center justify-center flex-shrink-0">
                            <img
                                :src="props.lastPick.team.logo"
                                alt="Team Logo"
                                class="mx-auto size-16 shrink-0 rounded-full bg-gray-300 outline -outline-offset-1 outline-black/5 dark:bg-gray-700 dark:outline-white/10"
                                v-if="props.lastPick.team.logo !== null"
                            />
                            <p class="mt-1 text-md font-medium text-center">Name: {{ props.lastPick.team.name }}</p>
                        </div>
                        <!-- Pokemon Card -->
                        <div v-if="props?.lastPick?.league_pokemon?.pokemon" class="flex items-center justify-center scale-75 flex-shrink-0">
                            <PokemonCard :pokemon="props?.lastPick?.league_pokemon?.pokemon" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Select Pokemon -->
        <div class="flex flex-col items-center justify-center">
            <SelectPokemonForm :pokemon="props.pokemon" :league="props.league" v-if="props.currentPicker.team?.id === props.userTeam.id" />
            <div v-else class="col-span-2 row-span-1 flex flex-col items-center outline-1 outline-blue-500">
                <p>Current Picker:</p>
                {{ props.currentPicker.team?.name ?? 'N/A' }}
            </div>
        </div>
        <!-- Draft Order -->
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <ScrollArea class="h-[600px] w-full">
                <div class="flex flex-col items-center">
                    <div
                        class="mb-2 overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
                    >
                        <h1 class="mb-2 text-center text-2xl font-bold">Draft Order</h1>
                        <ul role="list" class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 justify-items-center">
                            <li
                                v-for="draftOrderItem in props.draftOrders"
                                :key="draftOrderItem.id"
                                class="w-full max-w-[200px] flex flex-col divide-y divide-gray-200 rounded-lg bg-white text-center shadow-sm dark:divide-white/10 dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
                            >
                                <div class="flex flex-col p-4 sm:p-6">
                                    <img
                                        v-if="draftOrderItem.team && draftOrderItem.team.logo !== null"
                                        class="mx-auto size-20 sm:size-24 md:size-28 shrink-0 rounded-full bg-gray-300 outline -outline-offset-1 outline-black/5 dark:bg-gray-700 dark:outline-white/10"
                                        :src="draftOrderItem.team.logo"
                                        alt=""
                                    />
                                    <h3 class="mt-3 sm:mt-4 text-sm font-medium text-gray-900 dark:text-white">{{ draftOrderItem.team?.name ?? 'N/A' }}</h3>
                                    <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Round: {{ draftOrderItem.coach }}</p>
                                    <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Draft Points: {{ draftOrderItem.team?.draft_points ?? 'N/A' }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </ScrollArea>
        </div>
        <!-- Pokemon Grid -->
        <Tabs defaultValue="pokemon" class="mt-4 w-full items-center justify-center">
            <TabsList>
                <TabsTrigger value="pokemon" class="w-full dark:data-[state=active]:bg-black/80">Pokemon</TabsTrigger>
                <TabsTrigger value="teams" class="w-full dark:data-[state=active]:bg-black/80">Teams</TabsTrigger>
            </TabsList>
            <div>
                <TabsContent value="pokemon" class="w-full outline-1 outline-indigo-600">
                    <div class="mb-6 flex flex-col gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-800/50">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <Label for="min-cost">Minimum Cost</Label>
                                <Input
                                    id="min-cost"
                                    type="number"
                                    v-model="minCost"
                                    placeholder="Min"
                                    min="0"
                                />
                            </div>
                            <div class="flex-1">
                                <Label for="max-cost">Maximum Cost</Label>
                                <Input
                                    id="max-cost"
                                    type="number"
                                    v-model="maxCost"
                                    placeholder="Max"
                                    min="0"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-6">
                        <div
                            v-for="costHeader in filteredCostHeaders"
                            :key="costHeader"
                            class="flex flex-col gap-4"
                        >
                            <div class="sticky top-0 z-10 rounded-md border border-gray-200 bg-gray-100 px-4 py-3 text-center text-lg font-semibold text-gray-900 dark:border-white/10 dark:bg-gray-800 dark:text-white">
                                Cost: {{ costHeader }}
                            </div>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                <div
                                    v-for="pokemon in props.pokemon.filter((pokemon) => pokemon.cost === costHeader)"
                                    :key="pokemon.id"
                                    @click="openPokemonDialog(pokemon)"
                                    :class="[
                                        props.currentPicker.team?.id === props.userTeam.id && !isSubmitting 
                                            ? 'cursor-pointer transition-transform hover:scale-105' 
                                            : '',
                                        isSubmitting ? 'pointer-events-none opacity-50' : ''
                                    ]"
                                >
                                    <PokemonCard :pokemon="pokemon" />
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </div>
            <TabsContent value="teams" class="mr-4 ml-4">
                <ScrollArea class="h-[600px] w-full">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="team in props.teams"
                        :key="team.id"
                        class="flex flex-col rounded-lg bg-white shadow-sm dark:bg-gray-800/50 dark:shadow-none dark:outline dark:-outline-offset-1 dark:outline-white/10"
                    >
                        <div class="flex flex-col items-center border-b border-gray-200 px-4 py-3 dark:border-white/10">
                            <img
                                v-if="team.logo"
                                :src="team.logo"
                                alt="Team Logo"
                                class="mb-2 size-16 rounded-full bg-gray-300 outline -outline-offset-1 outline-black/5 dark:bg-gray-700 dark:outline-white/10"
                            />
                            <h3 class="text-center text-sm font-semibold text-gray-900 dark:text-white">{{ team.name }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Draft Points: {{ team.draft_points }}</p>
                        </div>
                        <div class="flex flex-col gap-2 p-4">
                            <PokemonCard
                                v-for="draft_pick in team.draft_picks"
                                :key="draft_pick.id"
                                :pokemon="{
                                    name: draft_pick.league_pokemon.pokemon.name,
                                    sprite_url: draft_pick.league_pokemon.pokemon.sprite_url,
                                    type1: draft_pick.league_pokemon.pokemon.type1,
                                    type2: draft_pick.league_pokemon.pokemon.type2 ?? '',
                                    cost: draft_pick.league_pokemon.cost,
                                }"
                            />
                        </div>
                    </div>
                    </div>
                </ScrollArea>
                <!-- <div v-for="key in Object.keys(props.teams)" :key="key" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
                </div> -->
            </TabsContent>
        </Tabs>
        
        <!-- Pokemon Selection Dialog -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirm Pokemon Selection</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to draft this Pokemon?
                    </DialogDescription>
                </DialogHeader>
                <div v-if="selectedPokemon" class="flex flex-col items-center gap-4 py-4">
                    <PokemonCard :pokemon="selectedPokemon" />
                    <div class="text-center">
                        <p class="text-lg font-semibold capitalize">{{ selectedPokemon.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Cost: {{ selectedPokemon.cost }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="() => { if (!isSubmitting) isDialogOpen = false; }" :disabled="isSubmitting">Cancel</Button>
                    <Button @click="submitPokemonPick" :disabled="isSubmitting">
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Confirm Selection
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
