<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import BlobBackground from '@/components/BlobBackground.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import LeagueMatches from '@/components/league/LeagueMatches.vue';
import { Head } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
}

interface Team {
    id: number;
    league_id: number;
    name: string;
    coach: string;
    logo: string | null;
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

defineProps<{
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
}>();
</script>

<template>
    <LeagueDetailLayout :league="league" section="matches" :teams="teams" :draft="draft" :adminFlag="adminFlag" :matchConfig="matchConfig">
        <Head :title="`Matches · ${league.name}`" />

        <div class="relative">
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
    </LeagueDetailLayout>
</template>
