<script setup lang="ts">
import LeagueCarousel from '@/components/league/LeagueCarousel.vue';
import LeagueForm from '@/components/league/LeagueForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

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
    logo: string | null;
}

// interface ParticipatingLeague {
//     id: number;
//     name: string;
// }

interface props {
    currentLeagues: CurrentLeagues[];
    // participatingLeague: ParticipatingLeague[];
}

const props = defineProps<props>();
</script>

<template>
    <Head title="Leagues" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <LeagueForm />
        <div class="mx-auto flex flex-col items-center">
            <h1 class="text-3xl font-bold">Current Leagues</h1>
            <LeagueCarousel :leagues="props.currentLeagues" />
        </div>
    </AppLayout>
</template>
