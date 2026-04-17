<script setup lang="ts">
import TeamForm from '@/components/team/TeamForm.vue';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { useMobileLayout } from '@/composables/useMobileLayout';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';

const { isMobile } = useMobileLayout();

export type LeagueDetailSection = 'dashboard' | 'draft' | 'rosters' | 'schedule' | 'pokemon' | 'stats' | 'trades' | 'match-prep' | 'commissioner';

// League status constants
const LEAGUE_STATUS_REGISTRATION = 2;

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    require_showdown_username?: boolean;
    status: number;
    playoffs_enabled: boolean;
}

interface Team {
    id: number;
    league_id: number;
    name: string;
    coach: string;
    logo: string | null;
    user_id: number;
    showdown_username?: string | null;
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
    require_replays_before_results: boolean;
    auto_complete_set_from_replays: boolean;
}

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfig | null;
}>();

const page = usePage();
const user = page.props.auth.user as { id?: number; name?: string; showdown_username?: string | null } | null;
const coachexists = props.teams.some((team) => team.user_id === user?.id);
const userTeam = props.teams.find((team) => team.user_id === user?.id) ?? null;

const showdownRequired = computed(() => !!props.league.require_showdown_username);
const userHasShowdown = computed(() => {
    const teamVal = userTeam?.showdown_username?.trim();
    const profileVal = user?.showdown_username?.trim();
    return !!(teamVal || profileVal);
});
const needsShowdownWarning = computed(
    () => showdownRequired.value && coachexists && !userHasShowdown.value,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
];

const isAdmin = computed(() => props.adminFlag === true || props.adminFlag === 1);

const baseSections: { value: LeagueDetailSection; label: string; route: string }[] = [
    { value: 'dashboard', label: 'Dashboard', route: 'leagues.dashboard' },
    { value: 'draft', label: 'Draft', route: 'leagues.draft' },
    { value: 'rosters', label: 'Rosters', route: 'leagues.rosters' },
    { value: 'schedule', label: 'Schedule', route: 'leagues.schedule' },
    { value: 'pokemon', label: 'Pokémon', route: 'leagues.pokemon' },
    { value: 'stats', label: 'Stats', route: 'leagues.stats' },
    { value: 'trades', label: 'Trades', route: 'leagues.trades' },
    { value: 'match-prep', label: 'Match Prep', route: 'match-prep.index' },
];

const sections = computed(() => {
    if (isAdmin.value) {
        return [
            ...baseSections,
            { value: 'commissioner' as LeagueDetailSection, label: 'Commissioner', route: 'leagues.admin' },
        ];
    }

    return baseSections;
});

const draftHref = computed(() => {
    if (props.draft !== null && props.draft.status === 0) {
        return route('leagues.draft', { league: props.league.id });
    }

    return route('draft.detail', { league_id: props.league.id });
});

function sectionHref(s: { value: LeagueDetailSection; route: string }): string {
    if (s.value === 'draft') {
        return draftHref.value;
    }

    if (s.value === 'match-prep') {
        return route('match-prep.index');
    }

    return route(s.route, { league: props.league.id });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="props.league.name" />
        <div class="mx-auto w-full max-w-screen-2xl px-4 pt-4 pb-10 sm:px-6 lg:px-8">
            <!-- Showdown username required notice -->
            <div
                v-if="needsShowdownWarning"
                class="mb-4 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="flex-1">
                    <p class="font-medium">Showdown username required</p>
                    <p class="text-amber-800 dark:text-amber-300">
                        This league requires all coaches to have a Pokémon Showdown username. Add yours via the
                        <strong>Edit Team</strong> button to participate in matches.
                    </p>
                </div>
            </div>

            <div class="mt-2 flex items-center justify-between gap-4">
                <h1 class="text-3xl font-bold">{{ props.league.name }}</h1>
                <div class="shrink-0">
                    <TeamForm
                        v-if="!coachexists && props.league.status === LEAGUE_STATUS_REGISTRATION"
                        :league_id="props.league.id"
                        command="create"
                        :user_team="null"
                    />
                    <TeamForm
                        v-if="coachexists"
                        :league_id="props.league.id"
                        command="edit"
                        :user_team="userTeam"
                        :initial-open="needsShowdownWarning"
                    />
                </div>
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
                        <Link :href="sectionHref(s)">{{ s.label }}</Link>
                    </Button>
                </div>
                <ButtonGroup v-else class="flex-wrap justify-center">
                    <template v-for="s in sections" :key="s.value">
                        <Button size="sm" :variant="props.section === s.value ? 'default' : 'outline'" as-child>
                            <Link :href="sectionHref(s)">{{ s.label }}</Link>
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
