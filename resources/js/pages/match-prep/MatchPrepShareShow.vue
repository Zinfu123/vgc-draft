<script setup lang="ts">
import type { DraftMon, MatchPrepCalcPayload, MatchPrepNotePayload, MatchRow } from '@/components/match-prep/MatchPrepMatchRow.vue';
import MatchPrepSetResultAndReplays from '@/components/match-prep/MatchPrepSetResultAndReplays.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    league_name: string;
    match: MatchRow;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Home', href: '/' },
    { title: 'Shared match prep', href: '#' },
];

function byMyId(id: number | null): DraftMon | null {
    if (id === null) {
        return null;
    }

    return props.match.my_roster.find((m) => m.league_pokemon_id === id) ?? null;
}

function byOppId(id: number | null): DraftMon | null {
    if (id === null) {
        return null;
    }

    return props.match.opponent.roster.find((m) => m.league_pokemon_id === id) ?? null;
}

function monLabel(mon: DraftMon | null): string {
    if (!mon) {
        return '—';
    }
    if (mon.nickname_label) {
        return `${mon.species_label} (${mon.nickname_label})`;
    }

    return mon.species_label;
}

function slotLabel(id: number | null): string {
    return monLabel(byMyId(id));
}

const note = computed(() => props.match.note as MatchPrepNotePayload);

function calcHeading(calc: MatchPrepCalcPayload, i: number): string {
    if (calc.legacy_title) {
        return calc.legacy_title;
    }
    const a = monLabel(byMyId(calc.my_league_pokemon_id));
    const b = monLabel(byOppId(calc.opponent_league_pokemon_id));
    if (a === '—' && b === '—') {
        return `Calc ${i + 1}`;
    }

    return `${a} vs ${b}`;
}
</script>

<template>
    <Head :title="`Match prep — ${match.opponent.name}`" />

    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <p v-if="league_name" class="text-muted-foreground text-sm">{{ league_name }}</p>
                <h1 class="text-2xl font-bold tracking-tight">Shared prep vs {{ match.opponent.name }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">Round {{ match.set.round }} · read-only</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Opponent draft</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <div
                        v-for="mon in match.opponent.roster"
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
                        <p class="line-clamp-2 text-center text-xs font-medium">{{ mon.species_label }}</p>
                    </div>
                    <p v-if="match.opponent.roster.length === 0" class="text-muted-foreground text-sm">No Pokémon.</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Your bring-6</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="list-decimal space-y-1 pl-4 text-sm">
                        <li v-for="i in 6" :key="i">{{ slotLabel(note.bring_six_slots[i - 1] ?? null) }}</li>
                    </ol>
                </CardContent>
            </Card>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card v-for="plan in [1, 2, 3] as const" :key="plan">
                    <CardHeader>
                        <CardTitle class="text-base">Game {{ plan }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3 text-sm">
                        <ol class="list-decimal space-y-1 pl-4">
                            <li v-for="i in 4" :key="i">
                                {{
                                    plan === 1
                                        ? slotLabel(note.plan_1_slots[i - 1] ?? null)
                                        : plan === 2
                                          ? slotLabel(note.plan_2_slots[i - 1] ?? null)
                                          : slotLabel(note.plan_3_slots[i - 1] ?? null)
                                }}
                            </li>
                        </ol>
                        <div v-if="plan === 1 && note.plan_1_notes" class="border-t pt-2 whitespace-pre-wrap">
                            {{ note.plan_1_notes }}
                        </div>
                        <div v-else-if="plan === 2 && note.plan_2_notes" class="border-t pt-2 whitespace-pre-wrap">
                            {{ note.plan_2_notes }}
                        </div>
                        <div v-else-if="plan === 3 && note.plan_3_notes" class="border-t pt-2 whitespace-pre-wrap">
                            {{ note.plan_3_notes }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <MatchPrepSetResultAndReplays
                :set-id="match.set.id"
                :team1-id="match.set.team1_id"
                :team2-id="match.set.team2_id"
                :team1-score="match.set.team1_score"
                :team2-score="match.set.team2_score"
                :winner-id="match.set.winner_id"
                :my-team-id="match.my_team_id"
                :opponent-team-id="match.opponent.team_id"
                :opponent-name="match.opponent.name"
                :replay1="match.set.replay1"
                :replay2="match.set.replay2"
                :replay3="match.set.replay3"
            />

            <div v-if="note.calcs.length > 0" class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold">Head-to-head calcs</h2>
                <Card v-for="(calc, i) in note.calcs" :key="i">
                    <CardHeader>
                        <CardTitle class="text-sm">{{ calcHeading(calc, i) }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2 text-sm">
                        <pre v-if="calc.body" class="bg-muted/50 max-h-96 overflow-auto rounded-md p-3 text-xs whitespace-pre-wrap">{{
                            calc.body
                        }}</pre>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppHeaderLayout>
</template>
