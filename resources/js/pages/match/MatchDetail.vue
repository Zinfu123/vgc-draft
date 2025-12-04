<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Match {
    id: number;
    league_id: number;
    pool_id: number;
    round: number;
    team1: {
        id: number;
        name: string;
        logo: string;
    };
    team2: {
        id: number;
        name: string;
        logo: string;
    };
    team1_score: number;
    team2_score: number;
    team1_pokepaste: string;
    team2_pokepaste: string;
    winner_id: number;
    winner_name: string;
    winner_logo: string;
}

interface props {
    match: Match;
}

const props = defineProps<props>();
</script>
<template>
    <AppLayout>
        <Head :title="`${props.match.team1.name} vs ${props.match.team2.name}`" />
        <div class="mx-auto mt-8 mb-8 flex max-w-4xl flex-col items-center">
            <h1 class="text-3xl font-bold">{{ props.match.team1.name }} vs {{ props.match.team2.name }}</h1>
        </div>
    </AppLayout>
</template>
