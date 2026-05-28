<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import MatchCard from '@/components/match/MatchCard.vue';
import PokemonCard from '@/components/pokemon/PokemonCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { isReverbBroadcastClientConfigured } from '@/lib/broadcasting';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { ArrowRight, Bell, CalendarClock, Clock, Flag, MessageSquare, RadioTower, Swords } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    status: number;
    playoffs_enabled: boolean;
    trade_deadline_at: string | null;
}

interface Team {
    id: number;
    league_id: number;
    name: string;
    coach: string;
    logo: string | null;
    user_id: number;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    draft_points: number;
    trades: number;
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

interface TeamPokemon {
    id: number;
    name: string;
    cost: number;
    pokemon: {
        sprite_url: string;
        type1: string;
        type2?: string;
    };
}

interface SelectedTeam {
    id: number;
    name: string;
    logo: string | null;
    coach: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    draft_points: number;
    trades: number;
    pokemon: TeamPokemon[];
}

interface LeaguePokemon {
    id: number;
    name: string;
    cost: number;
    pokemon: { sprite_url: string | null } | null;
}

interface TradesTeam {
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
    counterparty: 'team' | 'free_agency';
    requesting_team_id: number;
    target_team_id: number | null;
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

interface PendingScheduleRequest {
    id: number;
    proposed_at: string;
    is_mine: boolean;
}

interface NextSet {
    id: number;
    round: number;
    scheduled_at: string | null;
    opponent_name: string;
    unread_message_count: number;
    pending_schedule_request: PendingScheduleRequest | null;
}

interface SetSide {
    id: number;
    name: string;
    logo: string;
    user: { name: string };
}

interface TeamSetRow {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    status: number;
    scheduled_at: string | null;
    winner_id: number | null;
    team1_score: number | null;
    team2_score: number | null;
    is_bye: boolean;
    team1: SetSide;
    team2: SetSide | null;
}

type TeamSetsByRound = Record<string, TeamSetRow[]>;

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    selected_team: SelectedTeam | null;
    selected_team_id: number | null;
    userTradesTeam: TradesTeam | null;
    leagueTeamsForTrades: TradesTeam[];
    trades: Trade[];
    freeAgencyPool: PoolMon[];
    freeTradeWindowEndsAt: string | null;
    nextSet: NextSet | null;
    leagueTransactions: Trade[];
    team_sets_by_round: TeamSetsByRound;
}>(); 

const currentUser = usePage().props.auth.user as { id?: number; discord_id?: string | null } | null;

const isViewingOwnTeam = computed(
    () => props.userTradesTeam !== null && props.selected_team_id === props.userTradesTeam.id,
);

const tradeDeadlinePassed = computed(() => {
    if (!props.league.trade_deadline_at) return false;
    return new Date(props.league.trade_deadline_at) <= new Date();
});

const freeTradeWindowPassed = computed(() => {
    if (!props.freeTradeWindowEndsAt) {
        return false;
    }

    return new Date(props.freeTradeWindowEndsAt) <= new Date();
});

function formatDeadline(dateStr: string): string {
    return new Date(dateStr).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatDeadlineEastern(dateStr: string): string {
    return new Date(dateStr).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'America/New_York',
        timeZoneName: 'short',
    });
}

// --- Team picker ---
function winPct(wins: number, losses: number): string {
    const total = wins + losses;
    if (total === 0) {
        return '—';
    }

    return (wins / total).toLocaleString('en-US', { style: 'percent', minimumFractionDigits: 1 });
}

function onTeamChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    router.get(route('leagues.dashboard', { league: props.league.id }), { team: parseInt(target.value, 10) }, { preserveScroll: true });
}

const sortedSetRounds = computed(() =>
    Object.keys(props.team_sets_by_round)
        .map((key) => Number(key))
        .filter((round) => !Number.isNaN(round))
        .sort((a, b) => a - b),
);

function setForRound(round: number): TeamSetRow | null {
    const rows = props.team_sets_by_round[String(round)];

    return rows?.[0] ?? null;
}

const teamSetsInRoundOrder = computed(() =>
    sortedSetRounds.value
        .map((round) => ({
            round,
            set: setForRound(round),
        }))
        .filter((item): item is { round: number; set: TeamSetRow } => item.set !== null),
);

function setStatusLabel(set: TeamSetRow): string {
    if (set.is_bye) {
        return 'Bye';
    }

    if (set.status === 0 || set.winner_id !== null) {
        return 'Completed';
    }

    return 'Upcoming';
}

function setScoreLabel(set: TeamSetRow): string | null {
    if (set.team1_score === null || set.team2_score === null) {
        return null;
    }

    return `${set.team1_score}–${set.team2_score}`;
}

function formatSetScheduledAt(dateStr: string | null): string {
    if (!dateStr) {
        return 'Not yet scheduled';
    }

    return new Date(dateStr).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

// --- Trade sheet ---
const tradeSheetOpen = ref(false);
const pendingSheetOpen = ref(false);
const activeTradeTab = ref<'team' | 'free_agency'>('team');
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

const selectedTargetTeam = computed(() => props.leagueTeamsForTrades.find((t) => t.id === selectedTargetTeamId.value) ?? null);

const incomingTrades = computed(() =>
    props.trades.filter((t) => t.counterparty === 'team' && t.target_team_id === props.userTradesTeam?.id && t.status === 'pending'),
);

const outgoingTrades = computed(() =>
    props.trades.filter((t) => t.counterparty === 'team' && t.requesting_team_id === props.userTradesTeam?.id && t.status === 'pending'),
);

const pendingTradesCount = computed(() => incomingTrades.value.length + outgoingTrades.value.length);

function switchTradeTab(tab: 'team' | 'free_agency'): void {
    activeTradeTab.value = tab;
    tradeForm.reset();
    faForm.reset();
    selectedTargetTeamId.value = null;
}

function toggleOffered(id: number): void {
    const idx = tradeForm.offered_pokemon_ids.indexOf(id);
    if (idx === -1) {
        tradeForm.offered_pokemon_ids.push(id);
    } else {
        tradeForm.offered_pokemon_ids.splice(idx, 1);
    }
}

function toggleRequested(id: number): void {
    const idx = tradeForm.requested_pokemon_ids.indexOf(id);
    if (idx === -1) {
        tradeForm.requested_pokemon_ids.push(id);
    } else {
        tradeForm.requested_pokemon_ids.splice(idx, 1);
    }
}

function onTargetTeamChange(): void {
    tradeForm.target_team_id = selectedTargetTeamId.value;
    tradeForm.requested_pokemon_ids = [];
}

function submitTrade(): void {
    tradeForm.post(route('leagues.trades.create', { league: props.league.id }), {
        onSuccess: () => {
            tradeSheetOpen.value = false;
            tradeForm.reset();
            selectedTargetTeamId.value = null;
        },
    });
}

const offeredCostSum = computed(() => {
    if (!props.userTradesTeam) {
        return 0;
    }

    const byId = new Map(props.userTradesTeam.pokemon.map((p) => [p.id, p.cost]));
    return faForm.offered_pokemon_ids.reduce((s, id) => s + (byId.get(id) ?? 0), 0);
});

const requestedPoolCostSum = computed(() => {
    const byId = new Map(props.freeAgencyPool.map((p) => [p.id, p.cost]));
    return faForm.requested_pokemon_ids.reduce((s, id) => s + (byId.get(id) ?? 0), 0);
});

const faTradeTokenCost = computed(() => faForm.offered_pokemon_ids.length + faForm.requested_pokemon_ids.length);
const faCostOk = computed(() => offeredCostSum.value >= requestedPoolCostSum.value && faForm.requested_pokemon_ids.length > 0);

function toggleFaOffered(id: number): void {
    const idx = faForm.offered_pokemon_ids.indexOf(id);
    if (idx === -1) {
        faForm.offered_pokemon_ids.push(id);
    } else {
        faForm.offered_pokemon_ids.splice(idx, 1);
    }
}

function toggleFaRequested(id: number): void {
    const idx = faForm.requested_pokemon_ids.indexOf(id);
    if (idx === -1) {
        faForm.requested_pokemon_ids.push(id);
    } else {
        faForm.requested_pokemon_ids.splice(idx, 1);
    }
}

function submitFaTrade(): void {
    faForm.post(route('leagues.trades.free-agency', { league: props.league.id }), {
        onSuccess: () => {
            tradeSheetOpen.value = false;
            faForm.reset();
        },
    });
}

function respondToTrade(trade: Trade, response: 'accepted' | 'declined' | 'cancelled'): void {
    router.put(
        route('leagues.trades.respond', { league: props.league.id, trade: trade.id }),
        { response },
        { preserveScroll: true },
    );
}

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

// --- Free agency pool filters ---
const faPoolSearch = ref('');
const faPoolMinCost = ref<number | null>(null);
const faPoolMaxCost = ref<number | null>(null);

const filteredFreeAgencyPool = computed(() => {
    const search = faPoolSearch.value.trim().toLowerCase();
    return props.freeAgencyPool.filter((p) => {
        if (search && !p.name.toLowerCase().includes(search)) {
            return false;
        }
        if (faPoolMinCost.value !== null && p.cost < faPoolMinCost.value) {
            return false;
        }
        if (faPoolMaxCost.value !== null && p.cost > faPoolMaxCost.value) {
            return false;
        }
        return true;
    });
});

// --- League Activity pagination ---
const activityPageSize = 8;
const activityPage = ref(1);

const totalActivityPages = computed(() => Math.ceil(props.leagueTransactions.length / activityPageSize));

const paginatedTransactions = computed(() =>
    props.leagueTransactions.slice((activityPage.value - 1) * activityPageSize, activityPage.value * activityPageSize),
);

watch(() => props.leagueTransactions, () => {
    activityPage.value = 1;
});

// --- Real-time updates ---
if (isReverbBroadcastClientConfigured) {
    useEchoPublic(`league.transactions.${props.league.id}`, 'LeagueTransactionEvent', () => {
        router.reload({ only: ['leagueTransactions'], preserveScroll: true });
    });

    if (props.userTradesTeam) {
        useEchoPublic(`trade.pending.${props.userTradesTeam.id}`, 'TradePendingEvent', () => {
            router.reload({ only: ['trades'], preserveScroll: true });
        });
    }
}
</script>

<template>
    <LeagueDetailLayout
        :league="league"
        section="dashboard"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <Head :title="`Dashboard · ${league.name}`" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- ─── Left column (2/3) ─── -->
            <div class="flex flex-col gap-6 lg:col-span-2">

                <!-- Team picker + action button bar -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-semibold text-foreground">
                            {{ selected_team ? selected_team.name : 'No teams yet' }}
                        </h2>
                        <div v-if="teams.length > 1" class="shrink-0">
                            <select
                                :value="selected_team_id ?? undefined"
                                class="rounded-md border border-input bg-background px-3 py-1.5 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-ring dark:bg-background"
                                aria-label="View a different team"
                                @change="onTeamChange"
                            >
                                <option v-for="team in teams" :key="team.id" :value="team.id">
                                    {{ team.name }} ({{ team.coach }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Action button bar -->
                    <div v-if="selected_team" class="flex shrink-0 flex-wrap items-center gap-2">
                        <!-- Trade sheet trigger — only for the logged-in user's own team -->
                        <Sheet v-if="userTradesTeam" v-model:open="tradeSheetOpen">
                            <SheetTrigger as-child>
                                <Button size="sm" variant="default">Trade</Button>
                            </SheetTrigger>
                            <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                                <SheetHeader class="border-b border-border px-6 py-4">
                                    <SheetTitle>Trade Center</SheetTitle>
                                    <SheetDescription>
                                        {{ userTradesTeam.name }} —
                                        <span class="font-medium text-foreground">{{ userTradesTeam.trades }}</span>
                                        trade{{ userTradesTeam.trades === 1 ? '' : 's' }} remaining
                                    </SheetDescription>

                                    <!-- Discord warning -->
                                    <div
                                        v-if="!currentUser?.discord_id"
                                        class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                                    >
                                        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                        <span>
                                            Connect Discord before trading.
                                            <a :href="route('profile.edit')" class="font-semibold underline underline-offset-2 hover:no-underline">Profile Settings</a>
                                        </span>
                                    </div>

                                    <!-- Tab toggle -->
                                    <div class="mt-4 flex rounded-lg border border-border bg-muted p-1">
                                        <button
                                            type="button"
                                            :class="[
                                                'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                                activeTradeTab === 'team'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            ]"
                                            @click="switchTradeTab('team')"
                                        >
                                            Trade with a Team
                                        </button>
                                        <button
                                            type="button"
                                            :class="[
                                                'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                                activeTradeTab === 'free_agency'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            ]"
                                            @click="switchTradeTab('free_agency')"
                                        >
                                            Free Agency
                                        </button>
                                    </div>
                                </SheetHeader>

                                <!-- Scrollable form area -->
                                <div class="flex-1 overflow-y-auto px-6 py-5">

                                    <!-- Trade with team form -->
                                    <div v-if="activeTradeTab === 'team'" class="flex flex-col gap-4">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium">Select opposing team</label>
                                            <select
                                                v-model="selectedTargetTeamId"
                                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring dark:bg-background"
                                                @change="onTargetTeamChange"
                                            >
                                                <option :value="null" disabled>Choose a team…</option>
                                                <option v-for="team in leagueTeamsForTrades" :key="team.id" :value="team.id">
                                                    {{ team.name }} ({{ team.coach }}) — {{ team.trades }} trade{{ team.trades === 1 ? '' : 's' }} left
                                                </option>
                                            </select>
                                            <p v-if="tradeForm.errors.target_team_id" class="mt-1 text-sm text-destructive">{{ tradeForm.errors.target_team_id }}</p>
                                        </div>

                                        <template v-if="selectedTargetTeam">
                                            <div>
                                                <p class="mb-2 text-sm font-medium">Your Pokémon to offer</p>
                                                <p v-if="tradeForm.errors.offered_pokemon_ids" class="mb-2 text-sm text-destructive">{{ tradeForm.errors.offered_pokemon_ids }}</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <button
                                                        v-for="poke in userTradesTeam.pokemon"
                                                        :key="poke.id"
                                                        type="button"
                                                        :class="[
                                                            'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                                            tradeForm.offered_pokemon_ids.includes(poke.id)
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'border-border bg-background hover:bg-muted',
                                                        ]"
                                                        @click="toggleOffered(poke.id)"
                                                    >
                                                        <img v-if="poke.pokemon?.sprite_url" :src="poke.pokemon.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                                        {{ poke.name }}
                                                    </button>
                                                </div>
                                                <p v-if="userTradesTeam.pokemon.length === 0" class="text-sm text-muted-foreground">No Pokémon on your team.</p>
                                            </div>

                                            <div>
                                                <p class="mb-2 text-sm font-medium">Request from {{ selectedTargetTeam.name }}</p>
                                                <p v-if="tradeForm.errors.requested_pokemon_ids" class="mb-2 text-sm text-destructive">{{ tradeForm.errors.requested_pokemon_ids }}</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <button
                                                        v-for="poke in selectedTargetTeam.pokemon"
                                                        :key="poke.id"
                                                        type="button"
                                                        :class="[
                                                            'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                                            tradeForm.requested_pokemon_ids.includes(poke.id)
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'border-border bg-background hover:bg-muted',
                                                        ]"
                                                        @click="toggleRequested(poke.id)"
                                                    >
                                                        <img v-if="poke.pokemon?.sprite_url" :src="poke.pokemon.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                                        {{ poke.name }}
                                                    </button>
                                                </div>
                                                <p v-if="selectedTargetTeam.pokemon.length === 0" class="text-sm text-muted-foreground">No Pokémon on this team.</p>
                                            </div>

                                            <div class="flex items-center justify-between gap-4 border-t border-border pt-4">
                                                <p class="text-xs text-muted-foreground">
                                                    Offering {{ tradeForm.offered_pokemon_ids.length }} · Requesting {{ tradeForm.requested_pokemon_ids.length }}
                                                    <span v-if="tradeForm.requested_pokemon_ids.length > 0">
                                                        (costs {{ tradeForm.requested_pokemon_ids.length }} of your {{ userTradesTeam.trades }} remaining)
                                                    </span>
                                                </p>
                                                <Button
                                                    size="sm"
                                                    :disabled="tradeForm.processing || tradeForm.offered_pokemon_ids.length === 0 || tradeForm.requested_pokemon_ids.length === 0"
                                                    @click="submitTrade"
                                                >
                                                    Send Request
                                                </Button>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Free agency form -->
                                    <div v-else class="flex flex-col gap-4">
                                        <p class="text-sm text-muted-foreground">
                                            Return Pokémon to the pool and take available ones. Offered cost must be ≥ taken cost. Uses {{ faTradeTokenCost > 0 ? faTradeTokenCost : '…' }} trade slot(s).
                                        </p>

                                        <div>
                                            <p class="mb-2 text-sm font-medium">Your Pokémon to offer</p>
                                            <p v-if="faForm.errors.offered_pokemon_ids" class="mb-2 text-sm text-destructive">{{ faForm.errors.offered_pokemon_ids }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    v-for="poke in userTradesTeam.pokemon"
                                                    :key="poke.id"
                                                    type="button"
                                                    :class="[
                                                        'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                                        faForm.offered_pokemon_ids.includes(poke.id)
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'border-border bg-background hover:bg-muted',
                                                    ]"
                                                    @click="toggleFaOffered(poke.id)"
                                                >
                                                    <img v-if="poke.pokemon?.sprite_url" :src="poke.pokemon.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                                    {{ poke.name }} ({{ poke.cost }})
                                                </button>
                                            </div>
                                        </div>

                                        <div>
                                            <p class="mb-2 text-sm font-medium">Take from pool</p>
                                            <p v-if="faForm.errors.requested_pokemon_ids" class="mb-2 text-sm text-destructive">{{ faForm.errors.requested_pokemon_ids }}</p>

                                            <!-- Pool filters -->
                                            <div class="mb-3 flex flex-col gap-2">
                                                <input
                                                    v-model="faPoolSearch"
                                                    type="text"
                                                    placeholder="Search by name…"
                                                    class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring dark:bg-background"
                                                />
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        v-model.number="faPoolMinCost"
                                                        type="number"
                                                        min="0"
                                                        placeholder="Min pts"
                                                        class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring dark:bg-background"
                                                    />
                                                    <span class="shrink-0 text-xs text-muted-foreground">–</span>
                                                    <input
                                                        v-model.number="faPoolMaxCost"
                                                        type="number"
                                                        min="0"
                                                        placeholder="Max pts"
                                                        class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring dark:bg-background"
                                                    />
                                                </div>
                                            </div>

                                            <div class="flex max-h-52 flex-wrap gap-2 overflow-y-auto">
                                                <button
                                                    v-for="poke in filteredFreeAgencyPool"
                                                    :key="poke.id"
                                                    type="button"
                                                    :class="[
                                                        'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                                        faForm.requested_pokemon_ids.includes(poke.id)
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'border-border bg-background hover:bg-muted',
                                                    ]"
                                                    @click="toggleFaRequested(poke.id)"
                                                >
                                                    <img v-if="poke.sprite_url" :src="poke.sprite_url" :alt="poke.name" class="size-5 object-contain" />
                                                    {{ poke.name }} ({{ poke.cost }})
                                                </button>
                                            </div>
                                            <p v-if="freeAgencyPool.length === 0" class="text-sm text-muted-foreground">No Pokémon available in the pool.</p>
                                            <p v-else-if="filteredFreeAgencyPool.length === 0" class="text-sm text-muted-foreground">No Pokémon match your filters.</p>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
                                            <p class="text-xs text-muted-foreground">
                                                Offered cost: <span class="font-medium text-foreground">{{ offeredCostSum }}</span> · Pool cost:
                                                <span class="font-medium text-foreground">{{ requestedPoolCostSum }}</span>
                                                <span v-if="!faCostOk && faForm.requested_pokemon_ids.length > 0" class="text-destructive"> — offered cost must be ≥ pool cost</span>
                                            </p>
                                            <Button
                                                size="sm"
                                                :disabled="
                                                    faForm.processing ||
                                                    faForm.offered_pokemon_ids.length === 0 ||
                                                    faForm.requested_pokemon_ids.length === 0 ||
                                                    !faCostOk ||
                                                    faTradeTokenCost > (userTradesTeam.trades ?? 0)
                                                "
                                                @click="submitFaTrade"
                                            >
                                                Complete Trade
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>

                        <!-- Pending Trades sheet trigger -->
                        <Sheet v-if="userTradesTeam" v-model:open="pendingSheetOpen">
                            <SheetTrigger as-child>
                                <Button size="sm" variant="outline" class="relative">
                                    <Bell class="size-3.5" />
                                    Pending Trades
                                    <span
                                        v-if="incomingTrades.length > 0"
                                        class="absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground"
                                    >
                                        {{ incomingTrades.length }}
                                    </span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                                <SheetHeader class="border-b border-border px-6 py-4">
                                    <SheetTitle>Pending Trades</SheetTitle>
                                    <SheetDescription>
                                        {{ userTradesTeam.name }} —
                                        <span class="font-medium text-foreground">{{ pendingTradesCount }}</span>
                                        pending request{{ pendingTradesCount === 1 ? '' : 's' }}
                                    </SheetDescription>
                                </SheetHeader>

                                <div class="flex-1 overflow-y-auto px-6 py-5">
                                    <!-- Incoming requests -->
                                    <div v-if="incomingTrades.length > 0" class="mb-6">
                                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Incoming Requests</h3>
                                        <div class="flex flex-col gap-3">
                                            <div v-for="trade in incomingTrades" :key="trade.id" class="rounded-lg border border-border bg-card p-4">
                                                <div class="mb-3 flex items-center justify-between">
                                                    <span class="text-sm font-medium">From {{ trade.requesting_team.name }}</span>
                                                    <span class="rounded-full border border-border px-2.5 py-0.5 text-xs text-muted-foreground">Pending</span>
                                                </div>
                                                <div class="mb-4 grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <p class="mb-1.5 text-xs font-medium text-muted-foreground">They offer</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <span v-for="tp in trade.offered_pokemon" :key="tp.id" class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs">
                                                                <img v-if="tp.league_pokemon?.pokemon?.sprite_url" :src="tp.league_pokemon.pokemon.sprite_url" :alt="tp.league_pokemon?.name" class="size-4 object-contain" />
                                                                {{ tp.league_pokemon?.name ?? '?' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1.5 text-xs font-medium text-muted-foreground">They want</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <span v-for="tp in trade.requested_pokemon" :key="tp.id" class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs">
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

                                    <!-- Outgoing requests -->
                                    <div v-if="outgoingTrades.length > 0" class="mb-6">
                                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Outgoing Requests</h3>
                                        <div class="flex flex-col gap-3">
                                            <div v-for="trade in outgoingTrades" :key="trade.id" class="rounded-lg border border-border bg-card p-4">
                                                <div class="mb-3 flex items-center justify-between">
                                                    <span class="text-sm font-medium">To {{ trade.target_team?.name }}</span>
                                                    <span class="rounded-full border border-border px-2.5 py-0.5 text-xs text-muted-foreground">Pending</span>
                                                </div>
                                                <div class="mb-4 grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <p class="mb-1.5 text-xs font-medium text-muted-foreground">You offer</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <span v-for="tp in trade.offered_pokemon" :key="tp.id" class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs">
                                                                <img v-if="tp.league_pokemon?.pokemon?.sprite_url" :src="tp.league_pokemon.pokemon.sprite_url" :alt="tp.league_pokemon?.name" class="size-4 object-contain" />
                                                                {{ tp.league_pokemon?.name ?? '?' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1.5 text-xs font-medium text-muted-foreground">You want</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <span v-for="tp in trade.requested_pokemon" :key="tp.id" class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs">
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

                                    <!-- No pending trades -->
                                    <div
                                        v-if="pendingTradesCount === 0"
                                        class="rounded-lg border border-dashed border-border bg-muted/20 p-8 text-center"
                                    >
                                        <Bell class="mx-auto mb-3 size-8 text-muted-foreground/40" />
                                        <p class="text-sm text-muted-foreground">No pending trade requests.</p>
                                        <p class="mt-1 text-xs text-muted-foreground">Use the Trade button to start a new trade.</p>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>

                        <!-- My League Hub button -->
                        <Button v-if="!isViewingOwnTeam && userTradesTeam" size="sm" variant="outline" as-child>
                            <Link :href="route('leagues.dashboard', { league: league.id })">
                                My League Hub
                                <ArrowRight class="ml-1.5 size-3.5" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- No teams state -->
                <div v-if="!selected_team" class="rounded-2xl border border-dashed border-border bg-muted/30 py-16 text-center">
                    <p class="text-base font-medium text-foreground">No teams in this league yet.</p>
                    <p class="mt-1 text-sm text-muted-foreground">Create or join a team to see the dashboard.</p>
                </div>

                <template v-else>
                    <!-- Team card -->
                    <Card class="overflow-hidden border-border bg-card/80 shadow-sm">
                        <CardHeader class="items-center space-y-4 pb-4 text-center">
                            <div
                                v-if="selected_team.logo"
                                class="ring-offset-background rounded-full ring-2 ring-border ring-offset-2"
                            >
                                <img :src="selected_team.logo" alt="" class="inline size-24 rounded-full object-cover md:size-28" />
                            </div>
                            <div
                                v-else
                                class="flex size-24 items-center justify-center rounded-full bg-muted text-2xl font-bold text-muted-foreground ring-2 ring-border ring-offset-2 ring-offset-background md:size-28"
                                aria-hidden="true"
                            >
                                {{ selected_team.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="space-y-1">
                                <CardTitle class="text-balance text-2xl sm:text-3xl">{{ selected_team.name }}</CardTitle>
                                <CardDescription class="text-base">Coach: {{ selected_team.coach }}</CardDescription>
                            </div>
                        </CardHeader>
                    </Card>

                    <!-- Stats row -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Card class="border-border shadow-sm">
                            <CardContent class="flex flex-col items-center gap-1 py-4 text-center">
                                <span class="text-2xl font-bold tabular-nums">{{ selected_team.set_wins }}–{{ selected_team.set_losses }}</span>
                                <span class="text-xs text-muted-foreground">Set record</span>
                                <span class="text-xs text-muted-foreground">{{ winPct(selected_team.set_wins, selected_team.set_losses) }}</span>
                            </CardContent>
                        </Card>
                        <Card class="border-border shadow-sm">
                            <CardContent class="flex flex-col items-center gap-1 py-4 text-center">
                                <span class="text-2xl font-bold tabular-nums text-primary">{{ selected_team.victory_points }}</span>
                                <span class="text-xs text-muted-foreground">Victory points</span>
                            </CardContent>
                        </Card>
                        <Card class="border-border shadow-sm">
                            <CardContent class="flex flex-col items-center gap-1 py-4 text-center">
                                <span class="text-2xl font-bold tabular-nums">{{ selected_team.draft_points }}</span>
                                <span class="text-xs text-muted-foreground">Draft points</span>
                            </CardContent>
                        </Card>
                        <Card class="border-border shadow-sm">
                            <CardContent class="flex flex-col items-center gap-1 py-4 text-center">
                                <span class="text-2xl font-bold tabular-nums">{{ selected_team.trades }}</span>
                                <span class="text-xs text-muted-foreground">Trade points</span>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Drafted Pokémon -->
                    <Card class="border-border bg-primary/5 shadow-sm dark:bg-primary/10">
                        <CardHeader>
                            <CardTitle class="text-xl">Drafted Pokémon</CardTitle>
                            <CardDescription>Roster for this league.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="selected_team.pokemon.length > 0"
                                class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-3"
                            >
                                <PokemonCard
                                    v-for="pokemon in selected_team.pokemon"
                                    :key="pokemon.id"
                                    :pokemon="{
                                        name: pokemon.name,
                                        sprite_url: pokemon.pokemon.sprite_url,
                                        type1: pokemon.pokemon.type1,
                                        type2: pokemon.pokemon.type2 ?? '-',
                                        cost: pokemon.cost,
                                    }"
                                />
                            </div>
                            <div v-else class="rounded-md border border-dashed border-border bg-muted/30 p-8 text-center">
                                <p class="text-sm text-muted-foreground">No Pokémon drafted yet.</p>
                                <p class="mt-1 text-xs text-muted-foreground">Pokémon will appear here once the draft begins.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Match schedule by round -->
                    <Card class="border-border shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-xl">Match Schedule</CardTitle>
                            <CardDescription>Sets for this team, sorted by round.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="teamSetsInRoundOrder.length === 0"
                                class="rounded-md border border-dashed border-border bg-muted/30 p-8 text-center"
                            >
                                <p class="text-sm text-muted-foreground">No sets scheduled yet.</p>
                            </div>
                            <div v-else class="flex flex-col gap-6">
                                <section
                                    v-for="{ round, set } in teamSetsInRoundOrder"
                                    :key="round"
                                    class="flex flex-col gap-3"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-sm font-semibold text-foreground">Round {{ round }}</h3>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                :class="[
                                                    'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                    setStatusLabel(set) === 'Completed' || setStatusLabel(set) === 'Bye'
                                                        ? 'bg-muted text-muted-foreground'
                                                        : 'bg-primary/10 text-primary dark:bg-primary/20',
                                                ]"
                                            >
                                                {{ setStatusLabel(set) }}
                                            </span>
                                            <span
                                                v-if="setScoreLabel(set)"
                                                class="rounded-full border border-border px-2.5 py-0.5 text-xs font-medium tabular-nums text-foreground"
                                            >
                                                {{ setScoreLabel(set) }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatSetScheduledAt(set.scheduled_at) }}
                                    </p>
                                    <MatchCard :sets="set" :team1="set.team1" :team2="set.team2" />
                                </section>
                            </div>
                        </CardContent>
                    </Card>
                </template>

            </div>

            <!-- ─── Right column: League Transactions (1/3) ─── -->
            <div class="lg:col-span-1">
                <div class="sticky top-6 flex flex-col gap-3">

                    <!-- Next Match card -->
                    <Link
                        v-if="nextSet"
                        :href="route('sets.show', { set_id: nextSet.id })"
                        class="group block rounded-xl border border-border bg-card/80 px-4 py-3 shadow-sm transition-colors hover:bg-accent"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <Swords class="size-4 shrink-0 text-primary" />
                            <span class="text-sm font-semibold">Next Match</span>
                            <span class="ml-auto text-xs text-muted-foreground">Round {{ nextSet.round }}</span>
                        </div>
                        <p class="text-base font-semibold text-foreground">vs {{ nextSet.opponent_name }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{
                                nextSet.scheduled_at
                                    ? new Date(nextSet.scheduled_at).toLocaleString(undefined, {
                                          weekday: 'short',
                                          month: 'short',
                                          day: 'numeric',
                                          hour: 'numeric',
                                          minute: '2-digit',
                                      })
                                    : 'Not yet scheduled'
                            }}
                        </p>
                        <div v-if="nextSet.unread_message_count > 0 || nextSet.pending_schedule_request" class="mt-2.5 flex flex-wrap gap-2">
                            <span
                                v-if="nextSet.unread_message_count > 0"
                                class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary dark:bg-primary/20"
                            >
                                <MessageSquare class="size-3" />
                                {{ nextSet.unread_message_count }} unread
                            </span>
                            <span
                                v-if="nextSet.pending_schedule_request && !nextSet.pending_schedule_request.is_mine"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                <CalendarClock class="size-3" />
                                Time request — respond
                            </span>
                            <span
                                v-else-if="nextSet.pending_schedule_request && nextSet.pending_schedule_request.is_mine"
                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                <CalendarClock class="size-3" />
                                Time request pending
                            </span>
                        </div>
                    </Link>

                    <!-- Free trade window badge -->
                    <div v-if="freeTradeWindowEndsAt">
                        <div
                            :class="[
                                'flex items-start gap-2.5 rounded-xl border px-4 py-3',
                                freeTradeWindowPassed
                                    ? 'border-muted-foreground/30 bg-muted/40 dark:border-muted-foreground/40 dark:bg-muted/30'
                                    : 'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30',
                            ]"
                        >
                            <Flag
                                :class="[
                                    'mt-0.5 size-4 shrink-0',
                                    freeTradeWindowPassed ? 'text-muted-foreground' : 'text-sky-600 dark:text-sky-400',
                                ]"
                            />
                            <div>
                                <p
                                    :class="[
                                        'text-sm font-semibold',
                                        freeTradeWindowPassed ? 'text-muted-foreground' : 'text-sky-800 dark:text-sky-200',
                                    ]"
                                >
                                    {{ freeTradeWindowPassed ? 'Free Trade Window Closed' : 'Free Trade Window' }}
                                </p>
                                <p
                                    :class="[
                                        'mt-0.5 text-xs',
                                        freeTradeWindowPassed ? 'text-muted-foreground' : 'text-sky-700 dark:text-sky-300',
                                    ]"
                                >
                                    {{
                                        freeTradeWindowPassed
                                            ? `No-cost trades ended ${formatDeadlineEastern(freeTradeWindowEndsAt)}`
                                            : `No-cost trades end ${formatDeadlineEastern(freeTradeWindowEndsAt)}`
                                    }}
                                </p>
                                <p v-if="!freeTradeWindowPassed" class="mt-1 text-xs text-sky-700 dark:text-sky-300">
                                    Complete free agency and team trades before the window closes. Times shown in Eastern Time.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Trade Deadline badge -->
                    <div v-if="league.trade_deadline_at">
                        <div
                            :class="[
                                'flex items-start gap-2.5 rounded-xl border px-4 py-3',
                                tradeDeadlinePassed
                                    ? 'border-destructive/40 bg-destructive/10 dark:border-destructive/60 dark:bg-destructive/20'
                                    : 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30',
                            ]"
                        >
                            <Clock
                                :class="[
                                    'mt-0.5 size-4 shrink-0',
                                    tradeDeadlinePassed ? 'text-destructive' : 'text-amber-600 dark:text-amber-400',
                                ]"
                            />
                            <div>
                                <p
                                    :class="[
                                        'text-sm font-semibold',
                                        tradeDeadlinePassed ? 'text-destructive' : 'text-amber-800 dark:text-amber-200',
                                    ]"
                                >
                                    {{ tradeDeadlinePassed ? 'Trade Deadline Passed' : 'Trade Deadline' }}
                                </p>
                                <p
                                    :class="[
                                        'mt-0.5 text-xs',
                                        tradeDeadlinePassed ? 'text-destructive/80' : 'text-amber-700 dark:text-amber-300',
                                    ]"
                                >
                                    {{ formatDeadline(league.trade_deadline_at) }}
                                </p>
                                <p v-if="tradeDeadlinePassed" class="mt-1 text-xs text-destructive/80">
                                    Trades are locked.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-semibold">League Activity</h2>
                            <p class="text-xs text-muted-foreground">Completed trades &amp; moves</p>
                        </div>
                        <span
                            v-if="isReverbBroadcastClientConfigured"
                            class="flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                        >
                            <RadioTower class="size-3" />
                            Live
                        </span>
                    </div>

                    <div
                        v-if="leagueTransactions.length > 0"
                        class="flex flex-col gap-2 rounded-xl border border-border bg-card/60 p-3"
                    >
                        <div
                            v-for="tx in paginatedTransactions"
                            :key="tx.id"
                            class="flex items-start justify-between gap-3 rounded-lg border border-border bg-background px-3 py-2.5"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs font-medium text-foreground">
                                    <span>{{ tx.requesting_team?.name }}</span>
                                    <template v-if="tx.counterparty === 'team' && tx.target_team">
                                        <span class="text-muted-foreground">↔</span>
                                        <span>{{ tx.target_team.name }}</span>
                                    </template>
                                    <template v-else>
                                        <span class="font-normal text-muted-foreground">via FA</span>
                                    </template>
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ tradeTransactionLabel(tx) }}</p>
                            </div>
                            <span class="shrink-0 whitespace-nowrap text-xs text-muted-foreground">{{ relativeTime(tx.created_at) }}</span>
                        </div>

                        <!-- Pagination controls -->
                        <div v-if="totalActivityPages > 1" class="mt-1 flex items-center justify-between gap-2 border-t border-border pt-2">
                            <button
                                type="button"
                                :disabled="activityPage === 1"
                                class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="activityPage--"
                            >
                                ← Prev
                            </button>
                            <span class="text-xs text-muted-foreground">{{ activityPage }} / {{ totalActivityPages }}</span>
                            <button
                                type="button"
                                :disabled="activityPage === totalActivityPages"
                                class="rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="activityPage++"
                            >
                                Next →
                            </button>
                        </div>
                    </div>

                    <div v-else class="rounded-xl border border-dashed border-border bg-muted/20 p-6 text-center">
                        <p class="text-sm text-muted-foreground">No completed transactions yet.</p>
                        <p class="mt-1 text-xs text-muted-foreground">Accepted trades will appear here.</p>
                    </div>
                </div>
            </div>
        </div>
    </LeagueDetailLayout>
</template>
