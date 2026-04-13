<script setup lang="ts">
import TeamCard from '@/components/team/TeamCard.vue';
import { Link } from '@inertiajs/vue3';

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

defineProps<{
    teams: Teams[];
}>();
</script>

<template>
    <div
        v-if="teams.length === 0"
        class="rounded-xl border border-dashed border-border bg-muted/20 px-6 py-14 text-center dark:bg-muted/10"
    >
        <p class="text-sm font-medium text-foreground">No teams in this league yet</p>
        <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
            After coaches register, each team shows up here as a card with record and victory points—same stat emphasis as your dashboard.
        </p>
    </div>
    <div
        v-else
        class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
    >
        <Link
            v-for="team in teams"
            :key="team.id"
            :href="route('leagues.dashboard', { league: team.league_id, team: team.id })"
            class="group block min-w-0 rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
        >
            <TeamCard :team="team" />
        </Link>
    </div>
</template>
