<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import TeamForm from '@/components/team/TeamForm.vue';
import TeamCarousel from '@/components/team/TeamCarousel.vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsList, TabsTrigger, TabsContent} from '@/components/ui/tabs';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
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

interface props {
    league: League;
    teams: Teams[];
}


const props = defineProps<props>();

const user = usePage().props.auth.user;
const coachexists = props.teams.some(team => team.coach === user.name);

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
        <div class="flex flex-col justify-end items-end w-full" v-if="coachexists === false">
            <TeamForm :league_id="props.league.id" :user_id="usePage().props.auth.user.id" />
        </div>
        <div class="flex flex-col max-w-4xl mx-auto items-center mt-10">
            <h1 class="text-3xl font-bold">{{ props.league.name }}</h1>
        </div>
        <Tabs defaultValue="teams" class="mx-auto justify-center items-center w-[800px] mt-8">
        <TabsList class="w-full grid grid-cols-6">
            <TabsTrigger value="teams" class="dark:data-[state=active]:bg-black/80">Teams</TabsTrigger>
            <TabsTrigger value="matches" class="dark:data-[state=active]:bg-black/80">Matches</TabsTrigger>
            <TabsTrigger value="standings" class="dark:data-[state=active]:bg-black/80">Standings</TabsTrigger>
            <TabsTrigger value="trades" class="dark:data-[state=active]:bg-black/80">Trades</TabsTrigger>
            <TabsTrigger value="draft" class="dark:data-[state=active]:bg-black/80">Draft</TabsTrigger>
            <TabsTrigger value="pokemon" class="dark:data-[state=active]:bg-black/80">Pokemon</TabsTrigger>
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
    </Tabs>
    </AppLayout>
</template>