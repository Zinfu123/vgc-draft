<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
}
interface Command {
    command: string;
}

const props = defineProps<{
    league: League;
    command: Command;
}>();

const submit = () => {
    router.post(route('draft.create'), {
        league_id: props.league.id,
    });
}
</script>

<template>
        <Button variant="outline" @click="submit" v-if="props.command.command === 'create'"> 
            Start Draft 
        </Button>
        <Button variant="outline" @click="submit" v-if="props.command.command === 'next_round'"> 
            Next Round 
        </Button>
        <Button variant="outline" @click="submit" v-if="props.command.command === 'end_draft'"> 
            End Draft 
        </Button>
</template>