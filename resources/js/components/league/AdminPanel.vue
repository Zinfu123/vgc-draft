<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Link } from '@inertiajs/vue3';
import StartDraft from '../draft/StartDraft.vue';
import EditLeague from './EditLeague.vue';

interface League {
    id: number;
    name: string;
    league_owner: number;
}

interface Draft {
    id: number | null;
}

const props = defineProps<{
    league: League;
    draft: Draft | null;
}>();
</script>

<template>
    <div class="flex flex-col gap-4 md:items-center md:justify-between">
        <div class="mt-4 mr-14 flex w-full flex-col items-end justify-end gap-2">
            <ButtonGroup>
                <EditLeague :league="props.league" />
                <Button variant="outline" as-child>
                    <Link :href="route('leagues.admin.pokemon-pool', { league: props.league.id })">Pokémon pool</Link>
                </Button>
                <StartDraft :league="props.league" :command="{ command: 'create' }" v-if="props.draft === null" />
                <Button variant="outline" as-child>
                    <Link :href="route('leagues.admin', { league: props.league.id })">Admin Settings</Link>
                </Button>
            </ButtonGroup>
        </div>
    </div>
</template>
