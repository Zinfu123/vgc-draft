<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
}

interface MatchConfig {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
}

const props = defineProps<{
    league: League;
    teams: Team[];
    matchConfig: MatchConfig | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leagues', href: '/leagues' },
    { title: props.league.name, href: `/leagues/${props.league.id}` },
    { title: 'Admin', href: '#' },
];

const matchConfigForm = useForm({
    league_id: props.matchConfig?.league_id ?? props.league.id,
    number_of_pools: props.matchConfig?.number_of_pools ?? 1,
    frequency_type: props.matchConfig?.frequency_type ?? 1,
    frequency_value: props.matchConfig?.frequency_value ?? null,
    command: props.matchConfig?.id ? 'update' : 'create',
});

const winnerForm = useForm({
    winner_user_id: null as number | null,
});

const handleMatchConfigSubmit = () => {
    matchConfigForm.post(route('leagues.match-config.create-edit-show', { league: props.league.id }));
};

const createPools = () => {
    router.post(route('pools.create'), {
        league_id: props.league.id,
        command: 'create',
    });
};

const teamsToPools = () => {
    router.post(route('pools.assign-teams-to-pools'), {
        league_id: props.league.id,
    });
};

const createSets = () => {
    router.post(route('sets.create', { league: props.league.id }), {
        league_id: props.league.id,
    });
};

const handleWinnerSubmit = () => {
    winnerForm.post(route('leagues.set-winner', { league: props.league.id }));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} - Admin`" />
        <div class="mx-auto w-full max-w-2xl px-4 pt-8 pb-10 sm:px-6 lg:px-8">
            <h1 class="mb-8 text-2xl font-bold">{{ props.league.name }} — Admin</h1>

            <!-- Match Config -->
            <section class="mb-10">
                <h2 class="mb-1 text-lg font-semibold">Match Configuration</h2>
                <p class="mb-4 text-sm text-muted-foreground">Configure how matches are scheduled and pooled.</p>
                <form @submit.prevent="handleMatchConfigSubmit" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium" for="number_of_pools">Number of Pools</label>
                        <Input id="number_of_pools" type="number" v-model="matchConfigForm.number_of_pools" min="1" />
                        <p v-if="matchConfigForm.errors.number_of_pools" class="text-sm text-destructive">{{ matchConfigForm.errors.number_of_pools }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium" for="frequency_type">Frequency Type</label>
                        <select
                            id="frequency_type"
                            v-model="matchConfigForm.frequency_type"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none dark:bg-background"
                        >
                            <option :value="1">Daily</option>
                            <option :value="2">Weekly</option>
                            <option :value="3">Single Day</option>
                            <option :value="4">Custom</option>
                        </select>
                        <p v-if="matchConfigForm.errors.frequency_type" class="text-sm text-destructive">{{ matchConfigForm.errors.frequency_type }}</p>
                    </div>
                    <div v-if="matchConfigForm.frequency_type === 4" class="flex flex-col gap-1">
                        <label class="text-sm font-medium" for="frequency_value">Frequency Value</label>
                        <Input id="frequency_value" type="number" v-model="matchConfigForm.frequency_value" min="1" />
                        <p v-if="matchConfigForm.errors.frequency_value" class="text-sm text-destructive">{{ matchConfigForm.errors.frequency_value }}</p>
                    </div>
                    <div class="flex justify-end pt-2">
                        <Button type="submit" :disabled="matchConfigForm.processing">
                            {{ matchConfigForm.command === 'create' ? 'Create Match Config' : 'Save Match Config' }}
                        </Button>
                    </div>
                </form>
            </section>

            <hr class="mb-10 border-border" />

            <!-- Pools -->
            <section class="mb-10">
                <h2 class="mb-1 text-lg font-semibold">Pools</h2>
                <p class="mb-4 text-sm text-muted-foreground">Create pools and assign teams to them.</p>
                <div class="flex gap-3">
                    <Button variant="outline" @click="createPools">Create Pools</Button>
                    <Button variant="outline" @click="teamsToPools">Assign Teams to Pools</Button>
                </div>
            </section>

            <hr class="mb-10 border-border" />

            <!-- Sets -->
            <section class="mb-10">
                <h2 class="mb-1 text-lg font-semibold">Sets</h2>
                <p class="mb-4 text-sm text-muted-foreground">Generate match sets for the league.</p>
                <Button variant="outline" @click="createSets">Create Sets</Button>
            </section>

            <hr class="mb-10 border-border" />

            <!-- Set Winner -->
            <section class="mb-10">
                <h2 class="mb-1 text-lg font-semibold">Set League Winner</h2>
                <p class="mb-4 text-sm text-muted-foreground">Selecting a winner will mark the league as completed.</p>
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
            </section>
        </div>
    </AppLayout>
</template>
