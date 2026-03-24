<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/league/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    logo?: string;
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${props.league.name} — Admin`" />

        <AdminLayout :league-id="props.league.id" :league-name="props.league.name">
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Match Configuration" description="Configure how matches are scheduled and pooled." />

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
            </div>

            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Pools" description="Create pools and assign teams to them." />
                <div class="flex gap-3">
                    <Button variant="outline" @click="createPools">Create Pools</Button>
                    <Button variant="outline" @click="teamsToPools">Assign Teams to Pools</Button>
                </div>
            </div>

            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Sets" description="Generate match sets for the league." />
                <div>
                    <Button variant="outline" @click="createSets">Create Sets</Button>
                </div>
            </div>
        </AdminLayout>
    </AppLayout>
</template>
