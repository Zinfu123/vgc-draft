<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import LeaguePokemon from '@/components/league/LeaguePokemon.vue';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    status: number;
    playoffs_enabled: boolean;
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

interface Pokemon {
    id: number;
    cost: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2?: string;
}

defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
    pokemon: Pokemon[];
    pokemon_drafted: Pokemon[];
}>();
</script>

<template>
    <LeagueDetailLayout :league="league" section="pokemon" :teams="teams" :draft="draft" :adminFlag="adminFlag" :matchConfig="matchConfig">
        <LeaguePokemon :pokemon="pokemon" :league="league" :pokemon_drafted="pokemon_drafted" />
    </LeagueDetailLayout>
</template>
