<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import BlobBackground from '@/components/BlobBackground.vue';
import PageHeader from '@/components/PageHeader.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import TeamCarousel from '@/components/team/TeamCarousel.vue';
import { Head } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';

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

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
}>();

const teamCount = props.teams.length;
const teamCountLabel = teamCount === 1 ? '1 team' : `${teamCount} teams`;
</script>

<template>
    <LeagueDetailLayout :league="league" section="rosters" :teams="teams" :draft="draft" :adminFlag="adminFlag" :matchConfig="matchConfig">
        <Head :title="`Rosters · ${league.name}`" />

        <div class="relative">
            <BlobBackground>
                <div class="absolute -top-20 right-0 h-72 w-72 rounded-full bg-dragontype/12 blur-3xl dark:bg-dragontype/18" />
                <div class="absolute top-1/4 -left-24 h-64 w-64 rounded-full bg-watertype/12 blur-3xl dark:bg-watertype/18" />
                <div class="absolute bottom-0 left-1/3 h-48 w-64 rounded-full bg-steeltype/10 blur-3xl dark:bg-steeltype/15" />
            </BlobBackground>

            <div class="relative z-10 flex flex-col gap-6">
                <PageHeader eyebrow="Rosters" title="Teams" :icon="Users">
                    {{ teamCountLabel }}
                    <template v-if="teamCount > 0">
                        — Open a card for full roster, record, and league links. Stats mirror the dashboard cards.
                    </template>
                    <template v-else>Create or join a team with the controls above. Cards appear here for each coach.</template>
                </PageHeader>

                <div
                    class="rounded-2xl border border-border/80 bg-gradient-to-b from-muted/30 via-card/60 to-card p-4 shadow-sm backdrop-blur-sm dark:from-muted/20 dark:via-card/40 sm:p-6"
                >
                    <TeamCarousel :teams="teams" />
                </div>
            </div>
        </div>
    </LeagueDetailLayout>
</template>
