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
    drafted_pokemon: DraftedPokemon[];
}

interface Pokemon {
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
    id: number;
    round_number: number;
    pick_number: number;
}

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}

interface Sets {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: object;
    team2: object;
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
        coach: {
            name: string;
        };
    };
    team2: {
        id: number;
        name: string;
        logo: string;
        coach: {
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
    adminFlag: number;
    matchConfig: MatchConfig;
    sets: Sets[];
    team_next: TeamNext;
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
            <AdminPanel :league="props.league" :draft="props.draft" :matchConfig="props?.matchConfig ?? null" v-if="props.adminFlag === 1" />
        </div>
        <div class="mx-auto mt-8 mb-8 flex max-w-4xl flex-col items-center">
            <h1 class="text-3xl font-bold">{{ props.league.name }}</h1>
        </div>
        <Tabs defaultValue="teams">
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
                <LeagueMatches :team_next="props.team_next" :sets="props.sets" />
                <!-- <div class="mx-auto mt-10 flex w-[full] flex-col items-center">
                    <div class="flex flex-row gap-12">
                        <div class="flex flex-col items-center w-1/2">
                        <h1 class="text-3xl font-bold">Your Next Match</h1>
                        <div class="basis-64">
                            <MatchCard v-if="props.team_next" :sets="props.team_next" :team1="props.team_next.team1" :team2="props.team_next.team2"/>
                            </div>
                        </div>
                        <div class="flex flex-col items-center w-1/2">
                        <h1 class="text-3xl font-bold">Previous Matches</h1>
                        <div class="flex flex-row items-center flex-wrap gap-4">
                            <div class="flex-none w-64">
                                <MatchCard v-for="set in props.sets" :key="set.id"  :sets="set" :team1="set.team1" :team2="set.team2"/>
                            </div>
                                </div>
                            </div>
                    </div>
                </div> -->
            </TabsContent>
            <TabsContent value="pokemon">
                <LeaguePokemon :pokemon="props.pokemon" :league="props.league" />
            </TabsContent>
            <TabsContent value="draft">
                <div class="flex flex-row items-center justify-center">
                    <button
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
