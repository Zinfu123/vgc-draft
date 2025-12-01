<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

interface Sets {
    id: number;
    league_id: number;
    pool_id: number;
}

interface Team1 {
    id: number;
    name: string;
    logo: string;
    coach: {
        name: string;
    };
}

interface Team2 {
    id: number;
    name: string;
    logo: string;
    coach: {
        name: string;
    };
}

interface props {
    sets: Sets;
    team1: Team1;
    team2: Team2;
}

const props = defineProps<props>();
</script>
<template>
    <Card class="flex aspect-square cursor-pointer items-center hover:bg-black/50">
        <CardContent>
            <img :src="team1.logo" alt="team logo" class="h-20 w-20" />
            <div class="flex flex-row items-center gap-4">
                <div class="flex flex-col items-start gap-4">
                    {{ team1.name }}
                </div>
                <div class="flex flex-col items-start gap-4">Coach:{{ team1.coach.name }}</div>
            </div>
            <div class="mt-1 flex flex-col items-center text-2xl font-bold">VS:</div>
            <div class="mt-4 flex flex-row items-center gap-4">
                <img :src="team2.logo" alt="team logo" class="h-20 w-20" />
                <div class="flex flex-col items-start gap-4">
                    {{ team2.name }}
                    <div class="flex flex-col items-end gap-4">Coach: {{ team2.coach.name }}</div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
