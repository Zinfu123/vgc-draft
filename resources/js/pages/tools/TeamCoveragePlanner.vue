<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { bracketLabel, multiplier, normalizeTypeName, TYPE_ORDER } from '@/lib/typeEffectiveness';
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

const offensiveByEnemy = computed(() =>
    enemySlots.value.map((enemy, ei) => {
        if (!enemy.type1.trim()) {
            return { index: ei, label: '', lines: [] as { ally: string; move: string; mult: number; bracket: string }[] };
        }
        const lines: { ally: string; move: string; mult: number; bracket: string }[] = [];
        const el = enemy.label.trim() || `Enemy ${ei + 1}`;

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
                const m = multiplier(String(mv.type_slug), enemy.type1, enemy.type2 || null, enemy.teraType || null);
                lines.push({
                    ally: slot.label.trim() || '—',
                    move: mv.move_name,
                    mult: m,
                    bracket: bracketLabel(m),
                });
            }
        }

        return { index: ei, label: el, lines };
    }),
);

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
    }, 280);
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
</script>

<template>
    <Head title="Team coverage planner" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl space-y-8 p-4 md:p-6">
            <div>
                <h1 class="text-foreground text-2xl font-semibold tracking-tight">Team coverage planner</h1>
                <p class="text-muted-foreground mt-1 max-w-3xl text-sm">
                    Type matchup tables for six-on-six planning. Pick moves from Scarlet & Violet learnsets (or your league’s game). For exact damage ranges, use the
                    <a
                        href="https://calc.pokemonshowdown.com/"
                        class="text-primary underline-offset-4 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Pokémon Damage Calculator</a
                    >.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Game version</CardTitle>
                    <CardDescription>Learnsets and typings use this version group.</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex flex-col gap-1">
                        <Label for="vg-slug">Version</Label>
                        <select
                            id="vg-slug"
                            v-model="selectedVersionSlug"
                            class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs sm:w-72 dark:bg-transparent"
                        >
                            <option v-for="g in versionGroups" :key="g.slug" :value="g.slug">{{ g.name }}</option>
                        </select>
                    </div>
                    <div v-if="myTeams.length" class="flex flex-col gap-1">
                        <Label for="import-team">Import your drafted team (optional)</Label>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                id="import-team"
                                v-model="importTeamId"
                                class="border-input bg-background h-9 min-w-[12rem] rounded-md border px-2 text-sm shadow-xs dark:bg-transparent"
                            >
                                <option value="">— Select team —</option>
                                <option v-for="t in myTeams" :key="t.id" :value="t.id">
                                    {{ t.league_name }} — {{ t.name }}
                                </option>
                            </select>
                            <Button type="button" variant="secondary" size="sm" :disabled="importTeamId === '' || rosterLoading" @click="loadRoster">
                                {{ rosterLoading ? 'Loading…' : 'Load roster' }}
                            </Button>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" size="sm" @click="clearAll"> Clear all </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:gap-8">
                <div class="flex min-w-0 flex-1 flex-col gap-3">
                    <h2 class="text-lg font-medium">Your team</h2>
                    <p class="text-muted-foreground min-h-[2.75rem] text-xs leading-snug">
                        Add up to six Pokémon and moves. The defensive matrix uses typings (and Tera) from this side.
                    </p>
                    <div class="flex flex-col gap-4">
                        <Card v-for="(_, idx) in allySlots" :key="'ally-' + idx">
                            <CardHeader class="py-3">
                                <CardTitle class="text-base">Slot {{ idx + 1 }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="relative">
                                    <Label class="text-muted-foreground text-xs">Pokémon</Label>
                                    <Input
                                        v-model="allySlots[idx].search"
                                        placeholder="Search name…"
                                        class="mt-1"
                                        autocomplete="off"
                                        @input="scheduleSearch('ally', idx)"
                                        @focus="allySlots[idx].suggestionsOpen = allySlots[idx].suggestions.length > 0"
                                        @blur="blurSuggestions('ally', idx)"
                                    />
                                    <ul
                                        v-if="allySlots[idx].suggestionsOpen && allySlots[idx].suggestions.length"
                                        class="bg-popover text-popover-foreground absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border text-sm shadow-md"
                                    >
                                        <li
                                            v-for="h in allySlots[idx].suggestions"
                                            :key="h.id"
                                            class="hover:bg-accent cursor-pointer px-2 py-1.5"
                                            @mousedown.prevent="pickSuggestion('ally', idx, h)"
                                        >
                                            {{ h.name }}
                                            <span class="text-muted-foreground text-xs"
                                                >({{ h.type1 }}{{ h.type2 ? ` / ${h.type2}` : '' }})</span
                                            >
                                        </li>
                                    </ul>
                                </div>
                                <div v-if="allySlots[idx].pokedexId" class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <Label class="text-muted-foreground text-xs">Tera type</Label>
                                        <select
                                            v-model="allySlots[idx].teraType"
                                            class="border-input bg-background mt-1 h-9 w-full rounded-md border px-2 text-sm shadow-xs dark:bg-transparent"
                                        >
                                            <option value="">— None —</option>
                                            <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
                                        </select>
                                    </div>
                                    <div class="text-muted-foreground flex items-end text-xs">
                                        <span v-if="allySlots[idx].loadingLearnset">Loading learnset…</span>
                                        <span v-else>
                                            {{ allySlots[idx].type1 }}{{ allySlots[idx].type2 ? ` / ${allySlots[idx].type2}` : '' }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="allySlots[idx].pokedexId && !allySlots[idx].loadingLearnset" class="grid gap-2 sm:grid-cols-2">
                                    <div v-for="mi in 4" :key="mi" class="flex flex-col gap-1">
                                        <Label class="text-muted-foreground text-xs">Move {{ mi }}</Label>
                                        <select
                                            v-model="allySlots[idx].moveIds[mi - 1]"
                                            class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs dark:bg-transparent"
                                        >
                                            <option :value="null">—</option>
                                            <option v-for="mv in moveOptions(allySlots[idx])" :key="mv.move_id" :value="mv.move_id">
                                                {{ mv.move_name }} ({{ mv.type_slug }})
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <Button v-if="allySlots[idx].pokedexId" type="button" variant="ghost" size="sm" class="text-destructive" @click="clearSlot('ally', idx)">
                                    Remove
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-3">
                    <h2 class="text-lg font-medium">Enemy team (optional)</h2>
                    <p class="text-muted-foreground min-h-[2.75rem] text-xs leading-snug">
                        Used for offensive move coverage. Typings follow the selected game version.
                    </p>
                    <div class="flex flex-col gap-4">
                        <Card v-for="(_, idx) in enemySlots" :key="'enemy-' + idx">
                            <CardHeader class="py-3">
                                <CardTitle class="text-base">Opponent {{ idx + 1 }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="relative">
                                    <Label class="text-muted-foreground text-xs">Pokémon</Label>
                                    <Input
                                        v-model="enemySlots[idx].search"
                                        placeholder="Search name…"
                                        class="mt-1"
                                        autocomplete="off"
                                        @input="scheduleSearch('enemy', idx)"
                                        @focus="enemySlots[idx].suggestionsOpen = enemySlots[idx].suggestions.length > 0"
                                        @blur="blurSuggestions('enemy', idx)"
                                    />
                                    <ul
                                        v-if="enemySlots[idx].suggestionsOpen && enemySlots[idx].suggestions.length"
                                        class="bg-popover text-popover-foreground absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border text-sm shadow-md"
                                    >
                                        <li
                                            v-for="h in enemySlots[idx].suggestions"
                                            :key="h.id"
                                            class="hover:bg-accent cursor-pointer px-2 py-1.5"
                                            @mousedown.prevent="pickSuggestion('enemy', idx, h)"
                                        >
                                            {{ h.name }}
                                            <span class="text-muted-foreground text-xs"
                                                >({{ h.type1 }}{{ h.type2 ? ` / ${h.type2}` : '' }})</span
                                            >
                                        </li>
                                    </ul>
                                </div>
                                <div v-if="enemySlots[idx].pokedexId" class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <Label class="text-muted-foreground text-xs">Tera type</Label>
                                        <select
                                            v-model="enemySlots[idx].teraType"
                                            class="border-input bg-background mt-1 h-9 w-full rounded-md border px-2 text-sm shadow-xs dark:bg-transparent"
                                        >
                                            <option value="">— None —</option>
                                            <option v-for="t in typeOptions" :key="'et-' + t" :value="t">{{ t }}</option>
                                        </select>
                                    </div>
                                    <div class="text-muted-foreground flex items-end text-xs">
                                        {{ enemySlots[idx].type1 }}{{ enemySlots[idx].type2 ? ` / ${enemySlots[idx].type2}` : '' }}
                                    </div>
                                </div>
                                <Button
                                    v-if="enemySlots[idx].pokedexId"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="clearSlot('enemy', idx)"
                                >
                                    Remove
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Defensive coverage</CardTitle>
                    <CardDescription>
                        Per incoming move type: effectiveness on each of your Pokémon (2× / 4× / ½ / ¼ / immune). Totals count how many Pokémon are weak or resisting that type.
                    </CardDescription>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <p v-if="defensiveAllyColumns.length === 0" class="text-muted-foreground text-sm">
                        Add at least one Pokémon with typings to see the matrix.
                    </p>
                    <table v-else class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted/60 border-b">
                                <th class="text-foreground w-24 p-2 text-left text-xs font-semibold uppercase tracking-wide">Move</th>
                                <th
                                    v-for="col in defensiveAllyColumns"
                                    :key="'dhdr-' + col.index"
                                    class="text-foreground min-w-[4.5rem] p-2 text-center text-xs font-semibold"
                                >
                                    <img
                                        v-if="col.slot.spriteUrl"
                                        :src="col.slot.spriteUrl"
                                        :alt="col.slot.label"
                                        class="mx-auto mb-0.5 size-6 object-contain sm:size-7"
                                    />
                                    <span class="line-clamp-2">{{ col.slot.label.trim() || `Slot ${col.index + 1}` }}</span>
                                </th>
                                <th class="text-foreground w-16 p-2 text-center text-xs font-semibold">Total weak</th>
                                <th class="text-foreground w-16 p-2 text-center text-xs font-semibold">Total resist</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in defensiveRows"
                                :key="row.attack"
                                class="border-border/80 hover:bg-muted/30 border-b"
                            >
                                <td class="text-foreground p-2 font-medium">{{ row.attack }}</td>
                                <td
                                    v-for="(cell, ci) in row.cells"
                                    :key="row.attack + '-' + ci"
                                    class="text-foreground border-border/40 border-l p-2 text-center align-middle"
                                >
                                    <span
                                        v-if="cell.kind === 'weak4'"
                                        class="inline-block rounded-md bg-red-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-red-500"
                                    >
                                        {{ cell.display }}
                                    </span>
                                    <span
                                        v-else-if="cell.kind === 'weak2'"
                                        class="text-destructive text-xs font-semibold"
                                    >
                                        {{ cell.display }}
                                    </span>
                                    <span
                                        v-else-if="cell.kind === 'resistQuarter'"
                                        class="inline-block rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-emerald-500"
                                    >
                                        {{ cell.display }}
                                    </span>
                                    <span
                                        v-else-if="cell.kind === 'resistHalf'"
                                        class="text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ cell.display }}
                                    </span>
                                    <span
                                        v-else-if="cell.kind === 'immune'"
                                        class="bg-muted text-muted-foreground inline-block rounded-md px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{ cell.display }}
                                    </span>
                                </td>
                                <td
                                    class="border-border/40 border-l p-2 text-center text-sm font-semibold tabular-nums transition-colors"
                                    :class="row.totalWeak > 0 ? 'text-white' : 'text-muted-foreground'"
                                    :style="totalWeakCellStyle(row.totalWeak, defensiveAllyColumns.length)"
                                >
                                    {{ row.totalWeak }}
                                </td>
                                <td
                                    class="border-border/40 border-l p-2 text-center text-sm font-semibold tabular-nums transition-colors"
                                    :class="row.totalResist > 0 ? 'text-white' : 'text-muted-foreground'"
                                    :style="totalResistCellStyle(row.totalResist, defensiveAllyColumns.length)"
                                >
                                    {{ row.totalResist }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Offensive coverage (selected moves)</CardTitle>
                    <CardDescription>Each line is one of your picked moves against an opponent&apos;s typings.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <p v-if="!offensiveByEnemy.some((o) => o.lines.length)" class="text-muted-foreground text-sm">
                        Choose enemy Pokémon and your moves to see effectiveness.
                    </p>
                    <div v-for="block in offensiveByEnemy" v-else :key="'off-' + block.index" class="space-y-2">
                        <h3 class="text-sm font-medium">{{ block.label }}</h3>
                        <div v-if="!block.lines.length" class="text-muted-foreground text-xs">—</div>
                        <ul v-else class="space-y-1 text-sm">
                            <li v-for="(ln, li) in block.lines" :key="li">
                                <span class="font-medium">{{ ln.ally }}</span> · {{ ln.move }} →
                                <span
                                    :class="{
                                        'text-destructive font-medium': ln.bracket === 'super',
                                        'text-blue-600 dark:text-blue-400': ln.bracket === 'resist' || ln.bracket === 'immune',
                                    }"
                                >
                                    ×{{ ln.mult }} ({{ ln.bracket }})
                                </span>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
