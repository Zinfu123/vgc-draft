<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import TeamForm from '@/components/team/TeamForm.vue';
import TeamCarousel from '@/components/team/TeamCarousel.vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

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
        <div class="flex flex-col max-w-4xl mx-auto items-center mt-10">
            <h1 class="text-3xl font-bold">Teams</h1>
            <div class="flex flex-col max-w-4xl mx-auto items-center mt-10">
                <TeamCarousel :teams="props.teams" />
            </div>
            <div class="flex flex-col max-w-4xl mx-auto items-center mt-10">
            </div>
        </div>
    </AppLayout>
</template>