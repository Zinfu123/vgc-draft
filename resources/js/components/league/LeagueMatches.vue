<script setup lang="ts">
import ListWithHeadings from '@/components/ListWithHeadings.vue';
import MatchCard from '@/components/match/MatchCard.vue';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { router } from '@inertiajs/vue3';
import { Swords } from 'lucide-vue-next';
import { computed } from 'vue';

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

interface TeamOption {
    id: number;
    name: string;
}

const props = defineProps<{
    leagueId: number;
    teams: TeamOption[];
    team_next: TeamNext | null;
    played_sets: GroupedSets;
    upcoming_sets: GroupedSets;
    matchesFilterTeamId: number | null;
}>();

function teamIdsMatch(a: unknown, b: unknown): boolean {
    if (a === null || a === undefined || b === null || b === undefined) {
        return false;
    }

    return Number(a) === Number(b);
}

function filterGroupedSets(grouped: GroupedSets, teamId: number | null | undefined): GroupedSets {
    if (teamId == null) {
        return grouped;
    }

    const needle = Number(teamId);
    const out: GroupedSets = {};
    for (const key of Object.keys(grouped)) {
        const rows = (grouped as Record<string, SetRow[]>)[key];
        if (!rows) {
            continue;
        }
        const items = rows.filter(
            (s) => teamIdsMatch(s.team1.id, needle) || teamIdsMatch(s.team2?.id, needle),
        );
        if (items.length > 0) {
            out[Number(key)] = items;
        }
    }

    return out;
}

const filteredPlayedSets = computed(() => filterGroupedSets(props.played_sets, props.matchesFilterTeamId));

const filteredUpcomingSets = computed(() => filterGroupedSets(props.upcoming_sets, props.matchesFilterTeamId));

const filteredTeamName = computed(
    () => props.teams.find((t) => teamIdsMatch(t.id, props.matchesFilterTeamId))?.name ?? null,
);

const nextSetHeading = computed(() => {
    if (props.matchesFilterTeamId != null && filteredTeamName.value) {
        return `Next for ${filteredTeamName.value}`;
    }

    return 'Your next set';
});

const hasPlayedForFilter = computed(
    () => Object.keys(filteredPlayedSets.value).length > 0,
);

const hasUpcomingForFilter = computed(
    () => Object.keys(filteredUpcomingSets.value).length > 0,
);

function onTeamFilterChange(raw: string): void {
    const next = raw === '' ? undefined : Number(raw);
    if (next !== undefined && Number.isNaN(next)) {
        return;
    }

    const url = route('leagues.matches', {
        league: props.leagueId,
        ...(next !== undefined ? { team: next } : {}),
    });

    router.get(url, {}, { replace: true, preserveScroll: true });
}
</script>

<template>
    <div class="flex min-h-full w-full flex-col gap-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                    <Swords class="size-3.5 opacity-70" aria-hidden="true" />
                    Schedule
                </p>
                <h2 class="text-balance text-2xl font-bold tracking-tight sm:text-3xl">Matches</h2>
                <p class="max-w-xl text-sm text-muted-foreground sm:text-base">
                    Upcoming and played sets by round. Choose a team to show only their matches — the URL updates so
                    refresh and shared links keep the filter.
                </p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:max-w-xs">
                <Label for="matches-team-filter">Filter by team</Label>
                <select
                    id="matches-team-filter"
                    class="flex h-11 min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none md:h-10 md:min-h-10 md:text-sm"
                    :value="matchesFilterTeamId != null ? String(matchesFilterTeamId) : ''"
                    @change="onTeamFilterChange(($event.target as HTMLSelectElement).value)"
                >
                    <option value="">All teams</option>
                    <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
            </div>
        </div>

        <div
            class="rounded-2xl border border-border/80 bg-gradient-to-b from-muted/25 via-card/50 to-card p-4 shadow-sm backdrop-blur-sm dark:from-muted/15 dark:via-card/30 sm:p-6"
        >
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 lg:gap-6 xl:gap-8">
                <section class="flex min-h-0 flex-col gap-3">
                    <h3 class="text-center text-lg font-bold tracking-tight sm:text-xl">{{ nextSetHeading }}</h3>
                    <template v-if="team_next">
                        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                            <ul role="list" class="divide-y divide-border overflow-hidden bg-card">
                                <li class="relative transition-colors hover:bg-accent/50">
                                    <div class="px-4 py-4 sm:px-5 sm:py-5">
                                        <MatchCard :sets="team_next" :team1="team_next.team1" :team2="team_next.team2" />
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </template>
                    <template v-else>
                        <div class="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-8 text-center dark:bg-muted/10">
                            <p class="text-sm text-muted-foreground">No upcoming set scheduled for this selection.</p>
                        </div>
                    </template>
                </section>

                <section class="flex min-h-0 min-h-[12rem] flex-col gap-3 lg:min-h-[18rem]">
                    <h3 class="text-center text-lg font-bold tracking-tight sm:text-xl">Sets to play</h3>
                    <div
                        v-if="!hasUpcomingForFilter"
                        class="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-8 text-center dark:bg-muted/10"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{
                                matchesFilterTeamId != null
                                    ? 'No upcoming sets involve this team.'
                                    : 'No upcoming sets in this league.'
                            }}
                        </p>
                    </div>
                    <ScrollArea v-else class="h-[min(50vh,28rem)] w-full rounded-xl border border-border/60 bg-card/80 lg:h-[min(60vh,32rem)]">
                        <div class="p-2 pr-3">
                            <ListWithHeadings :set="filteredUpcomingSets" initial-round="first" />
                        </div>
                    </ScrollArea>
                </section>

                <section class="flex min-h-0 min-h-[12rem] flex-col gap-3 lg:min-h-[18rem]">
                    <h3 class="text-center text-lg font-bold tracking-tight sm:text-xl">Played sets</h3>
                    <div
                        v-if="!hasPlayedForFilter"
                        class="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-8 text-center dark:bg-muted/10"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{
                                matchesFilterTeamId != null
                                    ? 'No played sets involve this team.'
                                    : 'No completed sets yet.'
                            }}
                        </p>
                    </div>
                    <ScrollArea v-else class="h-[min(50vh,28rem)] w-full rounded-xl border border-border/60 bg-card/80 lg:h-[min(60vh,32rem)]">
                        <div class="p-2 pr-3">
                            <ListWithHeadings :set="filteredPlayedSets" />
                        </div>
                    </ScrollArea>
                </section>
            </div>
        </div>
    </div>
</template>
