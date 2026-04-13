<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import BlobBackground from '@/components/BlobBackground.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import LeagueMatches from '@/components/league/LeagueMatches.vue';
import StandingsList from '@/components/league/StandingsList.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useMobileLayout } from '@/composables/useMobileLayout';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const { isMobile } = useMobileLayout();

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

interface SetSide {
    id: number;
    name: string;
    logo: string;
    user: { name: string };
}

interface SetRow {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: SetSide;
    team2: SetSide | null;
}

type GroupedSets = Record<number, SetRow[]>;

interface TeamNext extends SetRow {
    team1: SetSide;
    team2: SetSide | null;
}

type StandingsData = Record<string, Array<{
    id: number;
    name: string;
    logo: string;
    user: { name: string };
    victory_points: number;
}>>

interface BracketCell {
    kind: string;
    seed_index?: number | null;
    seed_number?: number | null;
    team_id?: number | null;
    name?: string | null;
    coach?: string | null;
    pending_label?: string | null;
}

interface BracketMatchRow {
    slot: string;
    id: number | null;
    is_bronze?: boolean;
    complete?: boolean;
    winner_team_id?: number | null;
    team1_score?: number | null;
    team2_score?: number | null;
    top: BracketCell;
    bottom: BracketCell;
}

interface BracketRound {
    key: string;
    round_index: number;
    is_bronze_round: boolean;
    matches: BracketMatchRow[];
}

interface BracketLayout {
    mode: string;
    rounds: BracketRound[];
}

interface PlayoffPokepasteSides {
    team1: { public_id: string; has_data: boolean } | null;
    team2: { public_id: string; has_data: boolean } | null;
}

interface PlayoffMatchPayload {
    id: number;
    slot: string;
    round_index: number;
    sort_order: number;
    is_bronze: boolean;
    team1_id: number | null;
    team2_id: number | null;
    team1_name?: string | null;
    team2_name?: string | null;
    team1_score: number | null;
    team2_score: number | null;
    winner_team_id: number | null;
    completed_at: string | null;
    pokepaste_sides?: PlayoffPokepasteSides;
}

interface PlayoffPayload {
    id: number;
    format: string;
    bracket_size: number;
    status: string;
    seed_order: number[];
    matches: PlayoffMatchPayload[];
    require_team_match_pokepaste_before_results?: boolean;
}

type DragPayload = { source: 'seed'; seed_index: number } | { source: 'pool'; team_id: number };

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    played_sets: GroupedSets;
    upcoming_sets: GroupedSets;
    team_next: TeamNext | null;
    matches_filter_team_id: number | null;
    standings: StandingsData;
    playoff: PlayoffPayload;
    bracketLayout: BracketLayout;
    canAdjustPlayoff: boolean;
    canRecordPlayoffResults: boolean;
    allowedBracketSizes: number[];
    doubleEliminationSupported: boolean;
    hasActivePlayoffs: boolean;
    scheduleView: 'matches' | 'standings' | 'playoffs';
}>();

type ScheduleTab = 'matches' | 'standings' | 'playoffs';

const activeTab = ref<ScheduleTab>(props.scheduleView);

// --- Playoffs bracket logic (mirrors LeagueDetailPlayoffs.vue) ---

const bracketSeedIds = ref<number[]>([]);

function syncBracketSeedsFromProps(): void {
    const b = props.playoff.bracket_size;
    const s = props.playoff.seed_order ?? [];
    const prefix = s.slice(0, b).filter((id) => id > 0);
    const teamIds = props.teams.map((t) => t.id);
    const next = [...prefix];

    while (next.length < b) {
        const fill = teamIds.find((id) => !next.includes(id));
        if (fill === undefined) {
            break;
        }
        next.push(fill);
    }

    bracketSeedIds.value = next;
}

watch(
    () => [props.playoff.seed_order, props.playoff.bracket_size] as const,
    () => syncBracketSeedsFromProps(),
    { deep: true, immediate: true },
);

const configForm = useForm({
    format: props.playoff.format,
    bracket_size: props.playoff.bracket_size,
    seed_order: [] as number[],
});

watch(
    () => props.playoff.format,
    (v) => {
        configForm.format = v;
    },
);
watch(
    () => props.playoff.bracket_size,
    (v) => {
        configForm.bracket_size = v;
    },
);

function bracketPrefix(): number[] {
    const b = configForm.bracket_size;
    const teamIds = props.teams.map((t) => t.id);
    const arr = [...bracketSeedIds.value.slice(0, b)];

    while (arr.length < b) {
        const next = teamIds.find((id) => !arr.includes(id));
        if (next === undefined) {
            break;
        }
        arr.push(next);
    }

    return arr;
}

function resizeBracketSeedsForSize(newSize: number): void {
    let arr = [...bracketSeedIds.value];

    if (arr.length > newSize) {
        arr = arr.slice(0, newSize);
    }

    const teamIds = props.teams.map((t) => t.id);

    while (arr.length < newSize) {
        const next = teamIds.find((id) => !arr.includes(id));
        if (next === undefined) {
            break;
        }
        arr.push(next);
    }

    bracketSeedIds.value = arr;
}

const generateForm = useForm({});

const poolTeams = computed(() => {
    const inBracket = new Set(bracketSeedIds.value.slice(0, configForm.bracket_size));

    return props.teams.filter((t) => !inBracket.has(t.id));
});

function buildFullSeedOrder(bracketIds: number[]): number[] {
    const rest = props.teams.map((t) => t.id).filter((id) => !bracketIds.includes(id));

    return [...bracketIds, ...rest];
}

function saveConfigAndSeeds(): void {
    const b = configForm.bracket_size;
    const slice = bracketPrefix();
    configForm.seed_order = buildFullSeedOrder(slice.slice(0, b));
    configForm.patch(route('leagues.admin.playoffs.update', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function saveBracketSizeAndFormat(): void {
    resizeBracketSeedsForSize(configForm.bracket_size);
    const b = configForm.bracket_size;
    const slice = bracketPrefix().slice(0, b);
    configForm.seed_order = buildFullSeedOrder(slice);
    configForm.patch(route('leagues.admin.playoffs.update', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function generateBracket(): void {
    generateForm.post(route('leagues.admin.playoffs.generate', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function placeTeamInBracketSlot(teamId: number, seedIndex: number): void {
    const arr = bracketPrefix();
    const existingIdx = arr.indexOf(teamId);
    const out = arr[seedIndex];

    if (existingIdx !== -1) {
        arr[existingIdx] = out;
    }

    arr[seedIndex] = teamId;
    bracketSeedIds.value = arr;
}

function swapSeeds(i: number, j: number): void {
    const arr = bracketPrefix();
    const t = arr[i];
    arr[i] = arr[j];
    arr[j] = t;
    bracketSeedIds.value = arr;
}

function parseDragPayload(e: DragEvent): DragPayload | null {
    const raw = e.dataTransfer?.getData('application/json');

    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw) as DragPayload;
    } catch {
        return null;
    }
}

function onDragStartSeed(e: DragEvent, seedIndex: number): void {
    if (!props.canAdjustPlayoff) {
        return;
    }

    e.dataTransfer?.setData('application/json', JSON.stringify({ source: 'seed', seed_index: seedIndex }));
    e.dataTransfer!.effectAllowed = 'move';
}

function onDragStartPool(e: DragEvent, teamId: number): void {
    if (!props.canAdjustPlayoff) {
        return;
    }

    e.dataTransfer?.setData('application/json', JSON.stringify({ source: 'pool', team_id: teamId }));
    e.dataTransfer!.effectAllowed = 'move';
}

function onDropOnSeedCell(e: DragEvent, targetSeedIndex: number): void {
    if (!props.canAdjustPlayoff) {
        return;
    }

    e.preventDefault();
    const payload = parseDragPayload(e);

    if (payload === null) {
        return;
    }

    if (payload.source === 'pool') {
        placeTeamInBracketSlot(payload.team_id, targetSeedIndex);
    } else {
        swapSeeds(payload.seed_index, targetSeedIndex);
    }
}

function onDropOnPoolTeam(e: DragEvent, poolTeamId: number): void {
    if (!props.canAdjustPlayoff) {
        return;
    }

    e.preventDefault();
    const payload = parseDragPayload(e);

    if (payload?.source === 'seed') {
        placeTeamInBracketSlot(poolTeamId, payload.seed_index);
    }
}

function displayCell(cell: BracketCell): BracketCell {
    if (props.bracketLayout.mode !== 'draft' || cell.kind !== 'seed' || cell.seed_index === null || cell.seed_index === undefined) {
        return cell;
    }

    const tid = bracketSeedIds.value[cell.seed_index];
    const t = props.teams.find((x) => x.id === tid);

    return {
        ...cell,
        team_id: tid,
        name: t?.name ?? 'Empty slot',
        coach: t?.coach ?? null,
    };
}

function roundTitle(round: BracketRound): string {
    if (round.is_bronze_round) {
        return 'Bronze';
    }

    const nonBronze = props.bracketLayout.rounds.filter((r) => !r.is_bronze_round);
    const maxR = nonBronze.length ? Math.max(...nonBronze.map((r) => r.round_index)) : 0;
    const ri = round.round_index;

    if (ri === maxR && maxR > 0) {
        return 'Finals';
    }

    if (props.playoff.bracket_size === 6) {
        if (ri === 0) {
            return 'Opening';
        }

        if (ri === 1) {
            return 'Semis';
        }
    }

    if (ri === 0) {
        return 'Round 1';
    }

    return `Round ${ri + 1}`;
}

function cellWon(cell: BracketCell, match: BracketMatchRow): boolean {
    if (!match.complete || !cell.team_id || !match.winner_team_id) {
        return false;
    }

    return cell.team_id === match.winner_team_id;
}

const scoreDialogOpen = ref(false);
const selectedRoundAndMatch = ref<{ round: BracketRound; match: BracketMatchRow } | null>(null);
const scoreDraft = ref({ team1: 0, team2: 0 });

const recordForm = useForm({
    playoff_match_id: 0,
    team1_score: 0,
    team2_score: 0,
});

const rollbackForm = useForm({
    playoff_match_id: 0,
});

watch(scoreDialogOpen, (open) => {
    if (!open) {
        selectedRoundAndMatch.value = null;
    }
});

function canOpenPlayoffMatchDialog(match: BracketMatchRow): boolean {
    return props.canRecordPlayoffResults && props.bracketLayout.mode === 'live' && match.id !== null;
}

function playoffPayloadMatch(match: BracketMatchRow): PlayoffMatchPayload | undefined {
    if (match.id === null) {
        return undefined;
    }

    return props.playoff.matches.find((m) => m.id === match.id);
}

function playoffPasteBothReady(match: BracketMatchRow): boolean {
    const row = playoffPayloadMatch(match);

    if (!row?.pokepaste_sides) {
        return false;
    }

    return !!(row.pokepaste_sides.team1?.has_data && row.pokepaste_sides.team2?.has_data);
}

function canRecordThisMatch(match: BracketMatchRow): boolean {
    const base = match.top.team_id != null && match.bottom.team_id != null && match.winner_team_id == null && !match.complete;

    if (!base) {
        return false;
    }

    if (props.playoff.require_team_match_pokepaste_before_results && !playoffPasteBothReady(match)) {
        return false;
    }

    return true;
}

function openPlayoffMatchDialog(round: BracketRound, match: BracketMatchRow): void {
    if (!canOpenPlayoffMatchDialog(match)) {
        return;
    }

    selectedRoundAndMatch.value = { round, match };
    scoreDraft.value = { team1: 0, team2: 0 };
    scoreDialogOpen.value = true;
}

function totalGamesPlayed(match: BracketMatchRow): number | null {
    if (match.team1_score === null || match.team2_score === null) {
        return null;
    }

    return match.team1_score + match.team2_score;
}

function submitPlayoffRecord(): void {
    const ctx = selectedRoundAndMatch.value;

    if (ctx === null || ctx.match.id === null) {
        return;
    }

    recordForm.playoff_match_id = ctx.match.id;
    recordForm.team1_score = scoreDraft.value.team1;
    recordForm.team2_score = scoreDraft.value.team2;
    recordForm.post(route('leagues.admin.playoffs.record', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => {
            scoreDialogOpen.value = false;
        },
    });
}

function submitPlayoffRollback(): void {
    const ctx = selectedRoundAndMatch.value;

    if (ctx === null || ctx.match.id === null) {
        return;
    }

    rollbackForm.playoff_match_id = ctx.match.id;
    rollbackForm.post(route('leagues.admin.playoffs.rollback', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => {
            scoreDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Schedule · ${league.name}`" />

    <LeagueDetailLayout
        :league="league"
        section="schedule"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <div class="flex flex-col gap-6">
            <!-- Sub-tab navigation -->
            <div class="flex gap-1 rounded-lg border border-border bg-muted/40 p-1 dark:bg-muted/20">
                <button
                    v-for="tab in (['matches', 'standings', ...(hasActivePlayoffs ? ['playoffs'] : [])] as ScheduleTab[])"
                    :key="tab"
                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        activeTab === tab
                            ? 'bg-background text-foreground shadow-sm dark:bg-card'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = tab"
                >
                    {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
                </button>
            </div>

            <!-- Matches view -->
            <div v-if="activeTab === 'matches'" class="relative">
                <BlobBackground>
                    <div class="absolute -top-20 right-0 h-64 w-64 rounded-full bg-fightingtype/10 blur-3xl dark:bg-fightingtype/15" />
                    <div class="absolute top-1/3 -left-20 h-56 w-56 rounded-full bg-watertype/10 blur-3xl dark:bg-watertype/16" />
                </BlobBackground>
                <div class="relative z-10">
                    <LeagueMatches
                        :league-id="league.id"
                        :teams="teams"
                        :played_sets="played_sets"
                        :upcoming_sets="upcoming_sets"
                        :team_next="team_next"
                        :matches-filter-team-id="matches_filter_team_id"
                    />
                </div>
            </div>

            <!-- Standings view -->
            <div v-else-if="activeTab === 'standings'">
                <!-- eslint-disable-next-line @typescript-eslint/no-explicit-any -->
                <StandingsList :standings="(standings as any)" />
            </div>

            <!-- Playoffs view -->
            <div v-else-if="activeTab === 'playoffs'" class="flex flex-col gap-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">
                        Single-elimination bracket{{ bracketLayout.mode === 'draft' ? ' (preview)' : '' }}. Everyone can view; league admins can arrange seeds, then click a match while playoffs are active to enter results.
                    </p>
                    <div v-if="adminFlag === true || adminFlag === 1" class="flex flex-wrap gap-2">
                        <Button v-if="playoff.status === 'draft' || playoff.status === 'active'" variant="outline" size="sm" as-child>
                            <Link :href="route('leagues.admin.playoffs', { league: league.id })">Admin tools</Link>
                        </Button>
                    </div>
                </div>

                <div
                    v-if="canAdjustPlayoff"
                    class="rounded-lg border border-dashed border-border bg-muted/30 p-4 dark:bg-muted/15"
                >
                    <h2 class="mb-2 text-sm font-semibold">Arrange the field (draft)</h2>
                    <p class="mb-3 text-xs text-muted-foreground">
                        Drag coaches between seed slots and the bench. Top seeds sit in earlier rounds. Save, then generate the bracket from the admin page or below.
                    </p>
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="flex flex-col gap-1">
                            <Label for="cfg-format" class="text-xs">Format</Label>
                            <select
                                id="cfg-format"
                                v-model="configForm.format"
                                class="rounded-md border border-input bg-background px-2 py-1.5 text-sm dark:bg-background"
                                @change="saveBracketSizeAndFormat"
                            >
                                <option value="single_elimination">Single elimination</option>
                                <option value="double_elimination" :disabled="!doubleEliminationSupported">Double elimination</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label for="cfg-size" class="text-xs">Bracket size</Label>
                            <select
                                id="cfg-size"
                                v-model.number="configForm.bracket_size"
                                class="rounded-md border border-input bg-background px-2 py-1.5 text-sm dark:bg-background"
                                @change="saveBracketSizeAndFormat"
                            >
                                <option v-for="s in allowedBracketSizes" :key="s" :value="s">{{ s }} teams</option>
                            </select>
                        </div>
                        <Button type="button" size="sm" :disabled="configForm.processing" @click="saveConfigAndSeeds">Save seed order</Button>
                        <Button type="button" size="sm" variant="secondary" :disabled="generateForm.processing" @click="generateBracket">
                            Generate bracket
                        </Button>
                    </div>
                    <p v-if="configForm.errors.playoff" class="mb-2 text-sm text-destructive">{{ configForm.errors.playoff }}</p>
                    <p v-if="generateForm.errors.playoff" class="mb-2 text-sm text-destructive">{{ generateForm.errors.playoff }}</p>
                    <p v-if="generateForm.errors.format" class="mb-2 text-sm text-destructive">{{ generateForm.errors.format }}</p>

                    <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Bench</h3>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="t in poolTeams"
                            :key="t.id"
                            draggable="true"
                            class="cursor-grab rounded-md border border-border bg-card px-3 py-2 text-sm shadow-sm active:cursor-grabbing"
                            @dragstart="onDragStartPool($event, t.id)"
                            @dragover.prevent
                            @drop="onDropOnPoolTeam($event, t.id)"
                        >
                            <span class="font-medium">{{ t.name }}</span>
                            <span class="block text-xs text-muted-foreground">{{ t.coach }}</span>
                        </div>
                        <p v-if="poolTeams.length === 0" class="text-xs text-muted-foreground">All league teams are placed in the bracket field.</p>
                    </div>
                </div>

                <!-- Mobile bracket -->
                <div v-if="isMobile" class="flex flex-col gap-8">
                    <div v-for="round in bracketLayout.rounds" :key="round.key" class="flex flex-col gap-3">
                        <h3 class="border-b border-border pb-1 text-sm font-semibold tracking-wide text-foreground">
                            {{ roundTitle(round) }}
                        </h3>
                        <div
                            v-for="match in round.matches"
                            :key="match.slot"
                            :role="canOpenPlayoffMatchDialog(match) ? 'button' : undefined"
                            :tabindex="canOpenPlayoffMatchDialog(match) ? 0 : undefined"
                            class="flex flex-col overflow-hidden rounded-lg border border-border bg-card text-sm shadow-sm outline-none"
                            :class="{
                                'min-h-11 touch-manipulation cursor-pointer transition active:scale-[0.99] hover:ring-2 hover:ring-green-500/40 focus-visible:ring-2 focus-visible:ring-green-500/50':
                                    canOpenPlayoffMatchDialog(match),
                            }"
                            @click="openPlayoffMatchDialog(round, match)"
                            @keydown.enter.prevent="openPlayoffMatchDialog(round, match)"
                            @keydown.space.prevent="openPlayoffMatchDialog(round, match)"
                        >
                            <div
                                class="flex flex-col divide-y divide-border"
                                :class="{ 'ring-2 ring-green-500/35': bracketLayout.mode === 'live' && match.complete }"
                            >
                                <div
                                    v-for="(cell, idx) in [displayCell(match.top), displayCell(match.bottom)]"
                                    :key="idx"
                                    class="relative min-h-[3.25rem] px-3 py-2.5 transition-colors"
                                    :class="{
                                        'bg-green-500/15 dark:bg-green-500/20': bracketLayout.mode === 'live' && cellWon(cell, match),
                                        'ring-1 ring-dashed ring-primary/40': canAdjustPlayoff && cell.kind === 'seed',
                                    }"
                                    :draggable="canAdjustPlayoff && cell.kind === 'seed' && cell.seed_index !== null && cell.seed_index !== undefined"
                                    @dragstart="
                                        cell.kind === 'seed' && cell.seed_index !== null && cell.seed_index !== undefined
                                            ? onDragStartSeed($event, cell.seed_index)
                                            : undefined
                                    "
                                    @dragover.prevent
                                    @drop="
                                        cell.kind === 'seed' && cell.seed_index !== null && cell.seed_index !== undefined
                                            ? onDropOnSeedCell($event, cell.seed_index)
                                            : undefined
                                    "
                                >
                                    <span v-if="cell.seed_number" class="absolute top-2 right-2 text-[10px] font-semibold text-muted-foreground">#{{ cell.seed_number }}</span>
                                    <template v-if="cell.kind === 'pending'">
                                        <span class="text-xs text-muted-foreground">{{ cell.pending_label }}</span>
                                    </template>
                                    <template v-else>
                                        <span class="block font-medium leading-tight">{{ cell.name }}</span>
                                        <span v-if="cell.coach" class="block text-xs text-muted-foreground">{{ cell.coach }}</span>
                                    </template>
                                </div>
                                <div
                                    v-if="bracketLayout.mode === 'live' && match.id !== null && match.team1_score !== null && match.team2_score !== null"
                                    class="border-t border-border bg-muted/20 px-3 py-2 text-center text-xs text-muted-foreground dark:bg-muted/10"
                                >
                                    <span class="font-semibold tabular-nums text-foreground">{{ match.team1_score }}–{{ match.team2_score }}</span>
                                    <span class="mt-0.5 block text-[10px] font-medium">{{ totalGamesPlayed(match) }} games</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop bracket -->
                <div v-else class="overflow-x-auto pb-4">
                    <div class="flex min-w-max flex-row items-stretch gap-6 px-1">
                        <div v-for="round in bracketLayout.rounds" :key="round.key" class="flex w-48 shrink-0 flex-col justify-around gap-6">
                            <div
                                v-for="match in round.matches"
                                :key="match.slot"
                                :role="canOpenPlayoffMatchDialog(match) ? 'button' : undefined"
                                :tabindex="canOpenPlayoffMatchDialog(match) ? 0 : undefined"
                                class="flex flex-col overflow-hidden rounded-md border border-border bg-card text-sm shadow-sm outline-none"
                                :class="{
                                    'cursor-pointer transition hover:ring-2 hover:ring-green-500/40 focus-visible:ring-2 focus-visible:ring-green-500/50':
                                        canOpenPlayoffMatchDialog(match),
                                }"
                                @click="openPlayoffMatchDialog(round, match)"
                                @keydown.enter.prevent="openPlayoffMatchDialog(round, match)"
                                @keydown.space.prevent="openPlayoffMatchDialog(round, match)"
                            >
                                <div class="border-b border-border bg-muted/40 px-2 py-1 text-center text-xs font-medium text-muted-foreground dark:bg-muted/25">
                                    {{ roundTitle(round) }}
                                </div>
                                <div
                                    class="flex flex-col divide-y divide-border"
                                    :class="{ 'ring-2 ring-green-500/35': bracketLayout.mode === 'live' && match.complete }"
                                >
                                    <div
                                        v-for="(cell, idx) in [displayCell(match.top), displayCell(match.bottom)]"
                                        :key="idx"
                                        class="relative min-h-[3.25rem] px-2 py-2 transition-colors"
                                        :class="{
                                            'bg-green-500/15 dark:bg-green-500/20': bracketLayout.mode === 'live' && cellWon(cell, match),
                                            'ring-1 ring-dashed ring-primary/40': canAdjustPlayoff && cell.kind === 'seed',
                                        }"
                                        :draggable="canAdjustPlayoff && cell.kind === 'seed' && cell.seed_index !== null && cell.seed_index !== undefined"
                                        @dragstart="
                                            cell.kind === 'seed' && cell.seed_index !== null && cell.seed_index !== undefined
                                                ? onDragStartSeed($event, cell.seed_index)
                                                : undefined
                                        "
                                        @dragover.prevent
                                        @drop="
                                            cell.kind === 'seed' && cell.seed_index !== null && cell.seed_index !== undefined
                                                ? onDropOnSeedCell($event, cell.seed_index)
                                                : undefined
                                        "
                                    >
                                        <span v-if="cell.seed_number" class="absolute top-1 right-1 text-[10px] font-semibold text-muted-foreground">#{{ cell.seed_number }}</span>
                                        <template v-if="cell.kind === 'pending'">
                                            <span class="text-xs text-muted-foreground">{{ cell.pending_label }}</span>
                                        </template>
                                        <template v-else>
                                            <span class="block font-medium leading-tight">{{ cell.name }}</span>
                                            <span v-if="cell.coach" class="block text-xs text-muted-foreground">{{ cell.coach }}</span>
                                        </template>
                                    </div>
                                    <div
                                        v-if="bracketLayout.mode === 'live' && match.id !== null && match.team1_score !== null && match.team2_score !== null"
                                        class="border-t border-border bg-muted/20 px-2 py-1 text-center text-xs text-muted-foreground dark:bg-muted/10"
                                    >
                                        <span class="font-semibold tabular-nums text-foreground">{{ match.team1_score }}–{{ match.team2_score }}</span>
                                        <span class="mt-0.5 block text-[10px] font-medium">{{ totalGamesPlayed(match) }} games</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="playoff.status === 'active' && !(adminFlag === true || adminFlag === 1)" class="text-xs text-muted-foreground">
                    Results are entered by league admins.
                </p>
            </div>
        </div>

        <!-- Playoff score dialog -->
        <Dialog v-model:open="scoreDialogOpen">
            <DialogContent v-if="selectedRoundAndMatch" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ roundTitle(selectedRoundAndMatch.round) }} — result</DialogTitle>
                    <DialogDescription>
                        Best-of-three set: enter game wins (2–0 or 2–1). Seeds reflect the original bracket order.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="space-y-2 rounded-md border border-border bg-muted/20 p-3 text-sm dark:bg-muted/10">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium leading-tight">{{ selectedRoundAndMatch.match.top.name ?? 'TBD' }}</p>
                                <p v-if="selectedRoundAndMatch.match.top.coach" class="text-xs text-muted-foreground">{{ selectedRoundAndMatch.match.top.coach }}</p>
                            </div>
                            <span v-if="selectedRoundAndMatch.match.top.seed_number" class="shrink-0 text-xs font-semibold text-muted-foreground">
                                Seed {{ selectedRoundAndMatch.match.top.seed_number }}
                            </span>
                        </div>
                        <p class="text-center text-xs text-muted-foreground">vs</p>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium leading-tight">{{ selectedRoundAndMatch.match.bottom.name ?? 'TBD' }}</p>
                                <p v-if="selectedRoundAndMatch.match.bottom.coach" class="text-xs text-muted-foreground">{{ selectedRoundAndMatch.match.bottom.coach }}</p>
                            </div>
                            <span v-if="selectedRoundAndMatch.match.bottom.seed_number" class="shrink-0 text-xs font-semibold text-muted-foreground">
                                Seed {{ selectedRoundAndMatch.match.bottom.seed_number }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="
                            playoff.require_team_match_pokepaste_before_results &&
                            selectedRoundAndMatch.match.top.team_id != null &&
                            selectedRoundAndMatch.match.bottom.team_id != null &&
                            !selectedRoundAndMatch.match.complete &&
                            !playoffPasteBothReady(selectedRoundAndMatch.match)
                        "
                        class="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100"
                    >
                        Both teams must submit their playoff team paste before you can record a result.
                    </div>

                    <template v-if="canRecordThisMatch(selectedRoundAndMatch.match)">
                        <p v-if="recordForm.errors.playoff_result" class="text-sm text-destructive">{{ recordForm.errors.playoff_result }}</p>
                        <p v-if="recordForm.errors.playoff" class="text-sm text-destructive">{{ recordForm.errors.playoff }}</p>
                        <div class="flex flex-wrap gap-4">
                            <div class="flex flex-col gap-1">
                                <Label class="text-xs">{{ selectedRoundAndMatch.match.top.name }} — games won</Label>
                                <Input v-model.number="scoreDraft.team1" type="number" min="0" max="2" class="w-24" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="text-xs">{{ selectedRoundAndMatch.match.bottom.name }} — games won</Label>
                                <Input v-model.number="scoreDraft.team2" type="number" min="0" max="2" class="w-24" />
                            </div>
                        </div>
                    </template>
                    <template v-else-if="selectedRoundAndMatch.match.complete && totalGamesPlayed(selectedRoundAndMatch.match) !== null">
                        <p class="text-center text-sm">
                            <span class="font-semibold tabular-nums">{{ selectedRoundAndMatch.match.team1_score }}–{{ selectedRoundAndMatch.match.team2_score }}</span>
                            <span class="mt-1 block text-xs text-muted-foreground">{{ totalGamesPlayed(selectedRoundAndMatch.match) }} games played</span>
                        </p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">Both teams must be set before you can record a result.</p>
                </div>
                <DialogFooter class="flex-col gap-2 sm:flex-col">
                    <div v-if="canRecordThisMatch(selectedRoundAndMatch.match)" class="flex w-full justify-end gap-2">
                        <Button type="button" variant="outline" @click="scoreDialogOpen = false">Cancel</Button>
                        <Button type="button" :disabled="recordForm.processing" @click="submitPlayoffRecord">Save result</Button>
                    </div>
                    <div
                        v-else-if="selectedRoundAndMatch.match.winner_team_id !== null && selectedRoundAndMatch.match.complete"
                        class="flex w-full flex-col gap-2"
                    >
                        <p v-if="rollbackForm.errors.playoff_match_id" class="text-sm text-destructive">{{ rollbackForm.errors.playoff_match_id }}</p>
                        <p v-if="rollbackForm.errors.playoff" class="text-sm text-destructive">{{ rollbackForm.errors.playoff }}</p>
                        <Button type="button" variant="outline" :disabled="rollbackForm.processing" @click="submitPlayoffRollback">
                            Roll back result
                        </Button>
                    </div>
                    <Button v-else type="button" variant="outline" class="w-full sm:w-auto" @click="scoreDialogOpen = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueDetailLayout>
</template>
