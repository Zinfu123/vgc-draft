<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import ImportLeaguePokemon from './ImportLeaguePokemon.vue';
import StartDraft from '../draft/StartDraft.vue';

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
    draft: Draft;
}>();
</script>

<template>
    <div class="`flex md:justify-between` flex-col gap-4 md:items-center">
        <Head :title="`${props.league.name}`" />
        <div class="mt-4 mr-14 flex w-full flex-col items-end justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger>
                    <Button variant="outline"> Admin Panel </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="flex flex-col items-end justify-end">
                    <ImportLeaguePokemon :league="props.league" />
                    <StartDraft :league="props.league" :command="{ command: 'create' }" v-if="props.draft === undefined" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
