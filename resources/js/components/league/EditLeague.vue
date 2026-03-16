<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';

interface League {
    id: number;
    name: string;
    league_owner: number;
}

const props = defineProps<{
    league: League;
}>();

const editLeague = () => {
    router.get(route('leagues.create-edit', { league: props.league.id }), {
        league_id: props.league.id,
        league_name: props.league.name,
        command: 'edit',
    });
};
</script>

<template>
    <Button variant="outline" @click="editLeague"> Edit League </Button>
</template>
