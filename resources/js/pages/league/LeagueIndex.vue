<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import LeagueCarousel from '@/components/league/LeagueCarousel.vue';
import LeagueForm from '@/components/league/LeagueForm.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Leagues',
        href: '/Leagues',
    },
];
interface CurrentLeagues {
    id: number;
    name: string;
    draft_date: string;
    set_start_date: string;
}

interface LeagueLogos {
    logo: string;
}

// interface ParticipatingLeague {
//     id: number;
//     name: string;
// }

interface props {
    currentLeagues: CurrentLeagues[]
    leagueLogos: LeagueLogos[];
    // participatingLeague: ParticipatingLeague[];
}

const props = defineProps<props>();
</script>

<template>
    <Head title="Leagues" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <LeagueForm />
        <div class="flex flex-col max-w-4xl mx-auto items-center">
           <h1 class="text-3xl font-bold">Current Leagues</h1>
           <LeagueCarousel :leagues="props.currentLeagues" :leagueLogos="props.leagueLogos" />
        </div>
    </AppLayout>
</template>