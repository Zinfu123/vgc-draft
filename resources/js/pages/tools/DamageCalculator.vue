<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

interface VersionGroupRow {
    id: number;
    slug: string;
    name: string;
    generation: number;
    sort_order: number;
    showdown_format_key?: string | null;
}

interface NatureOption {
    value: number;
    label: string;
    export_label: string;
}

interface LearnsetRow {
    move_id: number;
    move_name: string;
    type_slug?: string | null;
    damage_class?: string | null;
    power?: number | null;
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
}

interface MatchPasteSlot {
    league_pokemon_id: number | null;
    ability: string;
    moves: string[];
    version_group_held_item_id: number | null;
    nature: number | null;
    tera_type: string | null;
    evs: Record<string, number> | null;
}

interface MatchContextSlot {
    slot_index: number;
    pokedex_id: number | null;
    name: string;
    sprite_url: string | null;
    type1: string;
    type2: string | null;
    paste: MatchPasteSlot;
}

interface MatchContextPayload {
    league_id: number;
    set_id: number | null;
    playoff_match_id: number | null;
    team: number;
    version_group_slug: string;
    slots: MatchContextSlot[];
}

function xsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : '';
}

async function jsonFetch(url: string, init?: RequestInit): Promise<Response> {
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(init?.headers ?? {}),
        },
        ...init,
    });
}

function speciesKeyFromName(name: string): string {
    return name
        .trim()
        .toLowerCase()
        .replace(/\./g, '')
        .replace(/♀|♂/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

const props = defineProps<{
    versionGroups: VersionGroupRow[];
    defaultVersionSlug: string;
    typeOrder: string[];
    natures: NatureOption[];
    matchContext: MatchContextPayload | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Damage calculator', href: '/damage-calculator' },
];

const selectedVersionSlug = ref(props.matchContext?.version_group_slug ?? props.defaultVersionSlug);

type SideKey = 'attacker' | 'defender';

interface SideState {
    role: SideKey;
    pokedexId: number | null;
    label: string;
    search: string;
    suggestions: PokedexHit[];
    suggestionsOpen: boolean;
    learnset: LearnsetRow[];
    moveId: number | null;
    level: number;
    nature: number;
    terastallized: boolean;
    burned: boolean;
    item: string;
    teraType: string;
    evHp: number;
    evAtk: number;
    evDef: number;
    evSpa: number;
    evSpd: number;
    evSpe: number;
}

function emptySide(role: SideKey): SideState {
    return {
        role,
        pokedexId: null,
        label: '',
        search: '',
        suggestions: [],
        suggestionsOpen: false,
        learnset: [],
        moveId: null,
        level: 50,
        nature: 12,
        terastallized: false,
        burned: false,
        item: 'none',
        teraType: '',
        evHp: 0,
        evAtk: 0,
        evDef: 0,
        evSpa: 0,
        evSpd: 0,
        evSpe: 0,
    };
}

const attacker = ref<SideState>(emptySide('attacker'));
const defender = ref<SideState>(emptySide('defender'));

const damageResult = ref<{ min: number | null; max: number | null; status: string } | null>(null);
const damageMeta = ref<{ move?: { name: string; power: number | null; type_slug: string; damage_class: string } } | null>(null);
const calculating = ref(false);
const calcError = ref<string | null>(null);

const vgcUsage = ref<{ usage_percent: number; detail: Record<string, unknown> | null; period: string } | null>(null);
const vgcUsageNote = ref<string | null>(null);

async function runSearch(side: SideState): Promise<void> {
    const q = side.search.trim();
    if (q.length < 2) {
        side.suggestions = [];
        side.suggestionsOpen = false;
        return;
    }
    const params = new URLSearchParams({ search: q, per_page: '24' });
    const url = route('damage-calculator.pokedex-search') + '?' + params.toString();
    const res = await jsonFetch(url);
    if (!res.ok) {
        side.suggestions = [];
        return;
    }
    const body = (await res.json()) as Paginator<PokedexHit>;
    side.suggestions = body.data ?? [];
    side.suggestionsOpen = side.suggestions.length > 0;
}

let atkTimer: ReturnType<typeof setTimeout> | null = null;
let defTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => attacker.value.search,
    () => {
        if (atkTimer) {
            clearTimeout(atkTimer);
        }
        atkTimer = setTimeout(() => {
            void runSearch(attacker.value);
        }, 280);
    },
);

watch(
    () => defender.value.search,
    () => {
        if (defTimer) {
            clearTimeout(defTimer);
        }
        defTimer = setTimeout(() => {
            void runSearch(defender.value);
        }, 280);
    },
);

async function loadLearnset(side: SideState): Promise<void> {
    if (!side.pokedexId) return;
    const url =
        route('damage-calculator.learnset', { pokedex: side.pokedexId }) +
        '?game=' +
        encodeURIComponent(selectedVersionSlug.value);
    const res = await jsonFetch(url);
    if (!res.ok) return;
    const body = (await res.json()) as { learnset: LearnsetRow[] };
    side.learnset = Array.isArray(body.learnset) ? body.learnset : [];
    const damaging = side.learnset.filter(
        (r) => r.damage_class === 'physical' || r.damage_class === 'special',
    );
    side.moveId = damaging[0]?.move_id ?? side.learnset[0]?.move_id ?? null;
}

async function pickHit(side: SideState, hit: PokedexHit): Promise<void> {
    side.pokedexId = hit.id;
    side.label = hit.name;
    side.search = hit.name;
    side.suggestionsOpen = false;
    side.suggestions = [];
    await loadLearnset(side);
    void refreshVgcUsage(side === attacker.value ? 'attacker' : 'defender');
}

function clearSide(side: SideState): void {
    Object.assign(side, emptySide(side.role));
}

async function refreshVgcUsage(which: 'attacker' | 'defender'): Promise<void> {
    const side = which === 'attacker' ? attacker.value : defender.value;
    if (!side.label) {
        vgcUsage.value = null;
        vgcUsageNote.value = null;
        return;
    }
    const key = speciesKeyFromName(side.label);
    const params = new URLSearchParams({
        version_group_slug: selectedVersionSlug.value,
        species_key: key,
    });
    const res = await jsonFetch(route('damage-calculator.vgc-usage') + '?' + params.toString());
    if (!res.ok) {
        vgcUsage.value = null;
        vgcUsageNote.value = 'Could not load ladder usage.';
        return;
    }
    const body = (await res.json()) as {
        row: { usage_percent: number; detail: Record<string, unknown> | null; period: string } | null;
        message?: string;
        format_key?: string;
    };
    vgcUsageNote.value = body.message ?? null;
    vgcUsage.value = body.row;
}

async function calculate(): Promise<void> {
    calcError.value = null;
    damageResult.value = null;
    damageMeta.value = null;
    if (!attacker.value.pokedexId || !defender.value.pokedexId || !attacker.value.moveId) {
        calcError.value = 'Select attacker, defender, and a damaging move.';
        return;
    }
    calculating.value = true;
    try {
        const payload = {
            version_group_slug: selectedVersionSlug.value,
            move_id: attacker.value.moveId,
            attacker: {
                pokedex_id: attacker.value.pokedexId,
                level: attacker.value.level,
                nature: attacker.value.nature,
                terastallized: attacker.value.terastallized,
                burned: attacker.value.burned,
                item: attacker.value.item === 'none' ? '' : attacker.value.item,
                tera_type: attacker.value.teraType || null,
                ev: {
                    hp: attacker.value.evHp,
                    atk: attacker.value.evAtk,
                    def: attacker.value.evDef,
                    spa: attacker.value.evSpa,
                    spd: attacker.value.evSpd,
                    spe: attacker.value.evSpe,
                },
            },
            defender: {
                pokedex_id: defender.value.pokedexId,
                level: defender.value.level,
                nature: defender.value.nature,
                terastallized: defender.value.terastallized,
                burned: defender.value.burned,
                item: defender.value.item === 'none' ? '' : defender.value.item,
                tera_type: defender.value.teraType || null,
                ev: {
                    hp: defender.value.evHp,
                    atk: defender.value.evAtk,
                    def: defender.value.evDef,
                    spa: defender.value.evSpa,
                    spd: defender.value.evSpd,
                    spe: defender.value.evSpe,
                },
            },
        };
        const res = await jsonFetch(route('damage-calculator.calculate'), {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        const body = (await res.json()) as {
            message?: string;
            damage?: { min: number | null; max: number | null; status: string };
            move?: { name: string; power: number | null; type_slug: string; damage_class: string };
        };
        if (!res.ok) {
            calcError.value = body.message ?? 'Calculation failed.';
            return;
        }
        damageResult.value = body.damage ?? null;
        damageMeta.value = { move: body.move };
    } finally {
        calculating.value = false;
    }
}

function applyMatchContext(ctx: MatchContextPayload): void {
    selectedVersionSlug.value = ctx.version_group_slug;
    const s0 = ctx.slots.find((s) => s.slot_index === 0);
    const s1 = ctx.slots.find((s) => s.slot_index === 1);
    if (s0?.pokedex_id) {
        attacker.value.pokedexId = s0.pokedex_id;
        attacker.value.label = s0.name;
        attacker.value.search = s0.name;
        const p = s0.paste;
        if (p.nature !== null) attacker.value.nature = p.nature;
        if (p.tera_type) attacker.value.teraType = p.tera_type;
        if (p.evs) {
            attacker.value.evHp = p.evs.hp ?? 0;
            attacker.value.evAtk = p.evs.atk ?? 0;
            attacker.value.evDef = p.evs.def ?? 0;
            attacker.value.evSpa = p.evs.spa ?? 0;
            attacker.value.evSpd = p.evs.spd ?? 0;
            attacker.value.evSpe = p.evs.spe ?? 0;
        }
        void loadLearnset(attacker.value).then(() => refreshVgcUsage('attacker'));
    }
    if (s1?.pokedex_id) {
        defender.value.pokedexId = s1.pokedex_id;
        defender.value.label = s1.name;
        defender.value.search = s1.name;
        const p = s1.paste;
        if (p.nature !== null) defender.value.nature = p.nature;
        if (p.tera_type) defender.value.teraType = p.tera_type;
        if (p.evs) {
            defender.value.evHp = p.evs.hp ?? 0;
            defender.value.evAtk = p.evs.atk ?? 0;
            defender.value.evDef = p.evs.def ?? 0;
            defender.value.evSpa = p.evs.spa ?? 0;
            defender.value.evSpd = p.evs.spd ?? 0;
            defender.value.evSpe = p.evs.spe ?? 0;
        }
        void loadLearnset(defender.value).then(() => refreshVgcUsage('defender'));
    }
}

onMounted(() => {
    if (props.matchContext) {
        applyMatchContext(props.matchContext);
    }
});

const topMoves = computed(() => {
    const d = vgcUsage.value?.detail;
    if (!d || typeof d !== 'object' || d === null) return [] as [string, number][];
    const raw = (d as Record<string, unknown>).Moves;
    if (!raw || typeof raw !== 'object') return [];
    return Object.entries(raw as Record<string, number>)
        .map(([k, v]) => [k, typeof v === 'number' ? v : 0] as [string, number])
        .sort((a, b) => b[1] - a[1])
        .slice(0, 12);
});
</script>

<template>
    <Head title="Damage calculator" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-4 md:p-6 lg:p-8">
            <div>
                <h1 class="text-foreground text-2xl font-semibold tracking-tight">Damage calculator</h1>
                <p class="text-muted-foreground mt-1 max-w-3xl text-sm">
                    Gen 9-style damage ranges from your Pokedex version group. Optional ladder usage appears when
                    <code class="text-xs">showdown_format_key</code> is set and data is imported (
                    <code class="text-xs">php artisan stats:import-showdown-vgc</code>).
                </p>
            </div>

            <div v-if="matchContext" class="bg-muted/50 rounded-lg border border-dashed p-3 text-sm">
                <span class="font-medium">Match context</span>
                — league #{{ matchContext.league_id }}, team {{ matchContext.team
                }}<span v-if="matchContext.set_id">, set #{{ matchContext.set_id }}</span
                ><span v-if="matchContext.playoff_match_id">, playoff #{{ matchContext.playoff_match_id }}</span
                >. Slots pre-filled from team paste; you can change Pokémon freely.
            </div>

            <div class="flex flex-col gap-4 lg:flex-row">
                <div class="flex-1 space-y-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Game</CardTitle>
                            <CardDescription>Mechanics follow this version group.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Label class="mb-1 block text-xs">Version group</Label>
                            <select
                                v-model="selectedVersionSlug"
                                class="border-input bg-background w-full max-w-md rounded-md border px-3 py-2 text-sm dark:border-neutral-700"
                            >
                                <option v-for="g in versionGroups" :key="g.id" :value="g.slug">
                                    {{ g.name }}
                                </option>
                            </select>
                        </CardContent>
                    </Card>

                    <div class="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base">Attacker</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="relative">
                                    <Label class="text-xs">Pokémon</Label>
                                    <Input v-model="attacker.search" type="text" placeholder="Search name…" autocomplete="off" />
                                    <div
                                        v-if="attacker.suggestionsOpen"
                                        class="bg-popover absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border text-sm shadow-md"
                                    >
                                        <button
                                            v-for="h in attacker.suggestions"
                                            :key="h.id"
                                            type="button"
                                            class="hover:bg-muted flex w-full items-center gap-2 px-3 py-2 text-left"
                                            @click="pickHit(attacker, h)"
                                        >
                                            <img v-if="h.sprite_url" :src="h.sprite_url" class="h-8 w-8" alt="" />
                                            <span>{{ h.name }}</span>
                                        </button>
                                    </div>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="clearSide(attacker)"> Clear </Button>
                                <div v-if="attacker.pokedexId" class="space-y-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label class="text-xs">Level</Label>
                                            <Input v-model.number="attacker.level" type="number" min="1" max="100" />
                                        </div>
                                        <div>
                                            <Label class="text-xs">Nature</Label>
                                            <select
                                                v-model.number="attacker.nature"
                                                class="border-input bg-background w-full rounded-md border px-2 py-2 text-xs dark:border-neutral-700"
                                            >
                                                <option v-for="n in natures" :key="n.value" :value="n.value">
                                                    {{ n.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-1 text-xs">
                                        <div>
                                            <Label class="text-[10px]">EV HP</Label>
                                            <Input v-model.number="attacker.evHp" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV Atk</Label>
                                            <Input v-model.number="attacker.evAtk" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV Def</Label>
                                            <Input v-model.number="attacker.evDef" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV SpA</Label>
                                            <Input v-model.number="attacker.evSpa" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV SpD</Label>
                                            <Input v-model.number="attacker.evSpd" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV Spe</Label>
                                            <Input v-model.number="attacker.evSpe" type="number" min="0" max="252" />
                                        </div>
                                    </div>
                                    <div>
                                        <Label class="text-xs">Tera type (optional)</Label>
                                        <select
                                            v-model="attacker.teraType"
                                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-xs dark:border-neutral-700"
                                        >
                                            <option value="">—</option>
                                            <option v-for="t in typeOrder" :key="t" :value="t">{{ t }}</option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 text-xs">
                                        <input v-model="attacker.terastallized" type="checkbox" class="rounded border" />
                                        Terastallized
                                    </label>
                                    <label class="flex items-center gap-2 text-xs">
                                        <input v-model="attacker.burned" type="checkbox" class="rounded border" />
                                        Burned
                                    </label>
                                    <div>
                                        <Label class="text-xs">Item modifier</Label>
                                        <select
                                            v-model="attacker.item"
                                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-xs dark:border-neutral-700"
                                        >
                                            <option value="none">None</option>
                                            <option value="choice_band">Choice Band (physical)</option>
                                            <option value="choice_specs">Choice Specs (special)</option>
                                            <option value="life_orb">Life Orb</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label class="text-xs">Move</Label>
                                        <select
                                            v-model.number="attacker.moveId"
                                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-xs dark:border-neutral-700"
                                        >
                                            <option v-for="m in attacker.learnset" :key="m.move_id" :value="m.move_id">
                                                {{ m.move_name }} ({{ m.damage_class ?? '?' }}, power {{ m.power ?? '—' }})
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base">Defender</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="relative">
                                    <Label class="text-xs">Pokémon</Label>
                                    <Input v-model="defender.search" type="text" placeholder="Search name…" autocomplete="off" />
                                    <div
                                        v-if="defender.suggestionsOpen"
                                        class="bg-popover absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border text-sm shadow-md"
                                    >
                                        <button
                                            v-for="h in defender.suggestions"
                                            :key="h.id"
                                            type="button"
                                            class="hover:bg-muted flex w-full items-center gap-2 px-3 py-2 text-left"
                                            @click="pickHit(defender, h)"
                                        >
                                            <img v-if="h.sprite_url" :src="h.sprite_url" class="h-8 w-8" alt="" />
                                            <span>{{ h.name }}</span>
                                        </button>
                                    </div>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="clearSide(defender)"> Clear </Button>
                                <div v-if="defender.pokedexId" class="space-y-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label class="text-xs">Level</Label>
                                            <Input v-model.number="defender.level" type="number" min="1" max="100" />
                                        </div>
                                        <div>
                                            <Label class="text-xs">Nature</Label>
                                            <select
                                                v-model.number="defender.nature"
                                                class="border-input bg-background w-full rounded-md border px-2 py-2 text-xs dark:border-neutral-700"
                                            >
                                                <option v-for="n in natures" :key="n.value" :value="n.value">
                                                    {{ n.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-1 text-xs">
                                        <div>
                                            <Label class="text-[10px]">EV HP</Label>
                                            <Input v-model.number="defender.evHp" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV Atk</Label>
                                            <Input v-model.number="defender.evAtk" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV Def</Label>
                                            <Input v-model.number="defender.evDef" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV SpA</Label>
                                            <Input v-model.number="defender.evSpa" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV SpD</Label>
                                            <Input v-model.number="defender.evSpd" type="number" min="0" max="252" />
                                        </div>
                                        <div>
                                            <Label class="text-[10px]">EV Spe</Label>
                                            <Input v-model.number="defender.evSpe" type="number" min="0" max="252" />
                                        </div>
                                    </div>
                                    <div>
                                        <Label class="text-xs">Tera type (optional)</Label>
                                        <select
                                            v-model="defender.teraType"
                                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-xs dark:border-neutral-700"
                                        >
                                            <option value="">—</option>
                                            <option v-for="t in typeOrder" :key="t" :value="t">{{ t }}</option>
                                        </select>
                                    </div>
                                    <label class="flex items-center gap-2 text-xs">
                                        <input v-model="defender.terastallized" type="checkbox" class="rounded border" />
                                        Terastallized
                                    </label>
                                    <div>
                                        <Label class="text-xs">Item modifier</Label>
                                        <select
                                            v-model="defender.item"
                                            class="border-input bg-background w-full rounded-md border px-2 py-2 text-xs dark:border-neutral-700"
                                        >
                                            <option value="none">None</option>
                                            <option value="choice_band">Choice Band (physical)</option>
                                            <option value="choice_specs">Choice Specs (special)</option>
                                            <option value="life_orb">Life Orb</option>
                                        </select>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button type="button" :disabled="calculating" @click="calculate">
                            {{ calculating ? 'Calculating…' : 'Calculate damage' }}
                        </Button>
                        <p v-if="calcError" class="text-destructive text-sm">{{ calcError }}</p>
                    </div>

                    <Card v-if="damageResult?.status === 'ok'">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Result</CardTitle>
                            <CardDescription v-if="damageMeta?.move">
                                {{ damageMeta.move.name }} — {{ damageMeta.move.type_slug }} ({{
                                    damageMeta.move.damage_class
                                }}, BP {{ damageMeta.move.power ?? '—' }})
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="text-lg font-semibold">
                            {{ damageResult.min }} – {{ damageResult.max }}
                            <span class="text-muted-foreground text-sm font-normal"> (before crit / screens / field ) </span>
                        </CardContent>
                    </Card>
                </div>

                <div class="lg:w-80">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">VGC ladder usage</CardTitle>
                            <CardDescription>
                                Showing data for the active species label when available (import required).
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p v-if="vgcUsageNote && !vgcUsage" class="text-muted-foreground">{{ vgcUsageNote }}</p>
                            <template v-if="vgcUsage">
                                <p>
                                    <span class="font-medium">{{ Number(vgcUsage.usage_percent).toFixed(2) }}%</span>
                                    usage ({{ vgcUsage.period }})
                                </p>
                                <div v-if="topMoves.length">
                                    <p class="text-muted-foreground text-xs font-medium uppercase">Top moves</p>
                                    <ul class="mt-1 space-y-0.5 text-xs">
                                        <li v-for="([name, pct]) in topMoves" :key="name">
                                            {{ name }}
                                            <span class="text-muted-foreground">({{ (100 * pct).toFixed(1) }}%)</span>
                                        </li>
                                    </ul>
                                </div>
                            </template>
                            <p v-else-if="!vgcUsageNote" class="text-muted-foreground text-xs">
                                Select a Pokémon or import stats for this format.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
