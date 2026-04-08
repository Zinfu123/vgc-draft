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

interface MatchConfig {
    require_replays_before_results: boolean;
    auto_complete_set_from_replays: boolean;
}

const props = defineProps<{
    league: League;
    draft: Draft | null;
    matchConfig: MatchConfig | null;
}>();

const isReplayMode = props.matchConfig?.require_replays_before_results && props.matchConfig?.auto_complete_set_from_replays;
</script>

<template>
    <div class="flex flex-col gap-4 md:items-center md:justify-between">
        <div class="mt-4 mr-14 flex w-full flex-col items-end justify-end gap-2">
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground text-xs font-medium">Result mode:</span>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="isReplayMode ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' : 'bg-muted text-muted-foreground'"
                >
                    {{ isReplayMode ? 'Replay Required' : 'Manual Entry' }}
                </span>
            </div>
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
