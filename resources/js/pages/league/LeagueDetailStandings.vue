<script setup lang="ts">
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import StandingsList from '@/components/league/StandingsList.vue';
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';

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

interface Standings {
    [key: number]: {
        id: number;
        name: string;
        logo: string;
        user: { name: string };
        victory_points: number;
    };
}

defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    standings: Standings;
}>();
</script>

<template>
    <LeagueDetailLayout
        :league="league"
        section="standings"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <StandingsList :standings="standings" />
    </LeagueDetailLayout>
</template>
