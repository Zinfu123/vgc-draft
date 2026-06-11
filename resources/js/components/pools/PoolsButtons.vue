<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { create } from '@/routes/pools';
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
    router.post(create.url(), {
        league_id: props.league.id,
        command: props.command.command,
    });
};
</script>

<template>
    <Button variant="outline" @click="submit" v-if="props.command.command === 'create'"> Create Pools </Button>
</template>
