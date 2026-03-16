<script setup lang="ts">
import LeagueCarousel from '@/components/league/LeagueCarousel.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
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
    winner: string | null;
}

interface PastLeagues {
    id: number;
    name: string;
    draft_date: string;
    set_start_date: string;
    logo: string | null;
    winner: string | null;
}

// interface ParticipatingLeague {
//     id: number;
//     name: string;
// }

interface props {
    currentLeagues: CurrentLeagues[];
    pastLeagues: PastLeagues[];
}

const props = defineProps<props>();
</script>

<template>
    <Head title="Leagues" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mt-6 mr-4 flex justify-end">
            <Button variant="outline" @click="router.get(route('leagues.create-edit'), { command: 'create' })"> Create League </Button>
        </div>
        <div class="mx-auto flex flex-col items-center">
            <div class="mb-10">
                <h1 class="mb-4 text-3xl font-bold">Current Leagues</h1>
                <LeagueCarousel :leagues="props.currentLeagues" />
            </div>
        </div>
        <div class="mx-auto flex flex-col items-center">
            <div class="mb-10">
                <h1 class="mb-4 text-3xl font-bold">Past Leagues</h1>
                <LeagueCarousel :leagues="props.pastLeagues" />
            </div>
        </div>
    </AppLayout>
</template>
