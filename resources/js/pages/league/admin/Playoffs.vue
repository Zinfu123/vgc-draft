<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface FlashProps {
    flash?: {
        success?: string | null;
    };
}

interface League {
    id: number;
    name: string;
    logo?: string;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
}

interface PlayoffMatchRow {
    id: number;
    slot: string;
    round_index: number;
    sort_order: number;
    is_bronze: boolean;
    team1_id: number | null;
    team2_id: number | null;
    team1_name: string | null;
    team2_name: string | null;
    team1_score: number | null;
    team2_score: number | null;
    winner_team_id: number | null;
    completed_at: string | null;
}

interface PlayoffPayload {
    id: number;
    format: string;
    bracket_size: number;
    status: string;
    seed_order: number[];
    matches: PlayoffMatchRow[];
}

const props = defineProps<{
    league: League;
    teams: Team[];
    playoff: PlayoffPayload;
    allowedBracketSizes: number[];
    doubleEliminationSupported: boolean;
}>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

function teamsByOrderedIds(seedIds: number[], teams: Team[]): Team[] {
    const map = new Map(teams.map((t) => [t.id, t]));
    return seedIds.map((id) => map.get(id)).filter((t): t is Team => t !== undefined);
}

const orderedTeams = ref<Team[]>(teamsByOrderedIds(props.playoff.seed_order, props.teams));

watch(
    () => [props.playoff.seed_order, props.teams] as const,
    ([ids, teams]) => {
        orderedTeams.value = teamsByOrderedIds([...ids], teams);
    },
    { deep: true },
);

const configForm = useForm({
    format: props.playoff.format,
    bracket_size: props.playoff.bracket_size,
    seed_order: [...props.playoff.seed_order],
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

let dragSeedIndex: number | null = null;

function onSeedDragStart(index: number): void {
    dragSeedIndex = index;
}

function onSeedDrop(index: number): void {
    if (dragSeedIndex === null) {
        return;
    }
    const next = [...orderedTeams.value];
    const [removed] = next.splice(dragSeedIndex, 1);
    next.splice(index, 0, removed);
    orderedTeams.value = next;
    dragSeedIndex = null;
}

const generateForm = useForm({});
const resetForm = useForm({});
const closeForm = useForm({});

const recordForm = useForm({
    playoff_match_id: 0,
    team1_score: 0,
    team2_score: 0,
});

const rollbackForm = useForm({
    playoff_match_id: 0,
});

const selectedMatchId = ref<number | null>(null);
const scoreDraft = ref({ team1: 0, team2: 0 });

const isDraft = computed(() => props.playoff.status === 'draft');
const isActive = computed(() => props.playoff.status === 'active');
const isCompleted = computed(() => props.playoff.status === 'completed');

const matchesByRound = computed(() => {
    const groups = new Map<number, PlayoffMatchRow[]>();
    for (const m of props.playoff.matches) {
        const key = m.round_index + (m.is_bronze ? 1000 : 0);
        const list = groups.get(key) ?? [];
        list.push(m);
        groups.set(key, list);
    }
    return [...groups.entries()]
        .sort(([a], [b]) => a - b)
        .map(([, rows]) => rows.sort((x, y) => x.sort_order - y.sort_order));
});

function roundLabel(match: PlayoffMatchRow): string {
    if (match.is_bronze) {
        return 'Bronze (3rd place)';
    }
    const maxR = Math.max(0, ...props.playoff.matches.filter((m) => !m.is_bronze).map((m) => m.round_index));
    if (match.round_index === maxR && maxR > 0) {
        return 'Finals';
    }
    if (props.playoff.bracket_size === 6) {
        if (match.round_index === 0) {
            return 'Opening round (4v5, 3v6)';
        }
        if (match.round_index === 1) {
            return 'Semifinals (1–2 have byes)';
        }
    }
    if (match.round_index === 0) {
        return 'Round 1';
    }
    return `Round ${match.round_index + 1}`;
}

function saveConfig(): void {
    configForm.seed_order = orderedTeams.value.map((t) => t.id);
    configForm.patch(route('leagues.admin.playoffs.update', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function generateBracket(): void {
    generateForm.post(route('leagues.admin.playoffs.generate', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function resetBracket(): void {
    resetForm.post(route('leagues.admin.playoffs.reset', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function closePlayoffs(): void {
    closeForm.post(route('leagues.admin.playoffs.close', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function openScoreEntry(match: PlayoffMatchRow): void {
    selectedMatchId.value = match.id;
    scoreDraft.value = { team1: 0, team2: 0 };
}

function submitRecord(match: PlayoffMatchRow): void {
    recordForm.playoff_match_id = match.id;
    recordForm.team1_score = scoreDraft.value.team1;
    recordForm.team2_score = scoreDraft.value.team2;
    recordForm.post(route('leagues.admin.playoffs.record', { league: props.league.id }), {
        preserveScroll: true,
        onSuccess: () => {
            selectedMatchId.value = null;
        },
    });
}

function submitRollback(match: PlayoffMatchRow): void {
    rollbackForm.playoff_match_id = match.id;
    rollbackForm.post(route('leagues.admin.playoffs.rollback', { league: props.league.id }), {
        preserveScroll: true,
    });
}

function canRecord(match: PlayoffMatchRow): boolean {
    return match.team1_id !== null && match.team2_id !== null && match.winner_team_id === null;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${league.name} — Playoffs`" />

        <AdminLayout :league-id="league.id" :league-name="league.name">
            <div class="flex max-w-3xl flex-col space-y-8">
                <HeadingSmall
                    title="Playoffs"
                    description="Configure seeds from standings, generate a single-elimination bracket with a bronze match, then record results. Only league admins can change the bracket."
                />

                <div
                    v-if="(page.props as FlashProps).flash?.success"
                    class="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm text-foreground"
                >
                    {{ (page.props as FlashProps).flash?.success }}
                </div>

                <section v-if="isCompleted" class="rounded-md border border-border bg-card p-4 text-sm text-muted-foreground">
                    Playoffs are closed for this league. The champion and medals are finalized.
                </section>

                <section v-if="isDraft" class="space-y-4">
                    <h3 class="text-lg font-semibold">Bracket setup</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="format">Format</Label>
                            <select
                                id="format"
                                v-model="configForm.format"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none dark:bg-background"
                            >
                                <option value="single_elimination">Single elimination</option>
                                <option value="double_elimination" :disabled="!doubleEliminationSupported">Double elimination</option>
                            </select>
                            <p v-if="!doubleEliminationSupported" class="text-xs text-muted-foreground">Double elimination is not available yet.</p>
                            <p v-if="configForm.errors.format" class="text-sm text-destructive">{{ configForm.errors.format }}</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="bracket_size">Bracket size</Label>
                            <select
                                id="bracket_size"
                                v-model.number="configForm.bracket_size"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none dark:bg-background"
                            >
                                <option v-for="s in allowedBracketSizes" :key="s" :value="s">{{ s }} teams</option>
                            </select>
                            <p v-if="configForm.errors.bracket_size" class="text-sm text-destructive">{{ configForm.errors.bracket_size }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label>Seed order (drag to reorder)</Label>
                        <p class="text-xs text-muted-foreground">Default order follows standings (points, then set wins). Top seeds are at the top.</p>
                        <ul class="divide-y divide-border rounded-md border border-border bg-card">
                            <li
                                v-for="(team, index) in orderedTeams"
                                :key="team.id"
                                draggable="true"
                                class="flex cursor-grab items-center justify-between gap-2 px-3 py-2 text-sm active:cursor-grabbing"
                                @dragstart="onSeedDragStart(index)"
                                @dragover.prevent
                                @drop="onSeedDrop(index)"
                            >
                                <span class="font-medium">{{ index + 1 }}. {{ team.name }}</span>
                                <span class="text-muted-foreground">{{ team.coach }}</span>
                            </li>
                        </ul>
                        <p v-if="configForm.errors.seed_order" class="text-sm text-destructive">{{ configForm.errors.seed_order }}</p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button type="button" class="min-h-11 w-full touch-manipulation sm:w-auto" :disabled="configForm.processing" @click="saveConfig"
                            >Save configuration</Button
                        >
                        <Button
                            type="button"
                            variant="default"
                            class="min-h-11 w-full touch-manipulation sm:w-auto"
                            :disabled="generateForm.processing"
                            @click="generateBracket"
                            >Generate bracket</Button
                        >
                    </div>
                    <p v-if="configForm.errors.playoff" class="text-sm text-destructive">{{ configForm.errors.playoff }}</p>
                    <p v-if="generateForm.errors.playoff" class="text-sm text-destructive">{{ generateForm.errors.playoff }}</p>
                    <p v-if="generateForm.errors.format" class="text-sm text-destructive">{{ generateForm.errors.format }}</p>
                </section>

                <section v-if="isActive || isCompleted" class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-semibold">Bracket</h3>
                        <Button v-if="isActive" type="button" variant="outline" :disabled="resetForm.processing" @click="resetBracket">Reset bracket</Button>
                    </div>
                    <p v-if="resetForm.errors.playoff" class="text-sm text-destructive">{{ resetForm.errors.playoff }}</p>

                    <div v-for="(group, gi) in matchesByRound" :key="gi" class="space-y-3">
                        <h4 class="text-sm font-semibold text-muted-foreground">{{ roundLabel(group[0]) }}</h4>
                        <div class="space-y-4">
                            <div
                                v-for="m in group"
                                :key="m.id"
                                class="rounded-md border border-border bg-card p-4 shadow-sm"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="text-sm">
                                        <span class="font-medium">{{ m.team1_name ?? 'TBD' }}</span>
                                        <span class="mx-2 text-muted-foreground">vs</span>
                                        <span class="font-medium">{{ m.team2_name ?? 'TBD' }}</span>
                                    </div>
                                    <div v-if="m.completed_at" class="text-xs text-muted-foreground">
                                        {{ m.team1_score }} – {{ m.team2_score }}
                                    </div>
                                </div>

                                <div v-if="isActive && canRecord(m)" class="mt-3 flex flex-col gap-3">
                                    <Button
                                        v-if="selectedMatchId !== m.id"
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        class="min-h-11 w-full max-w-md touch-manipulation sm:w-auto"
                                        @click="openScoreEntry(m)"
                                    >
                                        Enter result
                                    </Button>
                                    <template v-else>
                                        <div v-if="recordForm.errors.playoff_result" class="text-sm text-destructive">
                                            {{ recordForm.errors.playoff_result }}
                                        </div>
                                        <div v-if="recordForm.errors.playoff" class="text-sm text-destructive">
                                            {{ recordForm.errors.playoff }}
                                        </div>
                                        <div class="flex flex-wrap gap-3 sm:items-end">
                                            <div class="flex flex-col gap-1">
                                                <Label class="text-xs">{{ m.team1_name }} games</Label>
                                                <Input v-model.number="scoreDraft.team1" type="number" min="0" max="2" class="w-20" />
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <Label class="text-xs">{{ m.team2_name }} games</Label>
                                                <Input v-model.number="scoreDraft.team2" type="number" min="0" max="2" class="w-20" />
                                            </div>
                                            <Button type="button" class="self-end" :disabled="recordForm.processing" @click="submitRecord(m)">
                                                Save result
                                            </Button>
                                        </div>
                                    </template>
                                </div>

                                <div v-if="isActive && m.winner_team_id !== null" class="mt-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="rollbackForm.processing"
                                        @click="submitRollback(m)"
                                    >
                                        Roll back result
                                    </Button>
                                    <p v-if="rollbackForm.errors.playoff_match_id" class="mt-1 text-sm text-destructive">
                                        {{ rollbackForm.errors.playoff_match_id }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="isActive" class="flex flex-col gap-2 border-t border-border pt-4">
                        <Button type="button" class="min-h-11 w-full touch-manipulation sm:w-auto" :disabled="closeForm.processing" @click="closePlayoffs"
                            >Close playoffs & finalize league</Button
                        >
                        <p class="text-xs text-muted-foreground">
                            Requires a completed finals match and bronze match (when applicable). Sets league champion, 1st–3rd medals, and marks the league complete.
                        </p>
                        <p v-if="closeForm.errors.playoff" class="text-sm text-destructive">{{ closeForm.errors.playoff }}</p>
                    </div>
                </section>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
