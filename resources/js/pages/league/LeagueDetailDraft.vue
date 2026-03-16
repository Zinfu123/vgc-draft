<script setup lang="ts">
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
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

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
}>();

const draftDetail = () => {
    router.get(route('draft.detail', { league_id: props.league.id }));
};
</script>

<template>
    <LeagueDetailLayout
        :league="league"
        section="draft"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <div class="flex flex-row items-center justify-center py-8">
            <Button v-if="draft?.status === 1" size="lg" @click="draftDetail">Draft Detail</Button>
        </div>
    </LeagueDetailLayout>
</template>
