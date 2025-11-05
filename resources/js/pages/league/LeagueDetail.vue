<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import TeamForm from '@/components/team/TeamForm.vue';
import TeamCarousel from '@/components/team/TeamCarousel.vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsList, TabsTrigger, TabsContent} from '@/components/ui/tabs';
import LeaguePokemon from '@/components/league/LeaguePokemon.vue';
import AdminPanel from '@/components/league/AdminPanel.vue';
import { router } from '@inertiajs/vue3';

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

interface props {
    league: League;
    teams: Teams[];
    pokemon: Pokemon[];
    costHeaders: CostHeaders[];
}


const props = defineProps<props>();

const user = usePage().props.auth.user;
const coachexists = props.teams.some(team => team.coach === user.name);

const draftDetail = () => {
    router.get(route('draft.detail', { league_id: props.league.id }));
}

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
        <div class="flex flex-row justify-end items-end w-full mr-14 mt-4">
            <TeamForm :league_id="props.league.id" :user_id="usePage().props.auth.user.id" v-if="coachexists === false" />
            <AdminPanel :league="props.league" :draft="props.draft" v-if="props.league.league_owner === usePage().props.auth.user.id"/>
        </div>
        <div class="flex flex-col max-w-4xl mx-auto items-center mt-10">
            <h1 class="text-3xl font-bold">{{ props.league.name }}</h1>
        </div>
        <Tabs defaultValue="teams" class="justify-center items-center w-[full] mt-8 mr-8 ml-8">
        <TabsList class="w-full grid grid-flow-col">
            <TabsTrigger value="teams" class="dark:data-[state=active]:bg-black/80">Teams</TabsTrigger>
            <TabsTrigger value="matches" class="dark:data-[state=active]:bg-black/80">Matches</TabsTrigger>
            <TabsTrigger value="standings" class="dark:data-[state=active]:bg-black/80">Standings</TabsTrigger>
            <TabsTrigger value="trades" class="dark:data-[state=active]:bg-black/80">Trades</TabsTrigger>
            <TabsTrigger value="draft" class="dark:data-[state=active]:bg-black/80" v-if="props.draft !== null">Draft</TabsTrigger>
            <TabsTrigger value="pokemon" class="dark:data-[state=active]:bg-black/80" :href="route('leagues.pokemon', { league: props.league.id })">Pokemon</TabsTrigger>
        </TabsList>
        <TabsContent value="teams">
                <TeamCarousel :leagueteams="props.teams" />
        </TabsContent>
        <TabsContent value="matches">
            <div class="flex flex-col max-w-4xl mx-auto items-center mt-10">
                <div class=" grid grid-cols-2">
                <h1 class="text-3xl font-bold justify-start">Your Next Match</h1>
                <h1 class="text-3xl font-bold justify-end">This Weeks Matches</h1>
                </div>
            </div>
        </TabsContent>
        <TabsContent value="pokemon">
            <LeaguePokemon :pokemon="props.pokemon" :league="props.league" />
        </TabsContent>
        <TabsContent value="draft">
            <button class="text-1xl font-bold border-2 border-indigo-600 rounded-md p-2 m-2 bg-gray-800/85 dark:bg-muted/85" @click="draftDetail">Draft Detail</button>
        </TabsContent>
    </Tabs>
    </AppLayout>
</template>