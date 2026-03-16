<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import StartDraft from '../draft/StartDraft.vue';
import MatchConfigButton from '../match/MatchConfigButton.vue';
import PoolsButtons from '../pools/PoolsButtons.vue';
import ImportLeaguePokemon from './ImportLeaguePokemon.vue';
import EditLeague from './EditLeague.vue';

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

interface Team {
    id: number;
    name: string;
    coach: string;
    user_id: number;
}

const props = defineProps<{
    league: League;
    draft: Draft | null;
    matchConfig: MatchConfig | null;
    teams: Team[];
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

const winnerDialogOpen = ref(false);

const winnerForm = useForm({
    winner_user_id: null as number | null,
});

const setWinner = () => {
    winnerForm.post(route('leagues.set-winner', { league: props.league.id }), {
        onSuccess: () => {
            winnerDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-4 md:items-center md:justify-between">
        <div class="mt-4 mr-14 flex w-full flex-col items-end justify-end">
            <ButtonGroup>
                <EditLeague :league="props.league" />
                <ImportLeaguePokemon :league="props.league" />
                <StartDraft :league="props.league" :command="{ command: 'create' }" v-if="props.draft === null" />
                <PoolsButtons :league="props.league" :command="{ command: 'create' }" />
                <MatchConfigButton v-if="props.matchConfig" :league="props.league" :matchConfig="props.matchConfig" />
                <Button variant="outline" @click="teamsToPools"> Teams to Pools </Button>
                <Button variant="outline" @click="createSets"> Create Sets </Button>
                <Dialog v-model:open="winnerDialogOpen">
                    <DialogTrigger asChild>
                        <Button variant="outline"> Set Winner </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Set League Winner</DialogTitle>
                            <DialogDescription>
                                Select the winning coach. This will also mark the league as completed.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Winning Team</label>
                            <select
                                v-model="winnerForm.winner_user_id"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option :value="null" disabled>Select a team...</option>
                                <option v-for="team in props.teams" :key="team.id" :value="team.user_id">
                                    {{ team.name }} ({{ team.coach }})
                                </option>
                            </select>
                            <div v-if="winnerForm.errors.winner_user_id" class="text-sm text-destructive">
                                {{ winnerForm.errors.winner_user_id }}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" @click="winnerDialogOpen = false">Cancel</Button>
                            <Button @click="setWinner" :disabled="winnerForm.processing || winnerForm.winner_user_id === null">
                                Confirm Winner
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </ButtonGroup>
        </div>
    </div>
</template>
