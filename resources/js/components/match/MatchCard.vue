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
}

interface Team2 {
    id: number;
    name: string;
    logo: string;
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
            <div class="flex flex-col items-center text-2xl font-bold">
                <div class="flex flex-row items-center gap-4">
                    <div class="flex flex-row items-center gap-4">
                        {{ team1.name }}
                        <img :src="team1.logo" alt="team logo" class="h-45 w-45" />
                    </div>
                </div>
                <div class="flex flex-row items-center gap-4">
                    <div class="flex flex-row items-center gap-4">
                        {{ team2.name }}
                        <img :src="team2.logo" alt="team logo" class="h-45 w-45" />
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
