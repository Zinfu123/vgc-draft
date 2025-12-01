<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

interface Teams {
    id: number;
    name: string;
    logo: string;
    set_wins: number;
    set_losses: number;
    victory_points: number;
    coach: string;
}

interface props {
    team: Teams;
}

const props = defineProps<props>();
</script>
<template>
    <Card class="flex aspect-square cursor-pointer items-center hover:bg-black/50">
        <CardContent>
            <div class="flex flex-col items-center text-2xl font-bold">
                {{ team.name }}
            </div>
            <div class="flex flex-col items-center text-2xl font-bold">Coach: {{ team.coach }}</div>
            <div class="mx-auto flex flex-col items-center" v-if="team.logo !== null">
                <img :src="team.logo" alt="team logo" class="h-45 w-45" />
            </div>
            <div class="mx-auto flex flex-col items-center text-2xl font-bold">
                Record:
                <div class="flex flex-row items-center gap-8 text-2xl font-bold">
                    <div class="flex flex-row items-center gap-8 text-2xl font-bold text-green-500">
                        {{ team.set_wins }}
                    </div>
                    <div class="flex flex-row items-center gap-8 text-2xl font-bold">-</div>
                    <div class="flex flex-row items-center gap-8 text-2xl font-bold text-red-500">
                        {{ team.set_losses }}
                    </div>
                </div>
            </div>
            <div class="mx-auto flex flex-col items-center text-2xl font-bold">
                <div class="flex flex-row items-center gap-4">
                    Points:
                    <div class="flex flex-row items-center text-2xl font-bold text-orange-500">
                        {{ team.victory_points }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
