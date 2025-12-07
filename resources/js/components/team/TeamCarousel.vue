<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import TeamCard from './TeamCard.vue';

interface Teams {
    id: number;
    league_id: number;
    name: string;
    logo: string | null;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    coach: string;
}

interface props {
    teams: Teams[];
}

const props = defineProps<props>();
</script>

<template>
    <div class="flex flex-wrap gap-4">
        <TeamCard v-for="team in teams" :key="team.id" :team="team" @click="router.get(`/teams/${team.id}`)" />
    </div>
</template>
