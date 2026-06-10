<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        setId: number;
        team1Id: number;
        team2Id: number;
        team1Score: number | null;
        team2Score: number | null;
        winnerId: number | null;
        myTeamId: number;
        opponentTeamId: number;
        opponentName: string;
        replay1: string | null;
        replay2: string | null;
        replay3: string | null;
        /** When true, show a link to the match page to edit scores and replays. */
        showMatchPageLink?: boolean;
    }>(),
    { showMatchPageLink: false },
);

function num(v: number | null | undefined): number | null {
    if (v === null || v === undefined) {
        return null;
    }

    return Number(v);
}

const myScore = computed(() =>
    props.team1Id === props.myTeamId ? num(props.team1Score) : num(props.team2Score),
);

const oppScore = computed(() =>
    props.team1Id === props.myTeamId ? num(props.team2Score) : num(props.team1Score),
);

const scoresLine = computed((): string | null => {
    const a = myScore.value;
    const b = oppScore.value;
    if (a === null || b === null) {
        return null;
    }

    return `Games (you vs ${props.opponentName}): ${a}–${b}`;
});

const outcomeLine = computed((): string | null => {
    const w = props.winnerId;
    if (w === null || w === undefined) {
        return null;
    }
    if (w === props.myTeamId) {
        return 'Result: Win';
    }
    if (w === props.opponentTeamId) {
        return 'Result: Loss';
    }

    return null;
});

const replayLinks = computed(() => {
    const rows: { label: string; url: string }[] = [];
    const r1 = props.replay1?.trim();
    const r2 = props.replay2?.trim();
    const r3 = props.replay3?.trim();
    if (r1) {
        rows.push({ label: 'Game 1 replay', url: r1 });
    }
    if (r2) {
        rows.push({ label: 'Game 2 replay', url: r2 });
    }
    if (r3) {
        rows.push({ label: 'Game 3 replay', url: r3 });
    }

    return rows;
});

const hasMatchInfo = computed(
    () => outcomeLine.value !== null || scoresLine.value !== null || replayLinks.value.length > 0,
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Match result & replays</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col gap-3 text-sm">
            <template v-if="hasMatchInfo">
                <p v-if="outcomeLine" class="font-medium">
                    {{ outcomeLine }}
                </p>
                <p v-if="scoresLine" class="text-muted-foreground">
                    {{ scoresLine }}
                </p>
                <ul v-if="replayLinks.length > 0" class="flex flex-col gap-1">
                    <li v-for="(row, i) in replayLinks" :key="i">
                        <a
                            :href="row.url"
                            class="text-primary break-all underline-offset-4 hover:underline"
                            target="_blank"
                            rel="noopener noreferrer"
                            >{{ row.label }}</a
                        >
                    </li>
                </ul>
            </template>
            <p v-else class="text-muted-foreground">No match result or replay links recorded yet.</p>
            <p v-if="showMatchPageLink" class="text-muted-foreground text-xs">
                <Link :href="route('sets.show', setId)" class="text-primary underline-offset-4 hover:underline">
                    Open match
                </Link>
                to enter scores and Showdown replay URLs.
            </p>
        </CardContent>
    </Card>
</template>
