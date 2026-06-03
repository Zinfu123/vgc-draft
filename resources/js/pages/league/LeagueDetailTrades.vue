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
    draft_points: number;
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
    counterparty: 'team' | 'free_agency';
    requesting_team_id: number;
    target_team_id: number | null;
    draft_points_delta: number | null;
    requesting_team: { id: number; name: string; user_id: number | null };
    target_team: { id: number; name: string; user_id: number | null } | null;
    offered_pokemon: TradePokemon[];
    requested_pokemon: TradePokemon[];
    created_at: string;
}

interface PoolMon {
    id: number;
    name: string;
    cost: number;
    sprite_url: string | null;
    type1?: string;
    type2?: string;
}

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    status: number;
    playoffs_enabled: boolean;
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
    leagueTradeHistory: Trade[];
    freeAgencyPool: PoolMon[];
}>();

const currentUser = usePage().props.auth.user;

const showTradeForm = ref(false);
const showFaForm = ref(false);
const selectedTargetTeamId = ref<number | null>(null);

const tradeForm = useForm({
    target_team_id: null as number | null,
    offered_pokemon_ids: [] as number[],
    requested_pokemon_ids: [] as number[],
});

const faForm = useForm({
    offered_pokemon_ids: [] as number[],
    requested_pokemon_ids: [] as number[],
});

const selectedTargetTeam = computed(() =>
    props.leagueTeams.find((t) => t.id === selectedTargetTeamId.value) ?? null,
);

const incomingTrades = computed(() =>
    props.trades.filter(
        (t) => t.counterparty === 'team' && t.target_team_id === props.userTeam?.id && t.status === 'pending',
    ),
);

const outgoingTrades = computed(() =>
    props.trades.filter(
        (t) => t.counterparty === 'team' && t.requesting_team_id === props.userTeam?.id && t.status === 'pending',
    ),
);

function tradeTransactionLabel(trade: Trade): string {
    if (trade.counterparty === 'free_agency') {
        const offered = trade.offered_pokemon.map((tp) => tp.league_pokemon?.name).filter(Boolean).join(', ');
        const taken = trade.requested_pokemon.map((tp) => tp.league_pokemon?.name).filter(Boolean).join(', ');
        const parts: string[] = [];
        if (offered) {
            parts.push(`dropped ${offered}`);
        }
        if (taken) {
            parts.push(`picked up ${taken}`);
        }
        if (trade.draft_points_delta != null && trade.draft_points_delta < 0) {
            parts.push(`spent ${Math.abs(trade.draft_points_delta)} draft pts`);
        } else if (trade.draft_points_delta != null && trade.draft_points_delta > 0) {
            parts.push(`gained ${trade.draft_points_delta} draft pts`);
        }
        return parts.join('; ');
    }

    const offered = trade.offered_pokemon.map((tp) => tp.league_pokemon?.name).filter(Boolean).join(', ');
    const received = trade.requested_pokemon.map((tp) => tp.league_pokemon?.name).filter(Boolean).join(', ');
    if (offered && received) {
        return `${offered} ↔ ${received}`;
    }
    if (offered) {
        return `offered ${offered}`;
    }
    return received ? `received ${received}` : '—';
}

function relativeTime(dateStr: string): string {
    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (diff < 60) {
        return 'just now';
    }
    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }
    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}h ago`;
    }
    if (diff < 604800) {
        return `${Math.floor(diff / 86400)}d ago`;
    }
    return new Date(dateStr).toLocaleDateString();
}

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

const offeredCostSum = computed(() => {
    if (!props.userTeam) {
        return 0;
    }
    const byId = new Map(props.userTeam.pokemon.map((p) => [p.id, p.cost]));
    return faForm.offered_pokemon_ids.reduce((s, id) => s + (byId.get(id) ?? 0), 0);
});

const requestedPoolCostSum = computed(() => {
    const byId = new Map(props.freeAgencyPool.map((p) => [p.id, p.cost]));
    return faForm.requested_pokemon_ids.reduce((s, id) => s + (byId.get(id) ?? 0), 0);
});

const faTradeTokenCost = computed(() => faForm.offered_pokemon_ids.length + faForm.requested_pokemon_ids.length);

const faPointsShortfall = computed(() => Math.max(0, requestedPoolCostSum.value - offeredCostSum.value));

const faCanCoverShortfall = computed(() => {
    if (faPointsShortfall.value === 0) {
        return true;
    }

    return (props.userTeam?.draft_points ?? 0) >= faPointsShortfall.value;
});

const faTradeValid = computed(
    () => faForm.offered_pokemon_ids.length > 0 && faForm.requested_pokemon_ids.length > 0 && faCanCoverShortfall.value,
);

const toggleFaOffered = (id: number) => {
    const idx = faForm.offered_pokemon_ids.indexOf(id);
    if (idx === -1) {
        faForm.offered_pokemon_ids.push(id);
    } else {
        faForm.offered_pokemon_ids.splice(idx, 1);
    }
};

const toggleFaRequested = (id: number) => {
    const idx = faForm.requested_pokemon_ids.indexOf(id);
    if (idx === -1) {
        faForm.requested_pokemon_ids.push(id);
    } else {
        faForm.requested_pokemon_ids.splice(idx, 1);
    }
};

const submitFaTrade = () => {
    faForm.post(route('leagues.trades.free-agency', { league: props.league.id }), {
        onSuccess: () => {
            showFaForm.value = false;
            faForm.reset();
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
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-xl font-semibold">Trades</h2>
                    <p v-if="userTeam" class="mt-1 text-sm text-muted-foreground">
                        {{ userTeam.name }} — <span class="font-medium">{{ userTeam.trades }}</span> trade{{ userTeam.trades === 1 ? '' : 's' }} remaining
                    </p>
                </div>
                <div v-if="userTeam" class="flex flex-wrap gap-2">
                    <template v-if="!showTradeForm && !showFaForm">
                        <Button @click="showTradeForm = true">Trade with team</Button>
                        <Button variant="secondary" @click="showFaForm = true">Trade with free agency</Button>
                    </template>
                    <Button
                        v-else-if="showTradeForm"
                        variant="outline"
                        @click="showTradeForm = false; tradeForm.reset(); selectedTargetTeamId = null"
                    >
                        Cancel team trade
                    </Button>
                    <Button v-else-if="showFaForm" variant="outline" @click="showFaForm = false; faForm.reset()">Cancel free agency</Button>
                </div>
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

            <!-- Free agency trade -->
            <div v-if="showFaForm && userTeam" class="rounded-lg border border-border bg-card p-6">
                <h3 class="mb-2 text-base font-semibold">Trade with free agency</h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Return Pokémon to the pool and take available Pokémon. If pool cost exceeds what you offer, the difference is paid from your
                    <span class="font-medium text-foreground">{{ userTeam.draft_points }}</span> draft points. This uses
                    {{ faTradeTokenCost || '…' }} trade slot(s) (offered + taken count).
                </p>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <p class="mb-2 text-sm font-medium">📤 Your Pokémon to offer</p>
                        <p v-if="faForm.errors.offered_pokemon_ids" class="mb-2 text-sm text-destructive">{{ faForm.errors.offered_pokemon_ids }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="poke in userTeam.pokemon"
                                :key="poke.id"
                                type="button"
                                @click="toggleFaOffered(poke.id)"
                                :class="[
                                    'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                    faForm.offered_pokemon_ids.includes(poke.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background hover:bg-muted',
                                ]"
                            >
                                <img v-if="poke.pokemon?.sprite_url" :src="poke.pokemon.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                {{ poke.name }} ({{ poke.cost }})
                            </button>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium">📥 Take from pool</p>
                        <p v-if="faForm.errors.requested_pokemon_ids" class="mb-2 text-sm text-destructive">{{ faForm.errors.requested_pokemon_ids }}</p>
                        <div class="max-h-64 flex flex-wrap gap-2 overflow-y-auto">
                            <button
                                v-for="poke in freeAgencyPool"
                                :key="poke.id"
                                type="button"
                                @click="toggleFaRequested(poke.id)"
                                :class="[
                                    'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                    faForm.requested_pokemon_ids.includes(poke.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background hover:bg-muted',
                                ]"
                            >
                                <img v-if="poke.sprite_url" :src="poke.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                {{ poke.name }} ({{ poke.cost }})
                            </button>
                        </div>
                        <p v-if="freeAgencyPool.length === 0" class="text-sm text-muted-foreground">No Pokémon available in the pool.</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-muted-foreground">
                        Offered cost: <span class="font-medium text-foreground">{{ offeredCostSum }}</span> · Pool cost:
                        <span class="font-medium text-foreground">{{ requestedPoolCostSum }}</span>
                        <span v-if="faPointsShortfall > 0 && faCanCoverShortfall">
                            — <span class="font-medium text-foreground">{{ faPointsShortfall }}</span> draft pts will be spent
                        </span>
                        <span v-else-if="faPointsShortfall > 0 && !faCanCoverShortfall" class="text-destructive">
                            — need {{ faPointsShortfall }} draft pts (you have {{ userTeam.draft_points }})
                        </span>
                    </p>
                    <Button
                        @click="submitFaTrade"
                        :disabled="
                            faForm.processing ||
                            faForm.offered_pokemon_ids.length === 0 ||
                            faForm.requested_pokemon_ids.length === 0 ||
                            !faTradeValid ||
                            faTradeTokenCost > (userTeam?.trades ?? 0)
                        "
                    >
                        Complete trade
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
                            <span class="text-sm font-medium">To {{ trade.target_team?.name ?? '—' }}</span>
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
            <div v-if="leagueTradeHistory.length > 0">
                <h3 class="mb-3 text-base font-semibold">Trade History</h3>
                <div class="space-y-2">
                    <div
                        v-for="trade in leagueTradeHistory"
                        :key="trade.id"
                        class="flex items-start justify-between gap-3 rounded-lg border border-border bg-card px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-sm font-medium">
                                <span>{{ trade.requesting_team?.name ?? '—' }}</span>
                                <template v-if="trade.counterparty === 'team' && trade.target_team">
                                    <span class="text-muted-foreground">↔</span>
                                    <span>{{ trade.target_team.name }}</span>
                                </template>
                                <template v-else>
                                    <span class="font-normal text-muted-foreground">via free agency</span>
                                </template>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ tradeTransactionLabel(trade) }}</p>
                        </div>
                        <span class="shrink-0 whitespace-nowrap text-xs text-muted-foreground">{{ relativeTime(trade.created_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!userTeam && incomingTrades.length === 0 && outgoingTrades.length === 0 && leagueTradeHistory.length === 0" class="py-12 text-center text-muted-foreground">
                No trades yet.
            </div>

            <div v-if="!userTeam" class="py-8 text-center text-sm text-muted-foreground">
                You don't have a team in this league.
            </div>
        </div>
    </LeagueDetailLayout>
</template>
