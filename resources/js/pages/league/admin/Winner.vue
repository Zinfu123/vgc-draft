<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
}

const props = defineProps<{
    league: League;
    teams: Team[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

const winnerForm = useForm({
    winner_user_id: null as number | null,
});

const handleWinnerSubmit = () => {
    winnerForm.post(route('leagues.set-winner', { league: props.league.id }));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — Admin`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Set League Winner" description="Selecting a winner will mark the league as completed." />

                <form @submit.prevent="handleWinnerSubmit" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Winning Team</label>
                        <select
                            v-model="winnerForm.winner_user_id"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none dark:bg-background"
                        >
                            <option :value="null" disabled>Select a team...</option>
                            <option v-for="team in props.teams" :key="team.id" :value="team.user_id">
                                {{ team.name }} ({{ team.coach }})
                            </option>
                        </select>
                        <p v-if="winnerForm.errors.winner_user_id" class="text-sm text-destructive">{{ winnerForm.errors.winner_user_id }}</p>
                    </div>
                    <div class="flex justify-end pt-2">
                        <Button type="submit" :disabled="winnerForm.processing || winnerForm.winner_user_id === null">Set Winner</Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
