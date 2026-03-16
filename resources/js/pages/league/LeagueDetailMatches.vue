<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import LeagueMatches from '@/components/league/LeagueMatches.vue';

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

interface PlayedSets {
    [key: number]: Array<{
        id: number;
        league_id: number;
        pool_id: number;
        round: number;
        team1: { id: number; name: string; logo: string; user: { name: string } };
        team2: { id: number; name: string; logo: string; user: { name: string } };
    }>;
}

interface UpcomingSets {
    [key: number]: Array<{
        id: number;
        league_id: number;
        pool_id: number;
        round: number;
        team1: { id: number; name: string; logo: string; user: { name: string } };
        team2: { id: number; name: string; logo: string; user: { name: string } };
    }>;
}

interface TeamNext {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: { id: number; name: string; logo: string; user: { name: string } };
    team2: { id: number; name: string; logo: string; user: { name: string } };
}

defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    played_sets: PlayedSets;
    upcoming_sets: UpcomingSets;
    team_next: TeamNext | null;
}>();
</script>

<template>
    <LeagueDetailLayout :league="league" section="matches" :teams="teams" :draft="draft" :adminFlag="adminFlag" :matchConfig="matchConfig">
        <LeagueMatches :team_next="team_next" :played_sets="played_sets" :upcoming_sets="upcoming_sets" />
    </LeagueDetailLayout>
</template>
