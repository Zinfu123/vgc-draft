<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/vue3';
import StartDraft from '../draft/StartDraft.vue';
import MatchConfigButton from '../match/MatchConfigButton.vue';
import PoolsButtons from '../pools/PoolsButtons.vue';
import ImportLeaguePokemon from './ImportLeaguePokemon.vue';

interface League {
    id: number;
    name: string;
    league_owner: number;
}

interface Draft {
    id: number | null;
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
    draft: Draft;
    matchConfig: MatchConfig;
}>();

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
    <div class="flex flex-col gap-4 md:items-center md:justify-between">
        <div class="mt-4 mr-14 flex w-full flex-col items-end justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger>
                    <Button variant="outline"> Admin Panel </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="flex flex-col items-end justify-end">
                    <ImportLeaguePokemon :league="props.league" />
                    <StartDraft :league="props.league" :command="{ command: 'create' }" v-if="props.draft === null" />
                    <PoolsButtons :league="props.league" :command="{ command: 'create' }" />
                    <MatchConfigButton :league="props.league" :matchConfig="props.matchConfig" />
                    <Button variant="outline" @click="teamsToPools"> Teams to Pools </Button>
                    <Button variant="outline" @click="createSets"> Create Sets </Button>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
