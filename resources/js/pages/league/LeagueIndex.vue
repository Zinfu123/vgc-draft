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
        <div class="mt-4 flex justify-end px-4 sm:mt-6 sm:mr-4 sm:px-0">
            <Button
                variant="outline"
                class="min-h-11 w-full touch-manipulation sm:w-auto"
                @click="router.get(route('leagues.create-edit'), { command: 'create' })"
            >
                Create League
            </Button>
        </div>
        <div class="mx-auto flex w-full max-w-7xl flex-col items-center px-4 pb-10 sm:px-6">
            <div class="mb-10">
                <h1 class="mb-4 text-3xl font-bold">Current Leagues</h1>
                <div class="flex flex-wrap justify-center gap-4 sm:justify-start">
                    <LeagueCarousel :leagues="props.currentLeagues" />
                </div>
            </div>
        </div>
        <div class="mx-auto flex flex-col items-center">
            <div class="mb-10">
                <h1 class="mb-4 text-3xl font-bold">Past Leagues</h1>
                <div class="flex flex-wrap justify-center gap-4 sm:justify-start">
                    <LeagueCarousel :leagues="props.pastLeagues" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
