<script setup lang="ts">
import MatchPrepSetResultAndReplays from '@/components/match-prep/MatchPrepSetResultAndReplays.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

export interface DraftMon {
    league_pokemon_id: number;
    species_label: string;
    nickname_label: string | null;
    sprite_url: string | null;
    type1: string | null;
    type2: string | null;
    cost: number;
}

export interface MatchPrepCalcPayload {
    my_league_pokemon_id: number | null;
    opponent_league_pokemon_id: number | null;
    body: string;
    legacy_title?: string;
}

export interface MatchPrepNotePayload {
    id: number | null;
    bring_six_slots: (number | null)[];
    plan_1_slots: (number | null)[];
    plan_2_slots: (number | null)[];
    plan_3_slots: (number | null)[];
    plan_1_notes: string;
    plan_2_notes: string;
    plan_3_notes: string;
    calcs: MatchPrepCalcPayload[];
    share_enabled: boolean;
    share_uuid: string | null;
}

export interface MatchRow {
    my_team_id: number;
    set: {
        id: number;
        round: number;
        team1_id: number;
        team2_id: number;
        team1_score: number | null;
        team2_score: number | null;
        winner_id: number | null;
        replay1: string | null;
        replay2: string | null;
        replay3: string | null;
    };
    opponent: {
        team_id: number;
        name: string;
        logo: string | null;
        roster: DraftMon[];
    };
    my_roster: DraftMon[];
    note: MatchPrepNotePayload;
}

const props = defineProps<{
    row: MatchRow;
}>();

const textareaClass = cn(
    'border-input bg-background placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex min-h-[5rem] w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function normalizeCalc(c: MatchPrepCalcPayload): MatchPrepCalcPayload {
    return {
        my_league_pokemon_id: c.my_league_pokemon_id,
        opponent_league_pokemon_id: c.opponent_league_pokemon_id,
        body: c.body ?? '',
        legacy_title: c.legacy_title,
    };
}

function cloneNote(note: MatchPrepNotePayload) {
    return {
        bring_six_slots: [...note.bring_six_slots] as (number | null)[],
        plan_1_slots: [...note.plan_1_slots] as (number | null)[],
        plan_2_slots: [...note.plan_2_slots] as (number | null)[],
        plan_3_slots: [...note.plan_3_slots] as (number | null)[],
        plan_1_notes: note.plan_1_notes,
        plan_2_notes: note.plan_2_notes,
        plan_3_notes: note.plan_3_notes,
        calcs: note.calcs.map((c) => normalizeCalc(c)),
    };
}

const form = useForm(cloneNote(props.row.note));

watch(
    () => props.row.note,
    (n) => {
        const c = cloneNote(n);
        form.bring_six_slots = c.bring_six_slots;
        form.plan_1_slots = c.plan_1_slots;
        form.plan_2_slots = c.plan_2_slots;
        form.plan_3_slots = c.plan_3_slots;
        form.plan_1_notes = c.plan_1_notes;
        form.plan_2_notes = c.plan_2_notes;
        form.plan_3_notes = c.plan_3_notes;
        form.calcs = c.calcs;
    },
    { deep: true },
);

const shareForm = useForm({
    share_enabled: props.row.note.share_enabled,
    regenerate_uuid: false,
});

watch(
    () => props.row.note.share_enabled,
    (v) => {
        shareForm.share_enabled = v;
    },
);

const bringSixIdSet = computed(() => {
    const ids = new Set<number>();
    for (const v of form.bring_six_slots) {
        if (v !== null && v !== undefined) {
            ids.add(v);
        }
    }

    return ids;
});

/** Roster options for one bring slot: hide mons already picked in the other five slots (same set). */
function bringOptionsForSlot(slotIndex: number): DraftMon[] {
    const current = form.bring_six_slots[slotIndex];
    const takenElsewhere = new Set<number>();
    for (let j = 0; j < form.bring_six_slots.length; j++) {
        if (j === slotIndex) {
            continue;
        }
        const v = form.bring_six_slots[j];
        if (v !== null && v !== undefined) {
            takenElsewhere.add(v);
        }
    }

    return props.row.my_roster.filter(
        (m) => m.league_pokemon_id === current || !takenElsewhere.has(m.league_pokemon_id),
    );
}

/** Pokémon eligible for game plans: only chosen bring-6 (empty until at least one bring slot is set). */
const planPickPool = computed(() => {
    if (bringSixIdSet.value.size === 0) {
        return [];
    }

    return props.row.my_roster.filter((m) => bringSixIdSet.value.has(m.league_pokemon_id));
});

function planSlotsForGame(plan: 1 | 2 | 3): (number | null)[] {
    if (plan === 1) {
        return form.plan_1_slots;
    }
    if (plan === 2) {
        return form.plan_2_slots;
    }

    return form.plan_3_slots;
}

/** Options for one game-plan slot: hide mons already picked in the other three slots of that game. */
function planOptionsForSlot(plan: 1 | 2 | 3, slotIndex: number): DraftMon[] {
    const slots = planSlotsForGame(plan);
    const current = slots[slotIndex];
    const takenElsewhere = new Set<number>();
    for (let j = 0; j < slots.length; j++) {
        if (j === slotIndex) {
            continue;
        }
        const v = slots[j];
        if (v !== null && v !== undefined) {
            takenElsewhere.add(v);
        }
    }

    return planPickPool.value.filter(
        (m) => m.league_pokemon_id === current || !takenElsewhere.has(m.league_pokemon_id),
    );
}

function monLabel(mon: DraftMon): string {
    if (mon.nickname_label) {
        return `${mon.species_label} (${mon.nickname_label})`;
    }

    return mon.species_label;
}

function setBringSlot(index: number, raw: string): void {
    form.bring_six_slots[index] = raw === '' ? null : Number(raw);
}

function setSlot(plan: 1 | 2 | 3, index: number, raw: string): void {
    const v = raw === '' ? null : Number(raw);
    if (plan === 1) {
        form.plan_1_slots[index] = v;
    } else if (plan === 2) {
        form.plan_2_slots[index] = v;
    } else {
        form.plan_3_slots[index] = v;
    }
}

function addCalc(): void {
    form.calcs.push({
        my_league_pokemon_id: null,
        opponent_league_pokemon_id: null,
        body: '',
    });
}

function removeCalc(i: number): void {
    form.calcs.splice(i, 1);
}

function setCalcMy(i: number, raw: string): void {
    const c = form.calcs[i];
    if (!c) {
        return;
    }
    c.my_league_pokemon_id = raw === '' ? null : Number(raw);
}

function setCalcOpp(i: number, raw: string): void {
    const c = form.calcs[i];
    if (!c) {
        return;
    }
    c.opponent_league_pokemon_id = raw === '' ? null : Number(raw);
}

function saveNote(): void {
    form.put(route('match-prep.update', props.row.set.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.clearErrors();
        },
    });
}

function submitShare(enabled: boolean, regenerate: boolean): void {
    shareForm.share_enabled = enabled;
    shareForm.regenerate_uuid = regenerate;
    shareForm.post(route('match-prep.share', props.row.set.id), {
        preserveScroll: true,
        onSuccess: () => {
            shareForm.regenerate_uuid = false;
        },
    });
}

const shareUrl = computed(() => {
    const uuid = props.row.note.share_uuid;
    if (!uuid) {
        return '';
    }

    if (typeof window !== 'undefined') {
        return `${window.location.origin}/match-prep/share/${uuid}`;
    }

    return `/match-prep/share/${uuid}`;
});

async function copyShareUrl(): Promise<void> {
    if (!shareUrl.value) {
        return;
    }
    try {
        await navigator.clipboard.writeText(shareUrl.value);
    } catch {
        /* ignore */
    }
}
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="border-b bg-muted/30">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <CardTitle class="text-lg"> Round {{ row.set.round }} vs {{ row.opponent.name }} </CardTitle>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('sets.show', row.set.id)"> Open match </Link>
                </Button>
            </div>
        </CardHeader>
        <CardContent class="flex flex-col gap-6 p-4 pt-6">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]">
                <div>
                    <h3 class="text-muted-foreground mb-2 text-sm font-semibold tracking-wide uppercase">
                        Opponent draft
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="mon in row.opponent.roster"
                            :key="mon.league_pokemon_id"
                            class="bg-card flex w-[7.5rem] flex-col gap-1 rounded-lg border p-2 shadow-xs"
                        >
                            <div class="bg-muted/50 flex aspect-square items-center justify-center rounded-md">
                                <img
                                    v-if="mon.sprite_url"
                                    :src="mon.sprite_url"
                                    :alt="mon.species_label"
                                    class="max-h-full max-w-full object-contain"
                                />
                                <span v-else class="text-muted-foreground text-xs">No sprite</span>
                            </div>
                            <p class="line-clamp-2 text-center text-xs font-medium">
                                {{ mon.species_label }}
                            </p>
                            <p v-if="mon.type1" class="text-muted-foreground text-center text-[0.65rem] capitalize">
                                {{ mon.type1 }}<span v-if="mon.type2 && mon.type2 !== '-'"> / {{ mon.type2 }}</span>
                            </p>
                        </div>
                        <p v-if="row.opponent.roster.length === 0" class="text-muted-foreground text-sm">No Pokémon drafted yet.</p>
                    </div>
                </div>

                <div class="flex min-w-0 flex-col gap-4">
                    <div class="rounded-lg border bg-muted/20 p-4">
                        <h4 class="mb-1 text-sm font-semibold">Bring 6 for this set</h4>
                        <p class="text-muted-foreground mb-3 text-xs">
                            Choose the six Pokémon you’re registering for the match. Each game plan below picks four from this group (Regulation G/H style: bring 6, play 4).
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="i in 6" :key="`bring-${i}`" class="flex flex-col gap-1">
                                <Label :for="`bring-${row.set.id}-${i}`" class="text-muted-foreground text-xs">
                                    Slot {{ i }}
                                </Label>
                                <select
                                    :id="`bring-${row.set.id}-${i}`"
                                    class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs"
                                    :value="String(form.bring_six_slots[i - 1] ?? '')"
                                    @change="setBringSlot(i - 1, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="">—</option>
                                    <option
                                        v-for="mon in bringOptionsForSlot(i - 1)"
                                        :key="mon.league_pokemon_id"
                                        :value="mon.league_pokemon_id"
                                    >
                                        {{ monLabel(mon) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <div v-for="plan in [1, 2, 3] as const" :key="plan" class="flex flex-col gap-2 rounded-lg border p-3">
                            <h4 class="text-sm font-semibold">Game {{ plan }}</h4>
                            <p class="text-muted-foreground text-xs">
                                Four Pokémon for this game (from your bring-6). Fill bring 6 above before choosing slots.
                            </p>
                            <div class="flex flex-col gap-2">
                                <div v-for="i in 4" :key="`${plan}-${i}`" class="flex flex-col gap-1">
                                    <Label :for="`p${plan}-s${i}`" class="text-muted-foreground text-xs"> Slot {{ i }} </Label>
                                    <select
                                        :id="`p${plan}-s${i}`"
                                        class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs"
                                        :value="
                                            String(
                                                plan === 1
                                                    ? (form.plan_1_slots[i - 1] ?? '')
                                                    : plan === 2
                                                      ? (form.plan_2_slots[i - 1] ?? '')
                                                      : (form.plan_3_slots[i - 1] ?? ''),
                                            )
                                        "
                                        @change="
                                            setSlot(
                                                plan,
                                                i - 1,
                                                ($event.target as HTMLSelectElement).value,
                                            )
                                        "
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="mon in planOptionsForSlot(plan, i - 1)"
                                            :key="mon.league_pokemon_id"
                                            :value="mon.league_pokemon_id"
                                        >
                                            {{ monLabel(mon) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="text-muted-foreground text-xs">Gameplan / notes</Label>
                                <textarea v-if="plan === 1" v-model="form.plan_1_notes" :class="textareaClass" rows="4" />
                                <textarea v-else-if="plan === 2" v-model="form.plan_2_notes" :class="textareaClass" rows="4" />
                                <textarea v-else v-model="form.plan_3_notes" :class="textareaClass" rows="4" />
                            </div>
                        </div>
                    </div>

                    <MatchPrepSetResultAndReplays
                        :set-id="row.set.id"
                        :team1-id="row.set.team1_id"
                        :team2-id="row.set.team2_id"
                        :team1-score="row.set.team1_score"
                        :team2-score="row.set.team2_score"
                        :winner-id="row.set.winner_id"
                        :my-team-id="row.my_team_id"
                        :opponent-team-id="row.opponent.team_id"
                        :opponent-name="row.opponent.name"
                        :replay1="row.set.replay1"
                        :replay2="row.set.replay2"
                        :replay3="row.set.replay3"
                        show-match-page-link
                    />

                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-sm font-medium">Head-to-head calcs</Label>
                            <Button type="button" variant="secondary" size="sm" @click="addCalc"> Add matchup </Button>
                        </div>
                        <p class="text-muted-foreground text-xs">
                            Pick one of yours and one of theirs, then paste notes or output from the
                            <a
                                href="https://calc.pokemonshowdown.com/"
                                class="text-primary underline-offset-4 hover:underline"
                                target="_blank"
                                rel="noopener noreferrer"
                                >Pokémon Damage Calculator</a
                            >. You can add the same pair twice (e.g. different sets or guesses).
                        </p>
                        <div v-for="(calc, ci) in form.calcs" :key="ci" class="bg-muted/40 flex flex-col gap-2 rounded-lg border p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium">Matchup {{ ci + 1 }}</span>
                                <Button type="button" variant="ghost" size="sm" class="text-destructive" @click="removeCalc(ci)">
                                    Remove
                                </Button>
                            </div>
                            <div v-if="calc.legacy_title" class="text-muted-foreground text-xs">Legacy: {{ calc.legacy_title }}</div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="flex flex-col gap-1">
                                    <Label class="text-muted-foreground text-xs">Your Pokémon</Label>
                                    <select
                                        class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs"
                                        :value="String(calc.my_league_pokemon_id ?? '')"
                                        @change="setCalcMy(ci, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="mon in row.my_roster"
                                            :key="mon.league_pokemon_id"
                                            :value="mon.league_pokemon_id"
                                        >
                                            {{ monLabel(mon) }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="text-muted-foreground text-xs">Their Pokémon</Label>
                                    <select
                                        class="border-input bg-background h-9 w-full rounded-md border px-2 text-sm shadow-xs"
                                        :value="String(calc.opponent_league_pokemon_id ?? '')"
                                        @change="setCalcOpp(ci, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="mon in row.opponent.roster"
                                            :key="mon.league_pokemon_id"
                                            :value="mon.league_pokemon_id"
                                        >
                                            {{ monLabel(mon) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <textarea v-model="calc.body" :class="textareaClass" rows="5" placeholder="Damage ranges, assumptions, pasted calc…" />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="button" :disabled="form.processing" @click="saveNote"> Save notes </Button>
                    </div>
                    <p v-if="form.hasErrors" class="text-destructive text-sm">
                        {{ Object.values(form.errors).flat().join(' ') }}
                    </p>

                    <div class="border-t pt-4">
                        <h4 class="mb-2 text-sm font-semibold">Share this matchup</h4>
                        <p class="text-muted-foreground mb-3 text-xs">
                            Anyone with the link can view this prep sheet (read-only). The URL uses a random ID.
                        </p>
                        <div v-if="row.note.share_enabled && row.note.share_uuid" class="mb-3 flex flex-wrap items-center gap-2">
                            <Input :model-value="shareUrl" readonly class="min-w-[12rem] flex-1 font-mono text-xs" />
                            <Button type="button" variant="secondary" size="sm" @click="copyShareUrl"> Copy </Button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="!row.note.share_enabled"
                                type="button"
                                variant="secondary"
                                size="sm"
                                :disabled="shareForm.processing"
                                @click="submitShare(true, false)"
                            >
                                Enable sharing
                            </Button>
                            <template v-else>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="shareForm.processing"
                                    @click="submitShare(false, false)"
                                >
                                    Disable sharing
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    :disabled="shareForm.processing"
                                    @click="submitShare(true, true)"
                                >
                                    Regenerate link
                                </Button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
