<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Pokemon {
    id: number;
    name: string;
    sprite_url: string | null;
}

interface LeaguePokemon {
    id: number;
    name: string;
    cost: number;
    pokemon: Pokemon | null;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
    trades: number;
    pokemon: LeaguePokemon[];
}

interface TradePokemon {
    id: number;
    league_pokemon_id: number;
    direction: 'offered' | 'requested';
    league_pokemon: LeaguePokemon | null;
}

interface Trade {
    id: number;
    status: 'pending' | 'accepted' | 'declined' | 'cancelled';
    requesting_team_id: number;
    target_team_id: number;
    requesting_team: { id: number; name: string; user_id: number };
    target_team: { id: number; name: string; user_id: number };
    offered_pokemon: TradePokemon[];
    requested_pokemon: TradePokemon[];
    created_at: string;
}

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
}

interface Draft {
    id: number | null;
    round_number: number;
    pick_number: number;
    status: number;
}

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    userTeam: Team | null;
    leagueTeams: Team[];
    trades: Trade[];
}>();

const currentUser = usePage().props.auth.user;

const showTradeForm = ref(false);
const selectedTargetTeamId = ref<number | null>(null);

const tradeForm = useForm({
    target_team_id: null as number | null,
    offered_pokemon_ids: [] as number[],
    requested_pokemon_ids: [] as number[],
});

const selectedTargetTeam = computed(() =>
    props.leagueTeams.find((t) => t.id === selectedTargetTeamId.value) ?? null,
);

const incomingTrades = computed(() =>
    props.trades.filter((t) => t.target_team_id === props.userTeam?.id && t.status === 'pending'),
);

const outgoingTrades = computed(() =>
    props.trades.filter((t) => t.requesting_team_id === props.userTeam?.id && t.status === 'pending'),
);

const completedTrades = computed(() =>
    props.trades.filter((t) => t.status !== 'pending'),
);

const statusClass = (status: Trade['status']) => {
    switch (status) {
        case 'accepted': return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'declined': return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'cancelled': return 'bg-muted text-muted-foreground';
        default: return 'border border-border text-muted-foreground';
    }
};

const toggleOffered = (id: number) => {
    const idx = tradeForm.offered_pokemon_ids.indexOf(id);
    if (idx === -1) {
        tradeForm.offered_pokemon_ids.push(id);
    } else {
        tradeForm.offered_pokemon_ids.splice(idx, 1);
    }
};

const toggleRequested = (id: number) => {
    const idx = tradeForm.requested_pokemon_ids.indexOf(id);
    if (idx === -1) {
        tradeForm.requested_pokemon_ids.push(id);
    } else {
        tradeForm.requested_pokemon_ids.splice(idx, 1);
    }
};

const onTargetTeamChange = () => {
    tradeForm.target_team_id = selectedTargetTeamId.value;
    tradeForm.requested_pokemon_ids = [];
};

const submitTrade = () => {
    tradeForm.post(route('leagues.trades.create', { league: props.league.id }), {
        onSuccess: () => {
            showTradeForm.value = false;
            tradeForm.reset();
            selectedTargetTeamId.value = null;
        },
    });
};

const respondToTrade = (trade: Trade, response: 'accepted' | 'declined' | 'cancelled') => {
    router.put(route('leagues.trades.respond', { league: props.league.id, trade: trade.id }), {
        response,
    });
};
</script>

<template>
    <LeagueDetailLayout :league="league" section="trades" :teams="teams" :draft="draft" :adminFlag="adminFlag" :matchConfig="matchConfig">
        <div class="space-y-8">
            <!-- Discord not linked warning -->
            <div v-if="userTeam && !currentUser.discord_id" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>
                    You need to connect your Discord account before you can send trade requests.
                    <a :href="route('profile.edit')" class="font-semibold underline underline-offset-2 hover:no-underline">Connect Discord in Profile Settings.</a>
                </span>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Trades</h2>
                    <p v-if="userTeam" class="mt-1 text-sm text-muted-foreground">
                        {{ userTeam.name }} — <span class="font-medium">{{ userTeam.trades }}</span> trade{{ userTeam.trades === 1 ? '' : 's' }} remaining
                    </p>
                </div>
                <Button v-if="userTeam && !showTradeForm" @click="showTradeForm = true">Request Trade</Button>
                <Button v-else-if="userTeam && showTradeForm" variant="outline" @click="showTradeForm = false; tradeForm.reset(); selectedTargetTeamId = null">
                    Cancel
                </Button>
            </div>

            <!-- Trade Request Form -->
            <div v-if="showTradeForm && userTeam" class="rounded-lg border border-border bg-card p-6">
                <h3 class="mb-4 text-base font-semibold">New Trade Request</h3>

                <div class="mb-4 grid gap-2">
                    <label class="text-sm font-medium">Select opposing team</label>
                    <select
                        v-model="selectedTargetTeamId"
                        @change="onTargetTeamChange"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none dark:bg-background"
                    >
                        <option :value="null" disabled>Choose a team...</option>
                        <option v-for="team in leagueTeams" :key="team.id" :value="team.id">
                            {{ team.name }} ({{ team.coach }}) — {{ team.trades }} trade{{ team.trades === 1 ? '' : 's' }} left
                        </option>
                    </select>
                    <p v-if="tradeForm.errors.target_team_id" class="text-sm text-destructive">{{ tradeForm.errors.target_team_id }}</p>
                </div>

                <div v-if="selectedTargetTeam" class="grid gap-6 md:grid-cols-2">
                    <!-- Pokémon you're offering -->
                    <div>
                        <p class="mb-2 text-sm font-medium">📤 Your Pokémon to offer</p>
                        <p v-if="tradeForm.errors.offered_pokemon_ids" class="mb-2 text-sm text-destructive">{{ tradeForm.errors.offered_pokemon_ids }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="poke in userTeam.pokemon"
                                :key="poke.id"
                                type="button"
                                @click="toggleOffered(poke.id)"
                                :class="[
                                    'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                    tradeForm.offered_pokemon_ids.includes(poke.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background hover:bg-muted',
                                ]"
                            >
                                <img v-if="poke.pokemon?.sprite_url" :src="poke.pokemon.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                {{ poke.name }}
                            </button>
                        </div>
                        <p v-if="userTeam.pokemon.length === 0" class="text-sm text-muted-foreground">No Pokémon on your team.</p>
                    </div>

                    <!-- Pokémon you're requesting -->
                    <div>
                        <p class="mb-2 text-sm font-medium">📥 Pokémon to request from {{ selectedTargetTeam.name }}</p>
                        <p v-if="tradeForm.errors.requested_pokemon_ids" class="mb-2 text-sm text-destructive">{{ tradeForm.errors.requested_pokemon_ids }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="poke in selectedTargetTeam.pokemon"
                                :key="poke.id"
                                type="button"
                                @click="toggleRequested(poke.id)"
                                :class="[
                                    'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                    tradeForm.requested_pokemon_ids.includes(poke.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background hover:bg-muted',
                                ]"
                            >
                                <img v-if="poke.pokemon?.sprite_url" :src="poke.pokemon.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                {{ poke.name }}
                            </button>
                        </div>
                        <p v-if="selectedTargetTeam.pokemon.length === 0" class="text-sm text-muted-foreground">No Pokémon on this team.</p>
                    </div>
                </div>

                <div v-if="selectedTargetTeam" class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-muted-foreground">
                        Offering {{ tradeForm.offered_pokemon_ids.length }} · Requesting {{ tradeForm.requested_pokemon_ids.length }}
                        <span v-if="tradeForm.requested_pokemon_ids.length > 0">
                            (costs {{ tradeForm.requested_pokemon_ids.length }} of your {{ userTeam?.trades }} remaining trades)
                        </span>
                    </p>
                    <Button @click="submitTrade" :disabled="tradeForm.processing || tradeForm.offered_pokemon_ids.length === 0 || tradeForm.requested_pokemon_ids.length === 0">
                        Send Request
                    </Button>
                </div>
            </div>

            <!-- Incoming Trade Requests -->
            <div v-if="incomingTrades.length > 0">
                <h3 class="mb-3 text-base font-semibold">Incoming Requests</h3>
                <div class="space-y-3">
                    <div v-for="trade in incomingTrades" :key="trade.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-medium">From {{ trade.requesting_team.name }}</span>
                            <span class="rounded-full border border-border px-2.5 py-0.5 text-xs text-muted-foreground">Pending</span>
                        </div>
                        <div class="mb-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="mb-1.5 text-xs font-medium text-muted-foreground">They offer</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tp in trade.offered_pokemon"
                                        :key="tp.id"
                                        class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs"
                                    >
                                        <img v-if="tp.league_pokemon?.pokemon?.sprite_url" :src="tp.league_pokemon.pokemon.sprite_url" :alt="tp.league_pokemon?.name" class="size-4 object-contain" />
                                        {{ tp.league_pokemon?.name ?? '?' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <p class="mb-1.5 text-xs font-medium text-muted-foreground">They want</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tp in trade.requested_pokemon"
                                        :key="tp.id"
                                        class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs"
                                    >
                                        <img v-if="tp.league_pokemon?.pokemon?.sprite_url" :src="tp.league_pokemon.pokemon.sprite_url" :alt="tp.league_pokemon?.name" class="size-4 object-contain" />
                                        {{ tp.league_pokemon?.name ?? '?' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button size="sm" @click="respondToTrade(trade, 'accepted')">Accept</Button>
                            <Button size="sm" variant="outline" @click="respondToTrade(trade, 'declined')">Decline</Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outgoing Trade Requests -->
            <div v-if="outgoingTrades.length > 0">
                <h3 class="mb-3 text-base font-semibold">Outgoing Requests</h3>
                <div class="space-y-3">
                    <div v-for="trade in outgoingTrades" :key="trade.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-medium">To {{ trade.target_team.name }}</span>
                            <span class="rounded-full border border-border px-2.5 py-0.5 text-xs text-muted-foreground">Pending</span>
                        </div>
                        <div class="mb-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="mb-1.5 text-xs font-medium text-muted-foreground">You offer</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tp in trade.offered_pokemon"
                                        :key="tp.id"
                                        class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs"
                                    >
                                        <img v-if="tp.league_pokemon?.pokemon?.sprite_url" :src="tp.league_pokemon.pokemon.sprite_url" :alt="tp.league_pokemon?.name" class="size-4 object-contain" />
                                        {{ tp.league_pokemon?.name ?? '?' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <p class="mb-1.5 text-xs font-medium text-muted-foreground">You want</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tp in trade.requested_pokemon"
                                        :key="tp.id"
                                        class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs"
                                    >
                                        <img v-if="tp.league_pokemon?.pokemon?.sprite_url" :src="tp.league_pokemon.pokemon.sprite_url" :alt="tp.league_pokemon?.name" class="size-4 object-contain" />
                                        {{ tp.league_pokemon?.name ?? '?' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Button size="sm" variant="outline" @click="respondToTrade(trade, 'cancelled')">Cancel Request</Button>
                    </div>
                </div>
            </div>

            <!-- Trade History -->
            <div v-if="completedTrades.length > 0">
                <h3 class="mb-3 text-base font-semibold">Trade History</h3>
                <div class="space-y-2">
                    <div v-for="trade in completedTrades" :key="trade.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 text-sm">
                                <span class="font-medium">{{ trade.requesting_team.name }}</span>
                                <span class="text-muted-foreground"> ↔ </span>
                                <span class="font-medium">{{ trade.target_team.name }}</span>
                                <div class="mt-1.5 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                    <span>
                                        {{ trade.offered_pokemon.map((tp) => tp.league_pokemon?.name).join(', ') }}
                                        →
                                        {{ trade.requested_pokemon.map((tp) => tp.league_pokemon?.name).join(', ') }}
                                    </span>
                                </div>
                            </div>
                            <span :class="['shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', statusClass(trade.status)]">{{ trade.status }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!userTeam && incomingTrades.length === 0 && outgoingTrades.length === 0 && completedTrades.length === 0" class="py-12 text-center text-muted-foreground">
                No trades yet.
            </div>

            <div v-if="!userTeam" class="py-8 text-center text-sm text-muted-foreground">
                You don't have a team in this league.
            </div>
        </div>
    </LeagueDetailLayout>
</template>
