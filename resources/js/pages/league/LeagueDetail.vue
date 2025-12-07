<script setup lang="ts">
import AdminPanel from '@/components/league/AdminPanel.vue';
import LeagueMatches from '@/components/league/LeagueMatches.vue';
import LeaguePokemon from '@/components/league/LeaguePokemon.vue';
import TeamCarousel from '@/components/team/TeamCarousel.vue';
import TeamForm from '@/components/team/TeamForm.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
}

interface Teams {
    id: number;
    name: string;
    coach: string;
    logo: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
}

interface Pokemon {
    id: number;
    cost: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2?: string;
}

interface PokemonDrafted {
    id: number;
    cost: number;
    name: string;
    sprite_url: string;
    type1: string;
    type2?: string;
}

interface CostHeaders {
    costHeaders: number;
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
    [key: number]: {
        id: number;
        league_id: number;
        pool_id: number;
        round: number;
        team1: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
        team2: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
    };
}

interface UpcomingSets {
    [key: number]: {
        id: number;
        league_id: number;
        pool_id: number;
        round: number;
        team1: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
        team2: {
            id: number;
            name: string;
            logo: string;
            user: {
                name: string;
            };
        };
    };
}

interface TeamNext {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
    };
    team2: {
        id: number;
        name: string;
        logo: string;
        user: {
            name: string;
        };
    };
}

interface props {
    league: League;
    teams: Teams[];
    pokemon: Pokemon[];
    costHeaders: CostHeaders[];
    draft: Draft;
    adminFlag: boolean | number;
    matchConfig: MatchConfig;
    played_sets: PlayedSets;
    upcoming_sets: UpcomingSets;
    team_next: TeamNext;
    pokemon_drafted: PokemonDrafted[];
}

const props = defineProps<props>();

const user = usePage().props.auth.user;
const coachexists = props.teams.some((team) => team.coach === user.name);

const draftDetail = () => {
    router.get(route('draft.detail', { league_id: props.league.id }));
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Leagues',
        href: '/leagues',
    },
    {
        title: props.league.name,
        href: `/leagues/${props.league.id}`,
    },
];
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name}`" />
        <div class="mt-4 mr-14 flex w-full flex-row items-end justify-end">
            <TeamForm :league_id="props.league.id" :user_id="usePage().props.auth.user.id" v-if="coachexists === false" />
            <AdminPanel
                :league="props.league"
                :draft="props.draft"
                :matchConfig="props?.matchConfig ?? null"
                v-if="props.adminFlag === true || props.adminFlag === 1"
            />
        </div>
        <div class="mx-auto mt-8 mb-8 flex max-w-4xl flex-col items-center">
            <h1 class="text-3xl font-bold">{{ props.league.name }}</h1>
        </div>
        <Tabs defaultValue="matches">
            <TabsList class="mb-4 self-center">
                <TabsTrigger value="teams" class="dark:data-[state=active]:bg-black/80">Teams</TabsTrigger>
                <TabsTrigger value="pools" class="dark:data-[state=active]:bg-black/80">Pools</TabsTrigger>
                <TabsTrigger value="matches" class="dark:data-[state=active]:bg-black/80">Matches</TabsTrigger>
                <TabsTrigger value="standings" class="dark:data-[state=active]:bg-black/80">Standings</TabsTrigger>
                <TabsTrigger value="trades" class="dark:data-[state=active]:bg-black/80">Trades</TabsTrigger>
                <TabsTrigger value="draft" class="dark:data-[state=active]:bg-black/80" v-if="props.draft !== null">Draft</TabsTrigger>
                <TabsTrigger
                    value="pokemon"
                    class="dark:data-[state=active]:bg-black/80"
                    :href="route('leagues.pokemon', { league: props.league.id })"
                    >Pokemon</TabsTrigger
                >
            </TabsList>
            <TabsContent value="teams">
                <TeamCarousel :leagueteams="props.teams" />
            </TabsContent>
            <TabsContent value="pools"> </TabsContent>
            <TabsContent value="matches">
                <LeagueMatches :team_next="props.team_next" :played_sets="props.played_sets" :upcoming_sets="props.upcoming_sets" />
            </TabsContent>
            <TabsContent value="pokemon">
                <LeaguePokemon :pokemon="props.pokemon" :league="props.league" :pokemon_drafted="props.pokemon_drafted" />
            </TabsContent>
            <TabsContent value="draft">
                <div class="flex flex-row items-center justify-center">
                    <button
                        v-if="props.draft?.status === 1"
                        class="text-1xl m-2 rounded-md border-2 border-indigo-600 bg-gray-800/85 p-2 font-bold dark:bg-muted/85"
                        @click="draftDetail"
                    >
                        Draft Detail
                    </button>
                </div>
            </TabsContent>
        </Tabs>
    </AppLayout>
</template>
