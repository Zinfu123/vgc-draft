<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { multiplier, normalizeTypeName, TYPE_ORDER } from '@/lib/typeEffectiveness';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const STORAGE_KEY = 'vgc-team-coverage-planner-v1';

interface VersionGroupRow {
    id: number;
    slug: string;
    name: string;
    generation: number;
    sort_order: number;
}

interface MyTeamRow {
    id: number;
    name: string;
    league_id: number;
    league_name: string;
}

interface LearnsetRow {
    move_id: number;
    move_name: string;
    method: string;
    level: number;
    type_slug?: string | null;
    damage_class?: string | null;
    power?: number | null;
    accuracy?: number | null;
    ailment_name?: string | null;
}

interface PokedexHit {
    id: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2: string | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
}

const props = defineProps<{
    versionGroups: VersionGroupRow[];
    defaultVersionSlug: string;
    typeOrder: string[];
    myTeams: MyTeamRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Team coverage', href: '/team-coverage' },
];

function xsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return m ? decodeURIComponent(m[1]) : '';
}

type MoveIdTuple = [number | null, number | null, number | null, number | null];

interface SlotBase {
    pokedexId: number | null;
    label: string;
    spriteUrl: string | null;
    type1: string;
    type2: string;
    teraType: string;
    search: string;
    suggestionsOpen: boolean;
    suggestions: PokedexHit[];
}

interface AllySlot extends SlotBase {
    learnset: LearnsetRow[];
    moveIds: MoveIdTuple;
    loadingLearnset: boolean;
}

type EnemySlot = SlotBase;

function emptyAllySlot(): AllySlot {
    return {
        pokedexId: null,
        label: '',
        spriteUrl: null,
        type1: '',
        type2: '',
        teraType: '',
        search: '',
        suggestionsOpen: false,
        suggestions: [],
        learnset: [],
        moveIds: [null, null, null, null],
        loadingLearnset: false,
    };
}

function emptyEnemySlot(): EnemySlot {
    return {
        pokedexId: null,
        label: '',
        spriteUrl: null,
        type1: '',
        type2: '',
        teraType: '',
        search: '',
        suggestionsOpen: false,
        suggestions: [],
    };
}

const selectedVersionSlug = ref(props.defaultVersionSlug);
const allySlots = ref<AllySlot[]>(Array.from({ length: 6 }, emptyAllySlot));
const enemySlots = ref<EnemySlot[]>(Array.from({ length: 6 }, emptyEnemySlot));
const importTeamId = ref<number | ''>('');
const rosterLoading = ref(false);
const searchTimers = new Map<string, ReturnType<typeof setTimeout>>();

const typeOptions = computed(() => (props.typeOrder.length ? props.typeOrder : [...TYPE_ORDER]));

const selectedGeneration = computed(() => {
    const g = props.versionGroups.find((v) => v.slug === selectedVersionSlug.value);

    return g?.generation ?? null;
});

type DefensiveCellKind = 'neutral' | 'weak2' | 'weak4' | 'resistHalf' | 'resistQuarter' | 'immune';

interface DefensiveCell {
    kind: DefensiveCellKind;
    display: string;
    mult: number;
}

function defensiveCellForMultiplier(m: number): DefensiveCell {
    if (m === 0) {
        return { kind: 'immune', display: 'immune', mult: m };
    }
    if (Math.abs(m - 4) < 1e-6) {
        return { kind: 'weak4', display: '4x', mult: 4 };
    }
    if (Math.abs(m - 2) < 1e-6) {
        return { kind: 'weak2', display: '2x', mult: 2 };
    }
    if (Math.abs(m - 0.25) < 1e-6) {
        return { kind: 'resistQuarter', display: '¼', mult: 0.25 };
    }
    if (Math.abs(m - 0.5) < 1e-6) {
        return { kind: 'resistHalf', display: '½', mult: 0.5 };
    }
    if (m > 1) {
        return { kind: 'weak2', display: `${m}×`, mult: m };
    }
    if (m < 1) {
        return { kind: 'resistHalf', display: `${m}×`, mult: m };
    }

    return { kind: 'neutral', display: '', mult: 1 };
}

/** Ally slots with typings — one column per Pokémon in the defensive table. */
const defensiveAllyColumns = computed(() =>
    allySlots.value
        .map((slot, index) => ({ slot, index }))
        .filter(({ slot }) => slot.type1.trim() !== ''),
);

const defensiveRows = computed(() => {
    const cols = defensiveAllyColumns.value;

    return typeOptions.value.map((attack) => {
        const cells = cols.map(({ slot }) => {
            const m = multiplier(attack, slot.type1, slot.type2 || null, slot.teraType || null);

            return defensiveCellForMultiplier(m);
        });

        const totalWeak = cells.filter((c) => c.kind === 'weak2' || c.kind === 'weak4').length;
        const totalResist = cells.filter(
            (c) => c.kind === 'resistHalf' || c.kind === 'resistQuarter' || c.kind === 'immune',
        ).length;

        return {
            attack,
            cells,
            totalWeak,
            totalResist,
        };
    });
});

function totalWeakCellStyle(count: number, max: number): Record<string, string> {
    if (count <= 0 || max <= 0) {
        return {};
    }
    const step = Math.min(count, max);
    const t = max <= 1 ? 1 : (step - 1) / (max - 1);
    const L = 0.58 - t * 0.26;
    const C = 0.12 + t * 0.1;
    const alpha = 0.38 + t * 0.42;

    return {
        backgroundColor: `oklch(${L.toFixed(3)} ${C.toFixed(3)} 25 / ${alpha.toFixed(3)})`,
    };
}

function totalResistCellStyle(count: number, max: number): Record<string, string> {
    if (count <= 0 || max <= 0) {
        return {};
    }
    const step = Math.min(count, max);
    const t = max <= 1 ? 1 : (step - 1) / (max - 1);
    const L = 0.52 - t * 0.22;
    const C = 0.1 + t * 0.08;
    const alpha = 0.38 + t * 0.42;

    return {
        backgroundColor: `oklch(${L.toFixed(3)} ${C.toFixed(3)} 150 / ${alpha.toFixed(3)})`,
    };
}

function damagingLearnsetRows(rows: LearnsetRow[]): LearnsetRow[] {
    return rows.filter(
        (r) =>
            (r.damage_class === 'physical' || r.damage_class === 'special') &&
            r.type_slug &&
            normalizeTypeName(String(r.type_slug)),
    );
}

async function jsonFetch(url: string): Promise<Response> {
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });
}

function scheduleSearch(side: 'ally' | 'enemy', index: number): void {
    const key = `${side}-${index}`;
    const prev = searchTimers.get(key);
    if (prev) {
        clearTimeout(prev);
    }
    const timer = setTimeout(() => {
        void runSearch(side, index);
        searchTimers.delete(key);
    }, 120);
    searchTimers.set(key, timer);
}

async function runSearch(side: 'ally' | 'enemy', index: number): Promise<void> {
    const slot = side === 'ally' ? allySlots.value[index] : enemySlots.value[index];
    const q = slot.search.trim();
    if (q.length < 2) {
        slot.suggestions = [];
        slot.suggestionsOpen = false;

        return;
    }

    const params = new URLSearchParams({ search: q, per_page: '24' });
    if (selectedGeneration.value !== null) {
        params.set('generation', String(selectedGeneration.value));
    }
    const url = route('team-coverage.pokedex-search') + '?' + params.toString();
    const res = await jsonFetch(url);
    if (!res.ok) {
        slot.suggestions = [];

        return;
    }
    const body = (await res.json()) as Paginator<PokedexHit>;
    slot.suggestions = body.data ?? [];
    slot.suggestionsOpen = slot.suggestions.length > 0;
}

async function loadLearnset(side: 'ally' | 'enemy', index: number): Promise<void> {
    const slot = side === 'ally' ? allySlots.value[index] : enemySlots.value[index];
    if (!slot.pokedexId) {
        return;
    }
    if (side === 'ally') {
        allySlots.value[index].loadingLearnset = true;
    }
    try {
        const url = route('team-coverage.learnset', { pokedex: slot.pokedexId }) + '?game=' + encodeURIComponent(selectedVersionSlug.value);
        const res = await jsonFetch(url);
        if (!res.ok) {
            return;
        }
        const body = (await res.json()) as {
            learnset: LearnsetRow[];
            game: { type1: string | null; type2: string | null } | null;
        };

        if (body.game?.type1) {
            slot.type1 = body.game.type1;
            slot.type2 = body.game.type2 ?? '';
        }

        if (side === 'ally') {
            allySlots.value[index].learnset = Array.isArray(body.learnset) ? body.learnset : [];
            allySlots.value[index].moveIds = [null, null, null, null];
        }
    } finally {
        if (side === 'ally') {
            allySlots.value[index].loadingLearnset = false;
        }
    }
}

async function pickSuggestion(side: 'ally' | 'enemy', index: number, hit: PokedexHit): Promise<void> {
    const slot = side === 'ally' ? allySlots.value[index] : enemySlots.value[index];
    slot.pokedexId = hit.id;
    slot.label = hit.name;
    slot.spriteUrl = hit.sprite_url;
    slot.type1 = hit.type1;
    slot.type2 = hit.type2 ?? '';
    slot.search = hit.name;
    slot.suggestionsOpen = false;
    slot.suggestions = [];

    if (side === 'ally') {
        allySlots.value[index].learnset = [];
        allySlots.value[index].moveIds = [null, null, null, null];
        await loadLearnset(side, index);
    } else {
        await loadLearnset(side, index);
    }
}

function clearSlot(side: 'ally' | 'enemy', index: number): void {
    if (side === 'ally') {
        allySlots.value[index] = emptyAllySlot();
    } else {
        enemySlots.value[index] = emptyEnemySlot();
    }
}

function clearAll(): void {
    allySlots.value = Array.from({ length: 6 }, emptyAllySlot);
    enemySlots.value = Array.from({ length: 6 }, emptyEnemySlot);
}

async function loadRoster(): Promise<void> {
    if (importTeamId.value === '') {
        return;
    }
    rosterLoading.value = true;
    try {
        const url = route('team-coverage.roster', { team: importTeamId.value });
        const res = await jsonFetch(url);
        if (!res.ok) {
            return;
        }
        const body = (await res.json()) as {
            version_group_slug: string;
            slots: { pokedex_id: number | null; name: string; sprite_url: string | null; type1: string | null; type2: string | null }[];
        };

        if (body.version_group_slug) {
            selectedVersionSlug.value = body.version_group_slug;
        }

        await nextTick();

        for (let i = 0; i < 6; i++) {
            const s = body.slots[i];
            if (!s?.pokedex_id) {
                allySlots.value[i] = emptyAllySlot();
                continue;
            }
            const slot = allySlots.value[i];
            slot.pokedexId = s.pokedex_id;
            slot.label = s.name;
            slot.spriteUrl = s.sprite_url;
            slot.type1 = s.type1 ?? '';
            slot.type2 = s.type2 ?? '';
            slot.teraType = '';
            slot.search = s.name;
            slot.learnset = [];
            slot.moveIds = [null, null, null, null];
            await loadLearnset('ally', i);
        }
    } finally {
        rosterLoading.value = false;
    }
}

interface PersistShape {
    v: 1;
    versionSlug: string;
    ally: {
        pokedexId: number | null;
        label: string;
        type1: string;
        type2: string;
        teraType: string;
        moveIds: MoveIdTuple;
    }[];
    enemy: { pokedexId: number | null; label: string; type1: string; type2: string; teraType: string }[];
}

function persist(): void {
    const data: PersistShape = {
        v: 1,
        versionSlug: selectedVersionSlug.value,
        ally: allySlots.value.map((s) => ({
            pokedexId: s.pokedexId,
            label: s.label,
            type1: s.type1,
            type2: s.type2,
            teraType: s.teraType,
            moveIds: [...s.moveIds] as MoveIdTuple,
        })),
        enemy: enemySlots.value.map((s) => ({
            pokedexId: s.pokedexId,
            label: s.label,
            type1: s.type1,
            type2: s.type2,
            teraType: s.teraType,
        })),
    };
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {
        /* ignore */
    }
}

async function restore(): Promise<void> {
    let raw: string | null = null;
    try {
        raw = localStorage.getItem(STORAGE_KEY);
    } catch {
        return;
    }
    if (!raw) {
        return;
    }
    let data: PersistShape;
    try {
        data = JSON.parse(raw) as PersistShape;
    } catch {
        return;
    }
    if (data.v !== 1 || !Array.isArray(data.ally) || !Array.isArray(data.enemy)) {
        return;
    }

    if (data.versionSlug) {
        selectedVersionSlug.value = data.versionSlug;
    }

    await nextTick();

    for (let i = 0; i < 6; i++) {
        allySlots.value[i] = emptyAllySlot();
        const sa = data.ally[i];
        if (sa?.pokedexId) {
            allySlots.value[i].pokedexId = sa.pokedexId;
            allySlots.value[i].label = sa.label ?? '';
            allySlots.value[i].type1 = sa.type1 ?? '';
            allySlots.value[i].type2 = sa.type2 ?? '';
            allySlots.value[i].teraType = sa.teraType ?? '';
            allySlots.value[i].moveIds = sa.moveIds ?? [null, null, null, null];
            allySlots.value[i].search = sa.label ?? '';
        }
    }

    for (let i = 0; i < 6; i++) {
        enemySlots.value[i] = emptyEnemySlot();
        const se = data.enemy[i];
        if (se?.pokedexId) {
            enemySlots.value[i].pokedexId = se.pokedexId;
            enemySlots.value[i].label = se.label ?? '';
            enemySlots.value[i].type1 = se.type1 ?? '';
            enemySlots.value[i].type2 = se.type2 ?? '';
            enemySlots.value[i].teraType = se.teraType ?? '';
            enemySlots.value[i].search = se.label ?? '';
        }
    }

    await nextTick();

    for (let i = 0; i < 6; i++) {
        if (allySlots.value[i].pokedexId) {
            await loadLearnset('ally', i);
            allySlots.value[i].moveIds = data.ally[i]?.moveIds ?? [null, null, null, null];
        }
        if (enemySlots.value[i].pokedexId) {
            await loadLearnset('enemy', i);
        }
    }
}

let persistTimer: ReturnType<typeof setTimeout> | null = null;
function schedulePersist(): void {
    if (persistTimer) {
        clearTimeout(persistTimer);
    }
    persistTimer = setTimeout(() => {
        persist();
        persistTimer = null;
    }, 400);
}

watch([selectedVersionSlug, allySlots, enemySlots], schedulePersist, { deep: true });

watch(selectedVersionSlug, async () => {
    for (let i = 0; i < 6; i++) {
        if (allySlots.value[i].pokedexId) {
            await loadLearnset('ally', i);
        }
        if (enemySlots.value[i].pokedexId) {
            await loadLearnset('enemy', i);
        }
    }
});

onMounted(() => {
    void restore();
});

function moveOptions(slot: AllySlot): LearnsetRow[] {
    return damagingLearnsetRows(slot.learnset);
}

function blurSuggestions(side: 'ally' | 'enemy', index: number): void {
    setTimeout(() => {
        const slot = side === 'ally' ? allySlots.value[index] : enemySlots.value[index];
        slot.suggestionsOpen = false;
    }, 150);
}

const activeAllyIdx = ref<number | null>(null);
const activeEnemyIdx = ref<number | null>(null);

const activeAllySlot = computed(() => (activeAllyIdx.value !== null ? allySlots.value[activeAllyIdx.value] : null));
const activeEnemySlot = computed(() => (activeEnemyIdx.value !== null ? enemySlots.value[activeEnemyIdx.value] : null));

function toggleAllySlot(idx: number): void {
    activeAllyIdx.value = activeAllyIdx.value === idx ? null : idx;
}

function toggleEnemySlot(idx: number): void {
    activeEnemyIdx.value = activeEnemyIdx.value === idx ? null : idx;
}

function typeBadgeStyle(type: string): Record<string, string> {
    if (!type.trim()) {
        return {};
    }

    return { backgroundColor: `var(--${type.toLowerCase()}type)` };
}

const offensiveEnemyCols = computed(() => enemySlots.value.filter((e) => e.type1.trim()));

interface OffensiveRow {
    allyLabel: string;
    moveName: string;
    typeSlug: string;
    cells: DefensiveCell[];
}

const offensiveRows = computed((): OffensiveRow[] => {
    const enemyCols = offensiveEnemyCols.value;
    if (!enemyCols.length) {
        return [];
    }

    const rows: OffensiveRow[] = [];

    for (const slot of allySlots.value) {
        if (!slot.label.trim()) {
            continue;
        }
        for (let mi = 0; mi < 4; mi++) {
            const mid = slot.moveIds[mi];
            if (mid === null) {
                continue;
            }
            const mv = slot.learnset.find((r) => r.move_id === mid);
            if (!mv?.type_slug) {
                continue;
            }
            const cells = enemyCols.map((enemy) => {
                const m = multiplier(String(mv.type_slug), enemy.type1, enemy.type2 || null, enemy.teraType || null);

                return defensiveCellForMultiplier(m);
            });
            rows.push({ allyLabel: slot.label.trim() || '—', moveName: mv.move_name, typeSlug: String(mv.type_slug), cells });
        }
    }

    return rows;
});
</script>

<template>
    <Head title="Team coverage planner" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-6 p-4 md:p-6">
            <!-- Header -->
            <div>
                <h1 class="text-foreground text-2xl font-semibold tracking-tight">Team coverage planner</h1>
                <p class="text-muted-foreground mt-1 max-w-3xl text-sm">
                    Type matchup tables for six-on-six planning. Pick moves from your league's game learnsets. For exact damage ranges, use the
                    <a href="https://calc.pokemonshowdown.com/" class="text-primary underline-offset-4 hover:underline" target="_blank" rel="noopener noreferrer">Pokémon Damage Calculator</a>.
                </p>
            </div>

            <!-- Controls -->
            <Card>
                <CardContent class="flex flex-wrap items-end gap-4 pt-6">
                    <div class="flex flex-col gap-1">
                        <Label for="vg-slug">Version</Label>
                        <select id="vg-slug" v-model="selectedVersionSlug" class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs sm:w-72 dark:bg-transparent">
                            <option v-for="g in versionGroups" :key="g.slug" :value="g.slug">{{ g.name }}</option>
                        </select>
                    </div>
                    <div v-if="myTeams.length" class="flex flex-col gap-1">
                        <Label for="import-team">Import drafted team</Label>
                        <div class="flex items-center gap-2">
                            <select id="import-team" v-model="importTeamId" class="border-input bg-background h-9 min-w-[12rem] rounded-md border px-2 text-sm shadow-xs dark:bg-transparent">
                                <option value="">— Select team —</option>
                                <option v-for="t in myTeams" :key="t.id" :value="t.id">{{ t.league_name }} — {{ t.name }}</option>
                            </select>
                            <Button type="button" variant="secondary" size="sm" :disabled="importTeamId === '' || rosterLoading" @click="loadRoster">
                                {{ rosterLoading ? 'Loading...' : 'Load' }}
                            </Button>
                        </div>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="clearAll">Clear all</Button>
                </CardContent>
            </Card>

            <!-- Defensive Coverage — hoisted above slots -->
            <Card>
                <CardHeader>
                    <CardTitle>Defensive coverage</CardTitle>
                    <CardDescription>Effectiveness of each incoming type against your team. Add Pokémon below to populate the table.</CardDescription>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <p v-if="defensiveAllyColumns.length === 0" class="text-muted-foreground text-sm italic">
                        Add Pokémon to your team below to see the matrix.
                    </p>
                    <table v-else class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted/60 border-b">
                                <th class="text-foreground w-28 p-2 text-left text-xs font-semibold uppercase tracking-wide">Type</th>
                                <th v-for="col in defensiveAllyColumns" :key="'dhdr-' + col.index" class="text-foreground min-w-[4.5rem] p-2 text-center text-xs font-semibold">
                                    <img v-if="col.slot.spriteUrl" :src="col.slot.spriteUrl" :alt="col.slot.label" class="mx-auto mb-0.5 size-8 object-contain" />
                                    <span class="line-clamp-2">{{ col.slot.label.trim() || `Slot ${col.index + 1}` }}</span>
                                </th>
                                <th class="text-foreground w-14 p-2 text-center text-xs font-semibold">Weak</th>
                                <th class="text-foreground w-14 p-2 text-center text-xs font-semibold">Resist</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in defensiveRows" :key="row.attack" class="border-border/80 hover:bg-muted/30 border-b">
                                <td class="p-2">
                                    <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(row.attack)">
                                        {{ row.attack }}
                                    </span>
                                </td>
                                <td v-for="(cell, ci) in row.cells" :key="row.attack + '-' + ci" class="border-border/40 border-l p-2 text-center align-middle">
                                    <span v-if="cell.kind === 'weak4'" class="inline-block rounded bg-red-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-red-500">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'weak2'" class="text-destructive text-xs font-semibold">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'resistQuarter'" class="inline-block rounded bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-emerald-500">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'resistHalf'" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'immune'" class="bg-muted text-muted-foreground inline-block rounded px-2 py-0.5 text-xs font-medium">immune</span>
                                </td>
                                <td
                                    class="border-border/40 border-l p-2 text-center text-sm font-semibold tabular-nums transition-colors"
                                    :class="row.totalWeak > 0 ? 'text-white' : 'text-muted-foreground'"
                                    :style="totalWeakCellStyle(row.totalWeak, defensiveAllyColumns.length)"
                                >
                                    {{ row.totalWeak > 0 ? row.totalWeak : '' }}
                                </td>
                                <td
                                    class="border-border/40 border-l p-2 text-center text-sm font-semibold tabular-nums transition-colors"
                                    :class="row.totalResist > 0 ? 'text-white' : 'text-muted-foreground'"
                                    :style="totalResistCellStyle(row.totalResist, defensiveAllyColumns.length)"
                                >
                                    {{ row.totalResist > 0 ? row.totalResist : '' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- Team slot inputs -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Your team -->
                <div class="space-y-3">
                    <div>
                        <h2 class="text-lg font-medium">Your team</h2>
                        <p class="text-muted-foreground text-xs">Click a slot to add or edit. Moves drive the offensive table.</p>
                    </div>

                    <!-- Compact slot chips -->
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-3">
                        <button
                            v-for="(slot, idx) in allySlots"
                            :key="'ally-chip-' + idx"
                            type="button"
                            class="flex h-24 w-full flex-col items-center justify-center gap-0.5 rounded-lg border-2 px-1 transition-colors"
                            :class="
                                activeAllyIdx === idx
                                    ? 'border-primary bg-primary/5'
                                    : slot.pokedexId
                                      ? 'border-border hover:border-primary/40'
                                      : 'border-dashed border-muted-foreground/30 hover:border-muted-foreground/50'
                            "
                            @click="toggleAllySlot(idx)"
                        >
                            <template v-if="slot.pokedexId">
                                <img :src="slot.spriteUrl ?? ''" :alt="slot.label" class="size-10 object-contain" />
                                <span class="w-full truncate text-center text-[11px] font-medium leading-tight">{{ slot.label }}</span>
                                <div class="flex gap-0.5">
                                    <span v-if="slot.type1" class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(slot.teraType || slot.type1)">
                                        {{ slot.teraType ? slot.teraType : slot.type1 }}
                                    </span>
                                    <span v-if="slot.type2 && !slot.teraType" class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(slot.type2)">{{ slot.type2 }}</span>
                                </div>
                            </template>
                            <template v-else>
                                <span class="text-muted-foreground text-2xl leading-none">+</span>
                                <span class="text-muted-foreground text-[11px]">Slot {{ idx + 1 }}</span>
                            </template>
                        </button>
                    </div>

                    <!-- Expanded editor -->
                    <div v-if="activeAllySlot !== null" class="space-y-4 rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">{{ activeAllySlot.label || `Slot ${activeAllyIdx! + 1}` }}</span>
                            <Button v-if="activeAllySlot.pokedexId" type="button" variant="ghost" size="sm" class="text-destructive h-7 px-2 text-xs" @click="clearSlot('ally', activeAllyIdx!)">
                                Remove
                            </Button>
                        </div>

                        <div class="relative">
                            <Label class="text-muted-foreground text-xs">Pokémon</Label>
                            <Input
                                v-model="activeAllySlot.search"
                                placeholder="Search name…"
                                class="mt-1"
                                autocomplete="off"
                                @input="scheduleSearch('ally', activeAllyIdx!)"
                                @focus="activeAllySlot.suggestionsOpen = activeAllySlot.suggestions.length > 0"
                                @blur="blurSuggestions('ally', activeAllyIdx!)"
                            />
                            <ul
                                v-if="activeAllySlot.suggestionsOpen && activeAllySlot.suggestions.length"
                                class="bg-popover text-popover-foreground absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border text-sm shadow-md"
                            >
                                <li
                                    v-for="h in activeAllySlot.suggestions"
                                    :key="h.id"
                                    class="hover:bg-accent flex cursor-pointer items-center gap-2 px-2 py-1.5"
                                    @mousedown.prevent="pickSuggestion('ally', activeAllyIdx!, h)"
                                >
                                    <img :src="h.sprite_url" :alt="h.name" class="size-6 shrink-0 object-contain" />
                                    <span>{{ h.name }}</span>
                                    <span class="ml-auto flex shrink-0 gap-0.5">
                                        <span class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(h.type1)">{{ h.type1 }}</span>
                                        <span v-if="h.type2" class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(h.type2)">{{ h.type2 }}</span>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="activeAllySlot.pokedexId" class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-muted-foreground text-xs">Tera type</Label>
                                <select v-model="activeAllySlot.teraType" class="border-input bg-background mt-1 h-9 w-full rounded-md border px-2 text-sm shadow-xs dark:bg-transparent">
                                    <option value="">— None —</option>
                                    <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                            <div class="flex flex-col justify-end pb-1">
                                <span v-if="activeAllySlot.loadingLearnset" class="text-muted-foreground text-xs">Loading learnset…</span>
                                <div v-else class="flex flex-wrap gap-1">
                                    <span v-if="activeAllySlot.teraType" class="rounded px-1.5 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(activeAllySlot.teraType)">
                                        Tera: {{ activeAllySlot.teraType }}
                                    </span>
                                    <template v-else>
                                        <span v-if="activeAllySlot.type1" class="rounded px-1.5 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(activeAllySlot.type1)">{{ activeAllySlot.type1 }}</span>
                                        <span v-if="activeAllySlot.type2" class="rounded px-1.5 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(activeAllySlot.type2)">{{ activeAllySlot.type2 }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div v-if="activeAllySlot.pokedexId && !activeAllySlot.loadingLearnset" class="grid grid-cols-2 gap-2">
                            <div v-for="mi in 4" :key="mi" class="flex flex-col gap-1">
                                <Label class="text-muted-foreground text-xs">Move {{ mi }}</Label>
                                <select v-model="activeAllySlot.moveIds[mi - 1]" class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs dark:bg-transparent">
                                    <option :value="null">—</option>
                                    <option v-for="mv in moveOptions(activeAllySlot)" :key="mv.move_id" :value="mv.move_id">{{ mv.move_name }} ({{ mv.type_slug }})</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enemy team -->
                <div class="space-y-3">
                    <div>
                        <h2 class="text-lg font-medium">
                            Enemy team
                            <span class="text-muted-foreground text-sm font-normal">(optional)</span>
                        </h2>
                        <p class="text-muted-foreground text-xs">Used for the offensive coverage table. Click a slot to add.</p>
                    </div>

                    <!-- Compact slot chips -->
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-3">
                        <button
                            v-for="(slot, idx) in enemySlots"
                            :key="'enemy-chip-' + idx"
                            type="button"
                            class="flex h-24 w-full flex-col items-center justify-center gap-0.5 rounded-lg border-2 px-1 transition-colors"
                            :class="
                                activeEnemyIdx === idx
                                    ? 'border-primary bg-primary/5'
                                    : slot.pokedexId
                                      ? 'border-border hover:border-primary/40'
                                      : 'border-dashed border-muted-foreground/30 hover:border-muted-foreground/50'
                            "
                            @click="toggleEnemySlot(idx)"
                        >
                            <template v-if="slot.pokedexId">
                                <img :src="slot.spriteUrl ?? ''" :alt="slot.label" class="size-10 object-contain" />
                                <span class="w-full truncate text-center text-[11px] font-medium leading-tight">{{ slot.label }}</span>
                                <div class="flex gap-0.5">
                                    <span v-if="slot.type1" class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(slot.teraType || slot.type1)">
                                        {{ slot.teraType ? slot.teraType : slot.type1 }}
                                    </span>
                                    <span v-if="slot.type2 && !slot.teraType" class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(slot.type2)">{{ slot.type2 }}</span>
                                </div>
                            </template>
                            <template v-else>
                                <span class="text-muted-foreground text-2xl leading-none">+</span>
                                <span class="text-muted-foreground text-[11px]">Opp {{ idx + 1 }}</span>
                            </template>
                        </button>
                    </div>

                    <!-- Expanded editor -->
                    <div v-if="activeEnemySlot !== null" class="space-y-4 rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">{{ activeEnemySlot.label || `Opponent ${activeEnemyIdx! + 1}` }}</span>
                            <Button v-if="activeEnemySlot.pokedexId" type="button" variant="ghost" size="sm" class="text-destructive h-7 px-2 text-xs" @click="clearSlot('enemy', activeEnemyIdx!)">
                                Remove
                            </Button>
                        </div>

                        <div class="relative">
                            <Label class="text-muted-foreground text-xs">Pokémon</Label>
                            <Input
                                v-model="activeEnemySlot.search"
                                placeholder="Search name…"
                                class="mt-1"
                                autocomplete="off"
                                @input="scheduleSearch('enemy', activeEnemyIdx!)"
                                @focus="activeEnemySlot.suggestionsOpen = activeEnemySlot.suggestions.length > 0"
                                @blur="blurSuggestions('enemy', activeEnemyIdx!)"
                            />
                            <ul
                                v-if="activeEnemySlot.suggestionsOpen && activeEnemySlot.suggestions.length"
                                class="bg-popover text-popover-foreground absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border text-sm shadow-md"
                            >
                                <li
                                    v-for="h in activeEnemySlot.suggestions"
                                    :key="h.id"
                                    class="hover:bg-accent flex cursor-pointer items-center gap-2 px-2 py-1.5"
                                    @mousedown.prevent="pickSuggestion('enemy', activeEnemyIdx!, h)"
                                >
                                    <img :src="h.sprite_url" :alt="h.name" class="size-6 shrink-0 object-contain" />
                                    <span>{{ h.name }}</span>
                                    <span class="ml-auto flex shrink-0 gap-0.5">
                                        <span class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(h.type1)">{{ h.type1 }}</span>
                                        <span v-if="h.type2" class="rounded px-1 py-px text-[10px] font-semibold text-white" :style="typeBadgeStyle(h.type2)">{{ h.type2 }}</span>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="activeEnemySlot.pokedexId" class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-muted-foreground text-xs">Tera type</Label>
                                <select v-model="activeEnemySlot.teraType" class="border-input bg-background mt-1 h-9 w-full rounded-md border px-2 text-sm shadow-xs dark:bg-transparent">
                                    <option value="">— None —</option>
                                    <option v-for="t in typeOptions" :key="'et-' + t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                            <div class="flex flex-col justify-end pb-1">
                                <div class="flex flex-wrap gap-1">
                                    <span v-if="activeEnemySlot.teraType" class="rounded px-1.5 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(activeEnemySlot.teraType)">
                                        Tera: {{ activeEnemySlot.teraType }}
                                    </span>
                                    <template v-else>
                                        <span v-if="activeEnemySlot.type1" class="rounded px-1.5 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(activeEnemySlot.type1)">{{ activeEnemySlot.type1 }}</span>
                                        <span v-if="activeEnemySlot.type2" class="rounded px-1.5 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(activeEnemySlot.type2)">{{ activeEnemySlot.type2 }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Offensive Coverage Table -->
            <Card v-if="offensiveEnemyCols.length > 0">
                <CardHeader>
                    <CardTitle>Offensive coverage</CardTitle>
                    <CardDescription>Your selected moves against each opponent. Neutral (1×) cells are left blank.</CardDescription>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <p v-if="offensiveRows.length === 0" class="text-muted-foreground text-sm italic">Select moves for your Pokémon above to see coverage.</p>
                    <table v-else class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted/60 border-b">
                                <th class="text-foreground p-2 text-left text-xs font-semibold">Pokémon</th>
                                <th class="text-foreground p-2 text-left text-xs font-semibold">Move</th>
                                <th v-for="(enemy, ei) in offensiveEnemyCols" :key="'ohdr-' + ei" class="text-foreground min-w-[5rem] p-2 text-center text-xs font-semibold">
                                    <img v-if="enemy.spriteUrl" :src="enemy.spriteUrl" :alt="enemy.label" class="mx-auto mb-0.5 size-8 object-contain" />
                                    <span class="line-clamp-2">{{ enemy.label || `Opp ${ei + 1}` }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, ri) in offensiveRows" :key="ri" class="border-border/80 hover:bg-muted/30 border-b">
                                <td class="text-foreground p-2 font-medium">{{ row.allyLabel }}</td>
                                <td class="p-2">
                                    <span class="inline-block rounded px-1.5 py-0.5 text-xs font-semibold text-white" :style="typeBadgeStyle(row.typeSlug)">{{ row.moveName }}</span>
                                </td>
                                <td v-for="(cell, ci) in row.cells" :key="ri + '-' + ci" class="border-border/40 border-l p-2 text-center align-middle">
                                    <span v-if="cell.kind === 'weak4'" class="inline-block rounded bg-red-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-red-500">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'weak2'" class="text-destructive text-xs font-semibold">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'resistQuarter'" class="inline-block rounded bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-emerald-500">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'resistHalf'" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ cell.display }}</span>
                                    <span v-else-if="cell.kind === 'immune'" class="bg-muted text-muted-foreground inline-block rounded px-2 py-0.5 text-xs font-medium">immune</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
