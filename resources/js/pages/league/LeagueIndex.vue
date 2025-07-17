<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import LeagueCarousel from '@/components/league/leaguecarousel.vue';

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
    logo?: string;
    draft_date: string;
    set_start_date: string;
}

// interface ParticipatingLeague {
//     id: number;
//     name: string;
// }

interface props {
    currentLeagues: CurrentLeagues[]
    // participatingLeague: ParticipatingLeague[];
}

const props = defineProps<props>();

</script>

<template>
    <Head title="Leagues" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col max-w-4xl mx-auto items-center">
           <h1 class="text-3xl font-bold">Current Leagues</h1>
           <LeagueCarousel :leagues="props.currentLeagues" />
        </div>
    </AppLayout>
</template>