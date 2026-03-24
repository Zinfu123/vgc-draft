<script setup lang="ts">
import AdminPanel from '@/components/league/AdminPanel.vue';
import TeamForm from '@/components/team/TeamForm.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { useMobileLayout } from '@/composables/useMobileLayout';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { isMobile } = useMobileLayout();

export type LeagueDetailSection = 'teams' | 'matches' | 'standings' | 'trades' | 'draft' | 'pokemon' | 'playoffs';

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
    user_id: number;
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

const user = usePage().props.auth.user;
const coachexists = props.teams.some((team) => team.coach === user?.name);
const userTeam = props.teams.find((team) => team.coach === user?.name) ?? null;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
];

const sections: { value: LeagueDetailSection; label: string; route: string }[] = [
    { value: 'teams', label: 'Teams', route: 'leagues.teams' },
    { value: 'matches', label: 'Matches', route: 'leagues.matches' },
    { value: 'standings', label: 'Standings', route: 'leagues.standings' },
    { value: 'playoffs', label: 'Playoffs', route: 'leagues.playoffs' },
    { value: 'trades', label: 'Trades', route: 'leagues.trades' },
    { value: 'draft', label: 'Draft', route: 'leagues.draft' },
    { value: 'pokemon', label: 'Pokemon', route: 'leagues.pokemon' },
];

const draftHref = computed(() =>
    props.draft !== null
        ? route('draft.detail', { league_id: props.league.id })
        : route('leagues.draft', { league: props.league.id }),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="props.league.name" />
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 pb-10 sm:px-6 lg:px-8">
            <div class="flex w-full justify-end">
                <template v-if="!(props.adminFlag === true || props.adminFlag === 1)">
                    <TeamForm
                        v-if="!coachexists && (!props.draft || props.draft?.status === 1)"
                        :league_id="props.league.id"
                        :user_id="user?.id"
                        command="create"
                        :user_team="null"
                    />
                    <TeamForm v-if="coachexists" :league_id="props.league.id" :user_id="user?.id" command="edit" :user_team="userTeam" />
                </template>
                <div v-if="props.adminFlag === true || props.adminFlag === 1" class="flex flex-col items-end gap-2">
                    <AdminPanel :league="props.league" :draft="props.draft" />
                    <TeamForm
                        v-if="!coachexists && (!props.draft || props.draft?.status === 1)"
                        :league_id="props.league.id"
                        :user_id="user?.id"
                        command="create"
                        :user_team="null"
                    />
                    <TeamForm v-if="coachexists" :league_id="props.league.id" :user_id="user?.id" command="edit" :user_team="userTeam" />
                </div>
            </div>
            <div class="mt-6 flex flex-col items-center">
                <h1 class="text-3xl font-bold">{{ props.league.name }}</h1>
            </div>
            <div class="mt-4 w-full md:flex md:justify-center max-md:-mx-4 max-md:px-4">
                <div
                    v-if="isMobile"
                    class="flex w-full max-w-full gap-2 overflow-x-auto overscroll-x-contain pb-2 [scrollbar-width:thin] snap-x snap-mandatory [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30"
                >
                    <Button
                        v-for="s in sections"
                        :key="s.value"
                        size="sm"
                        class="shrink-0 snap-start touch-manipulation"
                        :variant="props.section === s.value ? 'default' : 'outline'"
                        as-child
                    >
                        <Link :href="s.value === 'draft' ? draftHref : route(s.route, { league: props.league.id })">{{ s.label }}</Link>
                    </Button>
                </div>
                <ButtonGroup v-else class="flex-wrap justify-center">
                    <template v-for="s in sections" :key="s.value">
                        <Button size="sm" :variant="props.section === s.value ? 'default' : 'outline'" as-child>
                            <Link :href="s.value === 'draft' ? draftHref : route(s.route, { league: props.league.id })">{{ s.label }}</Link>
                        </Button>
                    </template>
                </ButtonGroup>
            </div>
            <div class="min-h-[200px] w-full pt-6">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
