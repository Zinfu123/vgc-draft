<script setup lang="ts">
import type { LeagueDetailSection } from '@/components/league/LeagueDetailLayout.vue';
import CommissionerSubNav from '@/components/league/CommissionerSubNav.vue';
import LeagueDetailLayout from '@/components/league/LeagueDetailLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, router, useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
    draft_date: string;
    set_start_date: string;
    league_owner: number;
    status: number;
    playoffs_enabled: boolean;
}

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
}

interface Draft {
    id: number | null;
    round_number: number;
    pick_number: number;
    status: number;
}

interface MatchConfigData {
    id: number;
    league_id: number;
    number_of_pools: number;
    frequency_type: number;
    frequency_value: number;
    status: number;
    require_team_match_pokepaste_before_results?: boolean;
}

const props = defineProps<{
    league: League;
    section: LeagueDetailSection;
    teams: Team[];
    draft: Draft | null;
    adminFlag: boolean | number;
    matchConfig: MatchConfigData | null;
}>();

const matchConfigForm = useForm({
    league_id: props.matchConfig?.league_id ?? props.league.id,
    number_of_pools: props.matchConfig?.number_of_pools ?? 1,
    frequency_type: props.matchConfig?.frequency_type ?? 2,
    frequency_value: props.matchConfig?.frequency_value ?? null,
    command: props.matchConfig?.id ? 'update' : 'create',
});

const handleMatchConfigSubmit = () => {
    matchConfigForm.post(route('leagues.match-config.create-edit-show', { league: props.league.id }));
};

const createPools = () => {
    router.post(route('pools.create'), { league_id: props.league.id, command: 'create' });
};

const teamsToPools = () => {
    router.post(route('pools.assign-teams-to-pools'), { league_id: props.league.id });
};

const createSets = () => {
    router.post(route('sets.create', { league: props.league.id }), { league_id: props.league.id });
};
</script>

<template>
    <LeagueDetailLayout
        :league="league"
        section="commissioner"
        :teams="teams"
        :draft="draft"
        :adminFlag="adminFlag"
        :matchConfig="matchConfig"
    >
        <Head :title="`Match Config · ${league.name}`" />

        <div class="flex flex-col gap-8">
            <CommissionerSubNav :league="league" />

            <section class="flex flex-col gap-6">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Match Configuration</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">Configure how matches are scheduled and pooled.</p>
                </div>

                <form class="flex flex-col gap-4" @submit.prevent="handleMatchConfigSubmit">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="number_of_pools">Number of Pools</label>
                        <Input id="number_of_pools" v-model="matchConfigForm.number_of_pools" type="number" min="1" class="max-w-xs" />
                        <p v-if="matchConfigForm.errors.number_of_pools" class="text-sm text-destructive">{{ matchConfigForm.errors.number_of_pools }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="frequency_type">Frequency Type</label>
                        <select
                            id="frequency_type"
                            v-model="matchConfigForm.frequency_type"
                            class="max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring dark:bg-background"
                        >
                            <option :value="1">Daily</option>
                            <option :value="2">Weekly</option>
                            <option :value="3">Single Day</option>
                            <option :value="4">Custom</option>
                        </select>
                        <p v-if="matchConfigForm.errors.frequency_type" class="text-sm text-destructive">{{ matchConfigForm.errors.frequency_type }}</p>
                    </div>
                    <div v-if="matchConfigForm.frequency_type === 4" class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium" for="frequency_value">Frequency Value</label>
                        <Input id="frequency_value" v-model="matchConfigForm.frequency_value" type="number" min="1" class="max-w-xs" />
                        <p v-if="matchConfigForm.errors.frequency_value" class="text-sm text-destructive">{{ matchConfigForm.errors.frequency_value }}</p>
                    </div>
                    <div class="flex pt-2">
                        <Button type="submit" :disabled="matchConfigForm.processing">
                            {{ matchConfigForm.command === 'create' ? 'Create Match Config' : 'Save Match Config' }}
                        </Button>
                    </div>
                </form>
            </section>

            <section class="flex flex-col gap-4">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Pools</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">Create pools and assign teams to them.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Button variant="outline" @click="createPools">Create Pools</Button>
                    <Button variant="outline" @click="teamsToPools">Assign Teams to Pools</Button>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <div class="border-b border-border pb-3">
                    <h2 class="text-xl font-semibold">Sets</h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">Generate match sets for the league.</p>
                </div>
                <div>
                    <Button variant="outline" @click="createSets">Create Sets</Button>
                </div>
            </section>
        </div>
    </LeagueDetailLayout>
</template>
