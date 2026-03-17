<script setup lang="ts">
import DraftPicksPanel from '@/components/draft/DraftPicksPanel.vue';
import DraftTeamsPanel from '@/components/draft/DraftTeamsPanel.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import PokemonFilter, { type PokemonFilters } from '@/components/draft/PokemonFilter.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { Ban, CheckCircle, LoaderCircle, ShieldBan, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface League {
    id: number;
    name: string;
}

interface DraftConfig {
    ban_enabled: boolean;
    bans_per_user: number;
    minimum_cost_to_ban: number;
    draft_points: number;
}

interface Pokemon {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string;
    cost: number;
    banned: number | boolean;
    is_drafted: number | boolean;
}

interface TeamSummary {
    id: number;
    name: string;
    logo: string | null;
    draft_points: number;
}

interface Draft {
    id: number | null;
    round_number: number;
    pick_number: number;
    status: number;
}

interface DraftOrder {
    id: number;
    pick_number: number;
    round_number: number;
    status: number;
    team: TeamSummary | null;
}

interface BanOrderItem {
    id: number;
    ban_number: number;
    round_number: number;
    status: number;
    is_last_ban: number;
    team: TeamSummary | null;
}

interface UserTeam {
    id: number;
    admin_flag: number;
}

interface CurrentPicker {
    id: number;
    pick_number: number;
    round_number: number;
    team: (TeamSummary & { coach?: string | null }) | null;
}

interface CurrentBanner {
    id: number;
    ban_number: number;
    round_number: number;
    team: (TeamSummary & { coach?: string | null }) | null;
}

interface LastPick {
    id: number;
    round_number: number;
    pick_number: number;
    team: TeamSummary | null;
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

interface LastBan {
    id: number;
    round_number: number;
    team: TeamSummary | null;
    pokedex: {
        id: number;
        name: string;
        sprite_url: string;
        type1: string;
        type2: string;
    } | null;
}

interface Team {
    id: number;
    name: string;
    draft_points: number;
    draft_picks: {
        id: number;
        league_pokemon: {
            id: number;
            cost: number;
            pokemon: {
                id: number;
                name: string;
                sprite_url: string;
                type1: string;
                type2: string;
            };
        };
    }[];
    logo: string | null;
    set_wins: number;
    set_losses: number;
    victory_points: number;
}

interface BanEntry {
    id: number;
    round_number: number;
    team: {
        id: number;
        name: string;
        logo: string | null;
    } | null;
    pokedex: {
        id: number;
        name: string;
        type1: string;
        type2: string;
    } | null;
}

interface Props {
    league: League;
    draftConfig: DraftConfig | null;
    teams: Team[];
    pokemon: Pokemon[];
    draft: Draft | null;
    costHeaders: number[];
    draftOrders: DraftOrder[];
    banOrders: BanOrderItem[];
    currentPicker: CurrentPicker | null;
    currentBanner: CurrentBanner | null;
    userTeam: UserTeam | null;
    lastPick: LastPick | null;
    lastBan: LastBan | null;
    allBans: BanEntry[];
}

const props = defineProps<Props>();

const filters = ref<PokemonFilters>({ name: '', minCost: undefined, maxCost: undefined });
const selectedPokemon = ref<Pokemon | null>(null);
const isDialogOpen = ref(false);
const isSubmitting = ref(false);

const isBanPhase = computed(() => props.draft?.status === 2);
const isDraftPhase = computed(() => props.draft?.status === 1);

const isMyTurnToBan = computed(() => isBanPhase.value && props.currentBanner?.team?.id === props.userTeam?.id);
const isMyTurnToPick = computed(() => isDraftPhase.value && props.currentPicker?.team?.id === props.userTeam?.id);
const isMyTurn = computed(() => isMyTurnToBan.value || isMyTurnToPick.value);

const minCostToBan = computed(() => props.draftConfig?.minimum_cost_to_ban ?? 0);

const userTeamData = computed(() => props.teams.find((t) => t.id === props.userTeam?.id) ?? null);

const applyFilters = (pokemon: Pokemon[]) =>
    pokemon.filter((p) => {
        if (filters.value.name && !p.name.toLowerCase().includes(filters.value.name.toLowerCase())) return false;
        if (filters.value.minCost !== undefined && p.cost < filters.value.minCost) return false;
        if (filters.value.maxCost !== undefined && p.cost > filters.value.maxCost) return false;
        return true;
    });

const filteredPokemon = computed(() => applyFilters(props.pokemon));

const filteredCostHeaders = computed(() =>
    [...new Set(filteredPokemon.value.map((p) => p.cost))].sort((a, b) => b - a),
);


const isClickable = (pokemon: Pokemon) => {
    if (isSubmitting.value || pokemon.banned || pokemon.is_drafted) return false;
    if (isBanPhase.value && isMyTurnToBan.value) return pokemon.cost >= minCostToBan.value;
    if (isDraftPhase.value && isMyTurnToPick.value) return true;
    return false;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Draft', href: `/draft/${props.league.id}` },
];

const reloadKeys = ['banOrders', 'draftOrders', 'pokemon', 'costHeaders', 'teams', 'currentBanner', 'currentPicker', 'lastBan', 'lastPick', 'draft'];

useEchoPublic(`draft.detail.${props.league.id}`, 'DraftDetailEvent', () => {
    router.visit(route('draft.detail', { league_id: props.league.id }), {
        only: reloadKeys,
        preserveState: true,
        preserveScroll: true,
    });
});

useEchoPublic(`end.draft.${props.draft?.id ?? 0}`, 'EndDraftEvent', () => {
    router.visit(route('leagues.detail', { league: props.league.id }), {
        preserveState: true,
        preserveScroll: true,
    });
});

const revertLastPick = () => router.post(route('draft.revert-last-pick'), { league_id: props.league.id });
const abortDraft = () => router.post(route('draft.abort-draft'), { league_id: props.league.id });

const openActionDialog = (pokemon: Pokemon) => {
    if (!isClickable(pokemon)) return;
    selectedPokemon.value = pokemon;
    isDialogOpen.value = true;
};

const submitAction = () => {
    if (isSubmitting.value || !selectedPokemon.value) return;
    isSubmitting.value = true;

    const routeName = isBanPhase.value ? 'draft.ban' : 'draft.pick';
    const payload = isBanPhase.value
        ? { pokemon_id: selectedPokemon.value.id, league_id: props.league.id }
        : {
              pokemon_id: selectedPokemon.value.id,
              pokemon_name: selectedPokemon.value.name,
              pokemon_cost: selectedPokemon.value.cost,
              league_id: props.league.id,
          };

    router.post(route(routeName), payload, {
        onSuccess: () => {
            selectedPokemon.value = null;
            isDialogOpen.value = false;
            isSubmitting.value = false;
            router.visit(route('draft.detail', { league_id: props.league.id }), {
                only: reloadKeys,
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
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head>
            <title>{{ props.league.name }} Draft</title>
        </Head>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl dark:text-white">{{ props.league.name }} Draft</h1>
                    <span
                        v-if="isBanPhase"
                        class="inline-flex items-center gap-1.5 rounded-full bg-orange-100 px-3 py-1 text-sm font-semibold text-orange-800 dark:bg-orange-900/30 dark:text-orange-400"
                    >
                        <ShieldBan class="size-4" /> Ban Phase
                    </span>
                    <span
                        v-else-if="isDraftPhase"
                        class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                    >
                        <Swords class="size-4" /> Draft Phase
                    </span>
                    <span
                        v-if="userTeamData"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-semibold text-gray-700 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200"
                    >
                        {{ userTeamData.name }} &mdash; {{ userTeamData.draft_points }} pts
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <DraftPicksPanel :teams="props.teams" :bans="props.allBans" />
                    <DraftTeamsPanel :teams="props.teams" :bans="props.allBans" />
                    <ButtonGroup v-if="props.userTeam?.admin_flag === 1">
                        <Button variant="outline" size="sm" @click="revertLastPick">Revert Last Pick</Button>
                        <Button variant="destructive" size="sm" @click="abortDraft">Abort Draft</Button>
                    </ButtonGroup>
                </div>
            </div>

            <!-- Status Cards -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Current Actor -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ isBanPhase ? 'Currently Banning' : 'Currently Picking' }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-4 px-5 py-4">
                        <template v-if="isBanPhase">
                            <img
                                v-if="props.currentBanner?.team?.logo"
                                :src="props.currentBanner.team.logo"
                                class="size-14 rounded-full bg-gray-200 object-cover ring-2 ring-orange-400 dark:bg-gray-700"
                                alt=""
                            />
                            <div
                                v-else
                                class="flex size-14 shrink-0 items-center justify-center rounded-full bg-orange-100 ring-2 ring-orange-400 dark:bg-orange-900/30"
                            >
                                <ShieldBan class="size-6 text-orange-500" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                    {{ props.currentBanner?.team?.name ?? 'N/A' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Ban {{ props.currentBanner?.ban_number }} &mdash; Round {{ props.currentBanner?.round_number }} of
                                    {{ props.draftConfig?.bans_per_user }}
                                </p>
                            </div>
                        </template>
                        <template v-else>
                            <img
                                v-if="props.currentPicker?.team?.logo"
                                :src="props.currentPicker.team.logo"
                                class="size-14 rounded-full bg-gray-200 object-cover ring-2 ring-blue-400 dark:bg-gray-700"
                                alt=""
                            />
                            <div
                                v-else
                                class="flex size-14 shrink-0 items-center justify-center rounded-full bg-blue-100 ring-2 ring-blue-400 dark:bg-blue-900/30"
                            >
                                <Swords class="size-6 text-blue-500" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                    {{ props.currentPicker?.team?.name ?? 'N/A' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Round {{ props.currentPicker?.round_number }} &mdash; Pick {{ props.draft?.pick_number }}
                                </p>
                            </div>
                        </template>
                        <span
                            v-if="isMyTurn"
                            class="shrink-0 animate-pulse rounded-full px-3 py-1 text-xs font-bold"
                            :class="
                                isBanPhase
                                    ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                    : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                            "
                        >
                            YOUR TURN
                        </span>
                    </div>
                </div>

                <!-- Last Action -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Action</h2>
                    </div>
                    <!-- Last Ban -->
                    <div v-if="isBanPhase && props.lastBan" class="flex items-center gap-4 px-5 py-4">
                        <img
                            v-if="props.lastBan.team?.logo"
                            :src="props.lastBan.team.logo"
                            class="size-12 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                            alt=""
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ props.lastBan.team?.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">banned</p>
                            <p class="font-semibold capitalize text-red-600 dark:text-red-400">{{ props.lastBan.pokedex?.name }}</p>
                        </div>
                        <img
                            v-if="props.lastBan.pokedex"
                            :src="'https://raw.githubusercontent.com/Autumnchi/coloured-home-sprites/main/' + props.lastBan.pokedex.name + '.png'"
                            :alt="props.lastBan.pokedex.name"
                            class="size-16 rounded-lg bg-gray-100 object-contain dark:bg-gray-700"
                        />
                    </div>
                    <!-- Last Pick -->
                    <div v-else-if="!isBanPhase && props.lastPick" class="flex items-center gap-4 px-5 py-4">
                        <img
                            v-if="props.lastPick.team?.logo"
                            :src="props.lastPick.team.logo"
                            class="size-12 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                            alt=""
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ props.lastPick.team?.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">picked</p>
                            <p class="font-semibold capitalize text-blue-600 dark:text-blue-400">
                                {{ props.lastPick.league_pokemon.pokemon.name }}
                            </p>
                        </div>
                        <div class="shrink-0 scale-75 origin-right">
                            <PokemonCard
                                :pokemon="{
                                    ...props.lastPick.league_pokemon.pokemon,
                                    cost: props.lastPick.league_pokemon.cost,
                                }"
                            />
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-center px-5 py-8 text-sm text-gray-400 dark:text-gray-500">No actions yet</div>
                </div>
            </div>

            <!-- Order Row -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800/50">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-white/10">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <span v-if="isBanPhase">Ban Order &mdash; Round {{ props.banOrders[0]?.round_number ?? 1 }}</span>
                        <span v-else>Draft Order &mdash; Round {{ props.draft?.round_number ?? 1 }}</span>
                    </h2>
                </div>
                <ScrollArea class="w-full">
                    <div class="flex gap-3 px-5 py-4">
                        <!-- Ban order items -->
                        <template v-if="isBanPhase">
                            <div
                                v-for="banOrder in props.banOrders"
                                :key="banOrder.id"
                                class="flex min-w-[90px] flex-col items-center gap-1.5 rounded-lg p-3 text-center transition-colors"
                                :class="{
                                    'opacity-50 bg-gray-100 dark:bg-gray-700/50': banOrder.status === 0,
                                    'bg-orange-50 ring-2 ring-orange-400 dark:bg-orange-900/20': banOrder.status === 1 && banOrder.team?.id === props.currentBanner?.team?.id,
                                    'bg-gray-50 dark:bg-gray-800/30': banOrder.status === 1 && banOrder.team?.id !== props.currentBanner?.team?.id,
                                }"
                            >
                                <img
                                    v-if="banOrder.team?.logo"
                                    :src="banOrder.team.logo"
                                    class="size-10 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                    alt=""
                                />
                                <div v-else class="size-10 rounded-full bg-gray-200 dark:bg-gray-700" />
                                <p class="w-full truncate text-xs font-medium text-gray-900 dark:text-white">{{ banOrder.team?.name }}</p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ banOrder.team?.draft_points }} pts</span>
                                <span v-if="banOrder.status === 0" class="text-xs text-gray-400 dark:text-gray-500">✓ Done</span>
                                <span
                                    v-else-if="banOrder.team?.id === props.currentBanner?.team?.id"
                                    class="text-xs font-bold text-orange-600 dark:text-orange-400"
                                    >Banning</span
                                >
                                <span v-else class="text-xs text-gray-400 dark:text-gray-500">Waiting</span>
                            </div>
                        </template>
                        <!-- Draft order items -->
                        <template v-else>
                            <div
                                v-for="draftOrder in props.draftOrders"
                                :key="draftOrder.id"
                                class="flex min-w-[90px] flex-col items-center gap-1.5 rounded-lg p-3 text-center transition-colors"
                                :class="{
                                    'opacity-50 bg-gray-100 dark:bg-gray-700/50': draftOrder.status === 0,
                                    'bg-blue-50 ring-2 ring-blue-400 dark:bg-blue-900/20': draftOrder.status === 1 && draftOrder.team?.id === props.currentPicker?.team?.id,
                                    'bg-gray-50 dark:bg-gray-800/30': draftOrder.status === 1 && draftOrder.team?.id !== props.currentPicker?.team?.id,
                                }"
                            >
                                <img
                                    v-if="draftOrder.team?.logo"
                                    :src="draftOrder.team.logo"
                                    class="size-10 rounded-full bg-gray-200 object-cover dark:bg-gray-700"
                                    alt=""
                                />
                                <div v-else class="size-10 rounded-full bg-gray-200 dark:bg-gray-700" />
                                <p class="w-full truncate text-xs font-medium text-gray-900 dark:text-white">{{ draftOrder.team?.name }}</p>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ draftOrder.team?.draft_points }} pts</span>
                                <span v-if="draftOrder.status === 0" class="text-xs text-gray-400 dark:text-gray-500">✓ Done</span>
                                <span
                                    v-else-if="draftOrder.team?.id === props.currentPicker?.team?.id"
                                    class="text-xs font-bold text-blue-600 dark:text-blue-400"
                                    >Picking</span
                                >
                                <span v-else class="text-xs text-gray-400 dark:text-gray-500">Waiting</span>
                            </div>
                        </template>
                    </div>
                </ScrollArea>
            </div>

            <!-- Shared Pokémon Filter -->
            <PokemonFilter
                v-model="filters"
                :pokemon="props.pokemon"
                :is-ban-phase="isBanPhase"
                :min-cost-to-ban="minCostToBan"
            />

            <!-- Pokémon Section -->
            <div
                v-if="props.draft"
                class="overflow-hidden rounded-xl border-2 shadow-sm"
                :class="isBanPhase ? 'border-orange-400 dark:border-orange-600' : 'border-blue-400 dark:border-blue-600'"
            >
                <!-- Your turn header -->
                <div
                    v-if="isMyTurn"
                    class="px-5 py-3"
                    :class="isBanPhase ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-blue-50 dark:bg-blue-900/20'"
                >
                    <h2
                        class="flex items-center gap-2 font-semibold"
                        :class="isBanPhase ? 'text-orange-800 dark:text-orange-300' : 'text-blue-800 dark:text-blue-300'"
                    >
                        <ShieldBan v-if="isBanPhase" class="size-5" />
                        <Swords v-else class="size-5" />
                        <span v-if="isBanPhase">Your turn to ban &mdash; click a Pokémon to ban it (min cost: {{ minCostToBan }})</span>
                        <span v-else>Your turn to pick &mdash; click a Pokémon to draft it</span>
                    </h2>
                </div>
                <!-- Waiting header -->
                <div
                    v-else
                    class="px-5 py-3"
                    :class="isBanPhase ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-blue-50 dark:bg-blue-900/20'"
                >
                    <p
                        class="text-sm"
                        :class="isBanPhase ? 'text-orange-700 dark:text-orange-400' : 'text-blue-700 dark:text-blue-400'"
                    >
                        Waiting for
                        <span class="font-semibold">
                            {{ isBanPhase ? (props.currentBanner?.team?.name ?? 'N/A') : (props.currentPicker?.team?.name ?? 'N/A') }}
                        </span>
                        to {{ isBanPhase ? 'ban' : 'pick' }}...
                    </p>
                </div>
                <!-- Pokémon Grid -->
                <div class="bg-white p-4 dark:bg-gray-900/20">
                    <div v-if="filteredPokemon.length === 0" class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                        No Pokémon match your filters.
                    </div>
                    <div v-else class="flex flex-col gap-6">
                        <div v-for="costHeader in filteredCostHeaders" :key="costHeader" class="flex flex-col gap-3">
                            <div
                                class="sticky top-0 z-10 rounded-md border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300"
                            >
                                Cost: {{ costHeader }}
                                <span v-if="isBanPhase && costHeader < minCostToBan" class="ml-2 text-xs font-normal text-orange-500 dark:text-orange-400"
                                    >(not eligible to ban)</span
                                >
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                <div
                                    v-for="pokemon in filteredPokemon.filter((p) => p.cost === costHeader)"
                                    :key="pokemon.id"
                                    @click="openActionDialog(pokemon)"
                                    class="relative overflow-hidden rounded-lg"
                                    :class="isClickable(pokemon) ? 'cursor-pointer transition-all hover:scale-105 hover:shadow-md' : 'cursor-default'"
                                >
                                    <PokemonCard :pokemon="pokemon" />
                                    <!-- Banned overlay -->
                                    <div
                                        v-if="pokemon.banned"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-lg bg-red-900/75"
                                    >
                                        <Ban class="size-7 text-white" />
                                        <span class="text-xs font-bold uppercase tracking-wide text-white">Banned</span>
                                    </div>
                                    <!-- Drafted overlay -->
                                    <div
                                        v-else-if="pokemon.is_drafted"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-lg bg-gray-900/60"
                                    >
                                        <CheckCircle class="size-7 text-green-400" />
                                        <span class="text-xs font-bold uppercase tracking-wide text-white">Drafted</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ isBanPhase ? 'Confirm Ban' : 'Confirm Pick' }}</DialogTitle>
                    <DialogDescription>
                        {{
                            isBanPhase
                                ? 'Are you sure you want to ban this Pokémon? It will be unavailable for the rest of the draft.'
                                : 'Are you sure you want to draft this Pokémon?'
                        }}
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
                    <Button @click="submitAction" :disabled="isSubmitting" :variant="isBanPhase ? 'destructive' : 'default'">
                        <LoaderCircle v-if="isSubmitting" class="mr-2 size-4 animate-spin" />
                        {{ isBanPhase ? 'Ban Pokémon' : 'Draft Pokémon' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
